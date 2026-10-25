<?php
declare(strict_types=1);

namespace Convota\Auth;

use Convota\Database\Database;
use Convota\Services\AuditService;

/**
 * Core User Authentication & Session Security Service
 */
class AuthService
{
    private Database $db;
    private ?AuditService $audit;
    private array $securityConfig;

    public function __construct(Database $db, ?AuditService $audit = null)
    {
        $this->db = $db;
        $this->audit = $audit;
        $config = require __DIR__ . '/../../config/config.php';
        $this->securityConfig = $config['security'];
    }

    /**
     * Start secure session if not already active
     */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->checkIdleTimeout();
            return;
        }

        if (!headers_sent()) {
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_only_cookies', '1');
            @ini_set('session.cookie_httponly', '1');

            $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
            $samesite = $this->securityConfig['cookie_samesite'] ?? 'Strict';

            @session_set_cookie_params([
                'lifetime' => $this->securityConfig['session_lifetime'] ?? 7200,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => $samesite
            ]);

            @session_name($this->securityConfig['session_name'] ?? 'CONVOTA_SESSID');
        }

        @session_start();

        // Check idle timeout
        $this->checkIdleTimeout();
    }

    private function checkIdleTimeout(): void
    {
        $timeout = $this->securityConfig['session_idle_timeout'] ?? 1800;
        $now = time();

        if (isset($_SESSION['convota_last_activity'])) {
            if ($now - $_SESSION['convota_last_activity'] > $timeout) {
                $this->logout();
                return;
            }
        }
        $_SESSION['convota_last_activity'] = $now;
    }

    /**
     * Rate limiting check
     */
    public function isRateLimited(string $key): bool
    {
        $now = time();
        $maxAttempts = $this->securityConfig['rate_limit_max_attempts'] ?? 5;
        $decay = $this->securityConfig['rate_limit_decay_seconds'] ?? 900;

        $row = $this->db->fetch("SELECT attempts, last_attempt_at FROM rate_limits WHERE key = ?", [$key]);
        if (!$row) {
            return false;
        }

        if ($now - (int)$row['last_attempt_at'] > $decay) {
            // Expired lockout window
            $this->db->execute("DELETE FROM rate_limits WHERE key = ?", [$key]);
            return false;
        }

        return (int)$row['attempts'] >= $maxAttempts;
    }

    public function recordFailedAttempt(string $key): void
    {
        $now = time();
        $this->db->execute("
            INSERT INTO rate_limits (key, attempts, last_attempt_at)
            VALUES (?, 1, ?)
            ON CONFLICT(key) DO UPDATE SET
                attempts = attempts + 1,
                last_attempt_at = excluded.last_attempt_at
        ", [$key, $now]);
    }

    public function clearRateLimit(string $key): void
    {
        $this->db->execute("DELETE FROM rate_limits WHERE key = ?", [$key]);
    }

    /**
     * Authenticate user with email and password
     */
    public function login(string $email, string $password, ?string $ip = null, ?string $ua = null): array
    {
        $this->startSession();
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $email = strtolower(trim($email));
        $rateLimitKey = 'login:' . md5($email . '|' . $ip);

        if ($this->isRateLimited($rateLimitKey)) {
            return [
                'success' => false,
                'error' => 'Too many failed login attempts. Please wait 15 minutes before trying again.'
            ];
        }

        // Query all matching active users to resolve password authentication unambiguously even on shared institutional mailboxes
        $candidates = $this->db->fetchAll("
            SELECT u.*, o.name as organization_name, o.is_observer, o.voting_weight, o.is_president
            FROM users u
            LEFT JOIN organizations o ON u.organization_id = o.id
            WHERE LOWER(u.email) = ? AND u.is_active = 1
        ", [$email]);

        if (empty($candidates)) {
            $this->recordFailedAttempt($rateLimitKey);
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        $user = null;
        foreach ($candidates as $candidate) {
            if (!empty($candidate['password_hash']) && password_verify($password, $candidate['password_hash'])) {
                $user = $candidate;
                break;
            }
        }

        if (!$user) {
            $this->recordFailedAttempt($rateLimitKey);
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        // Check if password needs rehash
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            $newHash = self::hashPassword($password);
            $this->db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $user['id']]);
        }

        // Clear rate limit
        $this->clearRateLimit($rateLimitKey);

        // Regenerate session ID to prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }

        $_SESSION['convota_user'] = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'] . ' ' . $user['surname'],
            'first_name' => $user['name'],
            'surname' => $user['surname'],
            'system_role' => $user['system_role'],
            'organization_id' => $user['organization_id'] ? (int)$user['organization_id'] : null,
            'organization_name' => $user['organization_name'],
            'is_primary' => (bool)$user['is_primary'],
            'is_observer' => (bool)($user['is_observer'] ?? false),
            'auth_type' => 'user'
        ];
        $_SESSION['convota_last_activity'] = time();

        if ($this->audit) {
            $this->audit->log('user_login', (int)$user['id'], $user['organization_id'] ? (int)$user['organization_id'] : null, ['email' => $email], $ip, $ua);
        }

        return ['success' => true, 'user' => $_SESSION['convota_user']];
    }

    /**
     * Terminate the session
     */
    public function logout(): void
    {
        $this->startSession();
        $user = $_SESSION['convota_user'] ?? null;
        if ($user && $this->audit) {
            $this->audit->log('user_logout', $user['id'] ?? null, $user['organization_id'] ?? null);
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy();
    }

    /**
     * Get current authenticated user session data
     */
    public function user(): ?array
    {
        $this->startSession();
        return $_SESSION['convota_user'] ?? null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        $u = $this->user();
        return $u && $u['system_role'] === 'admin';
    }

    public function isPresident(): bool
    {
        $u = $this->user();
        return $u && ($u['system_role'] === 'admin' || $u['system_role'] === 'president');
    }

    /**
     * Hash password with Argon2id or fallback
     */
    public static function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 2
            ]);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Generate password reset token
     */
    public function generateResetToken(string $email): ?string
    {
        $user = $this->db->fetch("SELECT id FROM users WHERE LOWER(email) = ? AND is_active = 1", [strtolower(trim($email))]);
        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expires = gmdate('Y-m-d H:i:s', time() + 86400); // 24 hours

        $this->db->execute("
            UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?
        ", [$token, $expires, $user['id']]);

        return $token;
    }

    /**
     * Reset password using a valid reset token
     */
    public function resetPasswordWithToken(string $token, string $newPassword): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $user = $this->db->fetch("
            SELECT id, email, organization_id FROM users
            WHERE reset_token = ? AND reset_token_expires_at > ? AND is_active = 1
        ", [$token, $now]);

        if (!$user) {
            return false;
        }

        $newHash = self::hashPassword($newPassword);
        $this->db->execute("
            UPDATE users
            SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ", [$newHash, $user['id']]);

        if ($this->audit) {
            $this->audit->log('password_reset_completed', (int)$user['id'], $user['organization_id'] ? (int)$user['organization_id'] : null);
        }

        return true;
    }
}
