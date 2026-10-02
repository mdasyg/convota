<?php
declare(strict_types=1);

namespace Convota\Models;

use Convota\Database\Database;
use Convota\Auth\HashAuth;
use Convota\Auth\AuthService;
use Convota\Services\AuditService;
use Convota\Services\MailerService;
use Convota\Security\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Organization Entity & Management Model
 */
class Organization
{
    private Database $db;
    private AuditService $audit;
    private MailerService $mailer;

    public function __construct(Database $db, ?AuditService $audit = null, ?MailerService $mailer = null)
    {
        $this->db = $db;
        $this->audit = $audit ?? new AuditService($db);
        $this->mailer = $mailer ?? new MailerService(null, $this->audit);
        if ($this->audit !== null) {
            $this->mailer->setAudit($this->audit);
        }
    }

    /**
     * Create an organization along with its first member
     */
    public function createWithFirstMember(
        array $orgData,
        array $firstMemberData,
        ?int $creatorUserId = null,
        ?string $ip = null,
        ?string $ua = null,
        string $baseUrl = ''
    ): array {
        $name = Validator::cleanString($orgData['name'] ?? '');
        if (empty($name)) {
            throw new InvalidArgumentException("Organization name is required.");
        }

        $memberName = Validator::cleanString($firstMemberData['name'] ?? '');
        $memberSurname = Validator::cleanString($firstMemberData['surname'] ?? '');
        $memberEmail = strtolower(trim($firstMemberData['email'] ?? ''));
        $memberPhone = Validator::cleanString($firstMemberData['telephone'] ?? null);

        if (empty($memberName) || empty($memberSurname)) {
            throw new InvalidArgumentException("First member name and surname are required.");
        }

        if (!Validator::isValidEmail($memberEmail)) {
            throw new InvalidArgumentException("Valid email address is required for the first member.");
        }

        // Check if member email already exists
        $existingUser = $this->db->fetch("SELECT id FROM users WHERE LOWER(email) = ?", [$memberEmail]);
        if ($existingUser) {
            throw new InvalidArgumentException("A user with email '{$memberEmail}' already exists in the system.");
        }

        $this->db->beginTransactionImmediate();

        try {
            $commonHash = HashAuth::generateUniqueHash($this->db);
            $shortName = Validator::cleanString($orgData['short_name'] ?? null);
            $description = Validator::cleanString($orgData['description'] ?? null);
            $country = Validator::cleanString($orgData['country'] ?? null);
            $website = Validator::cleanString($orgData['website'] ?? null);
            $rawStatus = $orgData['status'] ?? 'active';
            $status = in_array($rawStatus, ['active', 'inactive', 'suspended'], true) ? $rawStatus : 'active';
            $votingWeight = Validator::isValidWeight($orgData['voting_weight'] ?? 1.0) ? (float)$orgData['voting_weight'] : 1.0;
            $isObserver = !empty($orgData['is_observer']) ? 1 : 0;
            if ($isObserver) {
                $votingWeight = 0.0;
            }
            $isPresident = !empty($orgData['is_president']) ? 1 : 0;

            if ($isPresident) {
                // Remove president flag from any other organization
                $this->db->execute("UPDATE organizations SET is_president = 0");
            }

            $this->db->execute("
                INSERT INTO organizations (
                    name, short_name, description, country, website, status,
                    voting_weight, is_observer, is_president, common_hash,
                    hash_auth_enabled, email_on_hash_login
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
            ", [
                $name, $shortName, $description, $country, $website, $status,
                $votingWeight, $isObserver, $isPresident, $commonHash
            ]);

            $orgId = (int)$this->db->lastInsertId();

            // Create initial member
            $resetToken = bin2hex(random_bytes(32));
            $resetExpires = gmdate('Y-m-d H:i:s', time() + 86400 * 3); // 3 days for setup
            $systemRole = $isPresident ? 'president' : 'member';

            $this->db->execute("
                INSERT INTO users (
                    organization_id, email, password_hash, name, surname, telephone,
                    is_primary, system_role, reset_token, reset_token_expires_at,
                    is_active, created_by
                ) VALUES (?, ?, NULL, ?, ?, ?, 1, ?, ?, ?, 1, ?)
            ", [
                $orgId, $memberEmail, $memberName, $memberSurname, $memberPhone,
                $systemRole, $resetToken, $resetExpires, $creatorUserId
            ]);

            $userId = (int)$this->db->lastInsertId();

            // Audit logging
            $this->audit->log('organization_created', $creatorUserId, $orgId, [
                'name' => $name,
                'first_member_email' => $memberEmail
            ], $ip, $ua);

            $this->audit->log('member_added', $creatorUserId, $orgId, [
                'user_id' => $userId,
                'email' => $memberEmail,
                'name' => "{$memberName} {$memberSurname}",
                'is_primary' => true
            ], $ip, $ua);

            $this->db->commit();

            // Dispatch welcome & password setup email
            if (empty($baseUrl)) {
                $baseUrl = \Convota\App::discoverBaseUrl();
            }
            $this->mailer->sendPasswordSetup($memberEmail, "{$memberName} {$memberSurname}", $resetToken, $baseUrl, $creatorUserId, $orgId);

            return [
                'organization_id' => $orgId,
                'user_id' => $userId,
                'common_hash' => $commonHash,
                'reset_token' => $resetToken
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update organization metadata and voting weight
     */
    public function update(int $id, array $data, ?int $actorUserId = null, ?string $ip = null, ?string $ua = null): bool
    {
        $org = $this->getById($id);
        if (!$org) {
            return false;
        }

        $name = Validator::cleanString($data['name'] ?? $org['name']);
        if (empty($name)) {
            throw new InvalidArgumentException("Organization name cannot be empty.");
        }

        $shortName = isset($data['short_name']) ? Validator::cleanString($data['short_name']) : $org['short_name'];
        $description = isset($data['description']) ? Validator::cleanString($data['description']) : $org['description'];
        $country = isset($data['country']) ? Validator::cleanString($data['country']) : $org['country'];
        $website = isset($data['website']) ? Validator::cleanString($data['website']) : $org['website'];
        $status = in_array($data['status'] ?? $org['status'], ['active', 'inactive', 'suspended'], true) ? $data['status'] : $org['status'];
        
        $isObserver = isset($data['is_observer']) ? ((int)$data['is_observer'] ? 1 : 0) : (int)$org['is_observer'];
        $votingWeight = $isObserver ? 0.0 : (float)($data['voting_weight'] ?? $org['voting_weight']);

        $this->db->execute("
            UPDATE organizations SET
                name = ?, short_name = ?, description = ?, country = ?, website = ?,
                status = ?, voting_weight = ?, is_observer = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ", [$name, $shortName, $description, $country, $website, $status, $votingWeight, $isObserver, $id]);

        $this->audit->log('organization_updated', $actorUserId, $id, [
            'name' => $name,
            'status' => $status,
            'voting_weight' => $votingWeight,
            'is_observer' => $isObserver
        ], $ip, $ua);

        return true;
    }

    /**
     * Designate an organization as the President Organization
     */
    public function setPresident(int $organizationId, ?int $adminUserId = null, ?string $ip = null): void
    {
        $this->db->beginTransactionImmediate();
        try {
            // Clear current president
            $this->db->execute("UPDATE organizations SET is_president = 0");
            // Set new president
            $this->db->execute("UPDATE organizations SET is_president = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$organizationId]);

            // Demote previous president users to member
            $this->db->execute("UPDATE users SET system_role = 'member' WHERE system_role = 'president'");

            // Promote primary member of new president organization to 'president'
            $this->db->execute("
                UPDATE users SET system_role = 'president'
                WHERE organization_id = ? AND is_primary = 1
            ", [$organizationId]);

            $org = $this->getById($organizationId);
            $this->audit->log('president_organization_changed', $adminUserId, $organizationId, [
                'organization_name' => $org['name'] ?? ''
            ], $ip);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getById(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM organizations WHERE id = ?", [$id]);
    }

    public function getByHash(string $hash): ?array
    {
        return $this->db->fetch("SELECT * FROM organizations WHERE common_hash = ?", [$hash]);
    }

    public function getAll(bool $onlyActive = false): array
    {
        $sql = "
            SELECT o.*, 
                   (SELECT COUNT(*) FROM users u WHERE u.organization_id = o.id AND u.is_active = 1) as member_count,
                   (SELECT u.name || ' ' || u.surname FROM users u WHERE u.organization_id = o.id AND u.is_primary = 1 AND u.is_active = 1 LIMIT 1) as primary_member_name
            FROM organizations o
        ";
        if ($onlyActive) {
            $sql .= " WHERE o.status = 'active'";
        }
        $sql .= " ORDER BY o.is_president DESC, o.name ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Get organization with its complete roster of members
     */
    public function getWithMembers(int $id): ?array
    {
        $org = $this->getById($id);
        if (!$org) {
            return null;
        }

        $org['members'] = $this->db->fetchAll("
            SELECT id, email, name, surname, telephone, is_primary, system_role, is_active, created_at
            FROM users
            WHERE organization_id = ? AND is_active = 1
            ORDER BY is_primary DESC, surname ASC, name ASC
        ", [$id]);

        return $org;
    }

    /**
     * Delete an organization (admin only)
     */
    public function delete(int $id, ?int $adminUserId = null, ?string $ip = null): bool
    {
        $org = $this->getById($id);
        if (!$org) {
            return false;
        }

        $this->db->beginTransactionImmediate();
        try {
            $this->db->execute("DELETE FROM users WHERE organization_id = ?", [$id]);
            $this->db->execute("DELETE FROM organizations WHERE id = ?", [$id]);

            $this->audit->log('organization_deleted', $adminUserId, $id, ['name' => $org['name']], $ip);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
