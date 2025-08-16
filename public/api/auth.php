<?php
/**
 * Authentication API Endpoint
 * Handles admin login/logout and session management
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['PATH_INFO'] ?? '';
    
    // Initialize core components
    $fileManager = new ConcurrentFileManager('../../data');
    $configManager = new ConfigManager($fileManager);
    
    // Route requests
    switch ($method) {
        case 'POST':
            if ($path === '/setup') {
                handleSetupRequest($configManager);
            } else {
                handleLoginRequest($path, $configManager);
            }
            break;
            
        case 'GET':
            if ($path === '/check-setup') {
                handleCheckSetupRequest($configManager);
            } else {
                handleStatusRequest($path, $configManager);
            }
            break;
            
        case 'DELETE':
            handleLogoutRequest($path);
            break;
            
        default:
            sendError(405, 'Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Auth API Error: " . $e->getMessage());
    sendError(500, 'Internal server error', $e->getMessage());
}

/**
 * Handle setup requests for first-time admin creation
 */
function handleSetupRequest($configManager) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError(400, 'Invalid JSON input');
    }
    
    if (!isset($input['username']) || !isset($input['password'])) {
        sendError(400, 'Username and password are required');
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    
    // Validate username format
    if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
        sendError(400, 'Username must be 3-50 characters with only letters, numbers, underscore, and dash');
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        sendError(400, 'Password must be at least 8 characters');
    }
    
    if (!preg_match('/(?=.*[a-zA-Z])(?=.*[0-9])/', $password)) {
        sendError(400, 'Password must contain both letters and numbers');
    }
    
    try {
        // Check if admin already exists
        $adminConfig = null;
        try {
            $adminConfig = $configManager->get('admin');
        } catch (Exception $e) {
            // Admin config doesn't exist, which is expected for setup
        }
        
        if ($adminConfig && isset($adminConfig['credentials']) && count($adminConfig['credentials']) > 0) {
            sendError(409, 'Admin account already exists. Please use the login page.');
        }
        
        // Create admin configuration
        $adminConfig = [
            'version' => '1.0',
            'credentials' => [
                $username => [
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'admin',
                    'created' => date('c'),
                    'last_login' => null
                ]
            ],
            'settings' => [
                'session_timeout' => 3600, // 1 hour
                'max_login_attempts' => 5,
                'lockout_duration' => 900 // 15 minutes
            ],
            'meta' => [
                'created' => date('c'),
                'modified' => date('c'),
                'schema_version' => '1.0'
            ]
        ];
        
        $success = $configManager->set('admin', $adminConfig);
        
        if (!$success) {
            sendError(500, 'Failed to create admin configuration');
        }
        
        // Log successful setup
        error_log("Admin setup completed: $username");
        
        sendSuccess([
            'message' => 'Admin account created successfully',
            'username' => $username,
            'created_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        error_log("Admin setup error: " . $e->getMessage());
        sendError(500, 'Failed to create admin account', $e->getMessage());
    }
}

/**
 * Handle check setup requests
 */
function handleCheckSetupRequest($configManager) {
    try {
        $adminConfig = $configManager->get('admin');
        $hasAdmin = $adminConfig && isset($adminConfig['credentials']) && count($adminConfig['credentials']) > 0;
        
        sendSuccess([
            'setup_required' => !$hasAdmin,
            'has_admin' => $hasAdmin
        ]);
        
    } catch (Exception $e) {
        // If config doesn't exist, setup is required
        sendSuccess([
            'setup_required' => true,
            'has_admin' => false
        ]);
    }
}

/**
 * Handle login requests
 */
function handleLoginRequest($path, $configManager) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError(400, 'Invalid JSON input');
    }
    
    if (!isset($input['username']) || !isset($input['password'])) {
        sendError(400, 'Username and password are required');
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    $rememberMe = isset($input['remember_me']) ? (bool)$input['remember_me'] : false;
    
    if (empty($username) || empty($password)) {
        sendError(400, 'Username and password cannot be empty');
    }
    
    // Check for rate limiting
    if (isLoginRateLimited($username)) {
        sendError(429, 'Too many login attempts. Please try again later.');
    }
    
    // Verify credentials
    if (verifyCredentials($username, $password, $configManager)) {
        // Create session
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_last_activity'] = time();
        $_SESSION['admin_remember_me'] = $rememberMe;
        
        // Set session timeout based on remember me
        $sessionTimeout = $rememberMe ? (30 * 24 * 3600) : 3600; // 30 days or 1 hour
        $_SESSION['admin_session_timeout'] = $sessionTimeout;
        
        // Update last login time in admin config
        updateLastLoginTime($username, $configManager);
        
        // Reset failed login attempts
        clearFailedLoginAttempts($username);
        
        // Log successful login
        error_log("Admin login successful: $username" . ($rememberMe ? " (remember me)" : ""));
        
        sendSuccess([
            'message' => 'Login successful',
            'username' => $username,
            'login_time' => date('c'),
            'session_expires' => date('c', time() + $sessionTimeout),
            'remember_me' => $rememberMe
        ]);
    } else {
        // Record failed login attempt
        recordFailedLoginAttempt($username);
        
        // Log failed login attempt
        error_log("Admin login failed: $username");
        
        sendError(401, 'Invalid credentials');
    }
}

/**
 * Handle status/check requests
 */
