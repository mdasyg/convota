<?php
declare(strict_types=1);

namespace Convota\Controllers;

use Convota\App;
use Convota\Models\Proposal;
use Convota\Models\Meeting;
use Convota\Models\Organization;
use Convota\Services\VotingService;
use Convota\Security\Validator;

/**
 * Proposal & Election Controller
 */
class ProposalController
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

    public function index(): void
    {
        $meetingId = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : null;
        $proposals = $this->proposalModel->getAll($meetingId);

        $this->app->render('proposals/index', [
            'proposals' => $proposals,
            'meetingId' => $meetingId,
            'isAdmin' => $this->app->getAuth()->isAdmin(),
            'isPresident' => $this->app->getAuth()->isPresident()
        ]);
    }

    public function create(): void
    {
        if (!$this->app->getAuth()->isAdmin() && !$this->app->getAuth()->isPresident()) {
            $this->app->renderError(403, 'Administrator or Presidium privileges required.');
            return;
        }

        $db = $this->app->getDb();
        $meetingModel = new Meeting($db, $this->app->getAudit());
        $orgModel = new Organization($db, $this->app->getAudit(), $this->app->getMailer());

        $meetings = $meetingModel->getAll();
        $organizations = $orgModel->getAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $rawOptions = $_POST['options'] ?? [];
                if (is_string($rawOptions)) {
                    $rawOptions = array_filter(array_map('trim', explode("\n", $rawOptions)));
                }

                $data = [
                    'meeting_id' => !empty($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : null,
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? null,
                    'voting_type' => $_POST['voting_type'] ?? 'yes_no_abstain',
                    'is_secret' => !empty($_POST['is_secret']) ? 1 : 0,
                    'timing_mode' => $_POST['timing_mode'] ?? 'manual',
                    'start_time' => !empty($_POST['start_time']) ? $_POST['start_time'] : null,
                    'end_time' => !empty($_POST['end_time']) ? $_POST['end_time'] : null,
                    'require_quorum' => !empty($_POST['require_quorum']) ? 1 : 0,
                    'quorum_percentage' => $_POST['quorum_percentage'] ?? 50.0,
                    'majority_percentage' => $_POST['majority_percentage'] ?? 50.0,
                    'notify_members_on_start' => !empty($_POST['notify_members_on_start']) ? 1 : 0,
                    'supervisor_organizations' => $_POST['supervisor_organizations'] ?? []
                ];

                $creatorId = $this->app->getAuth()->user()['id'] ?? 1;
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

                $proposalId = $this->proposalModel->create($data, $rawOptions, $creatorId, $ip);

                $this->app->setFlash('success', 'Proposal / Election created successfully.');
                redirect("/proposals/monitor?id={$proposalId}");
            } catch (\Throwable $e) {
                $this->app->setFlash('danger', $e->getMessage());
            }
        }

        $this->app->render('proposals/create', [
            'meetings' => $meetings,
            'organizations' => $organizations
        ]);
    }

    public function updateStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'draft';

        $this->assertCanSupervise($id);

        try {
            $user = $this->app->getAuth()->user();
            $actorId = $user ? (int)$user['id'] : null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $baseUrl = $this->app->getConfig()['app']['base_url'] ?? '';

            $this->proposalModel->updateStatus($id, $status, $actorId, $ip, $baseUrl);
            $this->app->setFlash('info', "Voting state transitioned to '{$status}'.");
        } catch (\Throwable $e) {
            $this->app->setFlash('danger', $e->getMessage());
        }

        redirect("/proposals/monitor?id={$id}");
    }


    /**
     * Real-time Asynchronous Status Screen for President / Supervisors
     * Shows who voted vs pending, but keeps vote choices sealed!
     */
    public function monitor(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $proposal = $this->proposalModel->getById($id);
        if (!$proposal) {
            $this->app->renderError(404, 'Proposal not found.');
            return;
        }

        $this->assertCanSupervise($id);

        $monitor = $this->votingService->getLiveMonitorStatus($id);

        $this->app->render('proposals/monitor', [
            'proposal' => $proposal,
            'monitor' => $monitor,
            'isAdmin' => $this->app->getAuth()->isAdmin(),
            'isPresident' => $this->app->getAuth()->isPresident()
        ]);
    }

    /**
     * View final unsealed results (after vote closure)
     */
    public function results(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $proposal = $this->proposalModel->getById($id);
        if (!$proposal) {
            $this->app->renderError(404, 'Proposal not found.');
            return;
        }

        $forceAllow = $this->canSupervise($id);
        $results = $this->votingService->getResults($id, $forceAllow);

        if (!$results) {
            $this->app->renderError(403, 'Results are sealed and confidential until voting is formally closed by the Presidium.');
            return;
        }

        $this->app->render('proposals/results', [
            'proposal' => $proposal,
            'results' => $results,
            'canSupervise' => $this->canSupervise($id)
        ]);
    }

    /**
     * Big-Screen Projector / Wall View for Assembly Hall
     */
    public function projector(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $proposal = $this->proposalModel->getById($id);
        if (!$proposal) {
            $this->app->renderError(404, 'Proposal not found.');
            return;
        }

        $monitor = $this->votingService->getLiveMonitorStatus($id);
        $results = null;
        if (in_array($proposal['status'], ['closed', 'archived'], true)) {
            $results = $this->votingService->getResults($id);
        }

        $this->app->render('proposals/projector', [
            'proposal' => $proposal,
            'monitor' => $monitor,
            'results' => $results
        ], 'projector_layout');
    }

    private function canSupervise(int $proposalId): bool
    {
        if ($this->app->getAuth()->isAdmin() || $this->app->getAuth()->isPresident()) {
            return true;
        }

        $user = $this->app->getAuth()->user();
        $userId = $user ? (int)$user['id'] : null;
        $orgId = $user ? (int)$user['organization_id'] : null;

        return $this->proposalModel->isUserSupervisor($proposalId, $userId, $orgId);
    }

    private function assertCanSupervise(int $proposalId): void
    {
        if (!$this->canSupervise($proposalId)) {
            $this->app->renderError(403, 'Access denied. Electoral committee or administrator authorization required.');
            exit;
        }
    }
}
