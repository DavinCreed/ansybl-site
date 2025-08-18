<?php

namespace AnsyblSite\Core;

use AnsyblSite\Exceptions\FileNotFoundException;
use AnsyblSite\Exceptions\FileSystemException;
use AnsyblSite\Exceptions\InvalidJsonException;
use AnsyblSite\Exceptions\ValidationException;

class LocalFeedManager 
{
    private ConcurrentFileManager $fileManager;
    private SchemaValidator $validator;
    private string $feedsDataPath;
    private string $feedsPublicPath;
    private string $uploadsPath;
    
    public function __construct(ConcurrentFileManager $fileManager, ?SchemaValidator $validator = null)
    {
        $this->fileManager = $fileManager;
        $this->validator = $validator ?? new SchemaValidator();
        
        // Use paths relative to the FileManager's data directory
        $this->feedsDataPath = 'local-feeds';
        $this->feedsPublicPath = '../public/feeds';
        $this->uploadsPath = '../public/uploads/feeds';
        
        $this->registerLocalFeedSchemas();
    }
    
    /**
     * Create a new local feed
     */
    public function createFeed(array $feedData): string
    {
        // Validate feed data
        if (!$this->validateFeedData($feedData)) {
            throw new ValidationException('Invalid feed data: ' . implode(', ', $this->validator->getErrorMessages()));
        }
        
        // Generate unique feed ID
        $feedId = $this->generateFeedId($feedData['name']);
        
        // Prepare feed metadata
        $metadata = [
            'id' => $feedId,
            'name' => $feedData['name'],
            'description' => $feedData['description'] ?? '',
            'author' => $feedData['author'] ?? [
                'type' => 'Person',
                'name' => 'Admin'
            ],
            'language' => $feedData['language'] ?? 'en',
            'created' => date('c'),
            'updated' => date('c'),
            'published' => $feedData['published'] ?? true,
            'url' => $this->generateFeedUrl($feedId),
            'items' => [],
            'totalItems' => 0,
            'meta' => [
                'version' => '1.0',
                'schema_version' => '1.0',
                'created' => date('c')
            ]
        ];
        
        // Save feed metadata
        $metadataFile = "{$feedId}.json";
        if (!$this->fileManager->atomicWrite($this->feedsDataPath . '/' . $metadataFile, $metadata)) {
            throw new FileSystemException("Failed to create feed metadata for: {$feedId}");
        }
        
        // Generate and save initial Activity Streams feed
        $this->generateActivityStreamsFeed($feedId);
        
        // Create upload directory for this feed
        $this->createFeedUploadDirectory($feedId);
        
        return $feedId;
    }
    
    /**
     * Get local feed metadata
     */
    public function getFeed(string $feedId): array
    {
        $metadataFile = "{$feedId}.json";
        $filePath = $this->feedsDataPath . '/' . $metadataFile;
        
        if (!$this->fileManager->exists($filePath)) {
            throw new FileNotFoundException("Local feed not found: {$feedId}");
        }
        
        return $this->fileManager->read($filePath);
    }
    
    /**
     * Update local feed metadata
     */
    public function updateFeed(string $feedId, array $updateData): bool
    {
        return $this->fileManager->transactionalUpdate(
            $this->feedsDataPath . "/{$feedId}.json",
            function($feedData) use ($updateData) {
                // Update allowed fields
                $allowedFields = ['name', 'description', 'author', 'language', 'published'];
                foreach ($allowedFields as $field) {
                    if (isset($updateData[$field])) {
                        $feedData[$field] = $updateData[$field];
                    }
                }
                
                $feedData['updated'] = date('c');
                
                return $feedData;
            }
        );
    }
    
