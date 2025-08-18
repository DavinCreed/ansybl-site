<?php
/**
 * Media Upload API Endpoint
 * Handles file uploads for local feeds and media management
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

// Authentication check - require admin login
function requireAuth() {
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        sendError(401, 'Authentication required');
    }
}

// Configuration
const UPLOAD_BASE_PATH = '../../public/uploads/feeds';
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const ALLOWED_AUDIO_TYPES = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4'];
const ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/ogg'];
const ALLOWED_DOCUMENT_TYPES = ['application/pdf', 'text/plain'];

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['PATH_INFO'] ?? '';
    
    // Route requests
    switch ($method) {
        case 'GET':
            handleGetRequest($path);
            break;
            
        case 'POST':
            handlePostRequest($path);
            break;
            
        case 'DELETE':
            handleDeleteRequest($path);
            break;
            
        default:
            sendError(405, 'Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Media API Error: " . $e->getMessage());
    sendError(500, 'Internal server error', $e->getMessage());
}

/**
 * Handle GET requests
 */
function handleGetRequest($path) {
    switch (true) {
        case preg_match('/^\/([a-zA-Z0-9_-]+)$/', $path, $matches):
            // List media files for feed
            listFeedMedia($matches[1]);
            break;
            
        case $path === '/info':
            // Get upload configuration info
            getUploadInfo();
            break;
            
        default:
            sendError(404, 'Endpoint not found');
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($path) {
    switch (true) {
        case preg_match('/^\/([a-zA-Z0-9_-]+)\/upload$/', $path, $matches):
            // Upload file to feed
            uploadFileToFeed($matches[1]);
            break;
            
        default:
            sendError(404, 'Endpoint not found');
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($path) {
    switch (true) {
        case preg_match('/^\/([a-zA-Z0-9_-]+)\/(.+)$/', $path, $matches):
            // Delete file from feed
            deleteFileFromFeed($matches[1], $matches[2]);
            break;
            
        default:
            sendError(404, 'Endpoint not found');
    }
}

/**
 * List media files for a feed
 */
function listFeedMedia($feedId) {
    try {
        $feedPath = UPLOAD_BASE_PATH . '/' . $feedId;
        
        if (!is_dir($feedPath)) {
            sendSuccess([
                'feedId' => $feedId,
                'files' => [],
                'count' => 0
            ]);
            return;
        }
        
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($feedPath));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($feedPath . '/', '', $file->getPathname());
                $files[] = [
                    'name' => $file->getFilename(),
                    'path' => $relativePath,
                    'size' => $file->getSize(),
                    'modified' => date('c', $file->getMTime()),
                    'type' => mime_content_type($file->getPathname()),
                    'url' => '/uploads/feeds/' . $feedId . '/' . $relativePath
                ];
            }
        }
        
        sendSuccess([
            'feedId' => $feedId,
            'files' => $files,
            'count' => count($files)
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to list media files', $e->getMessage());
    }
}

/**
 * Get upload configuration information
 */
function getUploadInfo() {
    sendSuccess([
        'maxFileSize' => MAX_FILE_SIZE,
        'maxFileSizeFormatted' => formatBytes(MAX_FILE_SIZE),
        'allowedTypes' => [
            'images' => ALLOWED_IMAGE_TYPES,
            'audio' => ALLOWED_AUDIO_TYPES,
            'video' => ALLOWED_VIDEO_TYPES,
            'documents' => ALLOWED_DOCUMENT_TYPES
        ],
        'allAllowedTypes' => array_merge(
            ALLOWED_IMAGE_TYPES,
            ALLOWED_AUDIO_TYPES,
            ALLOWED_VIDEO_TYPES,
            ALLOWED_DOCUMENT_TYPES
        )
    ]);
}

/**
 * Upload file to feed directory
 */
function uploadFileToFeed($feedId) {
    try {
        // Check if file was uploaded
        if (!isset($_FILES['file'])) {
            sendError(400, 'No file uploaded');
        }
        
        $file = $_FILES['file'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendError(400, 'File upload error: ' . getUploadErrorMessage($file['error']));
        }
        
        // Validate file size
        if ($file['size'] > MAX_FILE_SIZE) {
            sendError(400, 'File too large. Maximum size: ' . formatBytes(MAX_FILE_SIZE));
        }
        
        // Validate file type
        $mimeType = mime_content_type($file['tmp_name']);
        $allowedTypes = array_merge(
            ALLOWED_IMAGE_TYPES,
            ALLOWED_AUDIO_TYPES,
            ALLOWED_VIDEO_TYPES,
            ALLOWED_DOCUMENT_TYPES
        );
        
        if (!in_array($mimeType, $allowedTypes)) {
            sendError(400, 'File type not allowed: ' . $mimeType);
        }
        
        // Create feed upload directory if it doesn't exist
        $feedPath = UPLOAD_BASE_PATH . '/' . $feedId;
        if (!is_dir($feedPath)) {
            if (!mkdir($feedPath, 0755, true)) {
                sendError(500, 'Failed to create upload directory');
            }
        }
        
        // Generate unique filename
        $filename = generateUniqueFilename($file['name'], $feedPath);
        $destinationPath = $feedPath . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
            sendError(500, 'Failed to save uploaded file');
        }
        
        // Generate file information
        $fileInfo = [
            'name' => $filename,
            'originalName' => $file['name'],
            'size' => $file['size'],
            'type' => $mimeType,
            'url' => '/uploads/feeds/' . $feedId . '/' . $filename,
            'uploaded' => date('c')
        ];
        
        sendSuccess([
            'message' => 'File uploaded successfully',
            'feedId' => $feedId,
            'file' => $fileInfo
        ], 201);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to upload file', $e->getMessage());
    }
}

/**
 * Delete file from feed directory
 */
function deleteFileFromFeed($feedId, $filename) {
    try {
        $feedPath = UPLOAD_BASE_PATH . '/' . $feedId;
        $filePath = $feedPath . '/' . $filename;
        
        // Validate path is within feed directory (security check)
        if (!isPathSafe($filePath, $feedPath)) {
            sendError(400, 'Invalid file path');
        }
        
        if (!file_exists($filePath)) {
            sendError(404, 'File not found');
        }
        
        if (!unlink($filePath)) {
            sendError(500, 'Failed to delete file');
        }
        
        sendSuccess([
            'message' => 'File deleted successfully',
            'feedId' => $feedId,
            'filename' => $filename
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to delete file', $e->getMessage());
    }
}

/**
 * Generate unique filename to avoid conflicts
 */
function generateUniqueFilename($originalName, $directory) {
    $pathInfo = pathinfo($originalName);
    $basename = $pathInfo['filename'];
    $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
    
    // Sanitize filename
    $basename = preg_replace('/[^a-zA-Z0-9_-]/', '-', $basename);
    $basename = preg_replace('/-+/', '-', $basename);
    $basename = trim($basename, '-');
    
    $filename = $basename . $extension;
    $counter = 1;
    
    // Ensure uniqueness
    while (file_exists($directory . '/' . $filename)) {
        $filename = $basename . '-' . $counter . $extension;
        $counter++;
    }
    
    return $filename;
}

/**
 * Check if file path is safe (within allowed directory)
 */
function isPathSafe($filePath, $allowedDirectory) {
    $realFilePath = realpath($filePath);
    $realAllowedDir = realpath($allowedDirectory);
    
    if ($realFilePath === false || $realAllowedDir === false) {
        return false;
    }
    
    return strpos($realFilePath, $realAllowedDir) === 0;
}

/**
 * Get human-readable upload error message
 */
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'File exceeds upload_max_filesize directive';
        case UPLOAD_ERR_FORM_SIZE:
            return 'File exceeds MAX_FILE_SIZE directive';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Send success response
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

/**
 * Send error response
 */
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