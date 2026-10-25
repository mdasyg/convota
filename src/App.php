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
        $this->mailer = new MailerService($this->config['mail'] ?? null, $this->audit);
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

    private static ?string $cachedBasePath = null;

    public static function setBasePath(?string $basePath): void
    {
        self::$cachedBasePath = $basePath;
    }

    public static function detectBasePath(): string
    {
        if (self::$cachedBasePath !== null) {
            return self::$cachedBasePath;
        }

        $config = require __DIR__ . '/../config/config.php';
        if (!empty($config['app']['base_url'])) {
            $parsed = parse_url($config['app']['base_url'], PHP_URL_PATH);
            $basePath = !empty($parsed) ? rtrim($parsed, '/') : rtrim($config['app']['base_url'], '/');
            return self::$cachedBasePath = $basePath;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PREFIX'])) {
            $prefix = '/' . trim((string)$_SERVER['HTTP_X_FORWARDED_PREFIX'], '/');
            if ($prefix !== '/') {
                return self::$cachedBasePath = $prefix;
            }
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        if (str_starts_with($uri, '/convota/') || $uri === '/convota') {
            return self::$cachedBasePath = '/convota';
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (str_starts_with($script, '/convota/') || $script === '/convota') {
            return self::$cachedBasePath = '/convota';
        }

        if (!empty($_SERVER['HTTP_REFERER'])) {
            $refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?? '';
            if (str_starts_with($refPath, '/convota/') || $refPath === '/convota') {
                return self::$cachedBasePath = '/convota';
            }
        }

        return self::$cachedBasePath = '';
    }

    /**
     * Discover scheme, domain/host, and port of the running environment
     */
    public static function discoverOrigin(): string
    {
        $config = require __DIR__ . '/../config/config.php';

        // 1. If base_url in config already includes scheme and host, extract origin from it
        if (!empty($config['app']['base_url']) && preg_match('#^https?://#i', $config['app']['base_url'])) {
            $parsed = parse_url($config['app']['base_url']);
            $scheme = $parsed['scheme'] ?? 'http';
            $host = $parsed['host'] ?? 'localhost';
            $port = !empty($parsed['port']) && !(($scheme === 'http' && (int)$parsed['port'] === 80) || ($scheme === 'https' && (int)$parsed['port'] === 443))
                ? ':' . $parsed['port']
                : '';
            return "{$scheme}://{$host}{$port}";
        }

        // 2. Discover Scheme (Protocol)
        $scheme = 'http';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $protos = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO']);
            $p = strtolower(trim($protos[0]));
            if (in_array($p, ['http', 'https'], true)) {
                $scheme = $p;
            }
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SCHEME'])) {
            $protos = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_SCHEME']);
            $p = strtolower(trim($protos[0]));
            if (in_array($p, ['http', 'https'], true)) {
                $scheme = $p;
            }
        } elseif (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            $scheme = 'https';
        } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower((string)$_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            $scheme = 'https';
        } elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
            $scheme = strtolower((string)$_SERVER['REQUEST_SCHEME']);
        } elseif (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            $scheme = 'https';
        } elseif (!empty($_SERVER['HTTP_REFERER'])) {
            $refScheme = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_SCHEME);
            if (!empty($refScheme)) {
                $scheme = strtolower($refScheme);
            }
        } elseif (!empty($config['app']['force_https'])) {
            $scheme = 'https';
        }

        // 3. Discover Host and non-standard Port
        $host = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $hosts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_HOST']);
            $host = trim($hosts[0]);
        } elseif (!empty($_SERVER['HTTP_HOST'])) {
            $host = trim((string)$_SERVER['HTTP_HOST']);
        } elseif (!empty($_SERVER['SERVER_NAME'])) {
            $host = trim((string)$_SERVER['SERVER_NAME']);
            if (!empty($_SERVER['SERVER_PORT'])) {
                $port = (int)$_SERVER['SERVER_PORT'];
                if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
                    $host .= ':' . $port;
                }
            }
        } elseif (!empty($_SERVER['HTTP_REFERER'])) {
            $refParsed = parse_url($_SERVER['HTTP_REFERER']);
            if (!empty($refParsed['host'])) {
                $host = $refParsed['host'] . (!empty($refParsed['port']) ? ':' . $refParsed['port'] : '');
            }
        }

        if (empty($host)) {
            $host = getenv('APP_DOMAIN') ?: 'vm.dyn.vlsi.gr';
        }

        return "{$scheme}://{$host}";
    }

    /**
     * Generate an absolute URL with discovered scheme, host, and base path
     */
    public static function fullUrl(string $path = ''): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $origin = self::discoverOrigin();
        $relative = self::baseUrl($path);

        return rtrim($origin, '/') . '/' . ltrim($relative, '/');
    }

    /**
     * Discover full base URL (scheme + host + base path)
     */
    public static function discoverBaseUrl(): string
    {
        return rtrim(self::fullUrl('/'), '/');
    }

    public static function baseUrl(string $path = ''): string
    {
        $base = self::detectBasePath();
        if ($base === '') {
            if ($path === '') return '/';
            return str_starts_with($path, '/') ? $path : '/' . $path;
        }

        if ($path === '' || $path === '/') {
            return $base . '/';
        }

        if (str_starts_with($path, $base . '/') || $path === $base) {
            return $path;
        }

        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }

    /**
     * Sanitize and validate internal redirect target to prevent open redirect vulnerabilities
     */
    public static function sanitizeRedirectUrl(string $path, string $fallback = '/'): string
    {
        $path = trim($path);
        // Prevent HTTP header injection via CRLF
        $path = str_replace(["\r", "\n"], '', $path);

        if ($path === '' || str_starts_with($path, '//') || str_starts_with($path, '\\\\')) {
            return self::baseUrl($fallback);
        }

        // If an absolute URL is supplied, verify it matches our current host
        if (preg_match('#^https?://#i', $path)) {
            $parsed = parse_url($path);
            $targetHost = strtolower($parsed['host'] ?? '');

            $currentHostHeader = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
            $currentHost = strtolower(explode(':', explode(',', $currentHostHeader)[0])[0]);

            if (!empty($targetHost) && !empty($currentHost) && $targetHost === $currentHost) {
                // Same host: extract the internal path and query
                $localPath = ($parsed['path'] ?? '/') . (!empty($parsed['query']) ? '?' . $parsed['query'] : '');
                return self::baseUrl($localPath);
            }

            // Untrusted external host: redirect to safe fallback
            return self::baseUrl($fallback);
        }

        // Reject dangerous pseudo-protocols
        if (preg_match('#^[a-zA-Z0-9_\-]+:#', $path)) {
            return self::baseUrl($fallback);
        }

        // Relative internal path
        if (str_starts_with($path, '/')) {
            return self::baseUrl($path);
        }

        return self::baseUrl('/' . ltrim($path, '/'));
    }

    public static function redirectUrl(string $path, string $fallback = '/'): void
    {
        $target = self::sanitizeRedirectUrl($path, $fallback);
        header('Location: ' . $target);
        exit;
    }

    public function redirect(string $path): void
    {
        self::redirectUrl($path);
    }

    public function url(string $path = ''): string
    {
        return self::baseUrl($path);
    }

    public function appUrl(string $path = ''): string
    {
        return self::fullUrl($path);
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

        // Detect Base Path
        $basePath = self::detectBasePath();

        // Normalize URI
        $path = '/' . trim($uri ?? '/', '/');
        if ($path === '//') $path = '/';

        // Strip Base Path from URI for internal routing
        if ($basePath !== '' && (str_starts_with($path, $basePath . '/') || $path === $basePath)) {
            $path = substr($path, strlen($basePath));
            $path = '/' . trim($path, '/');
            if ($path === '//') $path = '/';
        }

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
        if ($path === '/member_login.sh' || str_starts_with($path, '/member_login.sh')) { $authController->processHashLogin(); return; }
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
            self::redirectUrl('/login');
        }


        // Organization Portal (Accessible via User or Common Hash)
        if ($path === '/organizations/portal') { $orgController->portal(); return; }
        if ($path === '/organizations/rotate-hash' && $method === 'POST') { $orgController->rotateHash(); return; }
        if ($path === '/organizations/email-hash' && $method === 'POST') { $orgController->emailHash(); return; }
        if ($path === '/organizations/toggle-hash' && $method === 'POST') { $orgController->toggleHash(); return; }
        if ($path === '/organizations/toggle-email-alert' && $method === 'POST') { $orgController->toggleEmailAlert(); return; }
        if ($path === '/organizations/add-member' && $method === 'POST') { $orgController->addMember(); return; }
        if ($path === '/organizations/update-member' && $method === 'POST') { $orgController->updateMember(); return; }
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
        if ($path === '/meetings/send-presence-reminders' && $method === 'POST') { $meetingController->sendPresenceReminders(); return; }
        if ($path === '/meetings/export-csv') { $meetingController->exportCsv(); return; }

        if ($path === '/proposals') { $proposalController->index(); return; }
        if ($path === '/proposals/create') { $proposalController->create(); return; }
        if ($path === '/proposals/status' && $method === 'POST') { $proposalController->updateStatus(); return; }
        if ($path === '/proposals/monitor') { $proposalController->monitor(); return; }

        if ($path === '/csv' || $path === '/csv/import') { $csvController->import(); return; }
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
        $basePath = self::detectBasePath();
        $app = $this;

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