    /**
     * Delete local feed
     */
    public function deleteFeed(string $feedId): bool
    {
        try {
            // Delete metadata
            $metadataFile = $this->feedsDataPath . "/{$feedId}.json";
            if ($this->fileManager->exists($metadataFile)) {
                $this->fileManager->delete($metadataFile);
            }
            
            // Delete public feed file
            $publicFile = $this->feedsPublicPath . "/{$feedId}.ansybl";
            if (file_exists($publicFile)) {
                unlink($publicFile);
            }
            
            // Delete upload directory
            $uploadDir = $this->uploadsPath . "/{$feedId}";
            if (is_dir($uploadDir)) {
                $this->removeDirectory($uploadDir);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Error deleting feed {$feedId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * List all local feeds
     */
    public function listFeeds(): array
    {
        $feedFiles = glob($this->fileManager->getFilePath($this->feedsDataPath . '/*.json'));
        $feeds = [];
        
        if ($feedFiles === false) {
            return [];
        }
        
        foreach ($feedFiles as $file) {
            $feedId = basename($file, '.json');
            try {
                $feedData = $this->getFeed($feedId);
                $feeds[] = [
                    'id' => $feedId,
                    'name' => $feedData['name'],
                    'description' => $feedData['description'] ?? '',
                    'totalItems' => $feedData['totalItems'] ?? 0,
                    'published' => $feedData['published'] ?? false,
                    'created' => $feedData['created'] ?? null,
                    'updated' => $feedData['updated'] ?? null,
                    'url' => $feedData['url'] ?? null
                ];
            } catch (\Exception $e) {
                error_log("Error reading feed {$feedId}: " . $e->getMessage());
            }
        }
        
        return $feeds;
    }
    
    /**
     * Add item to local feed
     */
    public function addItem(string $feedId, array $itemData): string
    {
        // Validate item data
        if (!$this->validateItemData($itemData)) {
            throw new ValidationException('Invalid item data: ' . implode(', ', $this->validator->getErrorMessages()));
        }
        
        // Generate unique item ID
        $itemId = $this->generateItemId($feedId);
        
        // Prepare item - handle both simple items and Collections
        $item = [
            'id' => $itemId,
            'type' => $itemData['type'],
            'name' => $itemData['name'] ?? '',
            'content' => $itemData['content'] ?? '',
            'summary' => $itemData['summary'] ?? '',
            'published' => $itemData['published'] ?? date('c'),
            'updated' => date('c'),
            'url' => $itemData['url'] ?? null,
            'mediaType' => $itemData['mediaType'] ?? null,
            'duration' => $itemData['duration'] ?? null,
            'attachment' => $itemData['attachment'] ?? [],
            'tag' => $itemData['tag'] ?? []
        ];
        
        // Add Collection-specific fields if this is a Collection
        if ($itemData['type'] === 'Collection') {
            $item['items'] = $itemData['items'] ?? [];
            $item['totalItems'] = $itemData['totalItems'] ?? count($item['items']);
        }
        
        // Remove null values
        $item = array_filter($item, fn($value) => $value !== null);
        
        // Update feed with new item
        $success = $this->fileManager->transactionalUpdate(
            $this->feedsDataPath . "/{$feedId}.json",
            function($feedData) use ($item) {
                $feedData['items'][] = $item;
                $feedData['totalItems'] = count($feedData['items']);
                $feedData['updated'] = date('c');
                return $feedData;
            }
        );
        
        if (!$success) {
            throw new FileSystemException("Failed to add item to feed: {$feedId}");
        }
        
        // Regenerate Activity Streams feed
        $this->generateActivityStreamsFeed($feedId);
        
        return $itemId;
    }
    
    /**
     * Update item in local feed
     */
    public function updateItem(string $feedId, string $itemId, array $updateData): bool
    {
        $success = $this->fileManager->transactionalUpdate(
            $this->feedsDataPath . "/{$feedId}.json",
            function($feedData) use ($itemId, $updateData) {
                foreach ($feedData['items'] as &$item) {
                    if ($item['id'] === $itemId) {
                        // Update allowed fields - including Collection-specific fields
                        $allowedFields = ['type', 'name', 'content', 'summary', 'published', 'url', 'mediaType', 'duration', 'attachment', 'tag', 'items', 'totalItems'];
                        foreach ($allowedFields as $field) {
                            if (isset($updateData[$field])) {
                                $item[$field] = $updateData[$field];
                            }
                        }
                        $item['updated'] = date('c');
                        break;
                    }
                }
                $feedData['updated'] = date('c');
                return $feedData;
            }
        );
        
        if ($success) {
            // Regenerate Activity Streams feed
            $this->generateActivityStreamsFeed($feedId);
        }
        
        return $success;
    }
    
    /**
     * Delete item from local feed
     */
    public function deleteItem(string $feedId, string $itemId): bool
    {
        $success = $this->fileManager->transactionalUpdate(
            $this->feedsDataPath . "/{$feedId}.json",
            function($feedData) use ($itemId) {
                $feedData['items'] = array_filter(
                    $feedData['items'], 
                    fn($item) => $item['id'] !== $itemId
                );
                $feedData['items'] = array_values($feedData['items']); // Reindex
                $feedData['totalItems'] = count($feedData['items']);
                $feedData['updated'] = date('c');
                return $feedData;
            }
        );
        
        if ($success) {
            // Regenerate Activity Streams feed
            $this->generateActivityStreamsFeed($feedId);
        }
        
        return $success;
    }
    
    /**
     * Get items from local feed
     */
    public function getItems(string $feedId, int $limit = null, int $offset = 0): array
    {
        $feedData = $this->getFeed($feedId);
        $items = $feedData['items'] ?? [];
        
        if ($limit !== null) {
            return array_slice($items, $offset, $limit);
        }
        
        return array_slice($items, $offset);
    }
    
    /**
     * Generate Activity Streams 2.0 feed file
     */
    public function generateActivityStreamsFeed(string $feedId): bool
    {
        try {
            $feedData = $this->getFeed($feedId);
            
            // Build Activity Streams 2.0 structure
            $activityStream = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'type' => 'Collection',
                'id' => $feedData['url'],
                'name' => $feedData['name'],
                'summary' => $feedData['description'],
                'totalItems' => $feedData['totalItems'],
                'published' => $feedData['created'],
                'updated' => $feedData['updated'],
                'attributedTo' => $feedData['author'],
                'items' => $feedData['items'] ?? []
            ];
            
            // Remove null values
            $activityStream = array_filter($activityStream, fn($value) => $value !== null);
            
            // Write to public feeds directory
            // Determine absolute path based on where we are
            $publicFile = $this->getAbsolutePublicPath() . "/{$feedId}.ansybl";
            $jsonContent = json_encode($activityStream, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            return file_put_contents($publicFile, $jsonContent) !== false;
            
        } catch (\Exception $e) {
            error_log("Error generating Activity Streams feed for {$feedId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get absolute path to public feeds directory
     */
    private function getAbsolutePublicPath(): string
    {
        // Try to find the project root by looking for composer.json
        $currentDir = __DIR__;
        while ($currentDir !== '/' && !file_exists($currentDir . '/composer.json')) {
            $currentDir = dirname($currentDir);
        }
        
        if (!file_exists($currentDir . '/composer.json')) {
            // Fallback: assume we're in src/Core and go up to project root
            $currentDir = dirname(dirname(__DIR__));
        }
        
        return $currentDir . '/public/feeds';
    }
    
    /**
     * Generate unique feed ID from name
     */
    private function generateFeedId(string $name): string
    {
        $baseId = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $name));
        $baseId = preg_replace('/-+/', '-', $baseId);
        $baseId = trim($baseId, '-');
        
        $feedId = $baseId;
        $counter = 1;
        
        // Ensure uniqueness
        while ($this->fileManager->exists($this->feedsDataPath . "/{$feedId}.json")) {
            $feedId = $baseId . '-' . $counter++;
        }
        
        return $feedId;
    }
    
    /**
     * Generate unique item ID
     */
    private function generateItemId(string $feedId): string
    {
        return $feedId . '-item-' . uniqid();
    }
    
    /**
     * Generate public feed URL
     */
    private function generateFeedUrl(string $feedId): string
    {
        $baseUrl = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return "{$protocol}://{$baseUrl}/feeds/{$feedId}.ansybl";
    }
    
    /**
     * Create upload directory for feed
     */
    private function createFeedUploadDirectory(string $feedId): bool
    {
        $uploadDir = $this->uploadsPath . "/{$feedId}";
        if (!is_dir($uploadDir)) {
            return mkdir($uploadDir, 0755, true);
        }
        return true;
    }
    
    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    /**
     * Validate feed data
     */
    private function validateFeedData(array $feedData): bool
    {
        return $this->validator->validate($feedData, 'local-feed');
    }
    
    /**
     * Validate item data
     */
    private function validateItemData(array $itemData): bool
    {
        return $this->validator->validate($itemData, 'local-feed-item');
    }
    
    /**
     * Register validation schemas
     */
    private function registerLocalFeedSchemas(): void
    {
        $this->validator->registerSchema('local-feed', [
            'required' => ['name'],
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100],
                'description' => ['type' => 'string', 'maxLength' => 500],
                'author' => ['type' => 'array'],
                'language' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 5],
                'published' => ['type' => 'boolean']
            ]
        ]);
        
        $this->validator->registerSchema('local-feed-item', [
            'required' => ['type'],
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => ['Article', 'Note', 'Image', 'Video', 'Audio']
                ],
                'name' => ['type' => 'string', 'maxLength' => 200],
                'content' => ['type' => 'string'],
                'summary' => ['type' => 'string', 'maxLength' => 500],
                'published' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'mediaType' => ['type' => 'string'],
                'duration' => ['type' => 'string'],
                'attachment' => ['type' => 'array'],
                'tag' => ['type' => 'array']
            ]
        ]);
    }
}