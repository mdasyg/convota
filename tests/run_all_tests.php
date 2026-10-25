<?php
declare(strict_types=1);

/**
 * Convota Master Test Runner
 * Executes all test suites across all phases and aggregates results.
 */

echo "========================================================\n";
echo "      CONVOTA MASTER AUTOMATED TEST SUITE RUNNER        \n";
echo "========================================================\n\n";

$testFiles = [
    'Phase 1 (Database, Security, Auth, Cryptography)' => __DIR__ . '/test_phase1.php',
    'Phase 2 (Organizations, Roster, CSV Conflict Engine)' => __DIR__ . '/test_phase2.php',
    'Phase 3 (Assembly Meetings, Presence, Dynamic Quorum)' => __DIR__ . '/test_phase3.php',
    'Phase 4 (Voting Engine, Decoupled Ballots, Idempotency)' => __DIR__ . '/test_phase4.php',
    'Phase 5 (REST APIs, Web Dispatcher, CSRF & Headers)' => __DIR__ . '/test_phase5.php',
    'Phase 6 (Nginx Subpath & URL Helper Routing)' => __DIR__ . '/test_nginx_routing.php',
    'Phase 7 (Security Audit & OWASP Top 10 Hardening)' => __DIR__ . '/test_security_fixes.php'
];


$allPassed = true;

foreach ($testFiles as $phaseTitle => $filePath) {
    echo ">> Running {$phaseTitle}...\n";
    $cmd = 'php ' . escapeshellarg($filePath);
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);

    echo implode("\n", $output) . "\n\n";

    if ($returnCode !== 0) {
        $allPassed = false;
        echo "[ERROR] Suite failed for: {$phaseTitle}\n\n";
    }
}

echo "========================================================\n";
if ($allPassed) {
    echo "       ALL CONVOTA TEST SUITES COMPLETED SUCCESSFULLY!  \n";
    echo "========================================================\n";
    exit(0);
} else {
    echo "       SOME TEST SUITES REPORTED FAILURES!              \n";
    echo "========================================================\n";
    exit(1);
}
