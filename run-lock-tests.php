<?php
/**
 * Test runner for FileLock class
 */

require_once 'vendor/autoload.php';

use AnsyblSite\Core\FileLock;
use AnsyblSite\Exceptions\FileLockException;

echo "Running FileLock Tests...\n";
echo "========================\n\n";

$testCount = 0;
$passedCount = 0;

function test(string $name, callable $testFunction): void {
    global $testCount, $passedCount;
    $testCount++;
    
    try {
        $testFunction();
        echo "✓ {$name}\n";
        $passedCount++;
    } catch (Exception $e) {
        echo "✗ {$name}: {$e->getMessage()}\n";
        echo "  Stack trace: " . $e->getTraceAsString() . "\n";
    }
}

// Setup
$tempDir = './tests/tmp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

$testFile = $tempDir . '/lock-test.json';
file_put_contents($testFile, '{"test": true}');

// Test 1: Can acquire lock
test("Can acquire lock", function() use ($testFile) {
    $lock = new FileLock($testFile);
    
    $result = $lock->acquire();
    assert($result === true, 'Acquire should return true');
    assert($lock->isLocked() === true, 'Lock should be active');
    
    $lock->release();
});

// Test 2: Can release lock
test("Can release lock", function() use ($testFile) {
    $lock = new FileLock($testFile);
    $lock->acquire();
    
    $result = $lock->release();
    assert($result === true, 'Release should return true');
    assert($lock->isLocked() === false, 'Lock should be released');
});

// Test 3: Lock file contains correct info
test("Lock file contains correct info", function() use ($testFile) {
    $lock = new FileLock($testFile);
    $lock->acquire();
    
    $lockFile = $testFile . '.lock';
    assert(file_exists($lockFile), 'Lock file should exist');
    
    $lockInfo = json_decode(file_get_contents($lockFile), true);
    assert(isset($lockInfo['pid']), 'Lock info should contain PID');
    assert(isset($lockInfo['timestamp']), 'Lock info should contain timestamp');
    assert(isset($lockInfo['hostname']), 'Lock info should contain hostname');
    assert($lockInfo['pid'] === getmypid(), 'PID should match current process');
    
    $lock->release();
});

// Test 4: Cannot acquire already locked file
test("Cannot acquire already locked file", function() use ($testFile) {
    $lock1 = new FileLock($testFile, 1); // 1 second timeout
    $lock2 = new FileLock($testFile, 1);
    
    $lock1->acquire();
    
    try {
        $lock2->acquire();
        assert(false, 'Should have thrown FileLockException');
    } catch (FileLockException $e) {
        assert(str_contains($e->getMessage(), 'Cannot acquire lock'), 'Exception should mention lock acquisition failure');
    }
    
    $lock1->release();
});

// Test 5: isLocked returns false for non-existent lock
test("isLocked returns false for non-existent lock", function() use ($testFile) {
    // Clean up any existing lock
    $lockFile = $testFile . '.lock';
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
    
    $lock = new FileLock($testFile);
    assert($lock->isLocked() === false, 'Should return false for non-existent lock');
});

// Test 6: Detects and cleans stale locks
test("Detects and cleans stale locks", function() use ($testFile) {
    $lockFile = $testFile . '.lock';
    
    // Create stale lock (old timestamp)
    $staleLockInfo = [
        'pid' => 99999, // Non-existent PID
        'timestamp' => time() - 400, // 400 seconds ago
        'hostname' => 'test-host'
    ];
    file_put_contents($lockFile, json_encode($staleLockInfo));
    
    $lock = new FileLock($testFile);
    
    // Should detect stale lock and return false
    assert($lock->isLocked() === false, 'Should detect stale lock');
    
    // Lock file should be cleaned up
    assert(!file_exists($lockFile), 'Stale lock file should be cleaned up');
});

// Test 7: Release returns false when no lock held
test("Release returns false when no lock held", function() use ($testFile) {
    $lock = new FileLock($testFile);
    
    $result = $lock->release();
    assert($result === false, 'Release should return false when no lock held');
});

echo "\nTest Results:\n";
echo "============\n";
echo "Passed: {$passedCount}/{$testCount}\n";

if ($passedCount === $testCount) {
    echo "🎉 All FileLock tests passed!\n";
    exit(0);
} else {
    echo "❌ Some FileLock tests failed!\n";
    exit(1);
}