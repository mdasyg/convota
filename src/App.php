<?php
declare(strict_types=1);

namespace Convota;

use Convota\Database\Database;
use Convota\Database\Migrations;
use Convota\Database\Seeder;
use Convota\Auth\AuthService;
use Convota\Auth\HashAuth;
use Convota\Services\AuditService;
use Convota\Services\MailerService;
use Convota\Services\QuorumService;
use Convota\Services\VotingService;
use Convota\Services\CsvService;
use Convota\Security\SecurityHeaders;
use Convota\Security\Csrf;
use Convota\Security\Validator;

/**
 * Convota Main Application Controller & Router
 */
class App
{
    private static ?self $instance = null;
    private Database $db;
    private array $config;
    private AuthService $auth;
    private HashAuth $hashAuth;
    private AuditService $audit;
    private MailerService $mailer;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/config.php';
        $this->db = Database::getInstance($this->config['database']['path'], $this->config['database']['timeout'] ?? 5000);
        $this->audit = new AuditService($this->db);
        $this->mailer = new MailerService($this->config['mail'] ?? null);
        $this->auth = new AuthService($this->db, $this->audit);
        $this->hashAuth = new HashAuth($this->db, $this->audit, $this->mailer);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function getDb(): Database { return $this->db; }
    public function getAuth(): AuthService { return $this->auth; }
    public function getHashAuth(): HashAuth { return $this->hashAuth; }
    public function getAudit(): AuditService { return $this->audit; }
    public function getMailer(): MailerService { return $this->mailer; }
    public function getConfig(): array { return $this->config; }

