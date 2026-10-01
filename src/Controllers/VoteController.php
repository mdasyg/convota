<?php
declare(strict_types=1);

namespace Convota\Controllers;

use Convota\App;
use Convota\Models\Proposal;
use Convota\Models\Meeting;
use Convota\Services\VotingService;

/**
 * Voter Ballot Casting & Receipt Verification Controller
 */
class VoteController
{
    private App $app;
    private Proposal $proposalModel;
    private VotingService $votingService;

    public function __construct(App $app)
    {
        $this->app = $app;
        $db = $app->getDb();
        $audit = $app->getAudit();
        $mailer = $app->getMailer();
        $this->proposalModel = new Proposal($db, $audit, $mailer);
        $this->votingService = new VotingService($db, $audit, $mailer);
    }

    /**
     * Display ballot casting form
     */
    public function ballot(): void
    {
        $proposalId = (int)($_GET['id'] ?? 0);
        $proposal = $this->proposalModel->getById($proposalId);
        if (!$proposal) {
            $this->app->renderError(404, 'Proposal not found.');
            return;
        }

        $user = $this->app->getAuth()->user();
        $orgAuth = $_SESSION['convota_org_auth'] ?? null;

        $orgId = null;
        $userId = null;
        $userName = '';

        if ($user) {
            $orgId = $user['organization_id'] ? (int)$user['organization_id'] : null;
            $userId = (int)$user['id'];
            $userName = $user['name'];
        } elseif ($orgAuth) {
            $orgId = (int)$orgAuth['organization_id'];
            // If logged in via common hash, fetch the designated representative or primary member
            $rep = $this->resolveDesignatedUserForOrg($orgId, $proposal['meeting_id'] ?? null);
            if ($rep) {
                $userId = (int)$rep['id'];
                $userName = "{$rep['name']} {$rep['surname']}";
            }
        }

        if (!$orgId || !$userId) {
            $this->app->renderError(403, 'You must be associated with an active member organization to cast a ballot.');
            return;
        }

        // Fetch organization details
        $org = $this->app->getDb()->fetch("SELECT * FROM organizations WHERE id = ?", [$orgId]);
        if (!$org || (int)$org['is_observer']) {
            $this->app->renderError(403, 'Observer organizations do not have voting rights.');
            return;
        }

        // Check if organization has already cast its vote
        $alreadyVoted = $this->votingService->hasVoted($proposalId, $orgId);
        $participation = null;
        if ($alreadyVoted) {
            $participation = $this->app->getDb()->fetch("
                SELECT voter_name_snapshot, voted_at FROM vote_participation
                WHERE proposal_id = ? AND organization_id = ?
            ", [$proposalId, $orgId]);
        }

        // Issue ballot nonce / idempotency key
        $idempotencyKey = $this->votingService->generateBallotNonce($proposalId, $orgId);

        $this->app->render('proposals/ballot', [
            'proposal' => $proposal,
            'org' => $org,
            'userId' => $userId,
            'userName' => $userName,
            'alreadyVoted' => $alreadyVoted,
            'participation' => $participation,
            'idempotencyKey' => $idempotencyKey
        ]);
    }

    /**
     * Process vote casting submission
     */
    public function castVote(): void
    {
        $proposalId = (int)($_POST['proposal_id'] ?? 0);
        $orgId = (int)($_POST['organization_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $optionId = (int)($_POST['option_id'] ?? 0);
        $idempotencyKey = $_POST['idempotency_key'] ?? null;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Web';

        try {
            $res = $this->votingService->castVote(
                $proposalId,
                $orgId,
                $userId,
                $optionId,
                $idempotencyKey,
                $ip,
                $ua
            );

            // Render confirmation screen
            $proposal = $this->proposalModel->getById($proposalId);
            $this->app->render('proposals/vote_confirmation', [
                'result' => $res,
                'proposal' => $proposal
            ]);
        } catch (\Throwable $e) {
            $this->app->setFlash('danger', $e->getMessage());
            redirect("/proposals/vote?id={$proposalId}");
        }
    }


    /**
     * Voter Self-Verification Tool (Receipt Hash lookup)
     */
    public function verifyReceipt(): void
    {
        $proposalId = (int)($_GET['proposal_id'] ?? ($_POST['proposal_id'] ?? 0));
        $receiptHash = trim($_GET['receipt_hash'] ?? ($_POST['receipt_hash'] ?? ''));

        $verificationResult = null;
        $proposal = null;

        if ($proposalId > 0 && !empty($receiptHash)) {
            $proposal = $this->proposalModel->getById($proposalId);
            $verificationResult = $this->votingService->verifyReceipt($proposalId, $receiptHash);
        }

        $allProposals = $this->proposalModel->getAll();

        $this->app->render('proposals/verify_receipt', [
            'proposalId' => $proposalId,
            'receiptHash' => $receiptHash,
            'proposal' => $proposal,
            'verificationResult' => $verificationResult,
            'allProposals' => $allProposals
        ]);
    }

    private function resolveDesignatedUserForOrg(int $orgId, ?int $meetingId): ?array
    {
        $db = $this->app->getDb();
        if ($meetingId) {
            $p = $db->fetch("
                SELECT u.* FROM meeting_presence mp
                JOIN users u ON mp.representative_user_id = u.id
                WHERE mp.meeting_id = ? AND mp.organization_id = ?
            ", [$meetingId, $orgId]);
            if ($p) return $p;
        }

        // Fallback to primary member
        return $db->fetch("
            SELECT * FROM users WHERE organization_id = ? AND is_primary = 1 AND is_active = 1
        ", [$orgId]);
    }
}
