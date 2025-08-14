<?php
/**
 * Test runner for FeedCache class
 */

require_once 'vendor/autoload.php';

use AnsyblSite\Core\FeedCache;
use AnsyblSite\Core\ConcurrentFileManager;

echo "Running FeedCache Tests...\n";
echo "=========================\n\n";

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
$tempDir = './tests/tmp/cache-test';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

$fileManager = new ConcurrentFileManager($tempDir);
$cache = new FeedCache($fileManager);

// Test 1: Can store feed in cache
test("Can store feed in cache", function() use ($cache) {
    $feedId = 'test-feed';
    $feedData = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Collection',
        'items' => [
            ['type' => 'Note', 'content' => 'Test content']
        ]
    ];
    
    $result = $cache->store($feedId, $feedData);
    
    assert($result === true, 'Store should return true');
    assert($cache->has($feedId) === true, 'Cache should contain stored feed');
});

// Test 2: Can retrieve feed from cache
test("Can retrieve feed from cache", function() use ($cache) {
    $feedId = 'retrieve-test-feed';
    $feedData = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Collection',
        'name' => 'Test Feed',
        'items' => []
    ];
    
    $cache->store($feedId, $feedData);
    $retrieved = $cache->get($feedId);
    
    assert(is_array($retrieved), 'Retrieved data should be array');
    assert($retrieved['data']['name'] === 'Test Feed', 'Name should match');
    assert($retrieved['data']['type'] === 'Collection', 'Type should match');
});

// Test 3: Returns false for missing feed
test("Returns false for missing feed", function() use ($cache) {
    assert($cache->has('non-existent') === false, 'Should return false for missing feed');
    assert($cache->get('non-existent') === null, 'Should return null for missing feed');
});

// Test 4: Can delete feed from cache
test("Can delete feed from cache", function() use ($cache) {
    $feedId = 'delete-test-feed';
    $feedData = ['type' => 'Collection', 'items' => []];
    
    $cache->store($feedId, $feedData);
    assert($cache->has($feedId) === true, 'Feed should exist before deletion');
    
    $result = $cache->delete($feedId);
    assert($result === true, 'Delete should return true');
    assert($cache->has($feedId) === false, 'Feed should not exist after deletion');
});

// Test 5: Stores timestamp with feed
test("Stores timestamp with feed", function() use ($cache) {
    $feedId = 'timestamped-feed';
    $feedData = ['type' => 'Collection'];
    
    $cache->store($feedId, $feedData);
    $cached = $cache->get($feedId);
    
    assert(array_key_exists('cached_at', $cached), 'Should have cached_at timestamp');
    assert(is_int($cached['cached_at']), 'Timestamp should be integer');
    assert($cached['cached_at'] > time() - 10, 'Timestamp should be recent');
});

// Test 6: Checks if feed is expired
test("Checks if feed is expired", function() use ($cache) {
    $feedId = 'expiring-feed';
    $feedData = ['type' => 'Collection'];
    $ttl = 1; // 1 second TTL
    
    $cache->store($feedId, $feedData, $ttl);
    assert($cache->isExpired($feedId) === false, 'Fresh feed should not be expired');
    
    // Wait for expiration
    sleep(2);
    assert($cache->isExpired($feedId) === true, 'Feed should be expired after TTL');
});

// Test 7: Can set custom TTL
test("Can set custom TTL", function() use ($cache) {
    $feedId = 'custom-ttl-feed';
    $feedData = ['type' => 'Collection'];
    $customTTL = 7200; // 2 hours
    
    $cache->store($feedId, $feedData, $customTTL);
    $cached = $cache->get($feedId);
    
    assert($cached['ttl'] === $customTTL, 'TTL should match custom value');
});

// Test 8: GetFresh only returns non-expired feeds
test("GetFresh only returns non-expired feeds", function() use ($cache) {
    $feedId = 'fresh-feed';
    $feedData = ['type' => 'Collection', 'name' => 'Fresh Feed'];
    
    // Store with very short TTL
    $cache->store($feedId, $feedData, 1);
    
    // Should get the feed immediately
    $fresh = $cache->getFresh($feedId);
    assert($fresh !== null, 'Should get fresh feed immediately');
    assert($fresh['name'] === 'Fresh Feed', 'Fresh feed data should match');
    
    // Wait for expiration
    sleep(2);
    
    // Should return null for expired feed
    $expired = $cache->getFresh($feedId);
    assert($expired === null, 'Should return null for expired feed');
});

// Test 9: Can get cache stats
test("Can get cache stats", function() use ($cache) {
    $cache->store('stats-feed1', ['type' => 'Collection']);
    $cache->store('stats-feed2', ['type' => 'Collection']);
    
    $stats = $cache->getStats();
    
    assert(array_key_exists('total_feeds', $stats), 'Stats should contain total_feeds');
    assert(array_key_exists('cache_size', $stats), 'Stats should contain cache_size');
    assert($stats['total_feeds'] >= 2, 'Should have at least 2 feeds');
    assert($stats['cache_size'] > 0, 'Cache size should be greater than 0');
});

// Test 10: Can cleanup expired feeds
test("Can cleanup expired feeds", function() use ($cache) {
    // Store feeds with different TTLs
    $cache->store('cleanup-fresh-feed', ['type' => 'Collection'], 3600);
    $cache->store('cleanup-expired-feed', ['type' => 'Collection'], 1);
    
    // Wait for one to expire
    sleep(2);
    
    assert($cache->has('cleanup-fresh-feed') === true, 'Fresh feed should exist');
    assert($cache->has('cleanup-expired-feed') === true, 'Expired feed should still be in cache');
    assert($cache->isExpired('cleanup-expired-feed') === true, 'Feed should be expired');
    
    // Cleanup expired feeds
    $cleaned = $cache->cleanup();
    
    assert($cleaned === 1, 'Should clean 1 expired feed');
    assert($cache->has('cleanup-fresh-feed') === true, 'Fresh feed should remain');
    assert($cache->has('cleanup-expired-feed') === false, 'Expired feed should be removed');
});

echo "\nTest Results:\n";
echo "============\n";
echo "Passed: {$passedCount}/{$testCount}\n";

if ($passedCount === $testCount) {
    echo "🎉 All FeedCache tests passed!\n";
    exit(0);
} else {
    echo "❌ Some FeedCache tests failed!\n";
    exit(1);
}