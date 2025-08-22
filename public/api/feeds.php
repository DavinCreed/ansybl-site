<?php
/**
 * Feeds API Endpoint
 * Handles feed management operations for the frontend
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
use AnsyblSite\Core\FeedParser;
use AnsyblSite\Core\FeedCache;

try {
    error_log("feeds.php: Starting initialization");
    // Initialize core components
    $fileManager = new ConcurrentFileManager('../../data');
    error_log("feeds.php: FileManager created");
    $configManager = new ConfigManager($fileManager);
    error_log("feeds.php: ConfigManager created");
    $feedParser = new FeedParser();
    error_log("feeds.php: FeedParser created");
    $feedCache = new FeedCache($fileManager);
    error_log("feeds.php: FeedCache created");
    
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['PATH_INFO'] ?? '';
    
    // Route requests
    switch ($method) {
        case 'GET':
            handleGetRequest($path, $configManager, $feedParser, $feedCache);
            break;
            
        case 'POST':
            handlePostRequest($path, $configManager, $feedParser, $feedCache);
            break;
            
        case 'PUT':
            handlePutRequest($path, $configManager, $feedParser, $feedCache);
            break;
            
        case 'DELETE':
            handleDeleteRequest($path, $configManager, $feedParser, $feedCache);
            break;
            
        default:
            sendError(405, 'Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Feeds API Error: " . $e->getMessage());
    sendError(500, 'Internal server error', $e->getMessage());
}

/**
 * Handle GET requests
 */
