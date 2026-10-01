<?php
declare(strict_types=1);

namespace Convota\Security;

/**
 * Robust Cross-Site Request Forgery (CSRF) Defender
 */
class Csrf
{
    private const SESSION_KEY = '_convota_csrf_token';

    /**
     * Get or generate the current CSRF token for the session
     */
    public static function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Regenerate the CSRF token
     */
    public static function regenerate(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Validate an incoming CSRF token (from POST body or X-CSRF-Token header)
     */
    public static function validate(?string $token = null): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($sessionToken) || empty($sessionToken)) {
            return false;
        }

        // If no token was passed, check POST then headers
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? null;
            if ($token === null && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
            }
        }

        if (!is_string($token) || empty($token)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Render a hidden HTML CSRF input field
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}
