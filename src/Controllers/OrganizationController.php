<?php
declare(strict_types=1);

namespace Convota\Controllers;

use Convota\App;
use Convota\Models\Organization;
use Convota\Models\User;
use Convota\Models\Meeting;
use Convota\Security\Validator;
use RuntimeException;

/**
 * Organization & Delegation Management Controller
 */
class OrganizationController
{
    private App $app;
    private Organization $orgModel;
    private User $userModel;

    public function __construct(App $app)
    {
        $this->app = $app;
        $db = $app->getDb();
        $audit = $app->getAudit();
        $mailer = $app->getMailer();
        $this->orgModel = new Organization($db, $audit, $mailer);
        $this->userModel = new User($db, $audit, $mailer);
    }

    /**
     * List all organizations (Admin / President view)
     */
    public function index(): void
    {
        $user = $this->app->getAuth()->user();
        if (!$this->app->getAuth()->isAdmin() && !$this->app->getAuth()->isPresident()) {
            $this->app->renderError(403, 'Access denied. Administrator or Presidium privileges required.');
            return;
        }

        $organizations = $this->orgModel->getAll();
        $this->app->render('organizations/index', [
            'organizations' => $organizations,
            'isAdmin' => $this->app->getAuth()->isAdmin()
        ]);
    }

    /**
     * Create new organization with its first member
     */
    public function create(): void
    {
        if (!$this->app->getAuth()->isAdmin()) {
            $this->app->renderError(403, 'Administrator privileges required to register new organizations.');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $orgData = [
                    'name' => \Convota\Security\Validator::sanitizeText($_POST['name'] ?? '', 255),
                    'short_name' => !empty($_POST['short_name']) ? \Convota\Security\Validator::sanitizeText($_POST['short_name'], 50) : null,
                    'description' => !empty($_POST['description']) ? \Convota\Security\Validator::sanitizeText($_POST['description'], 5000) : null,
                    'country' => !empty($_POST['country']) ? \Convota\Security\Validator::sanitizeText($_POST['country'], 100) : null,
                    'website' => !empty($_POST['website']) ? \Convota\Security\Validator::sanitizeText($_POST['website'], 255) : null,
                    'status' => $_POST['status'] ?? 'active',
                    'voting_weight' => $_POST['voting_weight'] ?? 1.0,
                    'is_observer' => !empty($_POST['is_observer']) ? 1 : 0,
                    'is_president' => !empty($_POST['is_president']) ? 1 : 0
                ];

                $memberData = [
                    'name' => \Convota\Security\Validator::sanitizeText($_POST['first_member_name'] ?? '', 100),
                    'surname' => \Convota\Security\Validator::sanitizeText($_POST['first_member_surname'] ?? '', 100),
                    'email' => \Convota\Security\Validator::cleanString($_POST['first_member_email'] ?? ''),
                    'telephone' => !empty($_POST['first_member_telephone']) ? \Convota\Security\Validator::sanitizeText($_POST['first_member_telephone'], 50) : null
                ];

                $adminId = $this->app->getAuth()->user()['id'] ?? 1;
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Web';
                $baseUrl = $this->app->getConfig()['app']['base_url'] ?? '';

                $res = $this->orgModel->createWithFirstMember($orgData, $memberData, $adminId, $ip, $ua, $baseUrl);

                $this->app->setFlash('success', "Organization '{$orgData['name']}' created successfully. Welcome email dispatched to first delegate.");
                redirect('/organizations');
            } catch (\Throwable $e) {
                $this->app->setFlash('danger', $e->getMessage());
            }
        }

