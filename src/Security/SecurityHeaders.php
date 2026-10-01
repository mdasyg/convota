<?php
declare(strict_types=1);

namespace Convota\Security;

/**
 * Security Headers & Transport Security Enforcer
 */
class SecurityHeaders
{
    /**
     * Enforce HTTPS and send hardened security headers
     */
    public static function apply(bool $forceHttps = true): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        // 1. Automatic HTTPS Redirection
        if ($forceHttps && !self::isHttps()) {
            $targetUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: ' . $targetUrl, true, 301);
            exit;
        }

        // 2. Strict Transport Security (HSTS)
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // 3. Defense-in-depth security headers
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none';");
        header('X-XSS-Protection: 0'); // Rely on CSP
    }

    /**
     * Check if current connection is HTTPS
     */
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
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
