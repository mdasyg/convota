<?php
declare(strict_types=1);

namespace Convota\Controllers;

use Convota\App;
use Convota\Services\QuorumService;
use Convota\Services\VotingService;
use Convota\Models\Proposal;
use Convota\Models\Meeting;
use RuntimeException;

/**
 * Hardened REST API Controller with Strict Authorization & Idempotency
 */
class ApiController
{
    private App $app;
    private QuorumService $quorumService;
    private VotingService $votingService;

    public function __construct(App $app)
    {
        $this->app = $app;
        $db = $app->getDb();
        $audit = $app->getAudit();
        $mailer = $app->getMailer();
        $this->quorumService = new QuorumService($db);
        $this->votingService = new VotingService($db, $audit, $mailer);
    }

    /**
     * GET /api/quorum?meeting_id=...
     */
    public function quorum(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        $meetingId = (int)($_GET['meeting_id'] ?? 0);
        if ($meetingId <= 0) {
            if (!headers_sent()) http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing meeting_id parameter.']);
            return;
        }

        try {
            $status = $this->quorumService->getQuorumStatus($meetingId);
            echo json_encode(['success' => true, 'quorum' => $status]);
        } catch (\Throwable $e) {
            if (!headers_sent()) http_response_code(404);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/voting-status?proposal_id=...
     */
    public function votingStatus(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        $proposalId = (int)($_GET['proposal_id'] ?? 0);
        if ($proposalId <= 0) {
            if (!headers_sent()) http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing proposal_id parameter.']);
            return;
        }

        try {
            $monitor = $this->votingService->getLiveMonitorStatus($proposalId);
            echo json_encode(['success' => true, 'monitor' => $monitor]);
        } catch (\Throwable $e) {
            if (!headers_sent()) http_response_code(404);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/vote (Idempotent Voting API)
     * Authorizes via Session or X-Organization-Key header
     */
    public function castVote(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        // 1. Authenticate caller
        $authContext = $this->resolveApiVoterContext();
        if (!$authContext) {
            if (!headers_sent()) http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Valid session or X-Organization-Key header required.']);
            return;
        }

        // 2. Parse JSON payload
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        $proposalId = (int)($data['proposal_id'] ?? 0);
        $optionId = (int)($data['option_id'] ?? 0);
        $idempotencyKey = $data['idempotency_key'] ?? ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null);

        if ($proposalId <= 0 || $optionId <= 0) {
            if (!headers_sent()) http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid proposal_id or option_id.']);
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'API';

        try {
            $res = $this->votingService->castVote(
                $proposalId,
                $authContext['organization_id'],
                $authContext['user_id'],
                $optionId,
                $idempotencyKey,
                $ip,
                $ua
            );

            echo json_encode(['success' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            if (!headers_sent()) http_response_code(422);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function resolveApiVoterContext(): ?array
    {
        // 1. Check active user session
        $user = $this->app->getAuth()->user();
        if ($user && !empty($user['organization_id'])) {
            return [
                'organization_id' => (int)$user['organization_id'],
                'user_id' => (int)$user['id'],
                'name' => $user['name']
            ];
        }

        // 2. Check active organization session
        if (isset($_SESSION['convota_org_auth'])) {
            $orgId = (int)$_SESSION['convota_org_auth']['organization_id'];
            $rep = $this->app->getDb()->fetch("
                SELECT id, name, surname FROM users WHERE organization_id = ? AND is_primary = 1 AND is_active = 1
            ", [$orgId]);

            if ($rep) {
                return [
                    'organization_id' => $orgId,
                    'user_id' => (int)$rep['id'],
                    'name' => "{$rep['name']} {$rep['surname']}"
                ];
            }
        }

        // 3. Check X-Organization-Key header
        $orgKey = $_SERVER['HTTP_X_ORGANIZATION_KEY'] ?? null;
        if (!empty($orgKey)) {
            $org = $this->app->getDb()->fetch("
                SELECT id, status, hash_auth_enabled FROM organizations WHERE common_hash = ?
            ", [$orgKey]);

            if ($org && $org['status'] === 'active' && (int)$org['hash_auth_enabled']) {
                $orgId = (int)$org['id'];
                $rep = $this->app->getDb()->fetch("
                    SELECT id, name, surname FROM users WHERE organization_id = ? AND is_primary = 1 AND is_active = 1
                ", [$orgId]);

                if ($rep) {
                    return [
                        'organization_id' => $orgId,
                        'user_id' => (int)$rep['id'],
                        'name' => "{$rep['name']} {$rep['surname']}"
                    ];
                }
            }
        }

        return null;
    }
}