        $this->app->render('organizations/create', []);
    }

    /**
     * Edit organization details
     */
    public function edit(): void
    {
        if (!$this->app->getAuth()->isAdmin()) {
            $this->app->renderError(403, 'Administrator privileges required.');
            return;
        }

        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        $org = $this->orgModel->getWithMembers($id);
        if (!$org) {
            $this->app->renderError(404, 'Organization not found.');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $data = [
                    'name' => $_POST['name'] ?? '',
                    'short_name' => $_POST['short_name'] ?? null,
                    'description' => $_POST['description'] ?? null,
                    'country' => $_POST['country'] ?? null,
                    'website' => $_POST['website'] ?? null,
                    'status' => $_POST['status'] ?? 'active',
                    'voting_weight' => $_POST['voting_weight'] ?? 1.0,
                    'is_observer' => !empty($_POST['is_observer']) ? 1 : 0
                ];

                $adminId = $this->app->getAuth()->user()['id'] ?? 1;
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

                $this->orgModel->update($id, $data, $adminId, $ip);

                $this->app->setFlash('success', 'Organization updated successfully.');
                redirect('/organizations');
            } catch (\Throwable $e) {
                $this->app->setFlash('danger', $e->getMessage());
            }
        }

        $this->app->render('organizations/edit', ['org' => $org]);
    }

    /**
     * Designate President Organization
     */
    public function setPresident(): void
    {
        if (!$this->app->getAuth()->isAdmin()) {
            $this->app->renderError(403, 'Administrator privileges required.');
            return;
        }

        $id = (int)($_POST['organization_id'] ?? 0);
        $adminId = $this->app->getAuth()->user()['id'] ?? 1;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $this->orgModel->setPresident($id, $adminId, $ip);
        $this->app->setFlash('success', 'President Organization designation updated.');
        redirect('/organizations');
    }


    /**
     * Self-Management Organization Portal
     * Accessible by logged-in members of this org or via Common Access Hash
     */
    public function portal(): void
    {
        $user = $this->app->getAuth()->user();
        $orgAuth = $_SESSION['convota_org_auth'] ?? null;

        $orgId = null;
        if ($user && $user['organization_id']) {
            $orgId = (int)$user['organization_id'];
        } elseif ($orgAuth && $orgAuth['organization_id']) {
            $orgId = (int)$orgAuth['organization_id'];
        } elseif ($this->app->getAuth()->isAdmin() && isset($_GET['id'])) {
            $orgId = (int)$_GET['id'];
        }

        if (!$orgId) {
            $this->app->renderError(403, 'No organization associated with this session.');
            return;
        }

        $org = $this->orgModel->getWithMembers($orgId);
        if (!$org) {
            $this->app->renderError(404, 'Organization not found.');
            return;
        }

        // Fetch active meeting to display designated voter status
        $meetingModel = new Meeting($this->app->getDb(), $this->app->getAudit());
        $activeMeeting = $meetingModel->getActiveMeeting();
        $presence = null;
        if ($activeMeeting) {
            $presence = $this->app->getDb()->fetch("
                SELECT * FROM meeting_presence WHERE meeting_id = ? AND organization_id = ?
            ", [$activeMeeting['id'], $orgId]);
        }

        $this->app->render('organizations/portal', [
            'org' => $org,
            'activeMeeting' => $activeMeeting,
            'presence' => $presence,
            'user' => $user,
            'orgAuth' => $orgAuth
        ]);
    }

    /**
     * Add member / substitute to organization roster
     */
    public function addMember(): void
    {
        $orgId = (int)($_POST['organization_id'] ?? 0);
        $this->assertOrgAccess($orgId);

        try {
            $user = $this->app->getAuth()->user();
            $actorId = $user ? (int)$user['id'] : null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Web';
            $baseUrl = $this->app->getConfig()['app']['base_url'] ?? '';

            $data = [
                'name' => $_POST['name'] ?? '',
                'surname' => $_POST['surname'] ?? '',
                'email' => $_POST['email'] ?? '',
                'telephone' => $_POST['telephone'] ?? null,
                'is_primary' => !empty($_POST['is_primary']) ? 1 : 0
            ];

            $this->userModel->addMember($orgId, $data, $actorId, $ip, $ua, $baseUrl);
            $this->app->setFlash('success', "Member '{$data['name']} {$data['surname']}' added to delegation roster.");
        } catch (\Throwable $e) {
            $this->app->setFlash('danger', $e->getMessage());
        }

        $redirectTo = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '/organizations/portal';
        redirect($redirectTo);
    }

    /**
     * Update an existing member's profile and delegation status
     */
    public function updateMember(): void
    {
        $orgId = (int)($_POST['organization_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $this->assertOrgAccess($orgId);

        try {
            $user = $this->app->getAuth()->user();
            $actorId = $user ? (int)$user['id'] : null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Web';

            $data = [
                'name' => $_POST['name'] ?? '',
                'surname' => $_POST['surname'] ?? '',
                'email' => $_POST['email'] ?? '',
                'telephone' => $_POST['telephone'] ?? null,
                'is_primary' => !empty($_POST['is_primary']) ? 1 : 0
            ];

            $this->userModel->updateMember($userId, $orgId, $data, $actorId, $ip, $ua);
            $this->app->setFlash('success', "Member '{$data['name']} {$data['surname']}' updated successfully.");
        } catch (\Throwable $e) {
            $this->app->setFlash('danger', $e->getMessage());
        }

        $redirectTo = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '/organizations/portal';
        redirect($redirectTo);
    }

    /**
     * Remove a member from the organization (including primary members)
     */
    public function removeMember(): void
    {
        $orgId = (int)($_POST['organization_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $this->assertOrgAccess($orgId);

        $user = $this->app->getAuth()->user();
        $actorId = $user ? (int)$user['id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Web';

        $removed = $this->userModel->removeMember($userId, $orgId, $actorId, $ip, $ua);
        if ($removed) {
            $this->app->setFlash('success', 'Member removed from organization roster.');
        } else {
            $this->app->setFlash('danger', 'Failed to remove member.');
        }

        $redirectTo = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '/organizations/portal';
        redirect($redirectTo);
    }

    /**
     * Set a member as the primary delegate
     */
    public function setPrimary(): void
    {
        $orgId = (int)($_POST['organization_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $this->assertOrgAccess($orgId);

        $user = $this->app->getAuth()->user();
        $actorId = $user ? (int)$user['id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $this->userModel->setPrimary($userId, $orgId, $actorId, $ip);
        $this->app->setFlash('success', 'Primary delegate designation updated. Other members set to substitutes.');

        $redirectTo = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '/organizations/portal';
        redirect($redirectTo);
    }

    /**
     * Reinitialize and rotate the organization access key
     */
    public function rotateHash(): void
    {
        $orgId = (int)($_POST['organization_id'] ?? 0);
        $this->assertOrgAccess($orgId);

        $user = $this->app->getAuth()->user();
        $actorId = $user ? (int)$user['id'] : null;
        $baseUrl = $this->app->getConfig()['app']['base_url'] ?? '';

        $newHash = $this->app->getHashAuth()->rotateHash($orgId, $actorId, $baseUrl);
        $this->app->setFlash('success', "Organization Access Key reinitialized and dispatched to all registered members.");

        // If currently authenticated via hash, update session hash
        if (isset($_SESSION['convota_org_auth']) && (int)$_SESSION['convota_org_auth']['organization_id'] === $orgId) {
            $_SESSION['convota_org_auth']['common_hash'] = $newHash;
        }

        redirect('/organizations/portal');
    }

    /**
     * Email current access key to members
     */
    public function emailHash(): void
    {
        $orgId = (int)($_POST['organization_id'] ?? 0);
        $this->assertOrgAccess($orgId);

        $user = $this->app->getAuth()->user();
        $actorId = $user ? (int)$user['id'] : null;
        $baseUrl = $this->app->getConfig()['app']['base_url'] ?? '';

        $sent = $this->app->getHashAuth()->emailHashToMembers($orgId, $actorId, $baseUrl);
        if ($sent) {
            $this->app->setFlash('success', 'Organization Access Key dispatched to all registered members.');
        } else {
            $this->app->setFlash('warning', 'No registered active members found to receive the email.');
        }

        redirect('/organizations/portal');
    }

    /**
     * Toggle hash authorization on/off
     */
    public function toggleHash(): void
    {
        $orgId = (int)($_POST['organization_id'] ?? 0);
        $this->assertOrgAccess($orgId);

        $enabled = !empty($_POST['enabled']);
        $user = $this->app->getAuth()->user();
        $actorId = $user ? (int)$user['id'] : null;

        $this->app->getHashAuth()->setHashAuthEnabled($orgId, $enabled, $actorId);
        $statusStr = $enabled ? 'enabled' : 'disabled';
        $this->app->setFlash('info', "Organization Key authorization has been {$statusStr}.");

        redirect('/organizations/portal');
    }

    /**
     * Toggle email on hash login
     */
    public function toggleEmailAlert(): void
    {
        $orgId = (int)($_POST['organization_id'] ?? 0);
        $this->assertOrgAccess($orgId);

        $enabled = !empty($_POST['enabled']);
        $user = $this->app->getAuth()->user();
        $actorId = $user ? (int)$user['id'] : null;

        $this->app->getHashAuth()->setEmailOnHashLogin($orgId, $enabled, $actorId);
        $statusStr = $enabled ? 'enabled' : 'disabled';
        $this->app->setFlash('info', "Member email notifications on key usage {$statusStr}.");

        redirect('/organizations/portal');
    }

    /**
     * Delete organization (Admin only)
     */
    public function delete(): void
    {
        if (!$this->app->getAuth()->isAdmin()) {
            $this->app->renderError(403, 'Administrator privileges required.');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $adminId = $this->app->getAuth()->user()['id'] ?? 1;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $this->orgModel->delete($id, $adminId, $ip);
        $this->app->setFlash('success', 'Organization deleted.');
        redirect('/organizations');
    }


    /**
     * Strict Authorization guard: Ensure caller has permission for this specific organization
     */
    private function assertOrgAccess(int $orgId): void
    {
        if ($this->app->getAuth()->isAdmin()) {
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

        $this->app->renderError(403, 'Unauthorized access to organization delegation.');
        exit;
    }
}
