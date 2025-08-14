<?php
/**
 * Comprehensive test runner for all Phase 1 components
 */

require_once 'vendor/autoload.php';

echo "🚀 Running All Phase 1 Tests\n";
echo "============================\n\n";

$totalTests = 0;
$totalPassed = 0;

function runTestSuite(string $name, string $file): array {
    echo "Running {$name}...\n";
    
    $output = [];
    $exitCode = 0;
    exec("php {$file}", $output, $exitCode);
    
    $passed = 0;
    $total = 0;
    
    foreach ($output as $line) {
        if (preg_match('/Passed: (\d+)\/(\d+)/', $line, $matches)) {
            $passed = (int)$matches[1];
            $total = (int)$matches[2];
            break;
        }
    }
    
    if ($exitCode === 0) {
        echo "✅ {$name}: {$passed}/{$total} tests passed\n";
    } else {
        echo "❌ {$name}: {$passed}/{$total} tests passed\n";
    }
    
    return ['passed' => $passed, 'total' => $total];
}

// Run all test suites
$suites = [
    'FileManager Tests' => 'run-tests.php',
    'FileLock Tests' => 'run-lock-tests.php', 
    'ConcurrentFileManager Tests' => 'run-concurrent-tests.php',
    'SchemaValidator Tests' => 'run-schema-tests.php',
    'FeedParser Tests' => 'run-feed-parser-tests.php',
    'FeedCache Tests' => 'run-feed-cache-tests.php',
    'ConfigManager Tests' => 'run-config-manager-tests.php',
    'StyleManager Tests' => 'run-style-manager-tests.php'
];

foreach ($suites as $name => $file) {
    $result = runTestSuite($name, $file);
    $totalPassed += $result['passed'];
    $totalTests += $result['total'];
    echo "\n";
}

echo "📊 Overall Results\n";
echo "==================\n";
echo "Total Passed: {$totalPassed}/{$totalTests}\n";

if ($totalPassed === $totalTests) {
    echo "🎉 ALL TESTS PASSED! Phase 3 is complete.\n\n";
    
    echo "✅ Completed Features:\n";
    echo "- File-based JSON storage system\n";
    echo "- Concurrent file access with locking\n";
    echo "- Atomic write operations\n";
    echo "- JSON schema validation\n";
    echo "- Activity Streams 2.0 feed parsing\n";
    echo "- Intelligent feed caching with TTL\n";
    echo "- Configuration management with dot notation\n";
    echo "- Dynamic CSS generation and theming\n";
    echo "- Responsive design system\n";
    echo "- Comprehensive error handling\n";
    echo "- Test-driven development workflow\n\n";
    
    echo "🚀 Ready for Phase 4: Frontend Development\n";
    exit(0);
} else {
    $failed = $totalTests - $totalPassed;
    echo "❌ {$failed} tests failed. Please fix before proceeding.\n";
    exit(1);
}