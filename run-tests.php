<?php
/**
 * Simple test runner for our classes
 */

require_once 'vendor/autoload.php';

use AnsyblSite\Core\FileManager;
use AnsyblSite\Exceptions\FileNotFoundException;
use AnsyblSite\Exceptions\InvalidJsonException;

echo "Running FileManager Tests...\n";
echo "============================\n\n";

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

$fileManager = new FileManager($tempDir);

// Test 1: Can write and read JSON data
test("Can write and read JSON data", function() use ($fileManager) {
    $data = ['test' => 'value', 'number' => 42];
    $filename = 'test-write-read.json';
    
    $writeResult = $fileManager->write($filename, $data);
    assert($writeResult === true, 'Write should return true');
    
    $readData = $fileManager->read($filename);
    assert($readData === $data, 'Read data should match written data');
});

// Test 2: File exists check
test("File exists check works", function() use ($fileManager) {
    $filename = 'test-exists.json';
    
    // Should not exist initially
    assert($fileManager->exists($filename) === false, 'File should not exist initially');
    
    // Write file
    $fileManager->write($filename, ['exists' => true]);
    
    // Should exist now
    assert($fileManager->exists($filename) === true, 'File should exist after writing');
});

// Test 3: Exception on missing file
test("Throws exception for missing file", function() use ($fileManager) {
    try {
        $fileManager->read('missing-file.json');
        assert(false, 'Should have thrown FileNotFoundException');
    } catch (FileNotFoundException $e) {
        assert(str_contains($e->getMessage(), 'File not found'), 'Exception message should contain "File not found"');
    }
});

// Test 4: Can delete files
test("Can delete files", function() use ($fileManager) {
    $filename = 'test-delete.json';
    
    // Create file
    $fileManager->write($filename, ['delete' => 'me']);
    assert($fileManager->exists($filename) === true, 'File should exist before deletion');
    
    // Delete file
    $result = $fileManager->delete($filename);
    assert($result === true, 'Delete should return true');
    assert($fileManager->exists($filename) === false, 'File should not exist after deletion');
});

// Test 5: Invalid JSON handling
test("Handles invalid JSON", function() use ($fileManager, $tempDir) {
    $filename = 'invalid.json';
    $path = $tempDir . '/' . $filename;
    
    // Write invalid JSON directly
    file_put_contents($path, '{"invalid": json}');
    
    try {
        $fileManager->read($filename);
        assert(false, 'Should have thrown InvalidJsonException');
    } catch (InvalidJsonException $e) {
        assert(str_contains($e->getMessage(), 'Invalid JSON'), 'Exception should mention invalid JSON');
    }
});

echo "\nTest Results:\n";
echo "============\n";
echo "Passed: {$passedCount}/{$testCount}\n";

if ($passedCount === $testCount) {
    echo "🎉 All tests passed!\n";
    exit(0);
} else {
    echo "❌ Some tests failed!\n";
    exit(1);
}