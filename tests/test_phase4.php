<?php
declare(strict_types=1);

/**
 * Convota Phase 4 Test Suite
 * Tests Proposal Lifecycle, Dual-Mode Voting, Anti-Replay & Idempotency,
 * Anonymous Cryptographic Decoupling, Sealed Results, and Notifications
 */

require_once __DIR__ . '/../src/Autoload.php';

use Convota\Database\Database;
use Convota\Database\Migrations;
use Convota\Database\Seeder;
use Convota\Services\AuditService;
use Convota\Services\MailerService;
use Convota\Services\VotingService;
use Convota\Models\Proposal;

$testDbPath = __DIR__ . '/test_phase4_' . time() . '.sqlite';
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

echo "=== CONVOTA TEST SUITE: PHASE 4 ===\n";

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
    $proposalModel = new Proposal($db, $audit, $mailer);
    $votingService = new VotingService($db, $audit, $mailer);

    // Fetch sample seeded entities
    $uowm = $db->fetch("SELECT id, name FROM organizations WHERE short_name = 'UOWM'");
    $uowmMember = $db->fetch("SELECT id, name, surname FROM users WHERE name = 'Minas' AND surname = 'Dasygenis' AND email = 'convota@email.vlsi.gr'");

    $eorn = $db->fetch("SELECT id, name FROM organizations WHERE short_name = 'EORN'");
    $eornMember = $db->fetch("SELECT id, name, surname FROM users WHERE name = 'Elena' AND surname = 'Rostova' AND email = 'convota@email.vlsi.gr'");

    $isc = $db->fetch("SELECT id, name, voting_weight FROM organizations WHERE short_name = 'ISC'"); // 2.0 votes
    $iscMember = $db->fetch("SELECT id, name, surname FROM users WHERE name = 'Sophie' AND surname = 'Dubois' AND email = 'convota@email.vlsi.gr'");

    $gecObserver = $db->fetch("SELECT id, name FROM organizations WHERE short_name = 'GEC'"); // 0 votes, observer
    $gecMember = $db->fetch("SELECT id, name, surname FROM users WHERE name = 'Astrid' AND surname = 'Lindqvist' AND email = 'convota@email.vlsi.gr'");

    // 1. Create Secret Ballot Proposal (Yes / No / Abstain)
    $propId = $proposalModel->create([
        'title' => 'Resolution 2026-A: Adoption of Common Open Protocol',
        'description' => 'Proposal to standardize interoperability interfaces across all collective members.',
        'voting_type' => 'yes_no_abstain',
        'is_secret' => 1,
        'timing_mode' => 'manual',
        'require_quorum' => 1,
        'quorum_percentage' => 50.0,
        'majority_percentage' => 50.0
    ], [], 1, '127.0.0.1');

    assertTest("Secret proposal created with ID", $propId > 0);

    $opts = $proposalModel->getOptions($propId);
    assertTest("Options automatically generated for yes_no_abstain (count: " . count($opts) . ")", count($opts) === 3);

    $yesOptId = null;
    $noOptId = null;
    $abstainOptId = null;
    foreach ($opts as $o) {
        if ($o['option_text'] === 'YES') $yesOptId = (int)$o['id'];
        if ($o['option_text'] === 'NO') $noOptId = (int)$o['id'];
        if ($o['option_text'] === 'ABSTAIN') $abstainOptId = (int)$o['id'];
    }

    // 2. State Machine Invariant: Cannot vote when DRAFT
    $threwOnDraft = false;
    try {
        $votingService->castVote($propId, (int)$uowm['id'], (int)$uowmMember['id'], $yesOptId);
    } catch (\Throwable $e) {
        $threwOnDraft = true;
    }
    assertTest("Casting vote in DRAFT status is strictly rejected", $threwOnDraft === true);

    // Open voting
    $proposalModel->updateStatus($propId, 'open', 1, '127.0.0.1');
    assertTest("Proposal transitioned to OPEN status", $proposalModel->getById($propId)['status'] === 'open');

    // 3. Observer Organization Voting Check
    $threwOnObserver = false;
    try {
        $votingService->castVote($propId, (int)$gecObserver['id'], (int)$gecMember['id'], $yesOptId);
    } catch (\Throwable $e) {
        $threwOnObserver = str_contains($e->getMessage(), 'Observer');
    }
    assertTest("Observer organization is prevented from casting vote", $threwOnObserver === true);

    // 4. Cast Secret Vote with Idempotency Token
    $idempotencyKey = $votingService->generateBallotNonce($propId, (int)$uowm['id']);
    $vote1 = $votingService->castVote(
        $propId,
        (int)$uowm['id'],
        (int)$uowmMember['id'],
        $yesOptId,
        $idempotencyKey,
        '192.168.1.10',
        'Mozilla/5.0 Voter'
    );

    assertTest("Vote cast successfully", $vote1['success'] === true && $vote1['idempotent_replay'] === false);
    assertTest("Voter received cryptographic receipt hash", !empty($vote1['receipt_hash']));
    $uowmReceipt = $vote1['receipt_hash'];

    // 5. Anti-Replay & Idempotency Testing
    // Replay with SAME idempotency key
    $replayVote = $votingService->castVote(
        $propId,
        (int)$uowm['id'],
        (int)$uowmMember['id'],
        $yesOptId,
        $idempotencyKey,
        '192.168.1.10',
        'Mozilla/5.0 Voter'
    );
    assertTest("Identical replay returns safe idempotent confirmation", $replayVote['success'] === true && $replayVote['idempotent_replay'] === true);

    // Attempt second vote with DIFFERENT idempotency key (Double voting attack)
    $threwOnDoubleVote = false;
    try {
        $votingService->castVote(
            $propId,
            (int)$uowm['id'],
            (int)$uowmMember['id'],
            $noOptId,
            'another_different_token',
            '192.168.1.10'
        );
    } catch (\Throwable $e) {
        $threwOnDoubleVote = str_contains($e->getMessage(), 'already cast its vote');
    }
    assertTest("Duplicate vote attempt with different token is rejected", $threwOnDoubleVote === true);

    // 6. Cryptographic Decoupling Verification
    $partRow = $db->fetch("SELECT * FROM vote_participation WHERE proposal_id = ? AND organization_id = ?", [$propId, $uowm['id']]);
    assertTest("vote_participation records member snapshot and IP", $partRow !== null && !empty($partRow['voter_name_snapshot']));

    $anonBallot = $db->fetch("SELECT * FROM anonymous_ballots WHERE proposal_id = ? AND receipt_hash = ?", [$propId, $uowmReceipt]);
    assertTest("anonymous_ballots stores vote choice and receipt", $anonBallot !== null && (int)$anonBallot['option_id'] === $yesOptId);

    // Database structure check: anonymous_ballots must NOT have organization_id column!
    $anonCols = $db->fetchAll("PRAGMA table_info(anonymous_ballots);");
    $anonColNames = array_column($anonCols, 'name');
    assertTest("anonymous_ballots has NO organization_id column", !in_array('organization_id', $anonColNames, true));
    assertTest("anonymous_ballots has NO user_id column", !in_array('user_id', $anonColNames, true));

    // Voter verification tool test
    $verifiedReceipt = $votingService->verifyReceipt($propId, $uowmReceipt);
    assertTest("Voter receipt verification succeeds for voter", $verifiedReceipt !== null && $verifiedReceipt['receipt_hash'] === $uowmReceipt);

    // 7. Sealed Results Guard (Results hidden during voting)
    $sealedResults = $votingService->getResults($propId);
    assertTest("Results are strictly sealed/null while voting is open", $sealedResults === null);

    // Live status monitor
    $monitor = $votingService->getLiveMonitorStatus($propId);
    assertTest("Live monitor shows 1 voted out of 3 eligible", $monitor['total_voted'] === 1 && $monitor['total_eligible'] === 3);
    assertTest("Live monitor participation percentage is 33.3%", $monitor['participation_percentage'] === 33.3);

    // 8. Cast Remaining Votes
    // EORN votes NO (weight 1.0)
    $votingService->castVote($propId, (int)$eorn['id'], (int)$eornMember['id'], $noOptId, null, '192.168.1.11');

    // ISC votes YES (weight 2.0 - dual vote)
    $votingService->castVote($propId, (int)$isc['id'], (int)$iscMember['id'], $yesOptId, null, '192.168.1.12');

    $monitorFull = $votingService->getLiveMonitorStatus($propId);
    assertTest("All 3 eligible organizations voted (100% turnout)", $monitorFull['total_voted'] === 3 && $monitorFull['participation_percentage'] === 100.0);

    // 9. Close Proposal and Verify Tallies
    $proposalModel->updateStatus($propId, 'closed', 1, '127.0.0.1');
    assertTest("Proposal closed successfully", $proposalModel->getById($propId)['status'] === 'closed');

    $finalResults = $votingService->getResults($propId);
    assertTest("Results unsealed upon closing", $finalResults !== null && $finalResults['total_ballots'] === 3);
    assertTest("Total weight cast is 4.0 (1.0 + 1.0 + 2.0)", $finalResults['total_weight'] === 4.0);

    // Tallies: YES weight = 1.0 (UOWM) + 2.0 (ISC) = 3.0 (75.0%)
    // NO weight = 1.0 (EORN) = 1.0 (25.0%)
    $yesRes = null;
    $noRes = null;
    foreach ($finalResults['options'] as $optRes) {
        if ($optRes['option_text'] === 'YES') $yesRes = $optRes;
        if ($optRes['option_text'] === 'NO') $noRes = $optRes;
    }

    assertTest("YES received 2 ballots with weight 3.0 (75.0%)", $yesRes['vote_count'] === 2 && $yesRes['total_weight'] === 3.0 && $yesRes['percentage'] === 75.0);
    assertTest("NO received 1 ballot with weight 1.0 (25.0%)", $noRes['vote_count'] === 1 && $noRes['total_weight'] === 1.0 && $noRes['percentage'] === 25.0);
    assertTest("Quorum achieved is true", $finalResults['quorum_achieved'] === true);

    // 10. Open Roll-Call Ballot Testing
    $openPropId = $proposalModel->create([
        'title' => 'Resolution 2026-B: Public Election of Assembly Teller',
        'description' => 'Open roll-call election for assembly teller seat.',
        'voting_type' => 'multiple_choice',
        'is_secret' => 0, // OPEN VOTE
        'timing_mode' => 'manual'
    ], ['Candidate Alpha', 'Candidate Beta', 'Abstain'], 1, '127.0.0.1');

    $proposalModel->updateStatus($openPropId, 'open', 1, '127.0.0.1');

    $openOpts = $proposalModel->getOptions($openPropId);
    $candAlphaId = (int)$openOpts[0]['id'];

    $votingService->castVote($openPropId, (int)$uowm['id'], (int)$uowmMember['id'], $candAlphaId, null, '192.168.1.10');
    $proposalModel->updateStatus($openPropId, 'closed', 1, '127.0.0.1');

    $openResults = $votingService->getResults($openPropId);
    assertTest("Open vote results unsealed", $openResults !== null && !empty($openResults['roll_call']));
    assertTest("Roll call records voter name and organization", $openResults['roll_call'][0]['representative_name'] === 'Minas Dasygenis' && $openResults['roll_call'][0]['organization_name'] === $uowm['name']);

    // 11. Mailer Notification Verification
    $mailLog = file_exists(__DIR__ . '/../data/mail.log') ? file_get_contents(__DIR__ . '/../data/mail.log') : '';
    assertTest("Intra-organization vote confirmation email dispatched to members", str_contains($mailLog, '[Vote Confirmed] Your Organization Cast a Vote'));

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
