<?php
declare(strict_types=1);

/**
 * Convota Global Helpers
 */

if (!function_exists('url')) {
    /**
     * Generate an absolute path URL including the base path (e.g. /convota/...)
     */
    function url(string $path = ''): string
    {
        return \Convota\App::baseUrl($path);
    }
}

if (!function_exists('full_url')) {
    /**
     * Generate a fully qualified URL including discovered scheme, host, and base path
     */
    function full_url(string $path = ''): string
    {
        return \Convota\App::fullUrl($path);
    }
}

if (!function_exists('redirect')) {
    /**
     * Send HTTP redirect header and terminate execution
     */
    function redirect(string $path): void
    {
        \Convota\App::redirectUrl($path);
    }
}

