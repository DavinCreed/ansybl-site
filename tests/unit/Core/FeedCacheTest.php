<?php

namespace AnsyblSite\Tests\Unit\Core;

use AnsyblSite\Tests\TestCase;
use AnsyblSite\Core\FeedCache;
use AnsyblSite\Core\ConcurrentFileManager;

class FeedCacheTest extends TestCase
{
    private FeedCache $cache;
    private ConcurrentFileManager $fileManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->fileManager = new ConcurrentFileManager($this->tempPath);
        $this->cache = new FeedCache($this->fileManager);
    }
    
    public function testCanStoreFeedInCache(): void
    {
        $feedId = 'test-feed';
        $feedData = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Collection',
            'items' => [
                ['type' => 'Note', 'content' => 'Test content']
            ]
        ];
        
        $result = $this->cache->store($feedId, $feedData);
        
        $this->assertTrue($result);
        $this->assertTrue($this->cache->has($feedId));
    }
    
    public function testCanRetrieveFeedFromCache(): void
    {
        $feedId = 'test-feed';
        $feedData = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Collection',
            'name' => 'Test Feed',
            'items' => []
        ];
        
        $this->cache->store($feedId, $feedData);
        $retrieved = $this->cache->get($feedId);
        
        $this->assertEquals($feedData['name'], $retrieved['name']);
        $this->assertEquals($feedData['type'], $retrieved['type']);
    }
    
    public function testReturnsFalseForMissingFeed(): void
    {
        $this->assertFalse($this->cache->has('non-existent'));
        $this->assertNull($this->cache->get('non-existent'));
    }
    
    public function testCanDeleteFeedFromCache(): void
    {
        $feedId = 'test-feed';
        $feedData = ['type' => 'Collection', 'items' => []];
        
        $this->cache->store($feedId, $feedData);
        $this->assertTrue($this->cache->has($feedId));
        
        $result = $this->cache->delete($feedId);
        $this->assertTrue($result);
        $this->assertFalse($this->cache->has($feedId));
    }
    
    public function testCanClearAllCache(): void
    {
        $this->cache->store('feed1', ['type' => 'Collection']);
        $this->cache->store('feed2', ['type' => 'Collection']);
        
        $this->assertTrue($this->cache->has('feed1'));
        $this->assertTrue($this->cache->has('feed2'));
        
        $result = $this->cache->clear();
        $this->assertTrue($result);
        
        $this->assertFalse($this->cache->has('feed1'));
        $this->assertFalse($this->cache->has('feed2'));
    }
    
    public function testStoresTimestampWithFeed(): void
    {
        $feedId = 'timestamped-feed';
        $feedData = ['type' => 'Collection'];
        
        $this->cache->store($feedId, $feedData);
        $cached = $this->cache->get($feedId);
        
        $this->assertArrayHasKey('cached_at', $cached);
        $this->assertIsInt($cached['cached_at']);
        $this->assertGreaterThan(time() - 10, $cached['cached_at']);
    }
    
    public function testChecksIfFeedIsExpired(): void
    {
        $feedId = 'expiring-feed';
        $feedData = ['type' => 'Collection'];
        $ttl = 1; // 1 second TTL
        
        $this->cache->store($feedId, $feedData, $ttl);
        $this->assertFalse($this->cache->isExpired($feedId));
        
        // Wait for expiration
        sleep(2);
        $this->assertTrue($this->cache->isExpired($feedId));
    }
    
    public function testCanSetCustomTTL(): void
    {
        $feedId = 'custom-ttl-feed';
        $feedData = ['type' => 'Collection'];
        $customTTL = 7200; // 2 hours
        
        $this->cache->store($feedId, $feedData, $customTTL);
        $cached = $this->cache->get($feedId);
        
        $this->assertEquals($customTTL, $cached['ttl']);
    }
    
    public function testGetFreshOnlyReturnsNonExpiredFeeds(): void
    {
        $feedId = 'fresh-feed';
        $feedData = ['type' => 'Collection', 'name' => 'Fresh Feed'];
        
        // Store with very short TTL
        $this->cache->store($feedId, $feedData, 1);
        
        // Should get the feed immediately
        $fresh = $this->cache->getFresh($feedId);
        $this->assertNotNull($fresh);
        $this->assertEquals('Fresh Feed', $fresh['name']);
        
        // Wait for expiration
        sleep(2);
        
        // Should return null for expired feed
        $expired = $this->cache->getFresh($feedId);
        $this->assertNull($expired);
    }
    
    public function testCanGetCacheStats(): void
    {
        $this->cache->store('feed1', ['type' => 'Collection']);
        $this->cache->store('feed2', ['type' => 'Collection']);
        
        $stats = $this->cache->getStats();
        
        $this->assertArrayHasKey('total_feeds', $stats);
        $this->assertArrayHasKey('cache_size', $stats);
        $this->assertEquals(2, $stats['total_feeds']);
        $this->assertGreaterThan(0, $stats['cache_size']);
    }
    
    public function testCanListCachedFeeds(): void
    {
        $this->cache->store('feed1', ['type' => 'Collection', 'name' => 'Feed 1']);
        $this->cache->store('feed2', ['type' => 'Collection', 'name' => 'Feed 2']);
        
        $feeds = $this->cache->list();
        
        $this->assertCount(2, $feeds);
        $this->assertContains('feed1', $feeds);
        $this->assertContains('feed2', $feeds);
    }
    
    public function testCanCleanupExpiredFeeds(): void
    {
        // Store feeds with different TTLs
        $this->cache->store('fresh-feed', ['type' => 'Collection'], 3600);
        $this->cache->store('expired-feed', ['type' => 'Collection'], 1);
        
        // Wait for one to expire
        sleep(2);
        
        $this->assertTrue($this->cache->has('fresh-feed'));
        $this->assertTrue($this->cache->has('expired-feed')); // Still in cache
        $this->assertTrue($this->cache->isExpired('expired-feed')); // But expired
        
        // Cleanup expired feeds
        $cleaned = $this->cache->cleanup();
        
        $this->assertEquals(1, $cleaned); // Should clean 1 feed
        $this->assertTrue($this->cache->has('fresh-feed'));
        $this->assertFalse($this->cache->has('expired-feed')); // Should be removed
    }
}