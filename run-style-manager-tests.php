<?php
/**
 * Test runner for StyleManager class
 */

require_once 'vendor/autoload.php';

use AnsyblSite\Core\StyleManager;
use AnsyblSite\Core\ConfigManager;
use AnsyblSite\Core\ConcurrentFileManager;

echo "Running StyleManager Tests...\n";
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
$tempDir = './tests/tmp/style-test';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

$fileManager = new ConcurrentFileManager($tempDir);
$configManager = new ConfigManager($fileManager);
$styleManager = new StyleManager($configManager, $fileManager);

// Test 1: Can generate basic CSS
test("Can generate basic CSS", function() use ($styleManager) {
    $css = $styleManager->generateCSS();
    
    assert(is_string($css), 'CSS should be string');
    assert(str_contains($css, '--primary-color'), 'Should contain primary color variable');
    assert(str_contains($css, '#007cba'), 'Should contain default color value');
});

// Test 2: Can compile theme variables
test("Can compile theme variables", function() use ($styleManager) {
    $theme = [
        'name' => 'Test Theme',
        'variables' => [
            '--primary-color' => '#ff0000',
            '--secondary-color' => '#00ff00',
            '--font-family' => "'Helvetica', sans-serif"
        ]
    ];
    
    $css = $styleManager->compileTheme($theme);
    
    assert(str_contains($css, '--primary-color: #ff0000'), 'Should contain primary color');
    assert(str_contains($css, '--secondary-color: #00ff00'), 'Should contain secondary color');
    assert(str_contains($css, "--font-family: 'Helvetica', sans-serif"), 'Should contain font family');
});

// Test 3: Can add and set custom CSS
test("Can add and set custom CSS", function() use ($styleManager) {
    $customCSS = '.custom-class { color: blue; margin: 10px; }';
    
    $result = $styleManager->setCustomCSS($customCSS);
    assert($result === true, 'setCustomCSS should return true');
    
    $retrievedCSS = $styleManager->getCustomCSS();
    assert($retrievedCSS === $customCSS, 'Retrieved CSS should match set CSS');
});

// Test 4: Can switch themes
test("Can switch themes", function() use ($styleManager) {
    // Add a custom theme
    $darkTheme = [
        'name' => 'Dark Theme',
        'variables' => [
            '--primary-color' => '#ffffff',
            '--background-color' => '#000000',
            '--text-color' => '#ffffff'
        ]
    ];
    
    $styleManager->addTheme('dark', $darkTheme);
    $result = $styleManager->setActiveTheme('dark');
    
    assert($result === true, 'setActiveTheme should return true');
    assert($styleManager->getActiveTheme() === 'dark', 'Active theme should be dark');
});

// Test 5: Generated CSS includes active theme
test("Generated CSS includes active theme", function() use ($styleManager) {
    // Set up dark theme
    $darkTheme = [
        'name' => 'Dark Theme',
        'variables' => [
            '--primary-color' => '#ffffff',
            '--background-color' => '#222222'
        ]
    ];
    
    $styleManager->addTheme('dark', $darkTheme);
    $styleManager->setActiveTheme('dark');
    
    $css = $styleManager->generateCSS();
    
    assert(str_contains($css, '--primary-color: #ffffff'), 'Should contain white primary color');
    assert(str_contains($css, '--background-color: #222222'), 'Should contain dark background');
});

// Test 6: Can get available themes
test("Can get available themes", function() use ($styleManager) {
    $themes = $styleManager->getThemes();
    
    assert(is_array($themes), 'Themes should be array');
    assert(array_key_exists('default', $themes), 'Should have default theme');
    assert($themes['default']['name'] === 'Default Theme', 'Default theme name should match');
});

