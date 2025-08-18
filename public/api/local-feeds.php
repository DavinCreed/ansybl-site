<?php
/**
 * Local Feeds API Endpoint
 * Handles CRUD operations for locally stored Activity Streams feeds
 */

session_start();

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
use AnsyblSite\Core\LocalFeedManager;

// Authentication check - require admin login
function requireAuth() {
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        sendError(401, 'Authentication required');
    }
}

try {
    requireAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['PATH_INFO'] ?? '';
    
    // Initialize core components
    $fileManager = new ConcurrentFileManager('../../data');
    $localFeedManager = new LocalFeedManager($fileManager);
    
    // Route requests
    switch ($method) {
        case 'GET':
            handleGetRequest($path, $localFeedManager);
            break;
            
        case 'POST':
            handlePostRequest($path, $localFeedManager);
            break;
            
        case 'PUT':
            handlePutRequest($path, $localFeedManager);
            break;
            
        case 'DELETE':
            handleDeleteRequest($path, $localFeedManager);
            break;
            
        default:
            sendError(405, 'Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Local Feeds API Error: " . $e->getMessage());
    sendError(500, 'Internal server error', $e->getMessage());
}

/**
 * Handle GET requests
 */
function handleGetRequest($path, $localFeedManager) {
    switch (true) {
        case $path === '' || $path === '/':
            // List all local feeds
            listLocalFeeds($localFeedManager);
            break;
            
        case preg_match('/^\/([a-zA-Z0-9_-]+)$/', $path, $matches):
            // Get specific feed
            getLocalFeed($matches[1], $localFeedManager);
            break;
            
        case preg_match('/^\/([a-zA-Z0-9_-]+)\/items$/', $path, $matches):
            // Get feed items
            getFeedItems($matches[1], $localFeedManager);
            break;
            
        case preg_match('/^\/([a-zA-Z0-9_-]+)\/items\/([a-zA-Z0-9_-]+)$/', $path, $matches):
            // Get specific item
            getFeedItem($matches[1], $matches[2], $localFeedManager);
            break;
            
        default:
            sendError(404, 'Endpoint not found');
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($path, $localFeedManager) {
    $input = getJsonInput();
    
    switch (true) {
        case $path === '' || $path === '/':
            // Create new local feed
            createLocalFeed($input, $localFeedManager);
            break;
            
        case preg_match('/^\/([a-zA-Z0-9_-]+)\/items$/', $path, $matches):
            // Add item to feed
            addFeedItem($matches[1], $input, $localFeedManager);
            break;
            
        case preg_match('/^\/([a-zA-Z0-9_-]+)\/generate$/', $path, $matches):
            // Regenerate Activity Streams feed
            regenerateFeed($matches[1], $localFeedManager);
            break;
            
        default:
            sendError(404, 'Endpoint not found');
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($path, $localFeedManager) {
    $input = getJsonInput();
    
    switch (true) {
        case preg_match('/^\/([a-zA-Z0-9_-]+)$/', $path, $matches):
            // Update feed
            updateLocalFeed($matches[1], $input, $localFeedManager);
            break;
            
        case preg_match('/^\/([a-zA-Z0-9_-]+)\/items\/([a-zA-Z0-9_-]+)$/', $path, $matches):
            // Update feed item
            updateFeedItem($matches[1], $matches[2], $input, $localFeedManager);
            break;
            
        default:
            sendError(404, 'Endpoint not found');
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($path, $localFeedManager) {
    switch (true) {
        case preg_match('/^\/([a-zA-Z0-9_-]+)$/', $path, $matches):
            // Delete feed
            deleteLocalFeed($matches[1], $localFeedManager);
            break;
            
        case preg_match('/^\/([a-zA-Z0-9_-]+)\/items\/([a-zA-Z0-9_-]+)$/', $path, $matches):
            // Delete feed item
            deleteFeedItem($matches[1], $matches[2], $localFeedManager);
            break;
            
        default:
            sendError(404, 'Endpoint not found');
    }
}

/**
 * List all local feeds
 */
function listLocalFeeds($localFeedManager) {
    try {
        $feeds = $localFeedManager->listFeeds();
        
        sendSuccess([
            'feeds' => $feeds,
            'count' => count($feeds),
            'type' => 'local'
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to list local feeds', $e->getMessage());
    }
}

/**
 * Get specific local feed
 */
function getLocalFeed($feedId, $localFeedManager) {
    try {
        $feed = $localFeedManager->getFeed($feedId);
        
        sendSuccess([
            'feed' => $feed,
            'type' => 'local'
        ]);
        
    } catch (Exception $e) {
        sendError(404, 'Local feed not found', $e->getMessage());
    }
}

/**
 * Create new local feed
 */
function createLocalFeed($input, $localFeedManager) {
    try {
        // Validate required fields
        if (!isset($input['name']) || empty(trim($input['name']))) {
            sendError(400, 'Feed name is required');
        }
        
        $feedData = [
            'name' => trim($input['name']),
            'description' => $input['description'] ?? '',
            'author' => $input['author'] ?? [
                'type' => 'Person',
                'name' => $_SESSION['admin_username'] ?? 'Admin'
            ],
            'language' => $input['language'] ?? 'en',
            'published' => $input['published'] ?? true
        ];
        
        $feedId = $localFeedManager->createFeed($feedData);
        $feed = $localFeedManager->getFeed($feedId);
        
        sendSuccess([
            'message' => 'Local feed created successfully',
            'feedId' => $feedId,
            'feed' => $feed
        ], 201);
        
    } catch (Exception $e) {
        sendError(400, 'Failed to create local feed', $e->getMessage());
    }
}

/**
 * Update local feed
 */
function updateLocalFeed($feedId, $input, $localFeedManager) {
    try {
        $success = $localFeedManager->updateFeed($feedId, $input);
        
        if (!$success) {
            sendError(500, 'Failed to update feed');
        }
        
        $feed = $localFeedManager->getFeed($feedId);
        
        sendSuccess([
            'message' => 'Feed updated successfully',
            'feed' => $feed
        ]);
        
    } catch (Exception $e) {
        sendError(400, 'Failed to update feed', $e->getMessage());
    }
}

/**
 * Delete local feed
 */
function deleteLocalFeed($feedId, $localFeedManager) {
    try {
        $success = $localFeedManager->deleteFeed($feedId);
        
        if (!$success) {
            sendError(500, 'Failed to delete feed');
        }
        
        sendSuccess([
            'message' => 'Feed deleted successfully',
            'feedId' => $feedId
        ]);
        
    } catch (Exception $e) {
        sendError(400, 'Failed to delete feed', $e->getMessage());
    }
}

/**
 * Get feed items
 */
function getFeedItems($feedId, $localFeedManager) {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $items = $localFeedManager->getItems($feedId, $limit, $offset);
        
        sendSuccess([
            'feedId' => $feedId,
            'items' => $items,
            'count' => count($items),
            'offset' => $offset,
            'limit' => $limit
        ]);
        
    } catch (Exception $e) {
        sendError(404, 'Failed to get feed items', $e->getMessage());
    }
}

/**
 * Get specific feed item
 */
function getFeedItem($feedId, $itemId, $localFeedManager) {
    try {
        $items = $localFeedManager->getItems($feedId);
        $item = null;
        
        foreach ($items as $feedItem) {
            if ($feedItem['id'] === $itemId) {
                $item = $feedItem;
                break;
            }
        }
        
        if (!$item) {
            sendError(404, 'Feed item not found');
        }
        
        sendSuccess([
            'feedId' => $feedId,
            'item' => $item
        ]);
        
    } catch (Exception $e) {
        sendError(404, 'Failed to get feed item', $e->getMessage());
    }
}

/**
 * Add item to feed
 */
function addFeedItem($feedId, $input, $localFeedManager) {
    try {
        // Validate required fields
        if (!isset($input['type']) || empty($input['type'])) {
            sendError(400, 'Item type is required');
        }
        
        $allowedTypes = ['Article', 'Note', 'Image', 'Video', 'Audio'];
        if (!in_array($input['type'], $allowedTypes)) {
            sendError(400, 'Invalid item type. Allowed: ' . implode(', ', $allowedTypes));
        }
        
        $itemId = $localFeedManager->addItem($feedId, $input);
        
        // Get the created item
        $items = $localFeedManager->getItems($feedId);
        $createdItem = null;
        foreach ($items as $item) {
            if ($item['id'] === $itemId) {
                $createdItem = $item;
                break;
            }
        }
        
        sendSuccess([
            'message' => 'Item added successfully',
            'feedId' => $feedId,
            'itemId' => $itemId,
            'item' => $createdItem
        ], 201);
        
    } catch (Exception $e) {
        sendError(400, 'Failed to add item', $e->getMessage());
    }
}

/**
 * Update feed item
 */
function updateFeedItem($feedId, $itemId, $input, $localFeedManager) {
    try {
        $success = $localFeedManager->updateItem($feedId, $itemId, $input);
        
        if (!$success) {
            sendError(500, 'Failed to update item');
        }
        
        // Get the updated item
        $items = $localFeedManager->getItems($feedId);
        $updatedItem = null;
        foreach ($items as $item) {
            if ($item['id'] === $itemId) {
                $updatedItem = $item;
                break;
            }
        }
        
        sendSuccess([
            'message' => 'Item updated successfully',
            'feedId' => $feedId,
            'itemId' => $itemId,
            'item' => $updatedItem
        ]);
        
    } catch (Exception $e) {
        sendError(400, 'Failed to update item', $e->getMessage());
    }
}

/**
 * Delete feed item
 */
function deleteFeedItem($feedId, $itemId, $localFeedManager) {
    try {
        $success = $localFeedManager->deleteItem($feedId, $itemId);
        
        if (!$success) {
            sendError(500, 'Failed to delete item');
        }
        
        sendSuccess([
            'message' => 'Item deleted successfully',
            'feedId' => $feedId,
            'itemId' => $itemId
        ]);
        
    } catch (Exception $e) {
        sendError(400, 'Failed to delete item', $e->getMessage());
    }
}

/**
 * Regenerate Activity Streams feed
 */
function regenerateFeed($feedId, $localFeedManager) {
    try {
        $success = $localFeedManager->generateActivityStreamsFeed($feedId);
        
        if (!$success) {
            sendError(500, 'Failed to regenerate feed');
        }
        
        sendSuccess([
            'message' => 'Feed regenerated successfully',
            'feedId' => $feedId
        ]);
        
    } catch (Exception $e) {
        sendError(400, 'Failed to regenerate feed', $e->getMessage());
    }
}

/**
 * Get and validate JSON input
 */
function getJsonInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError(400, 'Invalid JSON input: ' . json_last_error_msg());
    }
    
    return $input ?? [];
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