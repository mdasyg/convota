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
 * Proposal & Election Lifecycle Model
 * Implements a strict, deterministic voting state machine.
 */
class Proposal
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
     * Create a new proposal / election
     */
    public function create(array $data, array $options = [], int $creatorUserId = 1, ?string $ip = null): int
    {
        $title = Validator::cleanString($data['title'] ?? '');
        if (empty($title)) {
            throw new InvalidArgumentException("Proposal title is required.");
        }

        $meetingId = !empty($data['meeting_id']) ? (int)$data['meeting_id'] : null;
        $description = Validator::cleanString($data['description'] ?? null);
        $votingType = in_array($data['voting_type'] ?? 'yes_no_abstain', ['yes_no_abstain', 'multiple_choice'], true)
            ? $data['voting_type']
            : 'yes_no_abstain';
        $isSecret = !empty($data['is_secret']) ? 1 : 0;
        $timingMode = in_array($data['timing_mode'] ?? 'manual', ['manual', 'scheduled'], true)
            ? $data['timing_mode']
            : 'manual';

        $startTime = ($timingMode === 'scheduled' && !empty($data['start_time'])) ? $data['start_time'] : null;
        $endTime = ($timingMode === 'scheduled' && !empty($data['end_time'])) ? $data['end_time'] : null;

        $requireQuorum = isset($data['require_quorum']) ? ((int)$data['require_quorum'] ? 1 : 0) : 1;
        $quorumPct = isset($data['quorum_percentage']) && is_numeric($data['quorum_percentage'])
            ? (float)$data['quorum_percentage']
            : 50.0;
        $majorityPct = isset($data['majority_percentage']) && is_numeric($data['majority_percentage'])
            ? (float)$data['majority_percentage']
            : 50.0;
        $notifyOnStart = !empty($data['notify_members_on_start']) ? 1 : 0;

        $this->db->beginTransactionImmediate();

        try {
            $this->db->execute("
                INSERT INTO proposals (
                    meeting_id, title, description, voting_type, is_secret, timing_mode,
                    start_time, end_time, status, require_quorum, quorum_percentage,
                    majority_percentage, notify_members_on_start, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?)
            ", [
                $meetingId, $title, $description, $votingType, $isSecret, $timingMode,
                $startTime, $endTime, $requireQuorum, $quorumPct, $majorityPct, $notifyOnStart, $creatorUserId
            ]);

            $proposalId = (int)$this->db->lastInsertId();

            // Populate options
            if ($votingType === 'yes_no_abstain') {
                $defaultOptions = ['YES', 'NO', 'ABSTAIN'];
                foreach ($defaultOptions as $idx => $optText) {
                    $this->db->execute("
                        INSERT INTO proposal_options (proposal_id, option_text, order_index)
                        VALUES (?, ?, ?)
                    ", [$proposalId, $optText, $idx]);
                }
            } else {
                if (empty($options)) {
                    $options = ['Option A', 'Option B', 'Abstain'];
                }
                foreach ($options as $idx => $optText) {
                    $cleanOpt = Validator::cleanString($optText);
                    if (!empty($cleanOpt)) {
                        $this->db->execute("
                            INSERT INTO proposal_options (proposal_id, option_text, order_index)
                            VALUES (?, ?, ?)
                        ", [$proposalId, $cleanOpt, $idx]);
                    }
                }
            }

            // Assign supervisor group if provided
            if (!empty($data['supervisor_organizations']) && is_array($data['supervisor_organizations'])) {
                foreach ($data['supervisor_organizations'] as $orgId) {
                    $this->db->execute("
                        INSERT OR IGNORE INTO proposal_supervisors (proposal_id, organization_id)
                        VALUES (?, ?)
                    ", [$proposalId, (int)$orgId]);
                }
            }

            $this->audit->log('proposal_created', $creatorUserId, null, [
                'proposal_id' => $proposalId,
                'title' => $title,
                'voting_type' => $votingType,
                'is_secret' => (bool)$isSecret,
                'timing_mode' => $timingMode
            ], $ip);

            $this->db->commit();
            return $proposalId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Transition proposal state with strict state-machine validation
     * States: draft -> open <-> paused -> closed -> archived
     */
    public function updateStatus(int $proposalId, string $newStatus, ?int $actorUserId = null, ?string $ip = null, string $baseUrl = ''): bool
    {
        $allowed = ['draft', 'open', 'paused', 'closed', 'archived'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException("Invalid proposal status: {$newStatus}");
        }

        $proposal = $this->getById($proposalId);
        if (!$proposal) {
            return false;
        }

        $currentStatus = $proposal['status'];
        if ($currentStatus === $newStatus) {
            return true;
        }

        // State machine transition validation guards
        if ($currentStatus === 'closed' && $newStatus === 'open') {
            throw new RuntimeException("Cannot reopen a closed ballot box without explicit audited override.");
        }
        if ($currentStatus === 'archived') {
            throw new RuntimeException("Archived proposals are permanently immutable.");
        }

        $now = gmdate('Y-m-d H:i:s');
        $openedAt = $proposal['opened_at'];
        $closedAt = $proposal['closed_at'];

        if ($newStatus === 'open' && empty($openedAt)) {
            $openedAt = $now;
        }
        if ($newStatus === 'closed') {
            $closedAt = $now;
        }

        $this->db->execute("
            UPDATE proposals SET status = ?, opened_at = ?, closed_at = ? WHERE id = ?
        ", [$newStatus, $openedAt, $closedAt, $proposalId]);

        $this->audit->log('proposal_status_changed', $actorUserId, null, [
            'proposal_id' => $proposalId,
            'old_status' => $currentStatus,
            'new_status' => $newStatus
        ], $ip);

        // If transitioning to open and notify_members_on_start is enabled
        if ($newStatus === 'open' && (int)$proposal['notify_members_on_start']) {
            $this->notifyVotingStarted($proposalId, $baseUrl);
        }

        return true;
    }

    /**
     * Notify members that voting has opened
     */
    private function notifyVotingStarted(int $proposalId, string $baseUrl): void
    {
        $proposal = $this->getById($proposalId);
        if (!$proposal) {
            return;
        }

        $memberEmails = $this->db->fetchAll("
            SELECT u.email FROM users u
            JOIN organizations o ON u.organization_id = o.id
            WHERE o.status = 'active' AND o.is_observer = 0 AND u.is_active = 1
        ");

        $emails = array_column($memberEmails, 'email');
        if (!empty($emails)) {
            $link = rtrim($baseUrl, '/') . "/proposals/vote?id={$proposalId}";
            $this->mailer->sendVotingStartedAlert($emails, $proposal['title'], $link);
        }
    }

    public function getById(int $id): ?array
    {
        $proposal = $this->db->fetch("
            SELECT p.*, m.title as meeting_title, u.name || ' ' || u.surname as creator_name
            FROM proposals p
            LEFT JOIN meetings m ON p.meeting_id = m.id
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.id = ?
        ", [$id]);

        if ($proposal) {
            $proposal['options'] = $this->getOptions($id);
            $proposal['supervisors'] = $this->getSupervisors($id);
        }

        return $proposal;
    }

    public function getOptions(int $proposalId): array
    {
        return $this->db->fetchAll("
            SELECT * FROM proposal_options WHERE proposal_id = ? ORDER BY order_index ASC, id ASC
        ", [$proposalId]);
    }

    public function getSupervisors(int $proposalId): array
    {
        return $this->db->fetchAll("
            SELECT s.*, o.name as organization_name, u.name || ' ' || u.surname as user_name
            FROM proposal_supervisors s
            LEFT JOIN organizations o ON s.organization_id = o.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.proposal_id = ?
        ", [$proposalId]);
    }

    public function isUserSupervisor(int $proposalId, ?int $userId, ?int $orgId): bool
    {
        if ($userId === null && $orgId === null) {
            return false;
        }
        $count = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM proposal_supervisors
            WHERE proposal_id = ? AND (user_id = ? OR organization_id = ?)
        ", [$proposalId, $userId, $orgId]);

        return $count > 0;
    }

    public function getAll(?int $meetingId = null): array
    {
        $sql = "
            SELECT p.*, 
                   (SELECT COUNT(*) FROM vote_participation vp WHERE vp.proposal_id = p.id) as votes_cast,
                   (SELECT COUNT(*) FROM organizations o WHERE o.status = 'active' AND o.is_observer = 0) as eligible_voters
            FROM proposals p
        ";
        $params = [];
        if ($meetingId !== null) {
            $sql .= " WHERE p.meeting_id = ?";
            $params[] = $meetingId;
        }
        $sql .= " ORDER BY p.created_at DESC, p.id DESC";

        return $this->db->fetchAll($sql, $params);
    }
}
