<?php
/**
 * Configuration API Endpoint
 * Handles site configuration management for the frontend
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../vendor/autoload.php';

use AnsyblSite\Core\ConcurrentFileManager;
use AnsyblSite\Core\ConfigManager;

try {
    // Initialize core components
    $fileManager = new ConcurrentFileManager('../../data');
    $configManager = new ConfigManager($fileManager);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['PATH_INFO'] ?? '';
    
    // Route requests
    switch ($method) {
        case 'GET':
            handleGetRequest($path, $configManager);
            break;
            
        case 'POST':
            handlePostRequest($path, $configManager);
            break;
            
        case 'PUT':
            handlePutRequest($path, $configManager);
            break;
            
        case 'DELETE':
            handleDeleteRequest($path, $configManager);
            break;
            
        default:
            sendError(405, 'Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Config API Error: " . $e->getMessage());
    sendError(500, 'Internal server error', $e->getMessage());
}

/**
 * Handle GET requests
 */
function handleGetRequest($path, $configManager) {
    switch ($path) {
        case '':
        case '/':
            // Get all configurations
            getAllConfigs($configManager);
            break;
            
        case '/site':
            // Get site configuration
            getConfig('site', $configManager);
            break;
            
        case '/feeds':
            // Get feeds configuration
            getConfig('feeds', $configManager);
            break;
            
        case '/styles':
            // Get styles configuration
            getConfig('styles', $configManager);
            break;
            
        case '/list':
            // List available configurations
            listConfigs($configManager);
            break;
            
        default:
            // Get specific config by name
            if (preg_match('/^\/([a-zA-Z0-9_-]+)$/', $path, $matches)) {
                getConfig($matches[1], $configManager);
            } else {
                sendError(404, 'Configuration not found');
            }
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($path, $configManager) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError(400, 'Invalid JSON input');
    }
    
    switch ($path) {
        case '/backup':
            // Create backup of configurations
            backupConfigs($input, $configManager);
            break;
            
        case '/restore':
            // Restore configurations from backup
            restoreConfigs($input, $configManager);
            break;
            
        default:
            if (preg_match('/^\/([a-zA-Z0-9_-]+)\/backup$/', $path, $matches)) {
                // Backup specific configuration
                backupConfig($matches[1], $configManager);
            } else {
                sendError(404, 'Endpoint not found');
            }
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($path, $configManager) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError(400, 'Invalid JSON input');
    }
    
    switch ($path) {
        case '/site':
            // Update site configuration
            updateConfig('site', $input, $configManager);
            break;
            
        case '/feeds':
            // Update feeds configuration
            updateConfig('feeds', $input, $configManager);
            break;
            
        case '/styles':
            // Update styles configuration
            updateConfig('styles', $input, $configManager);
            break;
            
        default:
            if (preg_match('/^\/([a-zA-Z0-9_-]+)$/', $path, $matches)) {
                // Update specific configuration
                updateConfig($matches[1], $input, $configManager);
            } elseif (preg_match('/^\/([a-zA-Z0-9_-]+)\/([a-zA-Z0-9_.]+)$/', $path, $matches)) {
                // Update specific configuration value
                updateConfigValue($matches[1], $matches[2], $input, $configManager);
            } else {
                sendError(404, 'Configuration not found');
            }
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($path, $configManager) {
    if (preg_match('/^\/([a-zA-Z0-9_-]+)$/', $path, $matches)) {
        // Delete specific configuration
        deleteConfig($matches[1], $configManager);
    } else {
        sendError(404, 'Configuration not found');
    }
}

/**
 * Get all configurations
 */
function getAllConfigs($configManager) {
    try {
        $configNames = $configManager->list();
        $configs = [];
        
        foreach ($configNames as $name) {
            try {
                $configs[$name] = $configManager->get($name);
            } catch (Exception $e) {
                // Skip failed configs but log the error
                error_log("Failed to load config '$name': " . $e->getMessage());
            }
        }
        
        sendSuccess([
            'configs' => $configs,
            'available' => $configNames,
            'count' => count($configs)
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to get configurations', $e->getMessage());
    }
}

/**
 * Get specific configuration
 */
function getConfig($configName, $configManager) {
    try {
        $config = $configManager->get($configName);
        
        sendSuccess([
            'name' => $configName,
            'config' => $config,
            'exists' => $configManager->exists($configName)
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to get configuration', $e->getMessage());
    }
}

/**
 * List available configurations
 */
function listConfigs($configManager) {
    try {
        $configNames = $configManager->list();
        $configInfo = [];
        
        foreach ($configNames as $name) {
            try {
                $config = $configManager->get($name);
                $configInfo[] = [
                    'name' => $name,
                    'exists' => true,
                    'version' => $config['version'] ?? 'unknown',
                    'created' => $config['meta']['created'] ?? null,
                    'modified' => $config['meta']['modified'] ?? null,
                    'schema_version' => $config['meta']['schema_version'] ?? null
                ];
            } catch (Exception $e) {
                $configInfo[] = [
                    'name' => $name,
                    'exists' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        sendSuccess([
            'configurations' => $configInfo,
            'count' => count($configNames)
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to list configurations', $e->getMessage());
    }
}

/**
 * Update configuration
 */
function updateConfig($configName, $input, $configManager) {
    try {
        // Validate input based on config type
        $validatedInput = validateConfigInput($configName, $input);
        
        // Update the configuration
        $success = $configManager->set($configName, $validatedInput);
        
        if (!$success) {
            sendError(500, 'Failed to save configuration');
        }
        
        // Get the updated configuration to return
        $updatedConfig = $configManager->get($configName);
        
        sendSuccess([
            'message' => 'Configuration updated successfully',
            'name' => $configName,
            'config' => $updatedConfig
        ]);
        
    } catch (Exception $e) {
        sendError(400, 'Failed to update configuration', $e->getMessage());
    }
}

/**
 * Update specific configuration value
 */
function updateConfigValue($configName, $path, $input, $configManager) {
    try {
        if (!isset($input['value'])) {
            sendError(400, 'Missing value in request body');
        }
        
        $success = $configManager->setValue($configName, $path, $input['value']);
        
        if (!$success) {
            sendError(500, 'Failed to update configuration value');
        }
        
        // Get the updated value to return
        $updatedValue = $configManager->getValue($configName, $path);
        
        sendSuccess([
            'message' => 'Configuration value updated successfully',
            'name' => $configName,
            'path' => $path,
            'value' => $updatedValue
        ]);
        
    } catch (Exception $e) {
        sendError(400, 'Failed to update configuration value', $e->getMessage());
    }
}

/**
 * Delete configuration
 */
function deleteConfig($configName, $configManager) {
    try {
        if (!$configManager->exists($configName)) {
            sendError(404, 'Configuration not found');
        }
        
        $success = $configManager->delete($configName);
        
        if (!$success) {
            sendError(500, 'Failed to delete configuration');
        }
        
        sendSuccess([
            'message' => 'Configuration deleted successfully',
            'name' => $configName
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to delete configuration', $e->getMessage());
    }
}

/**
 * Backup configurations
 */
function backupConfigs($input, $configManager) {
    try {
        $configNames = $input['configs'] ?? $configManager->list();
        $backups = [];
        
        foreach ($configNames as $name) {
            try {
                $backupFile = $configManager->backup($name);
                $backups[] = [
                    'name' => $name,
                    'backup_file' => $backupFile,
                    'status' => 'success'
                ];
            } catch (Exception $e) {
                $backups[] = [
                    'name' => $name,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        sendSuccess([
            'message' => 'Backup completed',
            'backups' => $backups,
            'timestamp' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to create backups', $e->getMessage());
    }
}

/**
 * Backup specific configuration
 */
function backupConfig($configName, $configManager) {
    try {
        $backupFile = $configManager->backup($configName);
        
        sendSuccess([
            'message' => 'Configuration backed up successfully',
            'name' => $configName,
            'backup_file' => $backupFile,
            'timestamp' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to backup configuration', $e->getMessage());
    }
}

/**
 * Restore configurations from backup
 */
function restoreConfigs($input, $configManager) {
    try {
        if (!isset($input['backups']) || !is_array($input['backups'])) {
            sendError(400, 'Missing or invalid backups array');
        }
        
        $results = [];
        
        foreach ($input['backups'] as $backup) {
            if (!isset($backup['name']) || !isset($backup['backup_file'])) {
                $results[] = [
                    'status' => 'error',
                    'error' => 'Missing name or backup_file'
                ];
                continue;
            }
            
            try {
                $success = $configManager->restore($backup['name'], $backup['backup_file']);
                
                $results[] = [
                    'name' => $backup['name'],
                    'backup_file' => $backup['backup_file'],
                    'status' => $success ? 'success' : 'error'
                ];
            } catch (Exception $e) {
                $results[] = [
                    'name' => $backup['name'],
                    'backup_file' => $backup['backup_file'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        sendSuccess([
            'message' => 'Restore completed',
            'results' => $results,
            'timestamp' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to restore configurations', $e->getMessage());
    }
}

/**
 * Validate configuration input based on type
 */
function validateConfigInput($configName, $input) {
    // Basic validation - ensure required fields exist
    if (!is_array($input)) {
        throw new Exception('Configuration must be an object/array');
    }
    
    // Ensure version field exists
    if (!isset($input['version'])) {
        $input['version'] = '1.0';
    }
    
    // Type-specific validation
    switch ($configName) {
        case 'site':
            return validateSiteConfig($input);
            
        case 'feeds':
            return validateFeedsConfig($input);
            
        case 'styles':
            return validateStylesConfig($input);
            
        default:
            // Generic validation
            return $input;
    }
}

/**
 * Validate site configuration
 */
function validateSiteConfig($input) {
    // Ensure site object exists
    if (!isset($input['site'])) {
        throw new Exception('Site configuration must contain a "site" object');
    }
    
    $site = $input['site'];
    
    // Validate required fields
    if (!isset($site['title']) || empty(trim($site['title']))) {
        throw new Exception('Site title is required');
    }
    
    // Validate optional fields
    if (isset($site['language']) && !preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $site['language'])) {
        throw new Exception('Invalid language code format');
    }
    
    if (isset($site['url']) && !empty($site['url']) && !filter_var($site['url'], FILTER_VALIDATE_URL)) {
        throw new Exception('Invalid site URL format');
    }
    
    // Set defaults for display settings
    if (!isset($input['display'])) {
        $input['display'] = [];
    }
    
    $displayDefaults = [
        'items_per_page' => 10,
        'show_timestamps' => true,
        'date_format' => 'Y-m-d H:i:s',
        'excerpt_length' => 150
    ];
    
    foreach ($displayDefaults as $key => $default) {
        if (!isset($input['display'][$key])) {
            $input['display'][$key] = $default;
        }
    }
    
    // Validate numeric fields
    if (isset($input['display']['items_per_page'])) {
        $itemsPerPage = (int) $input['display']['items_per_page'];
        if ($itemsPerPage < 1 || $itemsPerPage > 100) {
            throw new Exception('Items per page must be between 1 and 100');
        }
        $input['display']['items_per_page'] = $itemsPerPage;
    }
    
    if (isset($input['display']['excerpt_length'])) {
        $excerptLength = (int) $input['display']['excerpt_length'];
        if ($excerptLength < 50 || $excerptLength > 1000) {
            throw new Exception('Excerpt length must be between 50 and 1000 characters');
        }
        $input['display']['excerpt_length'] = $excerptLength;
    }
    
    return $input;
}

/**
 * Validate feeds configuration
 */
function validateFeedsConfig($input) {
    if (!isset($input['feeds'])) {
        $input['feeds'] = [];
    }
    
    if (!is_array($input['feeds'])) {
        throw new Exception('Feeds must be an array');
    }
    
    // Validate each feed
    foreach ($input['feeds'] as $index => $feed) {
        if (!is_array($feed)) {
            throw new Exception("Feed at index $index must be an object");
        }
        
        if (!isset($feed['id']) || empty($feed['id'])) {
            throw new Exception("Feed at index $index missing required field: id");
        }
        
        if (!isset($feed['name']) || empty($feed['name'])) {
            throw new Exception("Feed at index $index missing required field: name");
        }
        
        if (!isset($feed['url']) || !filter_var($feed['url'], FILTER_VALIDATE_URL)) {
            throw new Exception("Feed at index $index has invalid URL");
        }
        
        // Set defaults
        if (!isset($feed['enabled'])) {
            $input['feeds'][$index]['enabled'] = true;
        }
        
        if (!isset($feed['order'])) {
            $input['feeds'][$index]['order'] = $index + 1;
        }
    }
    
    // Update metadata
    if (!isset($input['meta'])) {
        $input['meta'] = [];
    }
    
    $input['meta']['total_feeds'] = count($input['feeds']);
    $input['meta']['active_feeds'] = count(array_filter($input['feeds'], fn($f) => $f['enabled']));
    $input['meta']['last_updated'] = date('c');
    
    return $input;
}

/**
 * Validate styles configuration
 */
function validateStylesConfig($input) {
    // Validate active theme
    if (isset($input['active_theme'])) {
        if (!isset($input['themes'][$input['active_theme']])) {
            throw new Exception('Active theme does not exist in themes collection');
        }
    }
    
    // Validate themes
    if (isset($input['themes']) && is_array($input['themes'])) {
        foreach ($input['themes'] as $themeName => $theme) {
            if (!is_array($theme)) {
                throw new Exception("Theme '$themeName' must be an object");
            }
            
            if (!isset($theme['name'])) {
                $input['themes'][$themeName]['name'] = ucfirst($themeName) . ' Theme';
            }
            
            if (!isset($theme['variables'])) {
                $input['themes'][$themeName]['variables'] = [];
            }
        }
    }
    
    // Validate custom CSS
    if (isset($input['custom_css'])) {
        if (!is_array($input['custom_css'])) {
            $input['custom_css'] = [
                'enabled' => false,
                'css' => ''
            ];
        }
        
        if (!isset($input['custom_css']['enabled'])) {
            $input['custom_css']['enabled'] = false;
        }
        
        if (!isset($input['custom_css']['css'])) {
            $input['custom_css']['css'] = '';
        }
    }
    
    // Validate responsive settings
    if (isset($input['responsive'])) {
        if (!is_array($input['responsive'])) {
            $input['responsive'] = [
                'mobile_breakpoint' => '768px',
                'tablet_breakpoint' => '1024px'
            ];
        }
        
        // Basic validation of breakpoint format
        if (isset($input['responsive']['mobile_breakpoint']) && 
            !preg_match('/^\d+(px|em|rem|%)$/', $input['responsive']['mobile_breakpoint'])) {
            throw new Exception('Invalid mobile breakpoint format');
        }
        
        if (isset($input['responsive']['tablet_breakpoint']) && 
            !preg_match('/^\d+(px|em|rem|%)$/', $input['responsive']['tablet_breakpoint'])) {
            throw new Exception('Invalid tablet breakpoint format');
        }
    }
    
    return $input;
}

/**
 * Utility functions
 */

function sendSuccess($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => date('c')
    ]);
    exit();
}

function sendError($statusCode, $message, $details = null) {
    http_response_code($statusCode);
    $response = [
        'success' => false,
        'error' => [
            'message' => $message,
            'code' => $statusCode
        ],
        'timestamp' => date('c')
    ];
    
    if ($details) {
        $response['error']['details'] = $details;
    }
    
    echo json_encode($response);
    exit();
}