    /**
     * Dispatch HTTP request
     */
    public function run(): void
    {
        // 1. Enforce HTTPS and send hardened security headers
        $forceHttps = (bool)($this->config['app']['force_https'] ?? true);
        if ($this->config['app']['env'] === 'development' || php_sapi_name() === 'cli-server') {
            $forceHttps = false; // Allow local php -S dev server without forcing ssl
        }
        SecurityHeaders::apply($forceHttps);

        // 2. Start Secure Session
        $this->auth->startSession();

        // 3. Auto-initialize DB tables and seeder if needed
        (new Migrations($this->db))->run();
        (new Seeder($this->db))->seed();

        // 4. Parse URI & Method
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Normalize URI
        $path = '/' . trim($uri, '/');
        if ($path === '//') $path = '/';

        // 5. CSRF Defense for mutating requests
        if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            // Check if exempt (e.g. login submission has its own token validation in form)
            if (!Csrf::validate()) {
                http_response_code(403);
                if (str_starts_with($path, '/api/')) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token.']);
                } else {
                    $this->renderError(403, 'CSRF verification failed. Please refresh the page and try again.');
                }
                return;
            }
        }

        // 6. Router Dispatch
        try {
            $this->dispatch($path, $method);
        } catch (\Throwable $e) {
            if ($this->config['app']['debug']) {
                $this->renderError(500, $e->getMessage() . "\n" . $e->getTraceAsString());
            } else {
                $this->renderError(500, 'An unexpected server error occurred. Please contact the administrator.');
            }
        }
    }

    /**
     * Route Dispatcher
     */
    private function dispatch(string $path, string $method): void
    {
        $authController = new \Convota\Controllers\AuthController($this);
        $dashboardController = new \Convota\Controllers\DashboardController($this);
        $orgController = new \Convota\Controllers\OrganizationController($this);
        $meetingController = new \Convota\Controllers\MeetingController($this);
        $proposalController = new \Convota\Controllers\ProposalController($this);
        $voteController = new \Convota\Controllers\VoteController($this);
        $csvController = new \Convota\Controllers\CsvController($this);
        $auditController = new \Convota\Controllers\AuditController($this);
        $apiController = new \Convota\Controllers\ApiController($this);

        // Public Auth Routes
        if ($path === '/login') { $authController->login(); return; }
        if ($path === '/auth/login' && $method === 'POST') { $authController->processUserLogin(); return; }
        if ($path === '/auth/hash-login') { $authController->processHashLogin(); return; }
        if ($path === '/auth/forgot-password') { $authController->forgotPassword(); return; }
        if ($path === '/auth/reset-password') { $authController->resetPassword(); return; }
        if ($path === '/logout') { $authController->logout(); return; }

        // APIs
        if (str_starts_with($path, '/api/')) {
            if ($path === '/api/quorum') { $apiController->quorum(); return; }
            if ($path === '/api/voting-status') { $apiController->votingStatus(); return; }
            if ($path === '/api/vote' && $method === 'POST') { $apiController->castVote(); return; }
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found.']);
            return;
        }

        // Public/Hall Projector View
        if ($path === '/proposals/projector') { $proposalController->projector(); return; }
        if ($path === '/proposals/verify-receipt') { $voteController->verifyReceipt(); return; }

        // All subsequent routes require authentication (user or org hash)
        $user = $this->auth->user();
        $orgAuth = $_SESSION['convota_org_auth'] ?? null;

        if (!$user && !$orgAuth) {
            header('Location: /login');
            exit;
        }

        // Organization Portal (Accessible via User or Common Hash)
        if ($path === '/organizations/portal') { $orgController->portal(); return; }
        if ($path === '/organizations/rotate-hash' && $method === 'POST') { $orgController->rotateHash(); return; }
        if ($path === '/organizations/email-hash' && $method === 'POST') { $orgController->emailHash(); return; }
        if ($path === '/organizations/toggle-hash' && $method === 'POST') { $orgController->toggleHash(); return; }
        if ($path === '/organizations/toggle-email-alert' && $method === 'POST') { $orgController->toggleEmailAlert(); return; }
        if ($path === '/organizations/add-member' && $method === 'POST') { $orgController->addMember(); return; }
        if ($path === '/organizations/remove-member' && $method === 'POST') { $orgController->removeMember(); return; }
        if ($path === '/organizations/set-primary' && $method === 'POST') { $orgController->setPrimary(); return; }

        // Voting Interface for Delegates
        if ($path === '/proposals/vote') { $voteController->ballot(); return; }
        if ($path === '/proposals/cast-vote' && $method === 'POST') { $voteController->castVote(); return; }
        if ($path === '/proposals/results') { $proposalController->results(); return; }

        // Dashboard
        if ($path === '/' || $path === '/dashboard') { $dashboardController->index(); return; }

        // Admin & President Controlled Routes
        if ($path === '/organizations') { $orgController->index(); return; }
        if ($path === '/organizations/create') { $orgController->create(); return; }
        if ($path === '/organizations/edit') { $orgController->edit(); return; }
        if ($path === '/organizations/set-president' && $method === 'POST') { $orgController->setPresident(); return; }
        if ($path === '/organizations/delete' && $method === 'POST') { $orgController->delete(); return; }

        if ($path === '/meetings') { $meetingController->index(); return; }
        if ($path === '/meetings/create') { $meetingController->create(); return; }
        if ($path === '/meetings/status' && $method === 'POST') { $meetingController->updateStatus(); return; }
        if ($path === '/meetings/presence') { $meetingController->presence(); return; }
        if ($path === '/meetings/set-presence' && $method === 'POST') { $meetingController->setPresence(); return; }
        if ($path === '/meetings/export-csv') { $meetingController->exportCsv(); return; }

        if ($path === '/proposals') { $proposalController->index(); return; }
        if ($path === '/proposals/create') { $proposalController->create(); return; }
        if ($path === '/proposals/status' && $method === 'POST') { $proposalController->updateStatus(); return; }
        if ($path === '/proposals/monitor') { $proposalController->monitor(); return; }

        if ($path === '/csv/import') { $csvController->import(); return; }
        if ($path === '/csv/preview' && $method === 'POST') { $csvController->preview(); return; }
        if ($path === '/csv/commit' && $method === 'POST') { $csvController->commit(); return; }
        if ($path === '/csv/export-organizations') { $csvController->exportOrganizations(); return; }
        if ($path === '/csv/export-members') { $csvController->exportMembers(); return; }

        if ($path === '/audit') { $auditController->index(); return; }

        // 404
        $this->renderError(404, 'Page not found.');
    }

    /**
     * Set a flash notification message
     */
    public function setFlash(string $type, string $message): void
    {
        $this->auth->startSession();
        $_SESSION['convota_flash'] = [
            'type' => $type, // 'success', 'danger', 'warning', 'info'
            'message' => $message
        ];
    }

    /**
     * Retrieve and clear flash message
     */
    public function getFlash(): ?array
    {
        $this->auth->startSession();
        if (isset($_SESSION['convota_flash'])) {
            $flash = $_SESSION['convota_flash'];
            unset($_SESSION['convota_flash']);
            return $flash;
        }
        return null;
    }

    /**
     * Render view template inside layout
     */
    public function render(string $template, array $data = [], string $layout = 'layout'): void
    {
        // Extract variables to local scope
        extract($data);

        $currentUser = $this->auth->user();
        $orgAuth = $_SESSION['convota_org_auth'] ?? null;
        $flash = $this->getFlash();
        $appName = $this->config['app']['name'] ?? 'Convota';

        $templateFile = __DIR__ . '/Views/' . $template . '.php';
        if (!file_exists($templateFile)) {
            throw new RuntimeException("View template not found: {$template}");
        }

        ob_start();
        require $templateFile;
        $content = ob_get_clean();

        if ($layout === 'none') {
            echo $content;
            return;
        }

        $layoutFile = __DIR__ . '/Views/layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            echo $content;
            return;
        }

        require $layoutFile;
    }

    /**
     * Render error page
     */
    public function renderError(int $code, string $message): void
    {
        http_response_code($code);
        $this->render('error', [
            'code' => $code,
            'message' => $message
        ], 'layout');
    }
}
