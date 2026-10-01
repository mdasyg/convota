<?php
declare(strict_types=1);

/**
 * Convota Phase 1 Test Suite
 * Tests Database, Migrations, Seeding, Audit Hash Chaining, Auth, CSRF, and Security
 */

require_once __DIR__ . '/../src/Autoload.php';

use Convota\Database\Database;
use Convota\Database\Migrations;
use Convota\Database\Seeder;
use Convota\Services\AuditService;
use Convota\Auth\AuthService;
use Convota\Auth\HashAuth;
use Convota\Security\Csrf;
use Convota\Security\Validator;

$testDbPath = __DIR__ . '/test_phase1_' . time() . '.sqlite';
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

echo "=== CONVOTA TEST SUITE: PHASE 1 ===\n";

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
    // 1. Database Connection & PRAGMA Verification
    $db = new Database($testDbPath);
    Database::setInstance($db);

    $journalMode = $db->fetchColumn("PRAGMA journal_mode;");
    assertTest("SQLite journal_mode is WAL", strtolower((string)$journalMode) === 'wal');

    $foreignKeys = (int)$db->fetchColumn("PRAGMA foreign_keys;");
    assertTest("SQLite foreign_keys are enabled", $foreignKeys === 1);

    // 2. Migrations
    $migrations = new Migrations($db);
    $migrations->run();

    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table'");
    $tableNames = array_column($tables, 'name');
    assertTest("Table 'organizations' created", in_array('organizations', $tableNames, true));
    assertTest("Table 'users' created", in_array('users', $tableNames, true));
    assertTest("Table 'proposals' created", in_array('proposals', $tableNames, true));
    assertTest("Table 'vote_participation' created", in_array('vote_participation', $tableNames, true));
    assertTest("Table 'anonymous_ballots' created", in_array('anonymous_ballots', $tableNames, true));
    assertTest("Table 'audit_logs' created", in_array('audit_logs', $tableNames, true));

    // 3. Seeder
    $seeder = new Seeder($db);
    $seeder->seed();

    $admin = $db->fetch("SELECT * FROM users WHERE email = 'adminconvota@email.vlsi.gr'");
    assertTest("Super Admin seeded successfully", $admin !== null && $admin['system_role'] === 'admin');

    $orgCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM organizations");
    assertTest("Collective sample organizations seeded (count: {$orgCount})", $orgCount >= 4);

    $presOrg = $db->fetch("SELECT * FROM organizations WHERE is_president = 1");
    assertTest("President Organization designated", $presOrg !== null && !empty($presOrg['common_hash']));

    // 4. Audit Trail & Cryptographic Hash Chaining
    $audit = new AuditService($db);
    $logId1 = $audit->log('test_action_1', (int)$admin['id'], null, ['detail' => 'first test action']);
    $logId2 = $audit->log('test_action_2', (int)$admin['id'], (int)$presOrg['id'], ['detail' => 'second test action']);

    assertTest("Audit log entries created", $logId1 > 0 && $logId2 > $logId1);
    assertTest("Audit log SHA-256 chain verifies as intact", $audit->verifyChain() === true);

    // Tamper detection test: intentionally modify a row in SQLite
    $db->execute("UPDATE audit_logs SET details = '{\"tampered\": true}' WHERE id = ?", [$logId1]);
    assertTest("Audit log tamper detection correctly flags corrupted row", $audit->verifyChain() === false);

    // Restore intact state for subsequent tests
    $db->execute("DELETE FROM audit_logs WHERE id IN (?, ?)", [$logId1, $logId2]);

    // 5. Auth Service & Passwords
    $auth = new AuthService($db, $audit);
    $loginRes = $auth->login('adminconvota@email.vlsi.gr', 'AdminConvota2026!');
    assertTest("Admin login with valid password succeeds", $loginRes['success'] === true && $auth->isAdmin());

    $badLogin = $auth->login('adminconvota@email.vlsi.gr', 'WrongPassword!');
    assertTest("Login with invalid password fails", $badLogin['success'] === false);

    // Test Rate Limiting
    for ($i = 0; $i < 5; $i++) {
        $auth->login('adminconvota@email.vlsi.gr', 'WrongPasswordAgain!');
    }
    $lockedLogin = $auth->login('adminconvota@email.vlsi.gr', 'AdminConvota2026!');
    assertTest("Rate limiting triggers lockout after consecutive failures", $lockedLogin['success'] === false && str_contains($lockedLogin['error'], 'Too many'));

    // Clear lockout to test password reset
    $db->execute("DELETE FROM rate_limits");

    $resetToken = $auth->generateResetToken('adminconvota@email.vlsi.gr');
    assertTest("Password reset token generated", !empty($resetToken));
    $resetOk = $auth->resetPasswordWithToken($resetToken, 'NewSecurePassword2026!');
    assertTest("Password reset with valid token succeeds", $resetOk === true);

    $loginNewPass = $auth->login('adminconvota@email.vlsi.gr', 'NewSecurePassword2026!');
    assertTest("Login with new password succeeds", $loginNewPass['success'] === true);

    // 6. Organization Common Access Hash Auth
    $hashAuth = new HashAuth($db, $audit);
    $orgHash = $presOrg['common_hash'];

    $hashLogin = $hashAuth->loginWithHash($orgHash);
    assertTest("Organization login via Common Access Hash succeeds", $hashLogin['success'] === true && $hashLogin['organization']['organization_id'] === (int)$presOrg['id']);

    $rotatedHash = $hashAuth->rotateHash((int)$presOrg['id']);
    assertTest("Access key rotation produces new distinct hash", !empty($rotatedHash) && $rotatedHash !== $orgHash);

    $oldHashLogin = $hashAuth->loginWithHash($orgHash);
    assertTest("Old access key is revoked after rotation", $oldHashLogin['success'] === false);

    $newHashLogin = $hashAuth->loginWithHash($rotatedHash);
    assertTest("New rotated access key works", $newHashLogin['success'] === true);

    // Toggle hash auth disabled
    $hashAuth->setHashAuthEnabled((int)$presOrg['id'], false);
    $disabledLogin = $hashAuth->loginWithHash($rotatedHash);
    assertTest("Login rejected when hash authorization is toggled OFF", $disabledLogin['success'] === false);

    // 7. Security: CSRF & Validator
    $csrfToken = Csrf::getToken();
    assertTest("CSRF token generated (length: " . strlen($csrfToken) . ")", strlen($csrfToken) === 64);
    assertTest("CSRF token validation succeeds with valid token", Csrf::validate($csrfToken));
    assertTest("CSRF token validation fails with bad token", !Csrf::validate('invalid_token_12345'));

    $escaped = Validator::escape("<script>alert('xss')</script>&");
    assertTest("XSS filter escapes HTML characters", $escaped === "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;&amp;");
    assertTest("Validator validates valid email", Validator::isValidEmail('test@email.vlsi.gr') === true);
    assertTest("Validator rejects invalid email", Validator::isValidEmail('not-an-email') === false);

} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
} finally {
    // Cleanup temporary test database
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
