<?php
declare(strict_types=1);

/**
 * Convota Nginx Subpath Routing Test Suite
 * Tests base path auto-detection, URL helper prefixing, and route normalization.
 */

require_once __DIR__ . '/../src/Autoload.php';

use Convota\App;

echo "=== CONVOTA TEST SUITE: NGINX SUBPATH ROUTING ===\n";

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$desc}\n";
        $passed++;
    } else {
        echo " [FAIL] {$desc}\n";
        $failed++;
    }
}

try {
    // 1. Root / Standalone execution
    App::setBasePath(null);
    unset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_X_FORWARDED_PREFIX'], $_SERVER['SCRIPT_NAME']);
    $_SERVER['REQUEST_URI'] = '/dashboard';

    assertTest("Base path at root is empty string", App::detectBasePath() === '');
    assertTest("url('/login') at root returns '/login'", url('/login') === '/login');
    assertTest("url('/assets/css/app.css') at root returns '/assets/css/app.css'", url('/assets/css/app.css') === '/assets/css/app.css');

    // 2. Detection from REQUEST_URI
    App::setBasePath(null);
    $_SERVER['REQUEST_URI'] = '/convota/meetings/presence?id=2';
    assertTest("Detects base path '/convota' from REQUEST_URI", App::detectBasePath() === '/convota');
    assertTest("url('/dashboard') generates '/convota/dashboard'", url('/dashboard') === '/convota/dashboard');
    assertTest("url('/convota/dashboard') avoids double-prefixing", url('/convota/dashboard') === '/convota/dashboard');
    assertTest("url('/assets/css/app.css') generates '/convota/assets/css/app.css'", url('/assets/css/app.css') === '/convota/assets/css/app.css');
    assertTest("url('/proposals/vote?id=3') preserves query params", url('/proposals/vote?id=3') === '/convota/proposals/vote?id=3');
    assertTest("url('/') generates '/convota/'", url('/') === '/convota/');

    // 3. Detection from HTTP_X_FORWARDED_PREFIX
    App::setBasePath(null);
    unset($_SERVER['REQUEST_URI']);
    $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/convota';
    assertTest("Detects base path from HTTP_X_FORWARDED_PREFIX", App::detectBasePath() === '/convota');

    // 4. Reverse Proxy Origin and Full URL Discovery
    App::setBasePath(null);
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    $_SERVER['HTTP_X_FORWARDED_HOST'] = 'arch.ece.uowm.gr';
    $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/convota';
    $_SERVER['REQUEST_URI'] = '/auth/forgot-password';

    assertTest("Discovered origin is https://arch.ece.uowm.gr", App::discoverOrigin() === 'https://arch.ece.uowm.gr');
    assertTest("Discovered base URL is https://arch.ece.uowm.gr/convota", App::discoverBaseUrl() === 'https://arch.ece.uowm.gr/convota');
    assertTest("full_url('/login') generates 'https://arch.ece.uowm.gr/convota/login'", full_url('/login') === 'https://arch.ece.uowm.gr/convota/login');
    assertTest("full_url('/auth/reset-password?token=abc') includes scheme, domain and path", 
        full_url('/auth/reset-password?token=abc') === 'https://arch.ece.uowm.gr/convota/auth/reset-password?token=abc');

    // 5. MailerService link construction with discovered domain
    $mailer = new \Convota\Services\MailerService(['driver' => 'log']);
    $resetFullUrl = $mailer->buildFullUrl('/auth/reset-password?token=test123token');
    assertTest("Mailer buildFullUrl discovers full domain and subpath", 
        $resetFullUrl === 'https://arch.ece.uowm.gr/convota/auth/reset-password?token=test123token');

    $directKeyUrl = $mailer->buildFullUrl('/member_login.sh&hash=secret_org_hash');
    assertTest("Mailer buildFullUrl handles direct delegation key link",
        $directKeyUrl === 'https://arch.ece.uowm.gr/convota/member_login.sh&hash=secret_org_hash');

    // Reset environment
    App::setBasePath(null);
    unset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_X_FORWARDED_PREFIX'], $_SERVER['SCRIPT_NAME'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_HOST']);

} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n----------------------------------------\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "----------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
