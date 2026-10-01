<?php
declare(strict_types=1);

namespace Convota\Services;

use Convota\Database\Database;
use Convota\Models\Meeting;
use RuntimeException;

/**
 * Dynamic Quorum Engine & Assembly Attendance Calculator
 */
class QuorumService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Compute dynamic quorum statistics for a meeting
     */
    public function getQuorumStatus(int $meetingId): array
    {
        $meeting = $this->db->fetch("SELECT * FROM meetings WHERE id = ?", [$meetingId]);
        if (!$meeting) {
            throw new RuntimeException("Meeting not found.");
        }

        // 1. Total Organizations in Collective (Active)
        $totalOrgs = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM organizations WHERE status = 'active'
        ");

        // 2. Voting Organizations (Excluding observers)
        $totalVotingOrgs = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM organizations WHERE status = 'active' AND is_observer = 0
        ");

        // 3. Total Voting Power
        $totalVotingWeight = (float)$this->db->fetchColumn("
            SELECT COALESCE(SUM(voting_weight), 0) FROM organizations WHERE status = 'active' AND is_observer = 0
        ");

        // 4. Present Organizations (All checked in)
        $presentOrgs = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM meeting_presence p
            JOIN organizations o ON p.organization_id = o.id
            WHERE p.meeting_id = ? AND p.is_present = 1 AND o.status = 'active'
        ", [$meetingId]);

        // 5. Present Voting Organizations (Non-observers)
        $presentVotingOrgs = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM meeting_presence p
            JOIN organizations o ON p.organization_id = o.id
            WHERE p.meeting_id = ? AND p.is_present = 1 AND o.status = 'active' AND o.is_observer = 0
        ", [$meetingId]);

        // 6. Present Voting Power
        $presentVotingWeight = (float)$this->db->fetchColumn("
            SELECT COALESCE(SUM(o.voting_weight), 0) FROM meeting_presence p
            JOIN organizations o ON p.organization_id = o.id
            WHERE p.meeting_id = ? AND p.is_present = 1 AND o.status = 'active' AND o.is_observer = 0
        ", [$meetingId]);

        // Compute percentage based on voting organizations
        $attendancePercentage = $totalVotingOrgs > 0
            ? round(($presentVotingOrgs / $totalVotingOrgs) * 100, 1)
            : 0.0;

        $weightPercentage = $totalVotingWeight > 0
            ? round(($presentVotingWeight / $totalVotingWeight) * 100, 1)
            : 0.0;

        $threshold = (float)$meeting['quorum_percentage'];
        $requireQuorum = (bool)$meeting['require_quorum'];

        // If quorum is required, check if attendance percentage reaches threshold
        // By standard definition, > threshold or >= threshold (e.g., 50.0%)
        $isQuorumReached = !$requireQuorum || ($attendancePercentage >= $threshold);

        $statusMessage = sprintf(
            "Participating %d of %d voting organizations (%.1f%% attendance)",
            $presentVotingOrgs,
            $totalVotingOrgs,
            $attendancePercentage
        );

        return [
            'meeting_id' => $meetingId,
            'meeting_title' => $meeting['title'],
            'status' => $meeting['status'],
            'total_organizations' => $totalOrgs,
            'total_voting_organizations' => $totalVotingOrgs,
            'total_voting_weight' => $totalVotingWeight,
            'present_organizations' => $presentOrgs,
            'present_voting_organizations' => $presentVotingOrgs,
            'present_voting_weight' => $presentVotingWeight,
            'attendance_percentage' => $attendancePercentage,
            'weight_percentage' => $weightPercentage,
            'quorum_threshold' => $threshold,
            'require_quorum' => $requireQuorum,
            'is_quorum_reached' => $isQuorumReached,
            'status_message' => $statusMessage
        ];
    }

    /**
     * Get detailed attendance roster for a meeting
     */
    public function getDetailedAttendance(int $meetingId): array
    {
        return $this->db->fetchAll("
            SELECT 
                o.id as organization_id,
                o.name as organization_name,
                o.short_name,
                o.country,
                o.voting_weight,
                o.is_observer,
                o.is_president,
                COALESCE(p.is_present, 0) as is_present,
                p.check_in_method,
                p.checked_in_at,
                p.checked_in_ip,
                u.id as representative_id,
                u.name || ' ' || u.surname as representative_name,
                u.email as representative_email,
                u.telephone as representative_telephone,
                (
                    SELECT u2.name || ' ' || u2.surname 
                    FROM users u2 
                    WHERE u2.organization_id = o.id AND u2.is_primary = 1 AND u2.is_active = 1 
                    LIMIT 1
                ) as primary_member_name
            FROM organizations o
            LEFT JOIN meeting_presence p ON p.organization_id = o.id AND p.meeting_id = ?
            LEFT JOIN users u ON p.representative_user_id = u.id
            WHERE o.status = 'active'
            ORDER BY o.is_president DESC, o.is_observer ASC, o.name ASC
        ", [$meetingId]);
    }

    /**
     * Export meeting attendance to CSV
     */
    public function exportAttendanceCsv(int $meetingId): string
    {
        $status = $this->getQuorumStatus($meetingId);
        $roster = $this->getDetailedAttendance($meetingId);

        $f = fopen('php://temp', 'r+');

        // Header metadata
        fputcsv($f, ['Meeting Title', $status['meeting_title']]);
        fputcsv($f, ['Quorum Status', $status['status_message']]);
        fputcsv($f, ['Quorum Met', $status['is_quorum_reached'] ? 'YES' : 'NO']);
        fputcsv($f, ['Generated At (UTC)', gmdate('Y-m-d H:i:s')]);
        fputcsv($f, []); // Empty row separator

        // Data columns
        fputcsv($f, [
            'Organization Name',
            'Short Name',
            'Country',
            'Role',
            'Vote Weight',
            'Attendance Status',
            'Designated Representative',
            'Representative Email',
            'Check-In Method',
            'Check-In Time (UTC)',
            'Check-In IP'
        ]);

        foreach ($roster as $r) {
            $role = $r['is_president'] ? 'President' : ($r['is_observer'] ? 'Observer' : 'Voting Member');
            $attStatus = $r['is_present'] ? 'PRESENT' : 'ABSENT';
            $repName = $r['representative_name'] ?? ($r['is_present'] ? 'Unassigned Representative' : 'N/A');

            fputcsv($f, [
                $r['organization_name'],
                $r['short_name'],
                $r['country'],
                $role,
                $r['voting_weight'],
                $attStatus,
                $repName,
                $r['representative_email'] ?? 'N/A',
                $r['check_in_method'] ?? 'N/A',
                $r['checked_in_at'] ?? 'N/A',
                $r['checked_in_ip'] ?? 'N/A'
            ]);
        }

        rewind($f);
        $csv = stream_get_contents($f);
        fclose($f);
        return $csv;
    }
}
