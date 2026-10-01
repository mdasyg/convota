<?php
declare(strict_types=1);

namespace Convota\Models;

use Convota\Database\Database;
use Convota\Services\AuditService;
use Convota\Security\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Meeting / Assembly Lifecycle & Attendance Management Model
 */
class Meeting
{
    private Database $db;
    private AuditService $audit;

    public function __construct(Database $db, ?AuditService $audit = null)
    {
        $this->db = $db;
        $this->audit = $audit ?? new AuditService($db);
    }

    /**
     * Create a new meeting
     */
    public function create(array $data, int $creatorUserId, ?string $ip = null): int
    {
        $title = Validator::cleanString($data['title'] ?? '');
        if (empty($title)) {
            throw new InvalidArgumentException("Meeting title is required.");
        }

        $description = Validator::cleanString($data['description'] ?? null);
        $location = Validator::cleanString($data['location'] ?? null);
        $scheduledAt = $data['scheduled_at'] ?? gmdate('Y-m-d H:i:s');
        $requireQuorum = isset($data['require_quorum']) ? ((int)$data['require_quorum'] ? 1 : 0) : 1;
        $quorumPercentage = isset($data['quorum_percentage']) && is_numeric($data['quorum_percentage'])
            ? (float)$data['quorum_percentage']
            : 50.0;

        $this->db->execute("
            INSERT INTO meetings (
                title, description, location, scheduled_at, status,
                require_quorum, quorum_percentage, created_by
            ) VALUES (?, ?, ?, ?, 'draft', ?, ?, ?)
        ", [$title, $description, $location, $scheduledAt, $requireQuorum, $quorumPercentage, $creatorUserId]);

        $meetingId = (int)$this->db->lastInsertId();

        $this->audit->log('meeting_created', $creatorUserId, null, [
            'meeting_id' => $meetingId,
            'title' => $title,
            'scheduled_at' => $scheduledAt,
            'quorum_percentage' => $quorumPercentage
        ], $ip);

        return $meetingId;
    }

    /**
     * Update meeting status with strict state machine transitions
     */
    public function updateStatus(int $meetingId, string $newStatus, ?int $actorUserId = null, ?string $ip = null): bool
    {
        $allowed = ['draft', 'registration', 'in_session', 'concluded'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException("Invalid meeting status: {$newStatus}");
        }

        $meeting = $this->getById($meetingId);
        if (!$meeting) {
            return false;
        }

        $currentStatus = $meeting['status'];
        if ($currentStatus === $newStatus) {
            return true;
        }

        $concludedAt = ($newStatus === 'concluded') ? gmdate('Y-m-d H:i:s') : null;

        $this->db->execute("
            UPDATE meetings SET status = ?, concluded_at = ? WHERE id = ?
        ", [$newStatus, $concludedAt, $meetingId]);

        $this->audit->log('meeting_status_changed', $actorUserId, null, [
            'meeting_id' => $meetingId,
            'old_status' => $currentStatus,
            'new_status' => $newStatus
        ], $ip);

        return true;
    }

    /**
     * Designate the active participating representative and mark presence for an organization
     *
     * Core Principle: The vote belongs to the organization; the designated representative
     * is the authenticated person exercising that organization's voting right.
     */
    public function setPresence(
        int $meetingId,
        int $organizationId,
        ?int $representativeUserId,
        string $checkInMethod = 'self_login', // 'self_login', 'common_hash', 'admin_rollcall'
        ?int $actorUserId = null,
        ?string $ip = null
    ): bool {
        // Validate organization exists
        $org = $this->db->fetch("SELECT id, name FROM organizations WHERE id = ?", [$organizationId]);
        if (!$org) {
            throw new RuntimeException("Organization not found.");
        }

        // Validate representative belongs to organization if provided
        if ($representativeUserId !== null) {
            $user = $this->db->fetch("
                SELECT id, name, surname FROM users
                WHERE id = ? AND organization_id = ? AND is_active = 1
            ", [$representativeUserId, $organizationId]);

            if (!$user) {
                throw new InvalidArgumentException("Representative must be an active registered member of the organization.");
            }
        }

        $validMethods = ['self_login', 'common_hash', 'admin_rollcall'];
        if (!in_array($checkInMethod, $validMethods, true)) {
            $checkInMethod = 'self_login';
        }

        $this->db->execute("
            INSERT INTO meeting_presence (
                meeting_id, organization_id, representative_user_id, is_present,
                check_in_method, checked_in_ip, checked_in_at
            ) VALUES (?, ?, ?, 1, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(meeting_id, organization_id) DO UPDATE SET
                representative_user_id = excluded.representative_user_id,
                is_present = 1,
                check_in_method = excluded.check_in_method,
                checked_in_ip = excluded.checked_in_ip,
                checked_in_at = CURRENT_TIMESTAMP
        ", [$meetingId, $organizationId, $representativeUserId, $checkInMethod, $ip]);

        $this->audit->log('presence_marked', $actorUserId, $organizationId, [
            'meeting_id' => $meetingId,
            'organization_name' => $org['name'],
            'representative_user_id' => $representativeUserId,
            'check_in_method' => $checkInMethod
        ], $ip);

        return true;
    }

    /**
     * Remove organization presence (mark absent)
     */
    public function removePresence(int $meetingId, int $organizationId, ?int $actorUserId = null, ?string $ip = null): bool
    {
        $this->db->execute("
            DELETE FROM meeting_presence WHERE meeting_id = ? AND organization_id = ?
        ", [$meetingId, $organizationId]);

        $this->audit->log('presence_removed', $actorUserId, $organizationId, [
            'meeting_id' => $meetingId
        ], $ip);

        return true;
    }

    public function getById(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM meetings WHERE id = ?", [$id]);
    }

    public function getAll(): array
    {
        return $this->db->fetchAll("
            SELECT m.*, 
                   (SELECT COUNT(*) FROM meeting_presence p WHERE p.meeting_id = m.id AND p.is_present = 1) as attendee_count,
                   u.name || ' ' || u.surname as creator_name
            FROM meetings m
            LEFT JOIN users u ON m.created_by = u.id
            ORDER BY m.scheduled_at DESC, m.id DESC
        ");
    }

    public function getActiveMeeting(): ?array
    {
        return $this->db->fetch("
            SELECT * FROM meetings
            WHERE status IN ('registration', 'in_session')
            ORDER BY scheduled_at ASC LIMIT 1
        ");
    }
}