// Test 7: Can update theme variable
test("Can update theme variable", function() use ($styleManager) {
    $result = $styleManager->updateThemeVariable('default', '--primary-color', '#cc0000');
    assert($result === true, 'updateThemeVariable should return true');
    
    $theme = $styleManager->getTheme('default');
    assert($theme['variables']['--primary-color'] === '#cc0000', 'Variable should be updated');
});

// Test 8: Can generate responsive CSS
test("Can generate responsive CSS", function() use ($styleManager) {
    $css = $styleManager->generateResponsiveCSS();
    
    assert(is_string($css), 'CSS should be string');
    assert(str_contains($css, '@media'), 'Should contain media queries');
    assert(str_contains($css, '768px'), 'Should contain mobile breakpoint');
    assert(str_contains($css, '1024px'), 'Should contain tablet breakpoint');
});

// Test 9: Can compile full stylesheet
test("Can compile full stylesheet", function() use ($styleManager) {
    // Set up custom CSS
    $styleManager->setCustomCSS('.custom { font-size: 14px; }');
    
    $css = $styleManager->compileFullStylesheet();
    
    assert(is_string($css), 'CSS should be string');
    assert(str_contains($css, ':root {'), 'Should contain CSS variables');
    assert(str_contains($css, '.custom { font-size: 14px; }'), 'Should contain custom CSS');
    assert(str_contains($css, '@media'), 'Should contain responsive CSS');
});

// Test 10: Can save compiled CSS
test("Can save compiled CSS", function() use ($styleManager, $fileManager) {
    $filename = 'compiled-styles.css';
    
    $result = $styleManager->saveCompiledCSS($filename);
    assert($result === true, 'saveCompiledCSS should return true');
    
    $path = $fileManager->getFilePath($filename);
    assert(file_exists($path), 'CSS file should exist');
    
    $savedCSS = file_get_contents($path);
    assert(str_contains($savedCSS, ':root {'), 'Saved CSS should contain variables');
    assert(str_contains($savedCSS, 'Generated CSS'), 'Should contain generation comment');
});

// Test 11: Validates CSS syntax
test("Validates CSS syntax", function() use ($styleManager) {
    $validCSS = '.test { color: red; margin: 10px; }';
    assert($styleManager->validateCSS($validCSS) === true, 'Valid CSS should pass validation');
    
    $invalidCSS = '.test { color: red margin: 10px }'; // Missing semicolon
    assert($styleManager->validateCSS($invalidCSS) === false, 'Invalid CSS should fail validation');
});

// Test 12: Can minify CSS
test("Can minify CSS", function() use ($styleManager) {
    $css = "
    .test {
        color: red;
        margin: 10px;
        padding: 5px;
    }
    
    .another {
        background: blue;
    }
    ";
    
    $minified = $styleManager->minifyCSS($css);
    
    assert(!str_contains($minified, "\n"), 'Minified CSS should not contain newlines');
    assert(!str_contains($minified, '  '), 'Minified CSS should not contain double spaces');
    assert(str_contains($minified, '.test{color:red;margin:10px;padding:5px}'), 'Should be properly minified');
});

// Test 13: Can get CSS stats
test("Can get CSS stats", function() use ($styleManager) {
    $styleManager->setCustomCSS('.custom { color: red; }');
    
    $stats = $styleManager->getStats();
    
    assert(is_array($stats), 'Stats should be array');
    assert(array_key_exists('total_themes', $stats), 'Should have total_themes');
    assert(array_key_exists('active_theme', $stats), 'Should have active_theme');
    assert(array_key_exists('custom_css_enabled', $stats), 'Should have custom_css_enabled');
    assert(array_key_exists('compiled_css_size', $stats), 'Should have compiled_css_size');
});

echo "\nTest Results:\n";
echo "============\n";
echo "Passed: {$passedCount}/{$testCount}\n";

if ($passedCount === $testCount) {
    echo "🎉 All StyleManager tests passed!\n";
    exit(0);
} else {
    echo "❌ Some StyleManager tests failed!\n";
    exit(1);
}