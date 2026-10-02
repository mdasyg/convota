<?php
declare(strict_types=1);

namespace Convota\Services;

use Convota\Database\Database;
use Convota\Models\Proposal;
use Convota\Services\AuditService;
use Convota\Services\MailerService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Robust Dual-Mode Voting Engine
 * Implements:
 * - Anti-Replay & Idempotent Voting API
 * - Cryptographically Decoupled Secret Ballots (Zero relational linkage)
 * - Open Roll-Call Ballots
 * - Intra-organization member notification
 * - Sealed results during voting
 */
class VotingService
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
     * Generate a cryptographically random ballot nonce / idempotency key
     */
    public function generateBallotNonce(int $proposalId, int $organizationId): string
    {
        return hash('sha256', "ballot_nonce_{$proposalId}_{$organizationId}_" . bin2hex(random_bytes(16)));
    }

    /**
     * Check if an organization has already cast a vote for a proposal
     */
    public function hasVoted(int $proposalId, int $organizationId): bool
    {
        $row = $this->db->fetch("
            SELECT id FROM vote_participation WHERE proposal_id = ? AND organization_id = ?
        ", [$proposalId, $organizationId]);

        return $row !== null;
    }

    /**
     * Cast vote on a proposal (Idempotent and Anti-Replay protected)
     */
    public function castVote(
        int $proposalId,
        int $organizationId,
        int $userId,
        int $optionId,
        ?string $idempotencyKey = null,
        ?string $ip = null,
        ?string $ua = null
    ): array {
        // 1. Fetch Proposal
        $proposal = $this->db->fetch("SELECT * FROM proposals WHERE id = ?", [$proposalId]);
        if (!$proposal) {
            throw new RuntimeException("Proposal not found.");
        }

        // 2. State Machine Invariant: Voting strictly allowed ONLY in 'open' status
        if ($proposal['status'] !== 'open') {
            throw new RuntimeException("Voting is not currently active for this proposal (Status: {$proposal['status']}).");
        }

        // 3. If timing mode is scheduled, verify within [start_time, end_time]
        if ($proposal['timing_mode'] === 'scheduled') {
            $now = gmdate('Y-m-d H:i:s');
            if (!empty($proposal['start_time']) && $now < $proposal['start_time']) {
                throw new RuntimeException("Voting window has not opened yet. Starts at: {$proposal['start_time']}");
            }
            if (!empty($proposal['end_time']) && $now > $proposal['end_time']) {
                throw new RuntimeException("Voting window has expired at: {$proposal['end_time']}");
            }
        }

        // 4. Validate Organization Eligibility
        $org = $this->db->fetch("
            SELECT id, name, status, voting_weight, is_observer FROM organizations WHERE id = ?
        ", [$organizationId]);

        if (!$org || $org['status'] !== 'active') {
            throw new RuntimeException("Organization is not active.");
        }

        if ((int)$org['is_observer']) {
            throw new RuntimeException("Observer organizations do not have voting rights.");
        }

        $voteWeight = (float)$org['voting_weight'];
        if ($voteWeight <= 0.0) {
            throw new RuntimeException("Organization has zero voting weight.");
        }

        // 5. Validate User Representation
        $user = $this->db->fetch("
            SELECT id, name, surname, email, organization_id, is_active FROM users WHERE id = ?
        ", [$userId]);

        if (!$user || (int)$user['organization_id'] !== $organizationId || !(int)$user['is_active']) {
            throw new RuntimeException("Authenticated user is not an active representative of this organization.");
        }

        $voterFullName = "{$user['name']} {$user['surname']}";

        // 6. Validate Option
        $option = $this->db->fetch("
            SELECT id, option_text FROM proposal_options WHERE id = ? AND proposal_id = ?
        ", [$optionId, $proposalId]);

        if (!$option) {
            throw new InvalidArgumentException("Selected voting option does not belong to this proposal.");
        }

        // 7. Atomic Concurrency Control with BEGIN IMMEDIATE TRANSACTION
        $this->db->beginTransactionImmediate();

        try {
            // Check for duplicate vote submission
            $existingParticipation = $this->db->fetch("
                SELECT id, cast_by_user_id, voter_name_snapshot, voted_at, idempotency_key
                FROM vote_participation
                WHERE proposal_id = ? AND organization_id = ?
            ", [$proposalId, $organizationId]);

            if ($existingParticipation) {
                // If it's a replayed submission with the SAME idempotency key: return safe idempotent confirmation!
                if ($idempotencyKey !== null && $existingParticipation['idempotency_key'] === $idempotencyKey) {
                    $this->db->commit();
                    return [
                        'success' => true,
                        'idempotent_replay' => true,
                        'message' => 'Vote already recorded with this submission token.',
                        'voter_name' => $existingParticipation['voter_name_snapshot'],
                        'voted_at' => $existingParticipation['voted_at'],
                        'is_secret' => (bool)$proposal['is_secret']
                    ];
                }

                // Otherwise, reject duplicate vote attempt
                $this->db->rollBack();
                throw new RuntimeException("Your organization has already cast its vote for this proposal by {$existingParticipation['voter_name_snapshot']}.");
            }

            // Record Participation (WHO voted, to prevent double-voting and trigger transparency alert)
            $this->db->execute("
                INSERT INTO vote_participation (
                    proposal_id, organization_id, cast_by_user_id, voter_name_snapshot,
                    voter_ip, idempotency_key, voted_at
                ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ", [$proposalId, $organizationId, $userId, $voterFullName, $ip, $idempotencyKey]);

            $receiptHash = null;

            if ((int)$proposal['is_secret']) {
                // ANONYMOUS DECOUPLED BALLOT
                // Generate unlinked cryptographic receipt
                $secretNonce = bin2hex(random_bytes(16));
                $receiptHash = hash('sha256', "{$proposalId}:{$optionId}:{$secretNonce}");

                // Insert into anonymous_ballots with ZERO FK to voter or organization!
                $this->db->execute("
                    INSERT INTO anonymous_ballots (
                        proposal_id, option_id, vote_weight, receipt_hash, created_at
                    ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ", [$proposalId, $optionId, $voteWeight, $receiptHash]);
            } else {
                // OPEN ROLL-CALL BALLOT
                $this->db->execute("
                    INSERT INTO open_ballots (
                        proposal_id, organization_id, option_id, representative_name,
                        vote_weight, created_at
                    ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ", [$proposalId, $organizationId, $optionId, $voterFullName, $voteWeight]);
            }

            $this->audit->log('vote_cast', $userId, $organizationId, [
                'proposal_id' => $proposalId,
                'is_secret' => (bool)$proposal['is_secret'],
                'vote_weight' => $voteWeight
            ], $ip, $ua);

            $this->db->commit();

            // 8. Intra-organization Member Notification
            // Send email to EVERY registered member of the organization so everyone is aware
            $this->notifyOrganizationMembers($organizationId, $org['name'], $proposal['title'], $voterFullName);

            return [
                'success' => true,
                'idempotent_replay' => false,
                'message' => 'Your vote has been securely recorded.',
                'receipt_hash' => $receiptHash,
                'voter_name' => $voterFullName,
                'is_secret' => (bool)$proposal['is_secret']
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Dispatch email notification to all members of an organization upon vote cast
     */
    private function notifyOrganizationMembers(int $orgId, string $orgName, string $proposalTitle, string $voterName): void
    {
        $members = $this->db->fetchAll("
            SELECT email FROM users WHERE organization_id = ? AND is_active = 1
        ", [$orgId]);

        $emails = array_column($members, 'email');
        if (!empty($emails)) {
            $timestamp = gmdate('Y-m-d H:i:s');
            $this->mailer->sendVoteCastAlert($emails, $orgName, $proposalTitle, $voterName, $timestamp, null, $orgId);
        }
    }

    /**
     * Real-time voting status monitor (AJAX / jQuery polling)
     * Strictly hides ballot selections to prevent bandwagon bias during voting!
     */
    public function getLiveMonitorStatus(int $proposalId): array
    {
        $proposal = $this->db->fetch("SELECT * FROM proposals WHERE id = ?", [$proposalId]);
        if (!$proposal) {
            throw new RuntimeException("Proposal not found.");
        }

        $totalEligible = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM organizations WHERE status = 'active' AND is_observer = 0
        ");

        $totalVoted = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM vote_participation WHERE proposal_id = ?
        ", [$proposalId]);

        $participationPct = $totalEligible > 0 ? round(($totalVoted / $totalEligible) * 100, 1) : 0.0;

        // List of organizations and their voting status
        $orgs = $this->db->fetchAll("
            SELECT 
                o.id as organization_id,
                o.name as organization_name,
                o.short_name,
                o.voting_weight,
                o.is_president,
                vp.voted_at,
                vp.voter_name_snapshot,
                CASE WHEN vp.id IS NOT NULL THEN 1 ELSE 0 END as has_voted
            FROM organizations o
            LEFT JOIN vote_participation vp ON vp.organization_id = o.id AND vp.proposal_id = ?
            WHERE o.status = 'active' AND o.is_observer = 0
            ORDER BY has_voted DESC, o.name ASC
        ", [$proposalId]);

        return [
            'proposal_id' => $proposalId,
            'title' => $proposal['title'],
            'status' => $proposal['status'],
            'timing_mode' => $proposal['timing_mode'],
            'start_time' => $proposal['start_time'],
            'end_time' => $proposal['end_time'],
            'total_eligible' => $totalEligible,
            'total_voted' => $totalVoted,
            'participation_percentage' => $participationPct,
            'organizations' => $orgs
        ];
    }

    /**
     * Tabulate and return verified proposal results
     * Strictly seals results until the vote is formally CLOSED or ARCHIVED.
     */
    public function getResults(int $proposalId, bool $forceAllow = false): ?array
    {
        $proposal = $this->db->fetch("SELECT * FROM proposals WHERE id = ?", [$proposalId]);
        if (!$proposal) {
            return null;
        }

        // Sealed results guard: Do not reveal tallies while vote is still open!
        if (!$forceAllow && in_array($proposal['status'], ['draft', 'open', 'paused'], true)) {
            return null;
        }

        $options = $this->db->fetchAll("
            SELECT id, option_text, order_index FROM proposal_options
            WHERE proposal_id = ? ORDER BY order_index ASC, id ASC
        ", [$proposalId]);

        $isSecret = (bool)$proposal['is_secret'];
        $results = [];
        $totalWeight = 0.0;
        $totalBallots = 0;

        if ($isSecret) {
            // Secret ballot tabulation from decoupled anonymous_ballots
            $tallies = $this->db->fetchAll("
                SELECT option_id, COUNT(*) as ballot_count, COALESCE(SUM(vote_weight), 0) as weight_sum
                FROM anonymous_ballots
                WHERE proposal_id = ?
                GROUP BY option_id
            ", [$proposalId]);

            $talliesByOption = [];
            foreach ($tallies as $t) {
                $talliesByOption[$t['option_id']] = [
                    'count' => (int)$t['ballot_count'],
                    'weight' => (float)$t['weight_sum']
                ];
                $totalBallots += (int)$t['ballot_count'];
                $totalWeight += (float)$t['weight_sum'];
            }

            foreach ($options as $opt) {
                $optId = (int)$opt['id'];
                $cnt = $talliesByOption[$optId]['count'] ?? 0;
                $wt = $talliesByOption[$optId]['weight'] ?? 0.0;
                $pct = $totalWeight > 0 ? round(($wt / $totalWeight) * 100, 1) : 0.0;

                $results[] = [
                    'option_id' => $optId,
                    'option_text' => $opt['option_text'],
                    'vote_count' => $cnt,
                    'total_weight' => $wt,
                    'percentage' => $pct
                ];
            }
        } else {
            // Open roll-call ballot tabulation from open_ballots
            $tallies = $this->db->fetchAll("
                SELECT option_id, COUNT(*) as ballot_count, COALESCE(SUM(vote_weight), 0) as weight_sum
                FROM open_ballots
                WHERE proposal_id = ?
                GROUP BY option_id
            ", [$proposalId]);

            $talliesByOption = [];
            foreach ($tallies as $t) {
                $talliesByOption[$t['option_id']] = [
                    'count' => (int)$t['ballot_count'],
                    'weight' => (float)$t['weight_sum']
                ];
                $totalBallots += (int)$t['ballot_count'];
                $totalWeight += (float)$t['weight_sum'];
            }

            foreach ($options as $opt) {
                $optId = (int)$opt['id'];
                $cnt = $talliesByOption[$optId]['count'] ?? 0;
                $wt = $talliesByOption[$optId]['weight'] ?? 0.0;
                $pct = $totalWeight > 0 ? round(($wt / $totalWeight) * 100, 1) : 0.0;

                $results[] = [
                    'option_id' => $optId,
                    'option_text' => $opt['option_text'],
                    'vote_count' => $cnt,
                    'total_weight' => $wt,
                    'percentage' => $pct
                ];
            }
        }

        // Quorum and Majority Analysis
        $totalEligibleWeight = (float)$this->db->fetchColumn("
            SELECT COALESCE(SUM(voting_weight), 0) FROM organizations WHERE status = 'active' AND is_observer = 0
        ");

        $quorumAchieved = true;
        if ((int)$proposal['require_quorum']) {
            $requiredPct = (float)$proposal['quorum_percentage'];
            $actualTurnoutPct = $totalEligibleWeight > 0 ? ($totalWeight / $totalEligibleWeight) * 100 : 0.0;
            $quorumAchieved = $actualTurnoutPct >= $requiredPct;
        }

        // Fetch roll-call or anonymous verification receipts
        $rollCall = [];
        $verificationReceipts = [];

        if (!$isSecret) {
            $rollCall = $this->db->fetchAll("
                SELECT ob.representative_name, ob.vote_weight, ob.created_at,
                       o.name as organization_name, o.short_name, po.option_text
                FROM open_ballots ob
                JOIN organizations o ON ob.organization_id = o.id
                JOIN proposal_options po ON ob.option_id = po.id
                WHERE ob.proposal_id = ?
                ORDER BY o.name ASC
            ", [$proposalId]);
        } else {
            // Anonymous receipts for voter self-verification
            $verificationReceipts = $this->db->fetchAll("
                SELECT ab.receipt_hash, po.option_text, ab.vote_weight, ab.created_at
                FROM anonymous_ballots ab
                JOIN proposal_options po ON ab.option_id = po.id
                WHERE ab.proposal_id = ?
                ORDER BY ab.id ASC
            ", [$proposalId]);
        }

        return [
            'proposal_id' => $proposalId,
            'title' => $proposal['title'],
            'voting_type' => $proposal['voting_type'],
            'is_secret' => $isSecret,
            'status' => $proposal['status'],
            'total_ballots' => $totalBallots,
            'total_weight' => $totalWeight,
            'total_eligible_weight' => $totalEligibleWeight,
            'quorum_achieved' => $quorumAchieved,
            'quorum_percentage_required' => (float)$proposal['quorum_percentage'],
            'majority_percentage_required' => (float)$proposal['majority_percentage'],
            'options' => $results,
            'roll_call' => $rollCall,
            'verification_receipts' => $verificationReceipts
        ];
    }

    /**
     * Voter verification tool: verify if a receipt hash was counted
     */
    public function verifyReceipt(int $proposalId, string $receiptHash): ?array
    {
        return $this->db->fetch("
            SELECT ab.receipt_hash, po.option_text, ab.vote_weight, ab.created_at
            FROM anonymous_ballots ab
            JOIN proposal_options po ON ab.option_id = po.id
            WHERE ab.proposal_id = ? AND ab.receipt_hash = ?
        ", [$proposalId, trim($receiptHash)]);
    }
}
