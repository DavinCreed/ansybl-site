<?php

namespace AnsyblSite\Core;

use AnsyblSite\Exceptions\ConfigException;
use AnsyblSite\Exceptions\MissingConfigException;
use AnsyblSite\Exceptions\InvalidConfigException;

class ConfigManager 
{
    private ConcurrentFileManager $fileManager;
    private SchemaValidator $validator;
    private array $configSchemas = [];
    private array $defaults = [];
    
    public function __construct(ConcurrentFileManager $fileManager, ?SchemaValidator $validator = null)
    {
        $this->fileManager = $fileManager;
        $this->validator = $validator ?? new SchemaValidator();
        
        $this->registerConfigSchemas();
        $this->registerDefaults();
    }
    
    public function get(string $configName): array
    {
        $filename = $this->getConfigFilename($configName);
        
        if (!$this->fileManager->exists($filename)) {
            return $this->getDefaults($configName);
        }
        
        try {
            return $this->fileManager->read($filename);
        } catch (\Exception $e) {
            return $this->getDefaults($configName);
        }
    }
    
    public function getWithDefaults(string $configName): array
    {
        $config = $this->get($configName);
        $defaults = $this->getDefaults($configName);
        
        return $this->arrayMergeRecursive($defaults, $config);
    }
    
    public function set(string $configName, array $config): bool
    {
        // Validate configuration
        if (!$this->validateConfig($configName, $config)) {
            $errors = array_map(fn($error) => $error['message'], $this->validator->getErrors());
            throw new InvalidConfigException("Invalid configuration for {$configName}: " . 
                implode(', ', $errors));
        }
        
        // Ensure metadata
        $config = $this->ensureMetadata($config);
        
        $filename = $this->getConfigFilename($configName);
        return $this->fileManager->atomicWrite($filename, $config);
    }
    
    public function getValue(string $configName, string $path): mixed
    {
        $config = $this->get($configName);
        return $this->getValueByPath($config, $path);
    }
    
    public function setValue(string $configName, string $path, mixed $value): bool
    {
        return $this->fileManager->transactionalUpdate($this->getConfigFilename($configName), 
            function($config) use ($configName, $path, $value) {
                if (empty($config)) {
                    $config = $this->getDefaults($configName);
                }
                
                $this->setValueByPath($config, $path, $value);
                $config = $this->ensureMetadata($config);
                
                return $config;
            }
        );
    }
    
    public function merge(string $configName, array $updates): bool
    {
        return $this->fileManager->transactionalUpdate($this->getConfigFilename($configName),
            function($config) use ($updates, $configName) {
                if (empty($config)) {
                    $config = $this->getDefaults($configName);
                }
                
                $merged = $this->arrayMergeRecursive($config, $updates);
                $merged = $this->ensureMetadata($merged);
                
                return $merged;
            }
        );
    }
    
    public function exists(string $configName): bool
    {
        $filename = $this->getConfigFilename($configName);
        return $this->fileManager->exists($filename);
    }
    
    public function delete(string $configName): bool
    {
        $filename = $this->getConfigFilename($configName);
        return $this->fileManager->delete($filename);
    }
    
    public function list(): array
    {
        $configFiles = glob($this->fileManager->getFilePath('*.json'));
        $configs = [];
        
        if ($configFiles === false) {
            return [];
        }
        
        foreach ($configFiles as $file) {
            $basename = basename($file, '.json');
            // Exclude cache and backup files
            if (!str_contains($basename, 'cache') && !str_contains($basename, 'backup')) {
                $configs[] = $basename;
            }
        }
        
        return $configs;
    }
    
    public function backup(string $configName): string
    {
        if (!$this->exists($configName)) {
            throw new MissingConfigException("Cannot backup non-existent config: {$configName}");
        }
        
        $config = $this->get($configName);
        $timestamp = date('Y-m-d-H-i-s');
        $backupFilename = "{$configName}-backup-{$timestamp}.json";
        
        $this->fileManager->write($backupFilename, $config);
        
        return $backupFilename;
    }
    
    public function restore(string $configName, string $backupFilename): bool
    {
        if (!$this->fileManager->exists($backupFilename)) {
            throw new MissingConfigException("Backup file not found: {$backupFilename}");
        }
        
        $backupConfig = $this->fileManager->read($backupFilename);
        return $this->set($configName, $backupConfig);
    }
    
    private function getConfigFilename(string $configName): string
    {
        return "{$configName}.json";
    }
    
    private function validateConfig(string $configName, array $config): bool
    {
        $schemaName = "config-{$configName}";
        
        if (!$this->validator->hasSchema($schemaName)) {
            // Use generic config schema
            $schemaName = 'config-generic';
        }
        
        return $this->validator->validate($config, $schemaName);
    }
    
