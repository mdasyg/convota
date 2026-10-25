<?php
declare(strict_types=1);

namespace Convota\Security;

/**
 * Security Headers & Transport Security Enforcer
 */
class SecurityHeaders
{
    private static ?string $nonce = null;

    /**
     * Retrieve or generate the cryptographically random CSP nonce for the current request
     */
    public static function getNonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = base64_encode(random_bytes(16));
        }
        return self::$nonce;
    }

    /**
     * Enforce HTTPS and send hardened security headers
     */
    public static function apply(bool $forceHttps = true): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        // Apply whitelisted CORS policy
        self::applyCors();

        // 1. Automatic HTTPS Redirection
        if ($forceHttps && !self::isHttps()) {
            $host = !empty($_SERVER['HTTP_X_FORWARDED_HOST'])
                ? trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_HOST'])[0])
                : ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $targetUrl = 'https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: ' . $targetUrl, true, 301);
            exit;
        }

        // 2. Strict Transport Security (HSTS)
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        $nonce = self::getNonce();

        // 3. Defense-in-depth security headers
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none';");
        header('X-XSS-Protection: 0'); // Rely on CSP
    }

    /**
     * Apply strict origin CORS headers
     */
    public static function applyCors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (empty($origin)) {
            return;
        }

        $allowedOrigins = [
            'https://arch.ece.uowm.gr',
            'https://uowm.gr',
            'http://localhost',
            'http://127.0.0.1'
        ];

        $parsed = parse_url($origin);
        $host = strtolower($parsed['host'] ?? '');
        $currentHostHeader = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
        $currentHost = strtolower(explode(':', explode(',', $currentHostHeader)[0])[0]);

        if (in_array($origin, $allowedOrigins, true) || (!empty($host) && !empty($currentHost) && $host === $currentHost)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token, X-Organization-Key, Idempotency-Key');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * Check if current connection is HTTPS
     */
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $protos = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO']);
            if (strtolower(trim($protos[0])) === 'https') {
                return true;
            }
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_SCHEME'])) {
            $protos = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_SCHEME']);
            if (strtolower(trim($protos[0])) === 'https') {
                return true;
            }
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }
        if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower((string)$_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        return false;
    }
}
