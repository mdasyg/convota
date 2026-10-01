<?php
declare(strict_types=1);

/**
 * Convota Phase 3 Test Suite
 * Tests Meeting Lifecycle, Presence Tracking, Designated Representative, Dynamic Quorum, and Attendance Export
 */

require_once __DIR__ . '/../src/Autoload.php';

use Convota\Database\Database;
use Convota\Database\Migrations;
use Convota\Database\Seeder;
use Convota\Services\AuditService;
use Convota\Services\QuorumService;
use Convota\Models\Meeting;

$testDbPath = __DIR__ . '/test_phase3_' . time() . '.sqlite';
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

echo "=== CONVOTA TEST SUITE: PHASE 3 ===\n";

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$desc}\n";
        $passed++;
    } else {
        echo " [FAIL] {$desc}\n";
        $failed++;
    }
}

try {
    $db = new Database($testDbPath);
    Database::setInstance($db);
    (new Migrations($db))->run();
    (new Seeder($db))->seed();

    $audit = new AuditService($db);
    $meetingModel = new Meeting($db, $audit);
    $quorumService = new QuorumService($db);

    // 1. Create Meeting
    $meetingId = $meetingModel->create([
        'title' => 'Annual General Assembly 2026',
        'description' => 'Deliberation on annual budget, research priorities, and executive elections.',
        'location' => 'Kozani, Greece & Hybrid Online',
        'scheduled_at' => gmdate('Y-m-d H:i:s', time() + 3600),
        'require_quorum' => 1,
        'quorum_percentage' => 50.0
    ], 1, '127.0.0.1');

    assertTest("Meeting created with ID", $meetingId > 0);

    // 2. Lifecycle State Transitions
    assertTest("Initial meeting status is draft", $meetingModel->getById($meetingId)['status'] === 'draft');

    $meetingModel->updateStatus($meetingId, 'registration', 1, '127.0.0.1');
    assertTest("Meeting transitioned to registration", $meetingModel->getById($meetingId)['status'] === 'registration');

    $meetingModel->updateStatus($meetingId, 'in_session', 1, '127.0.0.1');
    assertTest("Meeting transitioned to in_session", $meetingModel->getById($meetingId)['status'] === 'in_session');

    // 3. Dynamic Quorum Before Any Check-ins
    // Seeded: 4 organizations (3 voting, 1 observer)
    $qInitial = $quorumService->getQuorumStatus($meetingId);
    assertTest("Total voting organizations identified as 3", $qInitial['total_voting_organizations'] === 3);
    assertTest("Initial present voting organizations is 0", $qInitial['present_voting_organizations'] === 0);
    assertTest("Initial attendance percentage is 0.0%", $qInitial['attendance_percentage'] === 0.0);
    assertTest("Initial quorum reached is false", $qInitial['is_quorum_reached'] === false);

    // 4. Organization 1 Check-In (President Org: UOWM)
    $uowm = $db->fetch("SELECT id FROM organizations WHERE short_name = 'UOWM'");
    $uowmMember = $db->fetch("SELECT id FROM users WHERE email = 'mdasyg@email.vlsi.gr'");

    $meetingModel->setPresence(
        $meetingId,
        (int)$uowm['id'],
        (int)$uowmMember['id'],
        'self_login',
        (int)$uowmMember['id'],
        '192.168.1.100'
    );

    $qAfter1 = $quorumService->getQuorumStatus($meetingId);
    assertTest("After 1 check-in: 1 of 3 voting orgs present", $qAfter1['present_voting_organizations'] === 1);
    assertTest("Attendance percentage is 33.3%", $qAfter1['attendance_percentage'] === 33.3);
    assertTest("Quorum (threshold 50%) is not yet reached", $qAfter1['is_quorum_reached'] === false);

    // 5. Observer Org Check-In (GEC - 0 votes, observer)
    $gec = $db->fetch("SELECT id FROM organizations WHERE short_name = 'GEC'");
    $gecMember = $db->fetch("SELECT id FROM users WHERE email = 'astrid@email.vlsi.gr'");

    $meetingModel->setPresence(
        $meetingId,
        (int)$gec['id'],
        (int)$gecMember['id'],
        'self_login',
        (int)$gecMember['id'],
        '192.168.1.101'
    );

    $qAfterObserver = $quorumService->getQuorumStatus($meetingId);
    assertTest("Total present orgs increased to 2", $qAfterObserver['present_organizations'] === 2);
    assertTest("Observer check-in does NOT inflate voting quorum numerator", $qAfterObserver['present_voting_organizations'] === 1);
    assertTest("Attendance percentage remains 33.3%", $qAfterObserver['attendance_percentage'] === 33.3);

    // 6. Organization 2 Check-In (EORN) via Common Access Hash with substitute member
    $eorn = $db->fetch("SELECT id FROM organizations WHERE short_name = 'EORN'");
    $eornSub = $db->fetch("SELECT id FROM users WHERE email = 'marcus.vogel@email.vlsi.gr'"); // Substitute member

    $meetingModel->setPresence(
        $meetingId,
        (int)$eorn['id'],
        (int)$eornSub['id'],
        'common_hash',
        null,
        '192.168.1.102'
    );

    $qAfter2 = $quorumService->getQuorumStatus($meetingId);
    assertTest("After 2 voting orgs: 2 of 3 voting present", $qAfter2['present_voting_organizations'] === 2);
    assertTest("Attendance percentage is 66.7%", $qAfter2['attendance_percentage'] === 66.7);
    assertTest("Quorum threshold (50.0%) reached successfully", $qAfter2['is_quorum_reached'] === true);
    assertTest("Status message is formatted properly", str_contains($qAfter2['status_message'], "Participating 2 of 3 voting organizations (66.7% attendance)"));

    // 7. Switching Designated Representative
    // EORN switches representative to Elena Rostova (primary)
    $eornPrimary = $db->fetch("SELECT id FROM users WHERE email = 'elena.rostova@email.vlsi.gr'");
    $meetingModel->setPresence(
        $meetingId,
        (int)$eorn['id'],
        (int)$eornPrimary['id'],
        'self_login',
        (int)$eornPrimary['id'],
        '192.168.1.103'
    );

    $roster = $quorumService->getDetailedAttendance($meetingId);
    $eornRow = null;
    foreach ($roster as $r) {
        if ($r['organization_id'] === (int)$eorn['id']) {
            $eornRow = $r;
            break;
        }
    }

    assertTest("EORN designated representative switched successfully", $eornRow !== null && $eornRow['representative_id'] === (int)$eornPrimary['id']);
    assertTest("Representative name is updated in roster", str_contains($eornRow['representative_name'], 'Elena Rostova'));

    // 8. Attendance CSV Export
    $csv = $quorumService->exportAttendanceCsv($meetingId);
    assertTest("Attendance CSV generated with header metadata", str_contains($csv, 'Annual General Assembly 2026') && str_contains($csv, 'Quorum Met') && str_contains($csv, 'YES'));
    assertTest("Attendance CSV includes designated representative", str_contains($csv, 'Minas Dasygenis') && str_contains($csv, 'Elena Rostova'));
    assertTest("Attendance CSV lists un-checked-in org as ABSENT", str_contains($csv, 'International Standards Collective') && str_contains($csv, 'ABSENT'));

    // 9. Conclude Meeting
    $meetingModel->updateStatus($meetingId, 'concluded', 1, '127.0.0.1');
    $concludedMeeting = $meetingModel->getById($meetingId);
    assertTest("Meeting concluded status recorded", $concludedMeeting['status'] === 'concluded' && !empty($concludedMeeting['concluded_at']));

} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
} finally {
    if (file_exists($testDbPath)) {
        @unlink($testDbPath);
    }
    if (file_exists($testDbPath . '-wal')) {
        @unlink($testDbPath . '-wal');
    }
    if (file_exists($testDbPath . '-shm')) {
        @unlink($testDbPath . '-shm');
    }
}

echo "\n----------------------------------------\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "----------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
