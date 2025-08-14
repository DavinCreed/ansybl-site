<?php

namespace AnsyblSite\Core;

class FeedCache 
{
    private ConcurrentFileManager $fileManager;
    private string $cacheDir;
    private int $defaultTTL;
    
    public function __construct(ConcurrentFileManager $fileManager, string $cacheDir = 'cache', int $defaultTTL = 3600)
    {
        $this->fileManager = $fileManager;
        $this->cacheDir = $cacheDir;
        $this->defaultTTL = $defaultTTL;
        
        $this->ensureCacheDirectory();
    }
    
    public function store(string $feedId, array $feedData, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTTL;
        
        $cacheData = [
            'feed_id' => $feedId,
            'cached_at' => time(),
            'ttl' => $ttl,
            'expires_at' => time() + $ttl,
            'data' => $feedData
        ];
        
        $filename = $this->getCacheFilename($feedId);
        return $this->fileManager->write($filename, $cacheData);
    }
    
    public function get(string $feedId): ?array
    {
        $filename = $this->getCacheFilename($feedId);
        
        if (!$this->fileManager->exists($filename)) {
            return null;
        }
        
        try {
            $cacheData = $this->fileManager->read($filename);
            return $cacheData;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function getFresh(string $feedId): ?array
    {
        $cached = $this->get($feedId);
        
        if ($cached === null || $this->isExpired($feedId)) {
            return null;
        }
        
        return $cached['data'] ?? null;
    }
    
    public function has(string $feedId): bool
    {
        $filename = $this->getCacheFilename($feedId);
        return $this->fileManager->exists($filename);
    }
    
    public function isExpired(string $feedId): bool
    {
        $cached = $this->get($feedId);
        
        if ($cached === null) {
            return true;
        }
        
        $expiresAt = $cached['expires_at'] ?? 0;
        return time() > $expiresAt;
    }
    
    public function delete(string $feedId): bool
    {
        $filename = $this->getCacheFilename($feedId);
        
        if (!$this->fileManager->exists($filename)) {
            return true;
        }
        
        return $this->fileManager->delete($filename);
    }
    
    public function clear(): bool
    {
        $feeds = $this->list();
        $success = true;
        
        foreach ($feeds as $feedId) {
            if (!$this->delete($feedId)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    public function cleanup(): int
    {
        $feeds = $this->list();
        $cleaned = 0;
        
        foreach ($feeds as $feedId) {
            if ($this->isExpired($feedId)) {
                if ($this->delete($feedId)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    public function list(): array
    {
        $cacheFiles = glob($this->fileManager->getFilePath($this->cacheDir . '/*.json'));
        $feedIds = [];
        
        if ($cacheFiles === false) {
            return [];
        }
        
        foreach ($cacheFiles as $file) {
            $basename = basename($file, '.json');
            if (str_starts_with($basename, 'feed_')) {
                $feedId = substr($basename, 5); // Remove 'feed_' prefix
                $feedIds[] = $feedId;
            }
        }
        
        return $feedIds;
    }
    
    public function getStats(): array
    {
        $feeds = $this->list();
        $totalSize = 0;
        $expiredCount = 0;
        
        foreach ($feeds as $feedId) {
            $filename = $this->getCacheFilename($feedId);
            $filepath = $this->fileManager->getFilePath($filename);
            
            if (file_exists($filepath)) {
                $totalSize += filesize($filepath);
            }
            
            if ($this->isExpired($feedId)) {
                $expiredCount++;
            }
        }
        
        return [
            'total_feeds' => count($feeds),
            'expired_feeds' => $expiredCount,
            'cache_size' => $totalSize,
            'cache_dir' => $this->cacheDir,
            'default_ttl' => $this->defaultTTL
        ];
    }
    
    public function touch(string $feedId, ?int $newTTL = null): bool
    {
        $cached = $this->get($feedId);
        
        if ($cached === null) {
            return false;
        }
        
        $ttl = $newTTL ?? $this->defaultTTL;
        $cached['cached_at'] = time();
        $cached['ttl'] = $ttl;
        $cached['expires_at'] = time() + $ttl;
        
        $filename = $this->getCacheFilename($feedId);
        return $this->fileManager->write($filename, $cached);
    }
    
    private function getCacheFilename(string $feedId): string
    {
        // Sanitize feed ID for filename
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $feedId);
        return $this->cacheDir . '/feed_' . $sanitized . '.json';
    }
    
    private function ensureCacheDirectory(): void
    {
        $cachePath = $this->fileManager->getFilePath($this->cacheDir);
        
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
    }
}