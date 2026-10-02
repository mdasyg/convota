<?php
declare(strict_types=1);

namespace Convota\Controllers;

use Convota\App;
use Convota\Models\Meeting;
use Convota\Models\Organization;
use Convota\Services\QuorumService;

/**
 * Meeting Presence & Dynamic Quorum Controller
 */
class MeetingController
{
    private App $app;
    private Meeting $meetingModel;
    private QuorumService $quorumService;

    public function __construct(App $app)
    {
        $this->app = $app;
        $db = $app->getDb();
        $audit = $app->getAudit();
        $this->meetingModel = new Meeting($db, $audit);
        $this->quorumService = new QuorumService($db);
    }

    public function index(): void
    {
        $meetings = $this->meetingModel->getAll();
        $this->app->render('meetings/index', [
            'meetings' => $meetings,
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

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? null,
                    'location' => $_POST['location'] ?? null,
                    'scheduled_at' => $_POST['scheduled_at'] ?? gmdate('Y-m-d H:i:s'),
                    'require_quorum' => !empty($_POST['require_quorum']) ? 1 : 0,
                    'quorum_percentage' => $_POST['quorum_percentage'] ?? 50.0
                ];

                $creatorId = $this->app->getAuth()->user()['id'] ?? 1;
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

                $id = $this->meetingModel->create($data, $creatorId, $ip);

                if (!empty($_POST['notify_members'])) {
                    $members = $this->app->getDb()->fetchAll("
                        SELECT u.email FROM users u
                        JOIN organizations o ON u.organization_id = o.id
                        WHERE u.is_active = 1 AND o.status = 'active'
                    ");
                    if (!empty($members)) {
                        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
                        $meetingUrl = $proto . '://' . $host . url("/meetings/presence?id={$id}");
                        $emails = array_unique(array_filter(array_column($members, 'email')));

                        $this->app->getMailer()->sendMeetingScheduledAlert(
                            $emails,
                            $data['title'],
                            $data['scheduled_at'],
                            $data['location'] ?? 'Assembly Hall / Hybrid',
                            $meetingUrl
                        );
                    }
                    $this->app->setFlash('success', 'General Assembly meeting scheduled and notification emails dispatched to all member participants.');
                } else {
                    $this->app->setFlash('success', 'General Assembly meeting scheduled.');
                }
                redirect("/meetings/presence?id={$id}");
            } catch (\Throwable $e) {
                $this->app->setFlash('danger', $e->getMessage());
            }
        }

        $this->app->render('meetings/create', []);
    }

    public function updateStatus(): void
    {
        if (!$this->app->getAuth()->isAdmin() && !$this->app->getAuth()->isPresident()) {
            $this->app->renderError(403, 'Administrator or Presidium privileges required.');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'draft';
        $actorId = $this->app->getAuth()->user()['id'] ?? 1;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $this->meetingModel->updateStatus($id, $status, $actorId, $ip);
        $this->app->setFlash('info', "Meeting status transitioned to '{$status}'.");
        redirect("/meetings/presence?id={$id}");
    }


    /**
     * Live Presence & Dynamic Quorum Screen
     */
    public function presence(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $meeting = $this->meetingModel->getById($id);
        if (!$meeting) {
            $this->app->renderError(404, 'Meeting not found.');
            return;
        }

        $quorum = $this->quorumService->getQuorumStatus($id);
        $roster = $this->quorumService->getDetailedAttendance($id);

        // Fetch all organizations with their members for the admin check-in modal
        $orgModel = new Organization($this->app->getDb(), $this->app->getAudit(), $this->app->getMailer());
        $allOrgs = $orgModel->getAll();
        foreach ($allOrgs as &$o) {
            $o['members'] = $this->app->getDb()->fetchAll("
                SELECT id, name, surname, email, is_primary FROM users
                WHERE organization_id = ? AND is_active = 1
                ORDER BY is_primary DESC, surname ASC
            ", [$o['id']]);
        }
        unset($o);

        $this->app->render('meetings/presence', [
            'meeting' => $meeting,
            'quorum' => $quorum,
            'roster' => $roster,
            'allOrgs' => $allOrgs,
            'isAdmin' => $this->app->getAuth()->isAdmin(),
            'isPresident' => $this->app->getAuth()->isPresident()
        ]);
    }

    /**
     * Mark presence & designate active representative
     */
    public function setPresence(): void
    {
        $meetingId = (int)($_POST['meeting_id'] ?? 0);
        $orgId = (int)($_POST['organization_id'] ?? 0);
        $repUserId = !empty($_POST['representative_user_id']) ? (int)$_POST['representative_user_id'] : null;
        $isAbsent = !empty($_POST['mark_absent']);

        // Check permission: Admin, President, or member of this org, or common hash of this org
        $this->assertCanManagePresence($orgId);

        $user = $this->app->getAuth()->user();
        $actorId = $user ? (int)$user['id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if ($isAbsent) {
            $this->meetingModel->removePresence($meetingId, $orgId, $actorId, $ip);
            $this->app->setFlash('info', 'Presence removed for organization.');
        } else {
            $method = 'self_login';
            if ($this->app->getAuth()->isAdmin() || $this->app->getAuth()->isPresident()) {
                $method = 'admin_rollcall';
            } elseif (isset($_SESSION['convota_org_auth'])) {
                $method = 'common_hash';
            }

            $this->meetingModel->setPresence($meetingId, $orgId, $repUserId, $method, $actorId, $ip);
            $this->app->setFlash('success', 'Designated representative and presence successfully updated.');
        }

        // Return to referrer or presence screen
        $referrer = $_SERVER['HTTP_REFERER'] ?? "/meetings/presence?id={$meetingId}";
        redirect($referrer);
    }


    /**
     * Export attendance CSV
     */
    public function exportCsv(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $meeting = $this->meetingModel->getById($id);
        if (!$meeting) {
            $this->app->renderError(404, 'Meeting not found.');
            return;
        }

        $csv = $this->quorumService->exportAttendanceCsv($id);
        $filename = 'attendance_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $meeting['title']) . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        echo $csv;
        exit;
    }

    private function assertCanManagePresence(int $orgId): void
    {
        if ($this->app->getAuth()->isAdmin() || $this->app->getAuth()->isPresident()) {
            return;
        }

        $user = $this->app->getAuth()->user();
        if ($user && (int)$user['organization_id'] === $orgId) {
            return;
        }

        $orgAuth = $_SESSION['convota_org_auth'] ?? null;
        if ($orgAuth && (int)$orgAuth['organization_id'] === $orgId) {
            return;
        }

        $this->app->renderError(403, 'Unauthorized to manage presence for this organization.');
        exit;
    }
}
