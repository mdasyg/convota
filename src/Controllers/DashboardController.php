<?php
declare(strict_types=1);

namespace Convota\Controllers;

use Convota\App;
use Convota\Models\Meeting;
use Convota\Models\Proposal;
use Convota\Models\Organization;
use Convota\Services\QuorumService;
use Convota\Services\VotingService;

/**
 * Main Dashboard Controller
 */
class DashboardController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function index(): void
    {
        $user = $this->app->getAuth()->user();
        $orgAuth = $_SESSION['convota_org_auth'] ?? null;

        $db = $this->app->getDb();
        $meetingModel = new Meeting($db, $this->app->getAudit());
        $proposalModel = new Proposal($db, $this->app->getAudit(), $this->app->getMailer());
        $orgModel = new Organization($db, $this->app->getAudit(), $this->app->getMailer());
        $quorumService = new QuorumService($db);
        $votingService = new VotingService($db, $this->app->getAudit(), $this->app->getMailer());

        $isAdmin = $this->app->getAuth()->isAdmin();
        $isPresident = $this->app->getAuth()->isPresident();

        // 1. Fetch Active Meeting
        $activeMeeting = $meetingModel->getActiveMeeting();
        $quorumData = null;
        if ($activeMeeting) {
            $quorumData = $quorumService->getQuorumStatus((int)$activeMeeting['id']);
        }

        // 2. Fetch Active Open Proposals
        $openProposals = $db->fetchAll("
            SELECT p.*, 
                   (SELECT COUNT(*) FROM vote_participation vp WHERE vp.proposal_id = p.id) as votes_cast,
                   (SELECT COUNT(*) FROM organizations o WHERE o.status = 'active' AND o.is_observer = 0) as eligible_voters
            FROM proposals p
            WHERE p.status = 'open'
            ORDER BY p.id DESC
        ");

        // 3. User / Organization Specific Context
        $currentOrgId = null;
        $currentOrg = null;
        if ($user && $user['organization_id']) {
            $currentOrgId = (int)$user['organization_id'];
        } elseif ($orgAuth) {
            $currentOrgId = (int)$orgAuth['organization_id'];
        }

        if ($currentOrgId) {
            $currentOrg = $orgModel->getWithMembers($currentOrgId);
        }

        // Attach voting status for current organization to open proposals
        foreach ($openProposals as &$p) {
            $p['has_voted'] = false;
            $p['voted_by'] = null;
            $p['voted_at'] = null;

            if ($currentOrgId) {
                $participation = $db->fetch("
                    SELECT voter_name_snapshot, voted_at FROM vote_participation
                    WHERE proposal_id = ? AND organization_id = ?
                ", [$p['id'], $currentOrgId]);

                if ($participation) {
                    $p['has_voted'] = true;
                    $p['voted_by'] = $participation['voter_name_snapshot'];
                    $p['voted_at'] = $participation['voted_at'];
                }
            }
        }
        unset($p);

        // 4. Collective Stats for Admin / President
        $stats = [
            'total_orgs' => (int)$db->fetchColumn("SELECT COUNT(*) FROM organizations WHERE status = 'active'"),
            'voting_orgs' => (int)$db->fetchColumn("SELECT COUNT(*) FROM organizations WHERE status = 'active' AND is_observer = 0"),
            'observer_orgs' => (int)$db->fetchColumn("SELECT COUNT(*) FROM organizations WHERE status = 'active' AND is_observer = 1"),
            'president_org' => $db->fetch("SELECT id, name, short_name FROM organizations WHERE is_president = 1"),
            'total_members' => (int)$db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_active = 1 AND system_role != 'admin'")
        ];

        // 5. Recent Proposals
        $recentProposals = $proposalModel->getAll();

        // 6. Security Audit: Check for active default admin password
        $defaultPasswordWarning = false;
        if ($isAdmin && $user) {
            $userRow = $db->fetch("SELECT password_hash FROM users WHERE id = ?", [(int)$user['id']]);
            if ($userRow && !empty($userRow['password_hash']) && password_verify('AdminConvota2026!', $userRow['password_hash'])) {
                $defaultPasswordWarning = true;
            }
        }

        $this->app->render('dashboard/index', [
            'isAdmin' => $isAdmin,
            'isPresident' => $isPresident,
            'defaultPasswordWarning' => $defaultPasswordWarning,
            'activeMeeting' => $activeMeeting,
            'quorumData' => $quorumData,
            'openProposals' => $openProposals,
            'recentProposals' => $recentProposals,
            'currentOrg' => $currentOrg,
            'stats' => $stats
        ]);
    }
}
