<?php
/**
 * Test runner for ConfigManager class
 */

require_once 'vendor/autoload.php';

use AnsyblSite\Core\ConfigManager;
use AnsyblSite\Core\ConcurrentFileManager;
use AnsyblSite\Exceptions\ConfigException;
use AnsyblSite\Exceptions\InvalidConfigException;

echo "Running ConfigManager Tests...\n";
echo "=============================\n\n";

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
$tempDir = './tests/tmp/config-test';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

$fileManager = new ConcurrentFileManager($tempDir);
$configManager = new ConfigManager($fileManager);

// Test 1: Can get default config
test("Can get default config", function() use ($configManager) {
    $config = $configManager->get('site');
    
    assert(is_array($config), 'Config should be array');
    assert(array_key_exists('version', $config), 'Should have version');
    assert(array_key_exists('site', $config), 'Should have site section');
    assert($config['version'] === '1.0', 'Version should be 1.0');
});

// Test 2: Can save and retrieve config
test("Can save and retrieve config", function() use ($configManager) {
    $siteConfig = [
        'version' => '1.0',
        'site' => [
            'title' => 'Test Site',
            'description' => 'A test configuration',
            'language' => 'en'
        ],
        'meta' => [
            'created' => date('c'),
            'schema_version' => '1.0'
        ]
    ];
    
    $result = $configManager->set('site', $siteConfig);
    assert($result === true, 'Set should return true');
    
    $retrieved = $configManager->get('site');
    assert($retrieved['site']['title'] === 'Test Site', 'Title should match');
    assert($retrieved['site']['description'] === 'A test configuration', 'Description should match');
});

// Test 3: Validates config structure
test("Validates config structure", function() use ($configManager) {
    $invalidConfig = [
        // Missing required 'version' field
        'site' => ['title' => 'Test']
    ];
    
    try {
        $configManager->set('site', $invalidConfig);
        assert(false, 'Should have thrown InvalidConfigException');
    } catch (InvalidConfigException $e) {
        assert(str_contains($e->getMessage(), 'Invalid configuration'), 'Exception should mention invalid config');
    }
});

// Test 4: Can get config value with dot notation
test("Can get config value with dot notation", function() use ($configManager) {
    $config = [
        'version' => '1.0',
        'site' => [
            'title' => 'My Site',
            'language' => 'en'
        ],
        'meta' => ['created' => date('c')]
    ];
    
    $configManager->set('site', $config);
    
    $title = $configManager->getValue('site', 'site.title');
    assert($title === 'My Site', 'Title should match');
    
    $language = $configManager->getValue('site', 'site.language');
    assert($language === 'en', 'Language should match');
});

// Test 5: Can set config value with dot notation
test("Can set config value with dot notation", function() use ($configManager) {
    // Set initial config
    $config = [
        'version' => '1.0',
        'site' => ['title' => 'Old Title'],
        'meta' => ['created' => date('c')]
    ];
    $configManager->set('site', $config);
    
    // Update single value
    $result = $configManager->setValue('site', 'site.title', 'New Title');
    assert($result === true, 'SetValue should return true');
    
    // Verify change
    $newTitle = $configManager->getValue('site', 'site.title');
    assert($newTitle === 'New Title', 'Title should be updated');
});

// Test 6: Can merge config values
test("Can merge config values", function() use ($configManager) {
    $initialConfig = [
        'version' => '1.0',
        'site' => [
            'title' => 'Original Title',
            'language' => 'en'
        ],
        'meta' => ['created' => date('c')]
    ];
    $configManager->set('site', $initialConfig);
    
    $updates = [
        'site' => [
            'title' => 'Updated Title',
            'description' => 'New description'
        ],
        'display' => [
            'theme' => 'dark'
        ]
    ];
    
    $result = $configManager->merge('site', $updates);
    assert($result === true, 'Merge should return true');
    
    $config = $configManager->get('site');
    assert($config['site']['title'] === 'Updated Title', 'Title should be updated');
    assert($config['site']['language'] === 'en', 'Language should be preserved');
    assert($config['site']['description'] === 'New description', 'Description should be added');
    assert($config['display']['theme'] === 'dark', 'New section should be added');
});

// Test 7: Can check if config exists
test("Can check if config exists", function() use ($configManager) {
    assert($configManager->exists('nonexistent') === false, 'Should return false for non-existent config');
    
    $configManager->set('test', ['version' => '1.0', 'meta' => []]);
    assert($configManager->exists('test') === true, 'Should return true for existing config');
});

// Test 8: Can backup and restore config
test("Can backup and restore config", function() use ($configManager) {
    $originalConfig = [
        'version' => '1.0',
        'site' => ['title' => 'Original'],
        'meta' => ['created' => date('c')]
    ];
    
    $configManager->set('restore-test', $originalConfig);
    $backupFile = $configManager->backup('restore-test');
    
    assert(is_string($backupFile), 'Backup should return filename');
    assert(str_contains($backupFile, 'backup'), 'Backup filename should contain "backup"');
    
    // Modify config
    $configManager->setValue('restore-test', 'site.title', 'Modified');
    assert($configManager->getValue('restore-test', 'site.title') === 'Modified', 'Config should be modified');
    
    // Restore from backup
    $result = $configManager->restore('restore-test', $backupFile);
    assert($result === true, 'Restore should return true');
    
    $restored = $configManager->getValue('restore-test', 'site.title');
    assert($restored === 'Original', 'Config should be restored');
});

// Test 9: Handles nested dot notation
test("Handles nested dot notation", function() use ($configManager) {
    $config = [
        'version' => '1.0',
        'site' => [
            'settings' => [
                'display' => [
                    'theme' => 'dark',
                    'font_size' => 16
                ]
            ]
        ],
        'meta' => ['created' => date('c')]
    ];
    
    $configManager->set('nested', $config);
    
    $theme = $configManager->getValue('nested', 'site.settings.display.theme');
    assert($theme === 'dark', 'Nested value should be retrieved');
    
    $configManager->setValue('nested', 'site.settings.display.font_size', 18);
    $fontSize = $configManager->getValue('nested', 'site.settings.display.font_size');
    assert($fontSize === 18, 'Nested value should be updated');
});

// Test 10: Throws exception for invalid dot path
test("Throws exception for invalid dot path", function() use ($configManager) {
    $config = [
        'version' => '1.0',
        'site' => ['title' => 'Test'],
        'meta' => ['created' => date('c')]
    ];
    
    $configManager->set('path-test', $config);
    
    try {
        $configManager->getValue('path-test', 'nonexistent.path.value');
        assert(false, 'Should have thrown ConfigException');
    } catch (ConfigException $e) {
        assert(str_contains($e->getMessage(), 'Path not found'), 'Exception should mention path not found');
    }
});

echo "\nTest Results:\n";
echo "============\n";
echo "Passed: {$passedCount}/{$testCount}\n";

if ($passedCount === $testCount) {
    echo "🎉 All ConfigManager tests passed!\n";
    exit(0);
} else {
    echo "❌ Some ConfigManager tests failed!\n";
    exit(1);
}