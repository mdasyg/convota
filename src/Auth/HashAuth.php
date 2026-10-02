<?php
declare(strict_types=1);

namespace Convota\Auth;

use Convota\Database\Database;
use Convota\Services\AuditService;
use Convota\Services\MailerService;

/**
 * Organization Common Access Hash Authentication & Security Manager
 */
class HashAuth
{
    private Database $db;
    private ?AuditService $audit;
    private MailerService $mailer;

    public function __construct(Database $db, ?AuditService $audit = null, ?MailerService $mailer = null)
    {
        $this->db = $db;
        $this->audit = $audit;
        $this->mailer = $mailer ?? new MailerService(null, $audit);
        if ($this->audit !== null) {
            $this->mailer->setAudit($this->audit);
        }
    }

    /**
     * Authenticate and start an organization session using the shared Common Access Hash
     */
    public function loginWithHash(string $hash, ?string $ip = null, ?string $ua = null): array
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $hash = trim($hash);

        if (empty($hash) || strlen($hash) < 16) {
            return ['success' => false, 'error' => 'Invalid or malformed access key.'];
        }

        $org = $this->db->fetch("
            SELECT id, name, short_name, status, voting_weight, is_observer, is_president,
                   common_hash, hash_auth_enabled, email_on_hash_login
            FROM organizations
            WHERE common_hash = ?
        ", [$hash]);

        if (!$org) {
            return ['success' => false, 'error' => 'Invalid Organization Access Key.'];
        }

        if ($org['status'] !== 'active') {
            return ['success' => false, 'error' => 'This organization is currently inactive or suspended.'];
        }

        if (!(int)$org['hash_auth_enabled']) {
            return ['success' => false, 'error' => 'Shared key authorization has been disabled for this organization.'];
        }

        // Establish session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }

        $_SESSION['convota_org_auth'] = [
            'organization_id' => (int)$org['id'],
            'organization_name' => $org['name'],
            'short_name' => $org['short_name'],
            'voting_weight' => (float)$org['voting_weight'],
            'is_observer' => (bool)$org['is_observer'],
            'is_president' => (bool)$org['is_president'],
            'auth_type' => 'common_hash'
        ];
        $_SESSION['convota_last_activity'] = time();

        // Dispatch email notification to members if setting is enabled
        if ((int)$org['email_on_hash_login']) {
            $memberEmails = $this->getMemberEmails((int)$org['id']);
            if (!empty($memberEmails)) {
                $this->mailer->sendHashLoginAlert($memberEmails, $org['name'], $ip, null, (int)$org['id']);
            }
        }

        if ($this->audit) {
            $this->audit->log('common_hash_login', null, (int)$org['id'], ['organization' => $org['name']], $ip, $ua);
        }

        return ['success' => true, 'organization' => $_SESSION['convota_org_auth']];
    }

    /**
     * Get member emails for an organization
     */
    public function getMemberEmails(int $organizationId): array
    {
        $rows = $this->db->fetchAll("
            SELECT email FROM users WHERE organization_id = ? AND is_active = 1
        ", [$organizationId]);
        return array_column($rows, 'email');
    }

    /**
     * Reinitialize and rotate the organization access key, revoking the previous one
     */
    public function rotateHash(int $organizationId, ?int $actorUserId = null, string $baseUrl = ''): string
    {
        $newHash = self::generateUniqueHash($this->db);

        $this->db->execute("
            UPDATE organizations SET common_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ", [$newHash, $organizationId]);

        $org = $this->db->fetch("SELECT name FROM organizations WHERE id = ?", [$organizationId]);
        $orgName = $org['name'] ?? 'Organization';

        // Notify all members with the newly rotated key
        $emails = $this->getMemberEmails($organizationId);
        if (!empty($emails)) {
            $this->mailer->sendOrganizationHash($emails, $orgName, $newHash, true, $baseUrl, $actorUserId, $organizationId);
        }

        if ($this->audit) {
            $this->audit->log('hash_rotated', $actorUserId, $organizationId, ['organization' => $orgName]);
        }

        return $newHash;
    }

    /**
     * Send current hash to all registered members of the organization
     */
    public function emailHashToMembers(int $organizationId, ?int $actorUserId = null, string $baseUrl = ''): bool
    {
        $org = $this->db->fetch("SELECT name, common_hash FROM organizations WHERE id = ?", [$organizationId]);
        if (!$org) {
            return false;
        }

        $emails = $this->getMemberEmails($organizationId);
        if (empty($emails)) {
            return false;
        }

        $success = $this->mailer->sendOrganizationHash($emails, $org['name'], $org['common_hash'], false, $baseUrl, $actorUserId, $organizationId);

        if ($this->audit) {
            $this->audit->log('hash_emailed_to_members', $actorUserId, $organizationId, [
                'recipient_count' => count($emails)
            ]);
        }

        return $success;
    }

    /**
     * Enable or disable hash-based authorization for an organization
     */
    public function setHashAuthEnabled(int $organizationId, bool $enabled, ?int $actorUserId = null): void
    {
        $this->db->execute("
            UPDATE organizations SET hash_auth_enabled = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ", [$enabled ? 1 : 0, $organizationId]);

        if ($this->audit) {
            $this->audit->log('hash_auth_toggled', $actorUserId, $organizationId, ['enabled' => $enabled]);
        }
    }

    /**
     * Toggle email notification on hash login
     */
    public function setEmailOnHashLogin(int $organizationId, bool $enabled, ?int $actorUserId = null): void
    {
        $this->db->execute("
            UPDATE organizations SET email_on_hash_login = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ", [$enabled ? 1 : 0, $organizationId]);

        if ($this->audit) {
            $this->audit->log('email_on_hash_login_toggled', $actorUserId, $organizationId, ['enabled' => $enabled]);
        }
    }

    /**
     * Generate a cryptographically secure, collision-free token
     */
    public static function generateUniqueHash(Database $db): string
    {
        do {
            $hash = 'convota_' . bin2hex(random_bytes(24));
            $existing = $db->fetch("SELECT id FROM organizations WHERE common_hash = ?", [$hash]);
        } while ($existing !== null);

        return $hash;
    }
}