function handleGetRequest($path, $configManager, $feedParser, $feedCache) {
    switch ($path) {
        case '':
        case '/':
            // Get all feeds configuration
            getFeedsConfig($configManager);
            break;
            
        case '/data':
            // Get aggregated feed data
            getFeedsData($configManager, $feedParser, $feedCache);
            break;
            
        case '/stats':
            // Get feed statistics
            getFeedsStats($configManager, $feedCache);
            break;
            
        default:
            // Get specific feed by ID
            if (preg_match('/^\/(\w+)$/', $path, $matches)) {
                getFeed($matches[1], $configManager, $feedParser, $feedCache);
            } else {
                sendError(404, 'Endpoint not found');
            }
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($path, $configManager, $feedParser, $feedCache) {
    $inputBody = file_get_contents('php://input');
    $input = null;
    
    // Only try to parse JSON if there's actually content
    if (!empty($inputBody)) {
        $input = json_decode($inputBody, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError(400, 'Invalid JSON input');
        }
    }
    
    switch ($path) {
        case '':
        case '/':
            // Add new feed
            addFeed($input, $configManager);
            break;
            
        case '/test':
            // Test feed URL
            testFeed($input, $feedParser);
            break;
            
        case '/refresh':
            // Refresh all feeds
            error_log("Refresh all feeds endpoint called");
            refreshAllFeeds($configManager, $feedParser, $feedCache);
            break;
            
        default:
            if (preg_match('/^\/(\w+)\/refresh$/', $path, $matches)) {
                // Refresh specific feed
                refreshFeed($matches[1], $configManager, $feedParser, $feedCache);
            } else {
                sendError(404, 'Endpoint not found');
            }
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($path, $configManager, $feedParser, $feedCache) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError(400, 'Invalid JSON input');
    }
    
    if (preg_match('/^\/(\w+)$/', $path, $matches)) {
        // Update specific feed
        updateFeed($matches[1], $input, $configManager);
    } else {
        sendError(404, 'Endpoint not found');
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($path, $configManager, $feedParser, $feedCache) {
    if (preg_match('/^\/(\w+)$/', $path, $matches)) {
        // Delete specific feed
        deleteFeed($matches[1], $configManager, $feedCache);
    } else {
        sendError(404, 'Endpoint not found');
    }
}

/**
 * Get feeds configuration
 */
function getFeedsConfig($configManager) {
    try {
        $feedsConfig = $configManager->get('feeds');
        
        sendSuccess([
            'feeds' => $feedsConfig['feeds'] ?? [],
            'meta' => $feedsConfig['meta'] ?? []
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to get feeds configuration', $e->getMessage());
    }
}

/**
 * Get aggregated feed data
 */
function getFeedsData($configManager, $feedParser, $feedCache) {
    try {
        $feedsConfig = $configManager->get('feeds');
        $feeds = $feedsConfig['feeds'] ?? [];
        
        $allItems = [];
        $feedStats = [];
        
        foreach ($feeds as $feed) {
            if (!$feed['enabled']) continue;
            
            try {
                // Try to get from cache first
                $cachedData = $feedCache->get($feed['id']);
                
                if ($cachedData) {
                    $feedData = $cachedData;
                } else {
                    // Fetch fresh data
                    $feedData = fetchFeedData($feed['url'], $feedParser);
                    
                    // Cache the data
                    $feedCache->store($feed['id'], $feedData, 300); // 5 minutes TTL
                }
                
                // Extract items from feed
                $items = $feedParser->extractItems($feedData);
                
                // Add feed metadata to each item
                foreach ($items as &$item) {
                    $item['feedId'] = $feed['id'];
                    $item['feedName'] = $feed['name'];
                    $item['feedUrl'] = $feed['url'];
                }
                
                $allItems = array_merge($allItems, $items);
                
                $feedStats[] = [
                    'id' => $feed['id'],
                    'name' => $feed['name'],
                    'itemCount' => count($items),
                    'lastFetched' => date('c'),
                    'status' => 'success'
                ];
                
            } catch (Exception $e) {
                error_log("Failed to fetch feed {$feed['id']}: " . $e->getMessage());
                
                $feedStats[] = [
                    'id' => $feed['id'],
                    'name' => $feed['name'],
                    'itemCount' => 0,
                    'lastFetched' => null,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Sort items by published date (newest first)
        usort($allItems, function($a, $b) {
            $dateA = new DateTime($a['published'] ?? 'now');
            $dateB = new DateTime($b['published'] ?? 'now');
            return $dateB <=> $dateA;
        });
        
        sendSuccess([
            'items' => $allItems,
            'totalItems' => count($allItems),
            'feedStats' => $feedStats,
            'lastUpdated' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to get feeds data', $e->getMessage());
    }
}

/**
 * Get feed statistics
 */
function getFeedsStats($configManager, $feedCache) {
    try {
        $feedsConfig = $configManager->get('feeds');
        $feeds = $feedsConfig['feeds'] ?? [];
        
        $stats = [
            'totalFeeds' => count($feeds),
            'activeFeeds' => count(array_filter($feeds, fn($f) => $f['enabled'])),
            'cacheStats' => $feedCache->getStats(),
            'lastUpdated' => date('c')
        ];
        
        sendSuccess($stats);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to get feed statistics', $e->getMessage());
    }
}

/**
 * Get specific feed
 */
function getFeed($feedId, $configManager, $feedParser, $feedCache) {
    try {
        $feedsConfig = $configManager->get('feeds');
        $feeds = $feedsConfig['feeds'] ?? [];
        
        $feed = null;
        foreach ($feeds as $f) {
            if ($f['id'] === $feedId) {
                $feed = $f;
                break;
            }
        }
        
        if (!$feed) {
            sendError(404, 'Feed not found');
        }
        
        // Get feed data
        $cachedData = $feedCache->get($feedId);
        
        if ($cachedData) {
            $feedData = $cachedData;
        } else {
            $feedData = fetchFeedData($feed['url'], $feedParser);
            $feedCache->store($feedId, $feedData, 300);
        }
        
        // Handle cached data structure vs raw feed data
        $actualFeedData = isset($feedData['data']) ? $feedData['data'] : $feedData;
        $items = $feedParser->extractItems($actualFeedData);
        
        sendSuccess([
            'feed' => $feed,
            'data' => $feedData,
            'items' => $items,
            'itemCount' => count($items),
            'lastFetched' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to get feed', $e->getMessage());
    }
}

/**
 * Add new feed
 */
function addFeed($input, $configManager) {
    try {
        // Validate input
        if (!isset($input['url']) || !isset($input['name'])) {
            sendError(400, 'Missing required fields: url, name');
        }
        
        // Validate URL
        if (!filter_var($input['url'], FILTER_VALIDATE_URL)) {
            sendError(400, 'Invalid URL format');
        }
        
        $feedsConfig = $configManager->get('feeds');
        $feeds = $feedsConfig['feeds'] ?? [];
        
        // Generate unique ID
        $feedId = generateFeedId($input['name'], $feeds);
        
        // Create new feed entry
        $newFeed = [
            'id' => $feedId,
            'name' => trim($input['name']),
            'url' => trim($input['url']),
            'enabled' => $input['enabled'] ?? true,
            'order' => $input['order'] ?? (count($feeds) + 1),
            'added' => date('c'),
            'lastFetched' => null
        ];
        
        // Add to feeds array
        $feeds[] = $newFeed;
        
        // Update metadata
        $feedsConfig['feeds'] = $feeds;
        $feedsConfig['meta']['total_feeds'] = count($feeds);
        $feedsConfig['meta']['active_feeds'] = count(array_filter($feeds, fn($f) => $f['enabled']));
        $feedsConfig['meta']['last_updated'] = date('c');
        
        // Save configuration
        $configManager->set('feeds', $feedsConfig);
        
        sendSuccess([
            'message' => 'Feed added successfully',
            'feed' => $newFeed
        ], 201);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to add feed', $e->getMessage());
    }
}

/**
 * Update existing feed
 */
function updateFeed($feedId, $input, $configManager) {
    try {
        $feedsConfig = $configManager->get('feeds');
        $feeds = $feedsConfig['feeds'] ?? [];
        
        $feedIndex = null;
        foreach ($feeds as $index => $feed) {
            if ($feed['id'] === $feedId) {
                $feedIndex = $index;
                break;
            }
        }
        
        if ($feedIndex === null) {
            sendError(404, 'Feed not found');
        }
        
        // Update feed properties
        $feed = $feeds[$feedIndex];
        
        if (isset($input['name'])) {
            $feed['name'] = trim($input['name']);
        }
        
        if (isset($input['url'])) {
            if (!filter_var($input['url'], FILTER_VALIDATE_URL)) {
                sendError(400, 'Invalid URL format');
            }
            $feed['url'] = trim($input['url']);
        }
        
        if (isset($input['enabled'])) {
            $feed['enabled'] = (bool) $input['enabled'];
        }
        
        if (isset($input['order'])) {
            $feed['order'] = (int) $input['order'];
        }
        
        $feed['modified'] = date('c');
        
        $feeds[$feedIndex] = $feed;
        
        // Update metadata
        $feedsConfig['feeds'] = $feeds;
        $feedsConfig['meta']['active_feeds'] = count(array_filter($feeds, fn($f) => $f['enabled']));
        $feedsConfig['meta']['last_updated'] = date('c');
        
        // Save configuration
        $configManager->set('feeds', $feedsConfig);
        
        sendSuccess([
            'message' => 'Feed updated successfully',
            'feed' => $feed
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to update feed', $e->getMessage());
    }
}

/**
 * Delete feed
 */
function deleteFeed($feedId, $configManager, $feedCache) {
    try {
        $feedsConfig = $configManager->get('feeds');
        $feeds = $feedsConfig['feeds'] ?? [];
        
        $feedIndex = null;
        foreach ($feeds as $index => $feed) {
            if ($feed['id'] === $feedId) {
                $feedIndex = $index;
                break;
            }
        }
        
        if ($feedIndex === null) {
            sendError(404, 'Feed not found');
        }
        
        // Remove feed from array
        array_splice($feeds, $feedIndex, 1);
        
        // Update metadata
        $feedsConfig['feeds'] = $feeds;
        $feedsConfig['meta']['total_feeds'] = count($feeds);
        $feedsConfig['meta']['active_feeds'] = count(array_filter($feeds, fn($f) => $f['enabled']));
        $feedsConfig['meta']['last_updated'] = date('c');
        
        // Save configuration
        $configManager->set('feeds', $feedsConfig);
        
        // Clear cache for this feed
        $feedCache->delete($feedId);
        
        sendSuccess(['message' => 'Feed deleted successfully']);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to delete feed', $e->getMessage());
    }
}

/**
 * Test feed URL
 */
function testFeed($input, $feedParser) {
    try {
        if (!isset($input['url'])) {
            sendError(400, 'Missing required field: url');
        }
        
        if (!filter_var($input['url'], FILTER_VALIDATE_URL)) {
            sendError(400, 'Invalid URL format');
        }
        
        // Attempt to fetch and parse the feed
        $feedData = fetchFeedData($input['url'], $feedParser);
        $metadata = $feedParser->extractMetadata($feedData);
        $items = $feedParser->extractItems($feedData);
        
        sendSuccess([
            'message' => 'Feed test successful',
            'url' => $input['url'],
            'metadata' => $metadata,
            'itemCount' => count($items),
            'sampleItems' => array_slice($items, 0, 3) // First 3 items as preview
        ]);
        
    } catch (Exception $e) {
        sendError(400, 'Feed test failed', $e->getMessage());
    }
}

/**
 * Refresh all feeds
 */
function refreshAllFeeds($configManager, $feedParser, $feedCache) {
    try {
        $feedsConfig = $configManager->get('feeds');
        $feeds = $feedsConfig['feeds'] ?? [];
        
        $results = [];
        
        foreach ($feeds as $feed) {
            if (!$feed['enabled']) continue;
            
            try {
                // Clear cache for this feed
                $feedCache->delete($feed['id']);
                
                // Fetch fresh data
                $feedData = fetchFeedData($feed['url'], $feedParser);
                $feedCache->store($feed['id'], $feedData, 300);
                
                $items = $feedParser->extractItems($feedData);
                
                $results[] = [
                    'id' => $feed['id'],
                    'name' => $feed['name'],
                    'status' => 'success',
                    'itemCount' => count($items),
                    'lastFetched' => date('c')
                ];
                
            } catch (Exception $e) {
                $results[] = [
                    'id' => $feed['id'],
                    'name' => $feed['name'],
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'lastFetched' => null
                ];
            }
        }
        
        sendSuccess([
            'message' => 'Feeds refresh completed',
            'results' => $results,
            'totalFeeds' => count($feeds),
            'successCount' => count(array_filter($results, fn($r) => $r['status'] === 'success'))
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to refresh feeds', $e->getMessage());
    }
}

/**
 * Refresh specific feed
 */
function refreshFeed($feedId, $configManager, $feedParser, $feedCache) {
    try {
        $feedsConfig = $configManager->get('feeds');
        $feeds = $feedsConfig['feeds'] ?? [];
        
        $feed = null;
        foreach ($feeds as $f) {
            if ($f['id'] === $feedId) {
                $feed = $f;
                break;
            }
        }
        
        if (!$feed) {
            sendError(404, 'Feed not found');
        }
        
        // Clear cache and fetch fresh data
        $feedCache->delete($feedId);
        $feedData = fetchFeedData($feed['url'], $feedParser);
        $feedCache->store($feedId, $feedData, 300);
        
        $items = $feedParser->extractItems($feedData);
        
        sendSuccess([
            'message' => 'Feed refreshed successfully',
            'feed' => $feed,
            'itemCount' => count($items),
            'lastFetched' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to refresh feed', $e->getMessage());
    }
}

/**
 * Utility functions
 */

function fetchFeedData($url, $feedParser) {
    error_log("fetchFeedData: Starting fetch for URL: $url");
    
    try {
        // Try cURL first (more reliable for HTTPS)
        if (function_exists('curl_init')) {
            error_log("fetchFeedData: Using cURL for HTTP request");
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_USERAGENT => 'Ansybl Site Feed Reader 1.0',
                CURLOPT_HTTPHEADER => [
                    'Accept: application/activity+json,application/ld+json,application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($data === false || !empty($curlError)) {
                error_log("fetchFeedData: cURL failed. Error: $curlError, HTTP Code: $httpCode");
                throw new Exception("Failed to fetch feed with cURL: $curlError");
            }
            
            if ($httpCode < 200 || $httpCode >= 300) {
                error_log("fetchFeedData: HTTP error. Code: $httpCode");
                throw new Exception("HTTP error $httpCode when fetching feed from $url");
            }
            
            error_log("fetchFeedData: cURL success - " . strlen($data) . " bytes received");
        } else {
            // Fallback to file_get_contents with improved context
            error_log("fetchFeedData: cURL not available, using file_get_contents");
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Ansybl Site Feed Reader 1.0',
                    'header' => 'Accept: application/activity+json,application/ld+json,application/json',
                    'follow_location' => 1,
                    'max_redirects' => 5
                ],
                'https' => [
                    'timeout' => 30,
                    'user_agent' => 'Ansybl Site Feed Reader 1.0',
                    'header' => 'Accept: application/activity+json,application/ld+json,application/json',
                    'follow_location' => 1,
                    'max_redirects' => 5,
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);
            
            $data = file_get_contents($url, false, $context);
            
            if ($data === false) {
                $error = error_get_last();
                error_log("fetchFeedData: file_get_contents failed. Last error: " . json_encode($error));
                throw new Exception("Failed to fetch feed from URL: $url. Error: " . ($error['message'] ?? 'Unknown error'));
            }
            
            error_log("fetchFeedData: file_get_contents success - " . strlen($data) . " bytes received");
        }
        
        error_log("fetchFeedData: Attempting to decode JSON");
        $json = json_decode($data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("fetchFeedData: JSON decode failed: " . json_last_error_msg());
            error_log("fetchFeedData: First 500 chars of data: " . substr($data, 0, 500));
            throw new Exception("Invalid JSON in feed from $url: " . json_last_error_msg());
        }
        
        error_log("fetchFeedData: JSON decoded successfully, calling feedParser->parse");
        $result = $feedParser->parse($data); // Pass raw JSON string, not decoded array
        error_log("fetchFeedData: feedParser->parse completed successfully");
        
        return $result;
        
    } catch (Exception $e) {
        error_log("fetchFeedData: Exception caught: " . $e->getMessage());
        throw $e;
    }
}

function generateFeedId($name, $existingFeeds) {
    $baseId = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    $baseId = substr($baseId, 0, 20); // Limit length
    
    if (empty($baseId)) {
        $baseId = 'feed';
    }
    
    $id = $baseId;
    $counter = 1;
    
    while (feedIdExists($id, $existingFeeds)) {
        $id = $baseId . $counter;
        $counter++;
    }
    
    return $id;
}

function feedIdExists($id, $feeds) {
    foreach ($feeds as $feed) {
        if ($feed['id'] === $id) {
            return true;
        }
    }
    return false;
}

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