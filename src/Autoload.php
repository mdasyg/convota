<?php
declare(strict_types=1);

/**
 * Convota PSR-4 Style Autoloader
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Convota\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/helpers.php';

