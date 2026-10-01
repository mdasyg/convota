<?php
declare(strict_types=1);

/**
 * Convota - Core Application Configuration
 */

return [
    'app' => [
        'name' => 'Convota',
        'title' => 'Convota — General Assembly Presence & Voting System',
        'version' => '1.0.0',
        'env' => getenv('APP_ENV') ?: 'production', // 'production', 'development', 'testing'
        'debug' => (bool)(getenv('APP_DEBUG') ?: false),
        'base_url' => getenv('APP_BASE_URL') ?: '',
        'force_https' => (bool)(getenv('APP_FORCE_HTTPS') ?? true),
    ],

    'security' => [
        'session_name' => 'CONVOTA_SESSID',
        'session_lifetime' => 3600 * 2, // 2 hours
        'session_idle_timeout' => 1800, // 30 minutes
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'argon_memory_cost' => 65536, // 64 MB
        'argon_time_cost' => 4,
        'argon_threads' => 2,
        'rate_limit_max_attempts' => 5,
        'rate_limit_decay_seconds' => 900, // 15 minutes lockout
    ],

    'database' => [
        'path' => __DIR__ . '/../data/convota.sqlite',
        'timeout' => 5000, // 5 seconds busy timeout
    ],

    'mail' => [
        'driver' => getenv('MAIL_DRIVER') ?: 'log', // 'log', 'mail', 'smtp'
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@convota.local',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'Convota Assembly System',
        'log_file' => __DIR__ . '/../data/mail.log',
    ],

    'audit' => [
        'enabled' => true,
        'hash_algorithm' => 'sha256',
    ]
];
