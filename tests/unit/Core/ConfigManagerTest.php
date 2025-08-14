<?php

namespace AnsyblSite\Tests\Unit\Core;

use AnsyblSite\Tests\TestCase;
use AnsyblSite\Core\ConfigManager;
use AnsyblSite\Core\ConcurrentFileManager;
use AnsyblSite\Exceptions\ConfigException;
use AnsyblSite\Exceptions\MissingConfigException;
use AnsyblSite\Exceptions\InvalidConfigException;

class ConfigManagerTest extends TestCase
{
    private ConfigManager $configManager;
    private ConcurrentFileManager $fileManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->fileManager = new ConcurrentFileManager($this->tempPath);
        $this->configManager = new ConfigManager($this->fileManager);
    }
    
    public function testCanGetDefaultConfig(): void
    {
        $config = $this->configManager->get('site');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('version', $config);
        $this->assertArrayHasKey('site', $config);
        $this->assertEquals('1.0', $config['version']);
    }
    
    public function testCanSaveAndRetrieveConfig(): void
    {
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
        
        $result = $this->configManager->set('site', $siteConfig);
        $this->assertTrue($result);
        
        $retrieved = $this->configManager->get('site');
        $this->assertEquals('Test Site', $retrieved['site']['title']);
        $this->assertEquals('A test configuration', $retrieved['site']['description']);
    }
    
    public function testValidatesConfigStructure(): void
    {
        $invalidConfig = [
            // Missing required 'version' field
            'site' => ['title' => 'Test']
        ];
        
        $this->expectException(InvalidConfigException::class);
        $this->configManager->set('site', $invalidConfig);
    }
    
    public function testCanGetConfigValue(): void
    {
        $config = [
            'version' => '1.0',
            'site' => [
                'title' => 'My Site',
                'language' => 'en'
            ],
            'meta' => ['created' => date('c')]
        ];
        
        $this->configManager->set('site', $config);
        
        $title = $this->configManager->getValue('site', 'site.title');
        $this->assertEquals('My Site', $title);
        
        $language = $this->configManager->getValue('site', 'site.language');
        $this->assertEquals('en', $language);
    }
    
    public function testCanSetConfigValue(): void
    {
        // Set initial config
        $config = [
            'version' => '1.0',
            'site' => ['title' => 'Old Title'],
            'meta' => ['created' => date('c')]
        ];
        $this->configManager->set('site', $config);
        
        // Update single value
        $result = $this->configManager->setValue('site', 'site.title', 'New Title');
        $this->assertTrue($result);
        
        // Verify change
        $newTitle = $this->configManager->getValue('site', 'site.title');
        $this->assertEquals('New Title', $newTitle);
    }
    
    public function testCanMergeConfigValues(): void
    {
        $initialConfig = [
            'version' => '1.0',
            'site' => [
                'title' => 'Original Title',
                'language' => 'en'
            ],
            'meta' => ['created' => date('c')]
        ];
        $this->configManager->set('site', $initialConfig);
        
        $updates = [
            'site' => [
                'title' => 'Updated Title',
                'description' => 'New description'
            ],
            'display' => [
                'theme' => 'dark'
            ]
        ];
        
        $result = $this->configManager->merge('site', $updates);
        $this->assertTrue($result);
        
        $config = $this->configManager->get('site');
        $this->assertEquals('Updated Title', $config['site']['title']);
        $this->assertEquals('en', $config['site']['language']); // Preserved
        $this->assertEquals('New description', $config['site']['description']); // Added
        $this->assertEquals('dark', $config['display']['theme']); // New section
    }
    
    public function testCanListAvailableConfigs(): void
    {
        $this->configManager->set('site', ['version' => '1.0', 'meta' => []]);
        $this->configManager->set('feeds', ['version' => '1.0', 'feeds' => []]);
        
        $configs = $this->configManager->list();
        
        $this->assertIsArray($configs);
        $this->assertContains('site', $configs);
        $this->assertContains('feeds', $configs);
    }
    
    public function testCanCheckIfConfigExists(): void
    {
        $this->assertFalse($this->configManager->exists('nonexistent'));
        
        $this->configManager->set('test', ['version' => '1.0', 'meta' => []]);
        $this->assertTrue($this->configManager->exists('test'));
    }
    
    public function testCanDeleteConfig(): void
    {
        $this->configManager->set('deleteme', ['version' => '1.0', 'meta' => []]);
        $this->assertTrue($this->configManager->exists('deleteme'));
        
        $result = $this->configManager->delete('deleteme');
        $this->assertTrue($result);
        $this->assertFalse($this->configManager->exists('deleteme'));
    }
    
    public function testCanBackupConfig(): void
    {
        $config = [
            'version' => '1.0',
            'site' => ['title' => 'Backup Test'],
            'meta' => ['created' => date('c')]
        ];
        
        $this->configManager->set('backup-test', $config);
        
        $backupFile = $this->configManager->backup('backup-test');
        $this->assertIsString($backupFile);
        $this->assertStringContains('backup', $backupFile);
        
        // Verify backup file exists
        $this->assertTrue($this->fileManager->exists($backupFile));
    }
    
    public function testCanRestoreFromBackup(): void
    {
        $originalConfig = [
            'version' => '1.0',
            'site' => ['title' => 'Original'],
            'meta' => ['created' => date('c')]
        ];
        
        $this->configManager->set('restore-test', $originalConfig);
        $backupFile = $this->configManager->backup('restore-test');
        
        // Modify config
        $this->configManager->setValue('restore-test', 'site.title', 'Modified');
        $this->assertEquals('Modified', $this->configManager->getValue('restore-test', 'site.title'));
        
        // Restore from backup
        $result = $this->configManager->restore('restore-test', $backupFile);
        $this->assertTrue($result);
        
        $restored = $this->configManager->getValue('restore-test', 'site.title');
        $this->assertEquals('Original', $restored);
    }
    
    public function testHandlesNestedDotNotation(): void
    {
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
        
        $this->configManager->set('nested', $config);
        
        $theme = $this->configManager->getValue('nested', 'site.settings.display.theme');
        $this->assertEquals('dark', $theme);
        
        $this->configManager->setValue('nested', 'site.settings.display.font_size', 18);
        $fontSize = $this->configManager->getValue('nested', 'site.settings.display.font_size');
        $this->assertEquals(18, $fontSize);
    }
    
    public function testThrowsExceptionForInvalidDotPath(): void
    {
        $config = [
            'version' => '1.0',
            'site' => ['title' => 'Test'],
            'meta' => ['created' => date('c')]
        ];
        
        $this->configManager->set('path-test', $config);
        
        $this->expectException(ConfigException::class);
        $this->configManager->getValue('path-test', 'nonexistent.path.value');
    }
    
    public function testCanGetConfigWithDefaults(): void
    {
        // Request non-existent config should return defaults
        $config = $this->configManager->getWithDefaults('nonexistent');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('version', $config);
    }
}