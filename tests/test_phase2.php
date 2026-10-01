<?php
declare(strict_types=1);

/**
 * Convota Phase 2 Test Suite
 * Tests Organizations, Members, Primary/Substitutes, President Role, CSV Conflict Engine, and Exports
 */

require_once __DIR__ . '/../src/Autoload.php';

use Convota\Database\Database;
use Convota\Database\Migrations;
use Convota\Database\Seeder;
use Convota\Services\AuditService;
use Convota\Services\MailerService;
use Convota\Services\CsvService;
use Convota\Models\Organization;
use Convota\Models\User;

$testDbPath = __DIR__ . '/test_phase2_' . time() . '.sqlite';
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

echo "=== CONVOTA TEST SUITE: PHASE 2 ===\n";

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
    $mailer = new MailerService();
    $orgModel = new Organization($db, $audit, $mailer);
    $userModel = new User($db, $audit, $mailer);
    $csvService = new CsvService($db, $audit, $mailer);

    // 1. Create Organization with First Member
    $createRes = $orgModel->createWithFirstMember(
        [
            'name' => 'Nordic AI Society',
            'short_name' => 'NAIS',
            'country' => 'Finland',
            'website' => 'https://nordic-ai.org',
            'voting_weight' => 1.5,
            'is_observer' => 0
        ],
        [
            'name' => 'Lars',
            'surname' => 'Virtanen',
            'email' => 'lars@email.vlsi.gr',
            'telephone' => '+358 9 1234567'
        ],
        1, // admin
        '192.168.1.50',
        'Mozilla/5.0 Test'
    );

    assertTest("Organization created with ID", $createRes['organization_id'] > 0);
    assertTest("First member created with ID", $createRes['user_id'] > 0);
    assertTest("Unique organization access hash created", !empty($createRes['common_hash']));

    $org = $orgModel->getWithMembers($createRes['organization_id']);
    assertTest("Organization has 1 member (Primary)", count($org['members']) === 1 && (int)$org['members'][0]['is_primary'] === 1);

    // 2. Add Substitute Member
    $subMember = $userModel->addMember(
        $createRes['organization_id'],
        [
            'name' => 'Tuomas',
            'surname' => 'Korhonen',
            'email' => 'tuomas@email.vlsi.gr',
            'telephone' => '+358 9 9876543',
            'is_primary' => 0
        ],
        $createRes['user_id'],
        '192.168.1.51',
        'Mozilla/5.0 Member'
    );

    assertTest("Substitute member added successfully", $subMember['id'] > 0);

    $orgAfterSub = $orgModel->getWithMembers($createRes['organization_id']);
    assertTest("Organization now has 2 members", count($orgAfterSub['members']) === 2);

    // 3. Historical Accountability Log Check
    $addLogs = $db->fetchAll("SELECT * FROM audit_logs WHERE action = 'member_added' AND organization_id = ?", [$createRes['organization_id']]);
    assertTest("Historical accountability log records member additions", count($addLogs) === 2);
    assertTest("Log captures IP address correctly", $addLogs[1]['ip_address'] === '192.168.1.51');

    // 4. Remove Member (and Primary member replacement)
    $removeOk = $userModel->removeMember(
        $createRes['user_id'], // Remove the original primary member Lars!
        $createRes['organization_id'],
        $subMember['id'], // Removed by Tuomas
        '192.168.1.52',
        'Mozilla/5.0 Remover'
    );

    assertTest("Primary member removal succeeded", $removeOk === true);

    $orgAfterRemoval = $orgModel->getWithMembers($createRes['organization_id']);
    assertTest("Organization now has 1 member", count($orgAfterRemoval['members']) === 1);
    assertTest("Substitute Tuomas automatically promoted to primary", (int)$orgAfterRemoval['members'][0]['is_primary'] === 1 && $orgAfterRemoval['members'][0]['id'] === $subMember['id']);

    $removeLog = $db->fetch("SELECT * FROM audit_logs WHERE action = 'member_removed' AND organization_id = ?", [$createRes['organization_id']]);
    assertTest("Historical accountability log recorded member removal with IP", $removeLog !== null && $removeLog['ip_address'] === '192.168.1.52');

    // 5. Change President Organization
    $orgModel->setPresident($createRes['organization_id'], 1, '127.0.0.1');
    $presOrg = $db->fetch("SELECT id, name FROM organizations WHERE is_president = 1");
    assertTest("President Organization successfully changed to Nordic AI Society", (int)$presOrg['id'] === $createRes['organization_id']);

    $promotedPresUser = $db->fetch("SELECT system_role FROM users WHERE id = ?", [$subMember['id']]);
    assertTest("Primary delegate of new president org promoted to president role", $promotedPresUser['system_role'] === 'president');

    // 6. CSV Conflict Engine Test
    $csvContent = <<<CSV
A/a,name,surname,email,organization,telephone,vote_weight
1,Frederik,Nielsen,frederik@email.vlsi.gr,Danish Research Consortium,+45 12345678,1.0
2,Tuomas,Korhonen,tuomas@email.vlsi.gr,Nordic AI Society,+358 9 9876543,1.5
3,Impostor,User,tuomas@email.vlsi.gr,Conflict University,+30 210 1111111,1.0
CSV;

    $preview = $csvService->previewImport($csvContent);
    assertTest("CSV preview parsed 3 rows", count($preview['rows']) === 3);
    assertTest("Row 1 detected as NEW member & org", $preview['rows'][0]['status'] === 'new' && $preview['rows'][0]['org_is_new'] === true);
    assertTest("Row 2 detected as EXISTING member in SAME org", $preview['rows'][1]['status'] === 'existing_same_org');
    assertTest("Row 3 detected as CRITICAL CONFLICT in DIFFERENT org", $preview['rows'][2]['status'] === 'conflict_different_org');
    assertTest("Row 3 contains prominent conflict warning", str_contains($preview['rows'][2]['warning'], 'Conflict:') && str_contains($preview['rows'][2]['warning'], 'already registered under'));

    // 7. CSV Commit Execution
    $commitRes = $csvService->commitImport(
        $preview['rows'],
        ['tuomas@email.vlsi.gr'], // User chose to update Tuomas
        1,
        '127.0.0.1',
        'CLI'
    );

    assertTest("CSV commit created 1 new org", $commitRes['created_orgs'] === 1);
    assertTest("CSV commit created 1 new member", $commitRes['created_members'] === 1);
    assertTest("CSV commit updated 1 existing member", $commitRes['updated_members'] === 1);
    assertTest("CSV commit safely skipped 1 conflicting member", $commitRes['skipped'] === 1);

    // Verify conflict row did NOT alter Tuomas's organization!
    $tuomasCheck = $db->fetch("SELECT organization_id FROM users WHERE email = 'tuomas@email.vlsi.gr'");
    assertTest("Conflicted member organization remained unchanged", (int)$tuomasCheck['organization_id'] === $createRes['organization_id']);

    // 8. CSV Exports
    $orgCsv = $csvService->exportOrganizationsCsv();
    assertTest("Export organizations CSV produces non-empty output", !empty($orgCsv) && str_contains($orgCsv, 'Nordic AI Society'));

    $membersCsv = $csvService->exportMembersCsv();
    assertTest("Export members CSV produces valid CSV with headers", !empty($membersCsv) && str_contains($membersCsv, 'tuomas@email.vlsi.gr'));

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
