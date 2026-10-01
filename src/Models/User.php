<?php
declare(strict_types=1);

namespace Convota\Models;

use Convota\Database\Database;
use Convota\Services\AuditService;
use Convota\Services\MailerService;
use Convota\Security\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * User & Organization Member Management Model
 */
class User
{
    private Database $db;
    private AuditService $audit;
    private MailerService $mailer;

    public function __construct(Database $db, ?AuditService $audit = null, ?MailerService $mailer = null)
    {
        $this->db = $db;
        $this->audit = $audit ?? new AuditService($db);
        $this->mailer = $mailer ?? new MailerService();
    }

    /**
     * Add a new member to an organization
     */
    public function addMember(
        int $organizationId,
        array $data,
        ?int $actorUserId = null,
        ?string $ip = null,
        ?string $ua = null,
        string $baseUrl = ''
    ): array {
        $name = Validator::cleanString($data['name'] ?? '');
        $surname = Validator::cleanString($data['surname'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $phone = Validator::cleanString($data['telephone'] ?? null);
        $isPrimary = !empty($data['is_primary']) ? 1 : 0;

        if (empty($name) || empty($surname)) {
            throw new InvalidArgumentException("Member name and surname are required.");
        }

        if (!Validator::isValidEmail($email)) {
            throw new InvalidArgumentException("A valid email address is required.");
        }

        // Ensure email is unique across the entire system
        $existing = $this->db->fetch("SELECT id FROM users WHERE LOWER(email) = ?", [$email]);
        if ($existing) {
            throw new InvalidArgumentException("A user with email '{$email}' already exists.");
        }

        // Verify organization exists
        $org = $this->db->fetch("SELECT id, name, is_president FROM organizations WHERE id = ?", [$organizationId]);
        if (!$org) {
            throw new RuntimeException("Organization not found.");
        }

        $this->db->beginTransactionImmediate();

        try {
            if ($isPrimary) {
                // Remove primary flag from other members of this organization
                $this->db->execute("UPDATE users SET is_primary = 0 WHERE organization_id = ?", [$organizationId]);
            }

            $resetToken = bin2hex(random_bytes(32));
            $resetExpires = gmdate('Y-m-d H:i:s', time() + 86400 * 3);
            $systemRole = ((int)$org['is_president'] && $isPrimary) ? 'president' : 'member';

            $this->db->execute("
                INSERT INTO users (
                    organization_id, email, name, surname, telephone,
                    is_primary, system_role, reset_token, reset_token_expires_at,
                    is_active, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
            ", [
                $organizationId, $email, $name, $surname, $phone,
                $isPrimary, $systemRole, $resetToken, $resetExpires, $actorUserId
            ]);

            $newUserId = (int)$this->db->lastInsertId();

            $this->audit->log('member_added', $actorUserId, $organizationId, [
                'user_id' => $newUserId,
                'name' => "{$name} {$surname}",
                'email' => $email,
                'is_primary' => (bool)$isPrimary
            ], $ip, $ua);

            $this->db->commit();

            // Dispatch welcome password setup email
            $this->mailer->sendPasswordSetup($email, "{$name} {$surname}", $resetToken, $baseUrl);

            return [
                'id' => $newUserId,
                'email' => $email,
                'name' => "{$name} {$surname}",
                'reset_token' => $resetToken
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Remove a member from an organization (including primary members)
     * Provides historical accountability by recording who removed whom, IP, and timestamp.
     */
    public function removeMember(
        int $userId,
        int $organizationId,
        ?int $actorUserId = null,
        ?string $ip = null,
        ?string $ua = null
    ): bool {
        $user = $this->db->fetch("
            SELECT id, email, name, surname, is_primary FROM users
            WHERE id = ? AND organization_id = ?
        ", [$userId, $organizationId]);

        if (!$user) {
            return false;
        }

        $this->db->beginTransactionImmediate();

        try {
            // Unlink created_by references before deleting user row
            $this->db->execute("UPDATE users SET created_by = NULL WHERE created_by = ?", [$userId]);
            $this->db->execute("DELETE FROM users WHERE id = ?", [$userId]);

            // If the deleted member was primary, automatically assign another active member as primary if available
            if ((int)$user['is_primary']) {
                $next = $this->db->fetch("
                    SELECT id FROM users
                    WHERE organization_id = ? AND is_active = 1
                    ORDER BY id ASC LIMIT 1
                ", [$organizationId]);

                if ($next) {
                    $this->db->execute("UPDATE users SET is_primary = 1 WHERE id = ?", [$next['id']]);
                }
            }

            // Historical accountability audit log
            $this->audit->log('member_removed', $actorUserId, $organizationId, [
                'removed_user_id' => $userId,
                'removed_name' => "{$user['name']} {$user['surname']}",
                'removed_email' => $user['email'],
                'was_primary' => (bool)$user['is_primary']
            ], $ip, $ua);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Set a member as the primary delegate for their organization
     */
    public function setPrimary(int $userId, int $organizationId, ?int $actorUserId = null, ?string $ip = null): bool
    {
        $this->db->beginTransactionImmediate();

        try {
            $this->db->execute("UPDATE users SET is_primary = 0 WHERE organization_id = ?", [$organizationId]);
            $this->db->execute("UPDATE users SET is_primary = 1 WHERE id = ? AND organization_id = ?", [$userId, $organizationId]);

            $user = $this->getById($userId);
            $this->audit->log('primary_delegate_changed', $actorUserId, $organizationId, [
                'user_id' => $userId,
                'name' => ($user['name'] ?? '') . ' ' . ($user['surname'] ?? '')
            ], $ip);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getById(int $id): ?array
    {
        return $this->db->fetch("
            SELECT u.*, o.name as organization_name, o.voting_weight, o.is_observer, o.is_president
            FROM users u
            LEFT JOIN organizations o ON u.organization_id = o.id
            WHERE u.id = ?
        ", [$id]);
    }

    public function getByEmail(string $email): ?array
    {
        return $this->db->fetch("
            SELECT u.*, o.name as organization_name, o.voting_weight, o.is_observer, o.is_president
            FROM users u
            LEFT JOIN organizations o ON u.organization_id = o.id
            WHERE LOWER(u.email) = ?
        ", [strtolower(trim($email))]);
    }

    public function getMembersByOrg(int $organizationId): array
    {
        return $this->db->fetchAll("
            SELECT id, email, name, surname, telephone, is_primary, system_role, is_active, created_at
            FROM users
            WHERE organization_id = ? AND is_active = 1
            ORDER BY is_primary DESC, surname ASC, name ASC
        ", [$organizationId]);
    }

    public function updateProfile(int $id, array $data, ?int $actorUserId = null, ?string $ip = null): bool
    {
        $name = Validator::cleanString($data['name'] ?? '');
        $surname = Validator::cleanString($data['surname'] ?? '');
        $phone = Validator::cleanString($data['telephone'] ?? null);

        if (empty($name) || empty($surname)) {
            throw new InvalidArgumentException("Name and surname cannot be empty.");
        }

        $this->db->execute("
            UPDATE users SET name = ?, surname = ?, telephone = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ", [$name, $surname, $phone, $id]);

        $this->audit->log('profile_updated', $actorUserId ?? $id, null, ['user_id' => $id, 'name' => "{$name} {$surname}"], $ip);
        return true;
    }
}
