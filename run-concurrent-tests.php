<?php
/**
 * Test runner for ConcurrentFileManager class
 */

require_once 'vendor/autoload.php';

use AnsyblSite\Core\ConcurrentFileManager;

echo "Running ConcurrentFileManager Tests...\n";
echo "======================================\n\n";

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
    }
}

// Setup
$tempDir = './tests/tmp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

$fileManager = new ConcurrentFileManager($tempDir);

// Test 1: Safe read with locking
test("Safe read with locking", function() use ($fileManager) {
    $filename = 'safe-read-test.json';
    $data = ['safe' => 'read', 'timestamp' => time()];
    
    $fileManager->write($filename, $data);
    $readData = $fileManager->safeRead($filename);
    
    assert($readData === $data, 'Safe read should return correct data');
});

// Test 2: Atomic write
test("Atomic write", function() use ($fileManager) {
    $filename = 'atomic-write-test.json';
    $data = ['atomic' => true, 'counter' => 42];
    
    $result = $fileManager->atomicWrite($filename, $data);
    assert($result === true, 'Atomic write should return true');
    
    $readData = $fileManager->read($filename);
    assert($readData === $data, 'Atomic write should preserve data integrity');
});

// Test 3: Transactional update
test("Transactional update", function() use ($fileManager) {
    $filename = 'transactional-test.json';
    $initialData = ['counter' => 0, 'items' => []];
    
    $fileManager->write($filename, $initialData);
    
    // Perform transactional update
    $result = $fileManager->transactionalUpdate($filename, function($data) {
        $data['counter']++;
        $data['items'][] = 'item-' . $data['counter'];
        return $data;
    });
    
    assert($result === true, 'Transactional update should return true');
    
    $updatedData = $fileManager->read($filename);
    assert($updatedData['counter'] === 1, 'Counter should be incremented');
    assert($updatedData['items'] === ['item-1'], 'Items should be updated');
});

// Test 4: Multiple transactional updates
test("Multiple transactional updates", function() use ($fileManager) {
    $filename = 'multi-transactional-test.json';
    $initialData = ['counter' => 0];
    
    $fileManager->write($filename, $initialData);
    
    // Perform multiple updates
    for ($i = 1; $i <= 3; $i++) {
        $fileManager->transactionalUpdate($filename, function($data) use ($i) {
            $data['counter'] += $i;
            return $data;
        });
    }
    
    $finalData = $fileManager->read($filename);
    assert($finalData['counter'] === 6, 'Counter should be 6 (0+1+2+3)'); // 0 + 1 + 2 + 3 = 6
});

// Test 5: Transactional update on non-existent file
test("Transactional update on non-existent file", function() use ($fileManager) {
    $filename = 'new-transactional-test.json';
    
    // Clean up if exists
    if ($fileManager->exists($filename)) {
        $fileManager->delete($filename);
    }
    
    $result = $fileManager->transactionalUpdate($filename, function($data) {
        $data['created'] = true;
        $data['timestamp'] = time();
        return $data;
    });
    
    assert($result === true, 'Transactional update should work on new file');
    
    $data = $fileManager->read($filename);
    assert($data['created'] === true, 'New file should have correct data');
    assert(isset($data['timestamp']), 'New file should have timestamp');
});

// Test 6: Backup and restore functionality
test("File backup during atomic write", function() use ($fileManager) {
    $filename = 'backup-test.json';
    $originalData = ['version' => 1, 'data' => 'original'];
    $newData = ['version' => 2, 'data' => 'updated'];
    
    // Create original file
    $fileManager->write($filename, $originalData);
    
    // Atomic write should work
    $result = $fileManager->atomicWrite($filename, $newData);
    assert($result === true, 'Atomic write should succeed');
    
    $readData = $fileManager->read($filename);
    assert($readData === $newData, 'File should contain new data');
});

echo "\nTest Results:\n";
echo "============\n";
echo "Passed: {$passedCount}/{$testCount}\n";

if ($passedCount === $testCount) {
    echo "🎉 All ConcurrentFileManager tests passed!\n";
    exit(0);
} else {
    echo "❌ Some ConcurrentFileManager tests failed!\n";
    exit(1);
}