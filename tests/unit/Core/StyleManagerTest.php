<?php

namespace AnsyblSite\Tests\Unit\Core;

use AnsyblSite\Tests\TestCase;
use AnsyblSite\Core\StyleManager;
use AnsyblSite\Core\ConfigManager;
use AnsyblSite\Core\ConcurrentFileManager;

class StyleManagerTest extends TestCase
{
    private StyleManager $styleManager;
    private ConfigManager $configManager;
    private ConcurrentFileManager $fileManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->fileManager = new ConcurrentFileManager($this->tempPath);
        $this->configManager = new ConfigManager($this->fileManager);
        $this->styleManager = new StyleManager($this->configManager, $this->fileManager);
    }
    
    public function testCanGenerateBasicCSS(): void
    {
        $css = $this->styleManager->generateCSS();
        
        $this->assertIsString($css);
        $this->assertStringContainsString('--primary-color', $css);
        $this->assertStringContainsString('#007cba', $css);
    }
    
    public function testCanCompileThemeVariables(): void
    {
        $theme = [
            'name' => 'Test Theme',
            'variables' => [
                '--primary-color' => '#ff0000',
                '--secondary-color' => '#00ff00',
                '--font-family' => "'Helvetica', sans-serif"
            ]
        ];
        
        $css = $this->styleManager->compileTheme($theme);
        
        $this->assertStringContainsString('--primary-color: #ff0000', $css);
        $this->assertStringContainsString('--secondary-color: #00ff00', $css);
        $this->assertStringContainsString("--font-family: 'Helvetica', sans-serif", $css);
    }
    
    public function testCanAddCustomCSS(): void
    {
        $customCSS = '.custom-class { color: blue; margin: 10px; }';
        
        $result = $this->styleManager->setCustomCSS($customCSS);
        $this->assertTrue($result);
        
        $retrievedCSS = $this->styleManager->getCustomCSS();
        $this->assertEquals($customCSS, $retrievedCSS);
    }
    
    public function testCanSwitchThemes(): void
    {
        // Add a custom theme
        $darkTheme = [
            'name' => 'Dark Theme',
            'variables' => [
                '--primary-color' => '#ffffff',
                '--background-color' => '#000000',
                '--text-color' => '#ffffff'
            ]
        ];
        
        $this->styleManager->addTheme('dark', $darkTheme);
        $result = $this->styleManager->setActiveTheme('dark');
        
        $this->assertTrue($result);
        $this->assertEquals('dark', $this->styleManager->getActiveTheme());
    }
    
    public function testGeneratedCSSIncludesActiveTheme(): void
    {
        // Set up dark theme
        $darkTheme = [
            'name' => 'Dark Theme',
            'variables' => [
                '--primary-color' => '#ffffff',
                '--background-color' => '#222222'
            ]
        ];
        
        $this->styleManager->addTheme('dark', $darkTheme);
        $this->styleManager->setActiveTheme('dark');
        
        $css = $this->styleManager->generateCSS();
        
        $this->assertStringContainsString('--primary-color: #ffffff', $css);
        $this->assertStringContainsString('--background-color: #222222', $css);
    }
    
    public function testCanGetAvailableThemes(): void
    {
        $themes = $this->styleManager->getThemes();
        
        $this->assertIsArray($themes);
        $this->assertArrayHasKey('default', $themes);
        $this->assertEquals('Default Theme', $themes['default']['name']);
    }
    
    public function testCanUpdateThemeVariable(): void
    {
        $result = $this->styleManager->updateThemeVariable('default', '--primary-color', '#cc0000');
        $this->assertTrue($result);
        
        $theme = $this->styleManager->getTheme('default');
        $this->assertEquals('#cc0000', $theme['variables']['--primary-color']);
    }
    
    public function testCanDeleteTheme(): void
    {
        // Add a theme to delete
        $testTheme = ['name' => 'Test Theme', 'variables' => []];
        $this->styleManager->addTheme('deleteme', $testTheme);
        
        $this->assertTrue($this->styleManager->hasTheme('deleteme'));
        
        $result = $this->styleManager->deleteTheme('deleteme');
        $this->assertTrue($result);
        $this->assertFalse($this->styleManager->hasTheme('deleteme'));
    }
    
    public function testCannotDeleteActiveTheme(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete active theme');
        
        $this->styleManager->deleteTheme('default');
    }
    
    public function testCanGenerateResponsiveCSS(): void
    {
        $css = $this->styleManager->generateResponsiveCSS();
        
        $this->assertIsString($css);
        $this->assertStringContainsString('@media', $css);
        $this->assertStringContainsString('768px', $css); // Mobile breakpoint
        $this->assertStringContainsString('1024px', $css); // Tablet breakpoint
    }
    
    public function testCanUpdateResponsiveSettings(): void
    {
        $settings = [
            'mobile_breakpoint' => '600px',
            'tablet_breakpoint' => '900px',
            'desktop_breakpoint' => '1200px'
        ];
        
        $result = $this->styleManager->updateResponsiveSettings($settings);
        $this->assertTrue($result);
        
        $responsive = $this->styleManager->getResponsiveSettings();
        $this->assertEquals('600px', $responsive['mobile_breakpoint']);
        $this->assertEquals('900px', $responsive['tablet_breakpoint']);
    }
    
    public function testCanCompileFullStylesheet(): void
    {
        // Set up custom CSS and theme
        $this->styleManager->setCustomCSS('.custom { font-size: 14px; }');
        
        $css = $this->styleManager->compileFullStylesheet();
        
        $this->assertIsString($css);
        $this->assertStringContainsString(':root {', $css); // CSS variables
        $this->assertStringContainsString('.custom { font-size: 14px; }', $css); // Custom CSS
        $this->assertStringContainsString('@media', $css); // Responsive CSS
    }
    
    public function testCanSaveCompiledCSS(): void
    {
        $filename = 'compiled-styles.css';
        
        $result = $this->styleManager->saveCompiledCSS($filename);
        $this->assertTrue($result);
        
        $this->assertTrue($this->fileManager->exists($filename));
        
        $savedCSS = file_get_contents($this->fileManager->getFilePath($filename));
        $this->assertStringContainsString(':root {', $savedCSS);
    }
    
    public function testValidatesCSSSyntax(): void
    {
        $validCSS = '.test { color: red; margin: 10px; }';
        $this->assertTrue($this->styleManager->validateCSS($validCSS));
        
        $invalidCSS = '.test { color: red margin: 10px }'; // Missing semicolon
        $this->assertFalse($this->styleManager->validateCSS($invalidCSS));
    }
    
    public function testCanMinifyCSS(): void
    {
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
        
        $minified = $this->styleManager->minifyCSS($css);
        
        $this->assertStringNotContainsString("\n", $minified);
        $this->assertStringNotContainsString('  ', $minified);
        $this->assertStringContainsString('.test{color:red;margin:10px;padding:5px}', $minified);
    }
    
    public function testCanGetCSSStats(): void
    {
        $this->styleManager->setCustomCSS('.custom { color: red; }');
        
        $stats = $this->styleManager->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_themes', $stats);
        $this->assertArrayHasKey('active_theme', $stats);
        $this->assertArrayHasKey('custom_css_enabled', $stats);
        $this->assertArrayHasKey('compiled_css_size', $stats);
    }
}