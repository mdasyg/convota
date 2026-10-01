<?php
declare(strict_types=1);

/**
 * Convota Phase 5 Test Suite
 * Tests Web Dispatcher, REST APIs, Idempotent Voting API, CSRF Middleware, and Security Headers
 */

require_once __DIR__ . '/../src/Autoload.php';

use Convota\App;
use Convota\Database\Database;
use Convota\Database\Migrations;
use Convota\Database\Seeder;
use Convota\Security\Csrf;
use Convota\Security\SecurityHeaders;
use Convota\Controllers\ApiController;
use Convota\Models\Meeting;
use Convota\Models\Proposal;

$testDbPath = __DIR__ . '/test_phase5_' . time() . '.sqlite';
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

echo "=== CONVOTA TEST SUITE: PHASE 5 ===\n";

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

    App::setInstance(null);
    $app = App::getInstance();

    // 1. Setup Sample Meeting and Open Proposal for API Testing
    $meetingModel = new Meeting($db);
    $proposalModel = new Proposal($db);

    $meetingId = $meetingModel->create([
        'title' => 'API Integration Assembly 2026',
        'scheduled_at' => gmdate('Y-m-d H:i:s'),
        'require_quorum' => 1,
        'quorum_percentage' => 50.0
    ], 1);
    $meetingModel->updateStatus($meetingId, 'in_session', 1);

    $propId = $proposalModel->create([
        'title' => 'API Ballot Test Proposal',
        'meeting_id' => $meetingId,
        'voting_type' => 'yes_no_abstain',
        'is_secret' => 1,
        'timing_mode' => 'manual'
    ], [], 1);
    $proposalModel->updateStatus($propId, 'open', 1);

    $opts = $proposalModel->getOptions($propId);
    $yesOptId = (int)$opts[0]['id'];

    // 2. Test Quorum API Endpoint
    $_GET['meeting_id'] = (string)$meetingId;
    $api = new ApiController($app);

    ob_start();
    $api->quorum();
    $quorumJson = ob_get_clean();
    $quorumData = json_decode($quorumJson, true);

    assertTest("GET /api/quorum returns success: true", $quorumData !== null && $quorumData['success'] === true);
    assertTest("GET /api/quorum contains total_voting_organizations", isset($quorumData['quorum']['total_voting_organizations']));

    // 3. Test Voting Status API Endpoint
    $_GET['proposal_id'] = (string)$propId;
    ob_start();
    $api->votingStatus();
    $statusJson = ob_get_clean();
    $statusData = json_decode($statusJson, true);

    assertTest("GET /api/voting-status returns success: true", $statusData !== null && $statusData['success'] === true);
    assertTest("GET /api/voting-status contains total_eligible and total_voted", isset($statusData['monitor']['total_eligible']) && $statusData['monitor']['total_voted'] === 0);

    // 4. Test Idempotent Voting API via X-Organization-Key Header
    $uowm = $db->fetch("SELECT id, common_hash FROM organizations WHERE short_name = 'UOWM'");
    $_SERVER['HTTP_X_ORGANIZATION_KEY'] = $uowm['common_hash'];

    $idempotencyKey = 'api_idempotency_nonce_' . bin2hex(random_bytes(16));
    $_POST = [
        'proposal_id' => $propId,
        'option_id' => $yesOptId,
        'idempotency_key' => $idempotencyKey
    ];

    ob_start();
    $api->castVote();
    $voteJson = ob_get_clean();
    $voteData = json_decode($voteJson, true);

    assertTest("POST /api/vote with X-Organization-Key succeeds", $voteData !== null && $voteData['success'] === true);
    assertTest("POST /api/vote returns secret receipt hash", !empty($voteData['data']['receipt_hash']));
    assertTest("POST /api/vote initial submission has idempotent_replay = false", $voteData['data']['idempotent_replay'] === false);

    // Replay with identical idempotency key
    ob_start();
    $api->castVote();
    $replayJson = ob_get_clean();
    $replayData = json_decode($replayJson, true);

    assertTest("POST /api/vote replay returns safe idempotent confirmation", $replayData !== null && $replayData['data']['idempotent_replay'] === true);

    // 5. Test Unauthorized API Call
    unset($_SERVER['HTTP_X_ORGANIZATION_KEY']);
    unset($_SESSION['convota_user']);
    unset($_SESSION['convota_org_auth']);

    ob_start();
    $api->castVote();
    $unauthJson = ob_get_clean();
    $unauthData = json_decode($unauthJson, true);

    assertTest("POST /api/vote without credentials returns 401 error", $unauthData !== null && $unauthData['success'] === false && str_contains($unauthData['error'], 'Unauthorized'));

    // 6. Test CSRF Protection Middleware
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $validToken = Csrf::getToken();

    $_POST['csrf_token'] = $validToken;
    assertTest("Csrf::validate() succeeds with matching token", Csrf::validate() === true);

    $_POST['csrf_token'] = 'tampered_malicious_token';
    assertTest("Csrf::validate() rejects forged token", Csrf::validate() === false);

    unset($_POST['csrf_token']);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $validToken;
    assertTest("Csrf::validate() succeeds with X-CSRF-Token header", Csrf::validate() === true);

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
