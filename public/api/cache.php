<?php
/**
 * Cache API Endpoint
 * Handles cache management operations for the frontend
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
use AnsyblSite\Core\FeedCache;
use AnsyblSite\Core\FeedParser;

try {
    // Initialize core components
    $fileManager = new ConcurrentFileManager('../../data');
    $configManager = new ConfigManager($fileManager);
    $feedCache = new FeedCache($fileManager);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['PATH_INFO'] ?? '';
    
    // Route requests
    switch ($method) {
        case 'GET':
            handleGetRequest($path, $feedCache, $configManager);
            break;
            
        case 'POST':
            handlePostRequest($path, $feedCache, $configManager);
            break;
            
        case 'DELETE':
            handleDeleteRequest($path, $feedCache, $configManager);
            break;
            
        default:
            sendError(405, 'Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Cache API Error: " . $e->getMessage());
    sendError(500, 'Internal server error', $e->getMessage());
}

/**
 * Handle GET requests
 */
function handleGetRequest($path, $feedCache, $configManager) {
    switch ($path) {
        case '':
        case '/':
            // Get cache overview
            getCacheOverview($feedCache, $configManager);
            break;
            
        case '/stats':
            // Get detailed cache statistics
            getCacheStats($feedCache);
            break;
            
        case '/feeds':
            // Get all cached feeds information
            getCachedFeeds($feedCache, $configManager);
            break;
            
        default:
            if (preg_match('/^\/feeds\/([a-zA-Z0-9_-]+)$/', $path, $matches)) {
                // Get specific cached feed
                getCachedFeed($matches[1], $feedCache);
            } else {
                sendError(404, 'Endpoint not found');
            }
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($path, $feedCache, $configManager) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE && !empty(file_get_contents('php://input'))) {
        sendError(400, 'Invalid JSON input');
    }
    
    switch ($path) {
        case '/cleanup':
            // Clean up expired cache entries
            cleanupCache($feedCache);
            break;
            
        case '/warm':
            // Warm up cache by fetching all feeds
            warmupCache($input, $feedCache, $configManager);
            break;
            
        default:
            sendError(404, 'Endpoint not found');
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($path, $feedCache, $configManager) {
    switch ($path) {
        case '':
        case '/':
            // Clear all cache
            clearAllCache($feedCache);
            break;
            
        case '/expired':
            // Clear only expired cache entries
            clearExpiredCache($feedCache);
            break;
            
        default:
            if (preg_match('/^\/feeds\/([a-zA-Z0-9_-]+)$/', $path, $matches)) {
                // Clear specific feed cache
                clearFeedCache($matches[1], $feedCache);
            } else {
                sendError(404, 'Endpoint not found');
            }
    }
}

/**
 * Get cache overview
 */
function getCacheOverview($feedCache, $configManager) {
    try {
        $stats = $feedCache->getStats();
        $feedsConfig = $configManager->get('feeds');
        $feeds = $feedsConfig['feeds'] ?? [];
        
        // Get cache status for each configured feed
        $feedStatuses = [];
        foreach ($feeds as $feed) {
            if (!$feed['enabled']) continue;
            
            $cached = $feedCache->get($feed['id']);
            $feedStatuses[] = [
                'id' => $feed['id'],
                'name' => $feed['name'],
                'cached' => $cached !== null,
                'cache_age' => $cached ? calculateCacheAge($cached) : null,
                'expired' => $cached ? isCacheExpired($cached) : null
            ];
        }
        
        sendSuccess([
            'overview' => [
                'total_cached_items' => $stats['totalItems'],
                'cache_hits' => $stats['hits'],
                'cache_misses' => $stats['misses'],
                'hit_ratio' => $stats['hitRatio'],
                'total_feeds_configured' => count($feeds),
                'feeds_cached' => count(array_filter($feedStatuses, fn($f) => $f['cached'])),
                'feeds_expired' => count(array_filter($feedStatuses, fn($f) => $f['expired']))
            ],
            'feeds' => $feedStatuses,
            'last_cleanup' => $stats['lastCleanup'] ?? null
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to get cache overview', $e->getMessage());
    }
}

/**
 * Get detailed cache statistics
 */
function getCacheStats($feedCache) {
    try {
        $stats = $feedCache->getStats();
        
        // Add more detailed information
        $detailedStats = $stats;
        $detailedStats['cache_efficiency'] = calculateCacheEfficiency($stats);
        $detailedStats['memory_usage'] = getMemoryUsage();
        $detailedStats['disk_usage'] = getDiskUsage();
        
        sendSuccess([
            'stats' => $detailedStats,
            'generated_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to get cache statistics', $e->getMessage());
    }
}

/**
 * Get all cached feeds information
 */
function getCachedFeeds($feedCache, $configManager) {
    try {
        $feedsConfig = $configManager->get('feeds');
        $feeds = $feedsConfig['feeds'] ?? [];
        
        $cachedFeeds = [];
        
        foreach ($feeds as $feed) {
            $cached = $feedCache->get($feed['id']);
            
            if ($cached) {
                $cachedFeeds[] = [
                    'id' => $feed['id'],
                    'name' => $feed['name'],
                    'url' => $feed['url'],
                    'cached_at' => $cached['cachedAt'] ?? null,
                    'expires_at' => $cached['expiresAt'] ?? null,
                    'age' => calculateCacheAge($cached),
                    'expired' => isCacheExpired($cached),
                    'size' => calculateDataSize($cached),
                    'item_count' => isset($cached['data']) ? count($cached['data']['processedItems'] ?? []) : 0
                ];
            }
        }
        
        sendSuccess([
            'cached_feeds' => $cachedFeeds,
            'total_cached' => count($cachedFeeds),
            'generated_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to get cached feeds', $e->getMessage());
    }
}

/**
 * Get specific cached feed
 */
function getCachedFeed($feedId, $feedCache) {
    try {
        $cached = $feedCache->get($feedId);
        
        if (!$cached) {
            sendError(404, 'Feed not found in cache');
        }
        
        // Return the full cached data including processedItems
        $feedInfo = [
            'id' => $feedId,
            'cached_at' => $cached['cachedAt'] ?? null,
            'expires_at' => $cached['expiresAt'] ?? null,
            'age' => calculateCacheAge($cached),
            'expired' => isCacheExpired($cached),
            'size' => calculateDataSize($cached),
            'item_count' => isset($cached['data']) ? count($cached['data']['processedItems'] ?? []) : 0,
            // Include the full cached data with processedItems
            'data' => $cached['data'] ?? null,
            'data_preview' => getDataPreview($cached)
        ];
        
        sendSuccess([
            'feed' => $feedInfo,
            'retrieved_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to get cached feed', $e->getMessage());
    }
}

/**
 * Clean up expired cache entries
 */
function cleanupCache($feedCache) {
    try {
        $beforeStats = $feedCache->getStats();
        
        $cleanedCount = $feedCache->cleanup();
        
        $afterStats = $feedCache->getStats();
        
        sendSuccess([
            'message' => 'Cache cleanup completed',
            'cleaned_items' => $cleanedCount,
            'before' => [
                'total_items' => $beforeStats['totalItems']
            ],
            'after' => [
                'total_items' => $afterStats['totalItems']
            ],
            'cleaned_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to cleanup cache', $e->getMessage());
    }
}

/**
 * Warm up cache by fetching all feeds
 */
function warmupCache($input, $feedCache, $configManager) {
    try {
        $feedsConfig = $configManager->get('feeds');
        $feeds = $feedsConfig['feeds'] ?? [];
        $activeFeeds = array_filter($feeds, fn($f) => $f['enabled']);
        
        $results = [];
        $successCount = 0;
        
        foreach ($activeFeeds as $feed) {
            try {
                // Actually fetch the feed URL
                $response = file_get_contents($feed['url']);
                if ($response === false) {
                    throw new Exception("Failed to fetch feed from URL: " . $feed['url']);
                }

                // Parse with FeedParser
                $parser = new FeedParser();
                $parsed = $parser->parse($response);
                
                // Process items into flat structure
                $flattened = $parser->flattenItems($parsed, true);
                
                // Create the structure frontend expects
                $feedData = [
                    'id' => $feed['id'],
                    'name' => $feed['name'],
                    'url' => $feed['url'],
                    'fetched_at' => date('c'),
                    'processedItems' => $flattened,
                    'totalItems' => count($flattened),
                    'type' => $parsed['type'] ?? 'Unknown',
                    '@context' => $parsed['@context'] ?? null,
                    'summary' => $parsed['summary'] ?? null
                ];
                
                // Cache the data with TTL
                $ttl = $input['ttl'] ?? 300; // Default 5 minutes
                $feedCache->store($feed['id'], $feedData, $ttl);
                
                $results[] = [
                    'id' => $feed['id'],
                    'name' => $feed['name'],
                    'status' => 'success',
                    'cached_at' => date('c'),
                    'items_processed' => count($flattened)
                ];
                
                $successCount++;
                
            } catch (Exception $e) {
                $results[] = [
                    'id' => $feed['id'],
                    'name' => $feed['name'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        sendSuccess([
            'message' => 'Cache warmup completed',
            'total_feeds' => count($activeFeeds),
            'successful' => $successCount,
            'failed' => count($activeFeeds) - $successCount,
            'results' => $results,
            'warmed_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to warmup cache', $e->getMessage());
    }
}

/**
 * Clear all cache
 */
function clearAllCache($feedCache) {
    try {
        $beforeStats = $feedCache->getStats();
        
        // Clear all cache entries
        $feedCache->clear();
        
        $afterStats = $feedCache->getStats();
        
        sendSuccess([
            'message' => 'All cache cleared successfully',
            'before' => [
                'total_items' => $beforeStats['totalItems']
            ],
            'after' => [
                'total_items' => $afterStats['totalItems']
            ],
            'cleared_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to clear cache', $e->getMessage());
    }
}

/**
 * Clear expired cache entries
 */
function clearExpiredCache($feedCache) {
    try {
        $beforeStats = $feedCache->getStats();
        
        $cleanedCount = $feedCache->cleanup();
        
        $afterStats = $feedCache->getStats();
        
        sendSuccess([
            'message' => 'Expired cache entries cleared successfully',
            'expired_items_cleared' => $cleanedCount,
            'before' => [
                'total_items' => $beforeStats['totalItems']
            ],
            'after' => [
                'total_items' => $afterStats['totalItems']
            ],
            'cleared_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to clear expired cache', $e->getMessage());
    }
}

/**
 * Clear specific feed cache
 */
function clearFeedCache($feedId, $feedCache) {
    try {
        // Check if feed exists in cache
        $cached = $feedCache->get($feedId);
        
        if (!$cached) {
            sendError(404, 'Feed not found in cache');
        }
        
        $feedCache->clearFeed($feedId);
        
        sendSuccess([
            'message' => 'Feed cache cleared successfully',
            'feed_id' => $feedId,
            'cleared_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendError(500, 'Failed to clear feed cache', $e->getMessage());
    }
}

/**
 * Utility functions
 */

function calculateCacheAge($cached) {
    if (!isset($cached['cachedAt'])) {
        return null;
    }
    
    $cachedTime = new DateTime($cached['cachedAt']);
    $now = new DateTime();
    $diff = $now->diff($cachedTime);
    
    if ($diff->days > 0) {
        return $diff->days . ' days ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hours ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minutes ago';
    } else {
        return 'Just now';
    }
}

function isCacheExpired($cached) {
    if (!isset($cached['expiresAt'])) {
        return false;
    }
    
    $expiresAt = new DateTime($cached['expiresAt']);
    $now = new DateTime();
    
    return $now > $expiresAt;
}

function calculateDataSize($data) {
    return strlen(json_encode($data));
}

function getDataPreview($cached) {
    if (!isset($cached['data'])) {
        return null;
    }
    
    $data = $cached['data'];
    
    return [
        'type' => $data['type'] ?? 'Unknown',
        'total_items' => isset($data['processedItems']) ? count($data['processedItems']) : 0,
        'first_item_preview' => isset($data['processedItems'][0]) ? [
            'type' => $data['processedItems'][0]['type'] ?? 'Unknown',
            'name' => $data['processedItems'][0]['name'] ?? null,
            'published' => $data['processedItems'][0]['published'] ?? null
        ] : null
    ];
}

function calculateCacheEfficiency($stats) {
    $totalRequests = $stats['hits'] + $stats['misses'];
    
    if ($totalRequests === 0) {
        return 0;
    }
    
    return round(($stats['hits'] / $totalRequests) * 100, 2);
}

function getMemoryUsage() {
    return [
        'current' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit')
    ];
}

function getDiskUsage() {
    $dataDir = '../data';
    $totalSize = 0;
    $fileCount = 0;
    
    if (is_dir($dataDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dataDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
                $fileCount++;
            }
        }
    }
    
    return [
        'total_size' => $totalSize,
        'file_count' => $fileCount,
        'data_directory' => $dataDir
    ];
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