function handleStatusRequest($path, $configManager) {
    if (isAuthenticated()) {
        // Update last activity
        $_SESSION['admin_last_activity'] = time();
        
        sendSuccess([
            'authenticated' => true,
            'username' => $_SESSION['admin_username'],
            'login_time' => date('c', $_SESSION['admin_login_time']),
            'last_activity' => date('c', $_SESSION['admin_last_activity']),
            'session_expires' => date('c', $_SESSION['admin_last_activity'] + 3600)
        ]);
    } else {
        sendSuccess([
            'authenticated' => false
        ]);
    }
}

/**
 * Handle logout requests
 */
function handleLogoutRequest($path) {
    $username = $_SESSION['admin_username'] ?? 'unknown';
    
    // Destroy session
    session_destroy();
    
    // Log logout
    error_log("Admin logout: $username");
    
    sendSuccess([
        'message' => 'Logout successful',
        'logged_out_at' => date('c')
    ]);
}

/**
 * Verify admin credentials
 */
function verifyCredentials($username, $password, $configManager) {
    try {
        // Try to get admin configuration
        $adminConfig = $configManager->get('admin');
        
        if (!$adminConfig || !isset($adminConfig['credentials'])) {
            // Create default admin credentials if none exist
            $defaultConfig = createDefaultAdminConfig($username, $password, $configManager);
            return true; // First time setup
        }
        
        $credentials = $adminConfig['credentials'];
        
        // Check if username exists
        if (!isset($credentials[$username])) {
            return false;
        }
        
        $userConfig = $credentials[$username];
        
        // Verify password
        if (isset($userConfig['password_hash'])) {
            return password_verify($password, $userConfig['password_hash']);
        } else if (isset($userConfig['password'])) {
            // Legacy plain text password (upgrade to hash)
            if ($userConfig['password'] === $password) {
                // Upgrade to hashed password
                upgradePasswordToHash($username, $password, $configManager);
                return true;
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error verifying credentials: " . $e->getMessage());
        return false;
    }
}

/**
 * Create default admin configuration
 */
function createDefaultAdminConfig($username, $password, $configManager) {
    $defaultConfig = [
        'version' => '1.0',
        'credentials' => [
            $username => [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'admin',
                'created' => date('c'),
                'last_login' => null
            ]
        ],
        'settings' => [
            'session_timeout' => 3600, // 1 hour
            'max_login_attempts' => 5,
            'lockout_duration' => 900 // 15 minutes
        ],
        'meta' => [
            'created' => date('c'),
            'modified' => date('c'),
            'schema_version' => '1.0'
        ]
    ];
    
    $configManager->set('admin', $defaultConfig);
    error_log("Created default admin configuration for: $username");
    
    return true;
}

/**
 * Upgrade plain text password to hash
 */
function upgradePasswordToHash($username, $password, $configManager) {
    try {
        $adminConfig = $configManager->get('admin');
        
        // Update password to hash
        $adminConfig['credentials'][$username]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        unset($adminConfig['credentials'][$username]['password']); // Remove plain text
        $adminConfig['meta']['modified'] = date('c');
        
        $configManager->set('admin', $adminConfig);
        error_log("Upgraded password to hash for: $username");
        
    } catch (Exception $e) {
        error_log("Error upgrading password: " . $e->getMessage());
    }
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        return false;
    }
    
    // Get session timeout (default 1 hour, or extended if remember me)
    $sessionTimeout = isset($_SESSION['admin_session_timeout']) ? $_SESSION['admin_session_timeout'] : 3600;
    
    // Check session timeout
    if (isset($_SESSION['admin_last_activity']) && 
        (time() - $_SESSION['admin_last_activity']) > $sessionTimeout) {
        session_destroy();
        return false;
    }
    
    return true;
}

/**
 * Rate limiting functions
 */
function isLoginRateLimited($username) {
    $attempts = getFailedLoginAttempts($username);
    if ($attempts >= 5) {
        $lastAttempt = getLastFailedLoginTime($username);
        $lockoutTime = 15 * 60; // 15 minutes
        return (time() - $lastAttempt) < $lockoutTime;
    }
    return false;
}

function recordFailedLoginAttempt($username) {
    $attempts = getFailedLoginAttempts($username) + 1;
    file_put_contents("../../data/login_attempts_$username.txt", "$attempts:" . time());
}

function getFailedLoginAttempts($username) {
    $file = "../../data/login_attempts_$username.txt";
    if (file_exists($file)) {
        $data = file_get_contents($file);
        $parts = explode(':', $data);
        return (int)$parts[0];
    }
    return 0;
}

function getLastFailedLoginTime($username) {
    $file = "../../data/login_attempts_$username.txt";
    if (file_exists($file)) {
        $data = file_get_contents($file);
        $parts = explode(':', $data);
        return isset($parts[1]) ? (int)$parts[1] : 0;
    }
    return 0;
}

function clearFailedLoginAttempts($username) {
    $file = "../../data/login_attempts_$username.txt";
    if (file_exists($file)) {
        unlink($file);
    }
}

/**
 * Update last login time in admin config
 */
function updateLastLoginTime($username, $configManager) {
    try {
        $adminConfig = $configManager->get('admin');
        if (isset($adminConfig['credentials'][$username])) {
            $adminConfig['credentials'][$username]['last_login'] = date('c');
            $adminConfig['meta']['modified'] = date('c');
            $configManager->set('admin', $adminConfig);
        }
    } catch (Exception $e) {
        error_log("Failed to update last login time: " . $e->getMessage());
    }
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