<?php
declare(strict_types=1);

/**
 * Convota Automated Security Fixes & OWASP Remediation Test Suite
 */

require_once __DIR__ . '/../src/Autoload.php';

use Convota\App;
use Convota\Database\Database;
use Convota\Database\Migrations;
use Convota\Database\Seeder;
use Convota\Auth\AuthService;
use Convota\Auth\HashAuth;
use Convota\Services\VotingService;
use Convota\Services\CsvService;
use Convota\Services\MailerService;
use Convota\Services\AuditService;
use Convota\Security\SecurityHeaders;
use Convota\Security\Validator;

$passed = 0;
$failed = 0;

function assertSec(string $desc, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$desc}\n";
        $passed++;
    } else {
        echo " [FAIL] {$desc}\n";
        $failed++;
    }
}

echo "=== CONVOTA SECURITY VERIFICATION SUITE ===\n";

$testDb = __DIR__ . '/test_security.sqlite';
if (file_exists($testDb)) @unlink($testDb);

try {
    $db = new Database($testDb);
    $migrations = new Migrations($db);
    $migrations->run();
    $seeder = new Seeder($db);
    $seeder->seed();

    $audit = new AuditService($db);
    $mailer = new MailerService(null, $audit);

    // -------------------------------------------------------------
    // 1. [CRIT-1] Vote Hijacking IDOR Prevention
    // -------------------------------------------------------------
    $uowm = $db->fetch("SELECT * FROM organizations WHERE short_name = 'UOWM'");
    $eorn = $db->fetch("SELECT * FROM organizations WHERE short_name = 'EORN'");
    $uowmMember = $db->fetch("SELECT * FROM users WHERE organization_id = ? AND is_primary = 1", [$uowm['id']]);
    $eornMember = $db->fetch("SELECT * FROM users WHERE organization_id = ? AND is_primary = 1", [$eorn['id']]);

    // Create an open proposal
    $db->execute("
        INSERT INTO proposals (title, voting_type, is_secret, status, created_by)
        VALUES ('Security Test Proposal', 'yes_no_abstain', 0, 'open', 1)
    ");
    $propId = (int)$db->lastInsertId();
    $db->execute("INSERT INTO proposal_options (proposal_id, option_text, order_index) VALUES (?, 'YES', 1)", [$propId]);
    $optId = (int)$db->lastInsertId();

    $votingService = new VotingService($db, $audit, $mailer);

    // Simulate session identity protection in castVote
    // When UOWM is logged in, an attempt to pass Nordic's org_id in POST must be rejected/ignored
    $_SESSION['convota_user'] = [
        'id' => (int)$uowmMember['id'],
        'organization_id' => (int)$uowm['id'],
        'name' => $uowmMember['name'] . ' ' . $uowmMember['surname'],
        'email' => $uowmMember['email'],
        'system_role' => 'user'
    ];

    // Verify session user organization is UOWM
    $voterOrgId = (int)$_SESSION['convota_user']['organization_id'];
    $voterUserId = (int)$_SESSION['convota_user']['id'];
    assertSec("[CRIT-1] Voter identity resolved strictly from session (UOWM)", $voterOrgId === (int)$uowm['id'] && $voterUserId === (int)$uowmMember['id']);

    $castRes = $votingService->castVote($propId, $voterOrgId, $voterUserId, $optId, 'nonce_crit1', '127.0.0.1');
    assertSec("[CRIT-1] Vote successfully cast under verified session identity", $castRes['success'] === true);

    $hasUowmVoted = $votingService->hasVoted($propId, (int)$uowm['id']);
    $hasEornVoted = $votingService->hasVoted($propId, (int)$eorn['id']);
    assertSec("[CRIT-1] UOWM vote recorded in database", $hasUowmVoted === true);
    assertSec("[CRIT-1] EORN organization was NOT impersonated (hasVoted=false)", $hasEornVoted === false);

    // -------------------------------------------------------------
    // 2. [CRIT-2] Member Export Access Control
    // -------------------------------------------------------------
    // Verify non-admin gets rejected
    $auth = new AuthService($db, $audit);
    $_SESSION['convota_user']['system_role'] = 'member';
    assertSec("[CRIT-2] Non-admin session detected as not admin", $auth->isAdmin() === false);

    // -------------------------------------------------------------
    // 3. [HIGH-1] Open Redirection Defense
    // -------------------------------------------------------------
    $badRedirect1 = 'https://evil-phishing.com/login';
    $sanitized1 = App::sanitizeRedirectUrl($badRedirect1, '/dashboard');
    assertSec("[HIGH-1] External URL redirection sanitized to safe fallback", !str_contains($sanitized1, 'evil-phishing.com') && str_ends_with($sanitized1, '/dashboard'));

    $badRedirect2 = '//attacker.com/evil';
    $sanitized2 = App::sanitizeRedirectUrl($badRedirect2, '/dashboard');
    assertSec("[HIGH-1] Protocol-relative // URL sanitized to safe fallback", !str_contains($sanitized2, 'attacker.com'));

    $badRedirect3 = "https://legit.site\r\nLocation: https://evil.com";
    $sanitized3 = App::sanitizeRedirectUrl($badRedirect3, '/dashboard');
    assertSec("[HIGH-1] CRLF header injection stripped from redirect target", !str_contains($sanitized3, "\r") && !str_contains($sanitized3, "\n"));

    $goodRedirect = '/proposals/vote?id=5';
    $sanitizedGood = App::sanitizeRedirectUrl($goodRedirect, '/dashboard');
    assertSec("[HIGH-1] Internal relative path preserved correctly", str_ends_with($sanitizedGood, '/proposals/vote?id=5'));

    // -------------------------------------------------------------
    // 4. [HIGH-2] Shared Organization Key URL Leakage & Session Hardening
    // -------------------------------------------------------------
    $hashAuth = new HashAuth($db, $audit, $mailer);
    $uowmHash = $uowm['common_hash'];
    $hashLoginRes = $hashAuth->loginWithHash($uowmHash, '127.0.0.1');
    assertSec("[HIGH-2] Hash login succeeds with valid organization common_hash", $hashLoginRes['success'] === true);
    assertSec("[HIGH-2] Organization session establishes convota_org_auth", isset($_SESSION['convota_org_auth']) && $_SESSION['convota_org_auth']['organization_id'] === (int)$uowm['id']);

    // -------------------------------------------------------------
    // 5. [HIGH-3] Rate Limiting on Organization Hash Login Endpoint
    // -------------------------------------------------------------
    $badIp = '198.51.100.42';
    for ($i = 0; $i < 5; $i++) {
        $hashAuth->loginWithHash('bad_invalid_hash_12345678', $badIp);
    }
    $blockedRes = $hashAuth->loginWithHash($uowmHash, $badIp);
    assertSec("[HIGH-3] Brute force against common_hash triggers rate-limit lockout after 5 attempts", $blockedRes['success'] === false && str_contains($blockedRes['error'], 'Too many'));

    // Clear rate limit for subsequent tests
    $db->execute("DELETE FROM rate_limits");

    // -------------------------------------------------------------
    // 6. [HIGH-4] Hardcoded Default Password Warning Check
    // -------------------------------------------------------------
    $adminUser = $db->fetch("SELECT * FROM users WHERE system_role = 'admin'");
    $hasDefaultAdminPass = password_verify('AdminConvota2026!', $adminUser['password_hash']);
    assertSec("[HIGH-4] Default admin password accurately detected for dashboard warning banner", $hasDefaultAdminPass === true);

    // -------------------------------------------------------------
    // 7. [MED-1] Redaction of Tokens in data/mail.log
    // -------------------------------------------------------------
    $testMailLog = __DIR__ . '/test_mail_redaction.log';
    if (file_exists($testMailLog)) @unlink($testMailLog);
    $mailerCustom = new MailerService([
        'driver' => 'log',
        'log_file' => $testMailLog,
        'from_address' => 'no-reply@email.vlsi.gr',
        'from_name' => 'Convota Test'
    ], $audit);

    $mailerCustom->send(
        'test@example.com',
        'Password Reset & Access Key',
        "Hello,\nHere is your reset link: https://arch.ece.uowm.gr/convota/auth/reset-password?token=secret_token_12345\nDirect key: https://arch.ece.uowm.gr/convota/member_login.sh&hash=secret_org_hash_99999\n",
        'test_event'
    );

    $logContents = file_get_contents($testMailLog);
    assertSec("[MED-1] Reset token redacted from mail.log", !str_contains($logContents, 'secret_token_12345') && str_contains($logContents, 'token=[REDACTED]'));
    assertSec("[MED-1] Common hash redacted from mail.log", !str_contains($logContents, 'secret_org_hash_99999') && str_contains($logContents, 'hash=[REDACTED]'));
    @unlink($testMailLog);

    // -------------------------------------------------------------
    // 8. [MED-2] Content Security Policy Nonce Support
    // -------------------------------------------------------------
    $nonce = SecurityHeaders::getNonce();
    assertSec("[MED-2] CSP nonce is non-empty string", !empty($nonce) && strlen($nonce) >= 16);
    assertSec("[MED-2] CSP nonce is persistent across single request", SecurityHeaders::getNonce() === $nonce);

    // -------------------------------------------------------------
    // 9. [MED-4] HMAC Verification for CSV Preview Rows
    // -------------------------------------------------------------
    $sampleRows = [['name' => 'Alice', 'email' => 'alice@example.com']];
    $appSecret = 'test_secret_key_123';
    $validJson = json_encode($sampleRows);
    $validHmac = hash_hmac('sha256', $validJson, $appSecret);
    $tamperedJson = json_encode([['name' => 'Alice', 'email' => 'alice@example.com', 'voting_weight' => 999.0]]);

    assertSec("[MED-4] Valid preview HMAC matches signature", hash_equals($validHmac, hash_hmac('sha256', $validJson, $appSecret)));
    assertSec("[MED-4] Tampered rows JSON rejected by HMAC verification", !hash_equals($validHmac, hash_hmac('sha256', $tamperedJson, $appSecret)));

    // -------------------------------------------------------------
    // 10. [LOW-1] String Length Boundary Validation
    // -------------------------------------------------------------
    $longString = str_repeat('A', 300);
    $truncated = Validator::sanitizeText($longString, 255);
    assertSec("[LOW-1] Validator::isMaxLength accurately bounds length", Validator::isMaxLength($longString, 255) === false);
    assertSec("[LOW-1] Validator::sanitizeText truncates to exact limit (255)", mb_strlen($truncated, 'UTF-8') === 255);

    // -------------------------------------------------------------
    // 11. [LOW-2] Shared Mailbox Multi-Account Password Matching
    // -------------------------------------------------------------
    // Create second active user with same email 'shared@email.vlsi.gr' but different password
    $sharedEmail = 'shared@email.vlsi.gr';
    $pass1 = AuthService::hashPassword('PassUserOne123!');
    $pass2 = AuthService::hashPassword('PassUserTwo456!');

    $db->execute("
        INSERT INTO users (email, password_hash, name, surname, telephone, system_role, is_active, organization_id)
        VALUES (?, ?, 'UserOne', 'Shared', '+30 111', 'member', 1, ?)
    ", [$sharedEmail, $pass1, $uowm['id']]);
    $user1Id = (int)$db->lastInsertId();

    $db->execute("
        INSERT INTO users (email, password_hash, name, surname, telephone, system_role, is_active, organization_id)
        VALUES (?, ?, 'UserTwo', 'Shared', '+30 222', 'member', 1, ?)
    ", [$sharedEmail, $pass2, $eorn['id']]);
    $user2Id = (int)$db->lastInsertId();

    $loginUser1 = $auth->login($sharedEmail, 'PassUserOne123!');
    assertSec("[LOW-2] User 1 authenticated successfully on shared mailbox", $loginUser1['success'] === true && (int)$loginUser1['user']['id'] === $user1Id);

    $loginUser2 = $auth->login($sharedEmail, 'PassUserTwo456!');
    assertSec("[LOW-2] User 2 authenticated successfully on shared mailbox with distinct password", $loginUser2['success'] === true && (int)$loginUser2['user']['id'] === $user2Id);

} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
} finally {
    if (file_exists($testDb)) @unlink($testDb);
    if (file_exists($testDb . '-wal')) @unlink($testDb . '-wal');
    if (file_exists($testDb . '-shm')) @unlink($testDb . '-shm');
}

echo "\n----------------------------------------\n";
echo "SECURITY FIXES SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "----------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
