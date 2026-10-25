<?php
declare(strict_types=1);

namespace Convota\Security;

/**
 * Input Validator & XSS Defense Escaper
 */
class Validator
{
    /**
     * Escape HTML output safely for UTF-8
     */
    public static function escape(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Validate an email address
     */
    public static function isValidEmail(string $email): bool
    {
        $sanitized = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        return $sanitized !== false && strlen($email) <= 254;
    }

    /**
     * Clean and normalize a string
     */
    public static function cleanString(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value));
    }

    /**
     * Check if a string length is within a maximum bound
     */
    public static function isMaxLength(?string $value, int $maxLength): bool
    {
        if ($value === null) {
            return true;
        }
        return mb_strlen($value, 'UTF-8') <= $maxLength;
    }

    /**
     * Sanitize and enforce maximum length on string input
     */
    public static function sanitizeText(?string $value, int $maxLength = 255): string
    {
        $clean = self::cleanString($value);
        return mb_substr($clean, 0, $maxLength, 'UTF-8');
    }

    /**
     * Validate positive float (voting weight)
     */
    public static function isValidWeight(mixed $weight): bool
    {
        if (!is_numeric($weight)) {
            return false;
        }
        $val = (float)$weight;
        return $val >= 0.0 && $val <= 1000000.0;
    }

    /**
     * Validate ISO datetime string
     */
    public static function isValidDateTime(string $datetime): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        if ($d && $d->format('Y-m-d H:i:s') === $datetime) {
            return true;
        }
        $d2 = \DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
        if ($d2) {
            return true;
        }
        $d3 = \DateTime::createFromFormat('Y-m-d\TH:i:s', $datetime);
        return $d3 !== false;
    }
}

if (!function_exists('e')) {
    /**
     * Global HTML escaping shortcut
     */
    function e(?string $value): string
    {
        return \Convota\Security\Validator::escape($value);
    }
}