    private function getValueByPath(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                throw new ConfigException("Path not found: {$path}");
            }
            $current = $current[$key];
        }
        
        return $current;
    }
    
    private function setValueByPath(array &$data, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$data;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }
    
    private function arrayMergeRecursive(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = $this->arrayMergeRecursive($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        
        return $array1;
    }
    
    private function ensureMetadata(array $config): array
    {
        if (!isset($config['meta'])) {
            $config['meta'] = [];
        }
        
        if (!isset($config['meta']['created'])) {
            $config['meta']['created'] = date('c');
        }
        
        $config['meta']['modified'] = date('c');
        
        if (!isset($config['meta']['schema_version'])) {
            $config['meta']['schema_version'] = '1.0';
        }
        
        return $config;
    }
    
    private function getDefaults(string $configName): array
    {
        return $this->defaults[$configName] ?? $this->defaults['generic'];
    }
    
    private function registerConfigSchemas(): void
    {
        $this->validator->registerSchema('config-generic', [
            'required' => ['version'],
            'properties' => [
                'version' => ['type' => 'string'],
                'meta' => ['type' => 'object']
            ]
        ]);
        
        $this->validator->registerSchema('config-site', [
            'required' => ['version', 'site'],
            'properties' => [
                'version' => ['type' => 'string'],
                'site' => [
                    'type' => 'object',
                    'required' => ['title'],
                    'properties' => [
                        'title' => ['type' => 'string', 'maxLength' => 100],
                        'description' => ['type' => 'string', 'maxLength' => 500],
                        'language' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 5],
                        'timezone' => ['type' => 'string'],
                        'url' => ['type' => 'string']
                    ]
                ],
                'display' => ['type' => 'object'],
                'features' => ['type' => 'object'],
                'meta' => ['type' => 'object']
            ]
        ]);
        
        $this->validator->registerSchema('config-feeds', [
            'required' => ['version', 'feeds'],
            'properties' => [
                'version' => ['type' => 'string'],
                'feeds' => ['type' => 'array'],
                'meta' => ['type' => 'object']
            ]
        ]);
        
        $this->validator->registerSchema('config-styles', [
            'required' => ['version'],
            'properties' => [
                'version' => ['type' => 'string'],
                'active_theme' => ['type' => 'string'],
                'themes' => ['type' => 'object'],
                'custom_css' => ['type' => 'object'],
                'responsive' => ['type' => 'object'],
                'meta' => ['type' => 'object']
            ]
        ]);
        
        $this->validator->registerSchema('config-menu', [
            'required' => ['version', 'menus'],
            'properties' => [
                'version' => ['type' => 'string'],
                'menus' => [
                    'type' => 'object',
                    'properties' => [
                        'primary' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'location' => ['type' => 'string'],
                                'items' => ['type' => 'array']
                            ]
                        ]
                    ]
                ],
                'meta' => ['type' => 'object']
            ]
        ]);
    }
    
    private function registerDefaults(): void
    {
        $this->defaults['generic'] = [
            'version' => '1.0',
            'meta' => [
                'created' => date('c'),
                'schema_version' => '1.0'
            ]
        ];
        
        $this->defaults['site'] = [
            'version' => '1.0',
            'site' => [
                'title' => 'Ansybl Site',
                'description' => 'A dynamic content site powered by Ansybl feeds',
                'language' => 'en',
                'timezone' => 'UTC'
            ],
            'display' => [
                'theme' => 'default',
                'items_per_page' => 10,
                'show_timestamps' => true,
                'date_format' => 'Y-m-d H:i:s',
                'excerpt_length' => 150
            ],
            'features' => [
                'search_enabled' => true,
                'comments_enabled' => false,
                'social_sharing' => true
            ],
            'meta' => [
                'created' => date('c'),
                'schema_version' => '1.0'
            ]
        ];
        
        $this->defaults['feeds'] = [
            'version' => '1.0',
            'feeds' => [],
            'meta' => [
                'total_feeds' => 0,
                'active_feeds' => 0,
                'last_updated' => date('c'),
                'schema_version' => '1.0'
            ]
        ];
        
        $this->defaults['styles'] = [
            'version' => '1.0',
            'active_theme' => 'default',
            'themes' => [
                'default' => [
                    'name' => 'Default Theme',
                    'variables' => [
                        '--primary-color' => '#007cba',
                        '--secondary-color' => '#333333',
                        '--background-color' => '#ffffff',
                        '--text-color' => '#333333',
                        '--font-family' => "'Arial', sans-serif"
                    ]
                ]
            ],
            'custom_css' => [
                'enabled' => false,
                'css' => ''
            ],
            'responsive' => [
                'mobile_breakpoint' => '768px',
                'tablet_breakpoint' => '1024px'
            ],
            'meta' => [
                'created' => date('c'),
                'schema_version' => '1.0'
            ]
        ];
        
        $this->defaults['menu'] = [
            'version' => '1.0',
            'menus' => [
                'primary' => [
                    'name' => 'Primary Navigation',
                    'location' => 'header',
                    'items' => [
                        [
                            'id' => 'home',
                            'type' => 'link',
                            'title' => 'Home',
                            'url' => '/',
                            'order' => 1,
                            'visible' => true,
                            'target' => '_self',
                            'css_class' => '',
                            'icon' => ''
                        ],
                        [
                            'id' => 'feeds',
                            'type' => 'link',
                            'title' => 'All Feeds',
                            'url' => '#feeds',
                            'order' => 2,
                            'visible' => true,
                            'target' => '_self',
                            'css_class' => '',
                            'icon' => ''
                        ]
                    ]
                ]
            ],
            'meta' => [
                'created' => date('c'),
                'schema_version' => '1.0'
            ]
        ];
    }
}