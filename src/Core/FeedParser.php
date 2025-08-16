<?php

namespace AnsyblSite\Core;

use AnsyblSite\Exceptions\InvalidJsonException;
use AnsyblSite\Exceptions\SchemaValidationException;

class FeedParser 
{
    private SchemaValidator $validator;
    private array $validationErrors = [];
    
    public function __construct(?SchemaValidator $validator = null)
    {
        $this->validator = $validator ?? new SchemaValidator();
        $this->registerFeedSchemas();
    }
    
    public function parse(string $feedJson): array
    {
        // Parse JSON
        $data = json_decode($feedJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidJsonException("Invalid JSON: " . json_last_error_msg());
        }
        
        // Validate structure
        if (!$this->validate($data)) {
            throw new SchemaValidationException("Feed validation failed: " . implode(', ', $this->getValidationErrorMessages()));
        }
        
        return $data;
    }
    
    public function validate(array $feedData): bool
    {
        $this->validationErrors = [];
        
        // Validate against Activity Streams 2.0 schema
        $isValid = $this->validator->validate($feedData, 'activitystreams-feed');
        
        if (!$isValid) {
            $this->validationErrors = $this->validator->getErrors();
        }
        
        return $isValid;
    }
    
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
    
    public function getValidationErrorMessages(): array
    {
        return array_map(fn($error) => $error['message'], $this->validationErrors);
    }
    
    public function extractMetadata(array $feedData): array
    {
        return [
            'id' => $feedData['id'] ?? null,
            'name' => $feedData['name'] ?? null,
            'summary' => $feedData['summary'] ?? null,
            'type' => $feedData['type'] ?? null,
            'totalItems' => $feedData['totalItems'] ?? null,
            'published' => $feedData['published'] ?? null,
            'updated' => $feedData['updated'] ?? null,
            'context' => $feedData['@context'] ?? null
        ];
    }
    
    public function extractItems(array $feedData): array
    {
        return $feedData['items'] ?? [];
    }
    
    public function extractAuthors(array $item): array
    {
        if (!isset($item['attributedTo'])) {
            return [];
        }
        
        $attributedTo = $item['attributedTo'];
        
        // Handle single person or array of people
        if (isset($attributedTo['type']) && $attributedTo['type'] === 'Person') {
            return [$attributedTo];
        }
        
        if (is_array($attributedTo)) {
            return array_filter($attributedTo, fn($attr) => 
                isset($attr['type']) && $attr['type'] === 'Person'
            );
        }
        
        return [];
    }
    
    public function extractTags(array $item): array
    {
        if (!isset($item['tag']) || !is_array($item['tag'])) {
            return [];
        }
        
        return array_map(
            fn($tag) => $tag['name'] ?? $tag,
            array_filter($item['tag'], fn($tag) => 
                !isset($tag['type']) || $tag['type'] === 'Hashtag'
            )
        );
    }
    
    public function extractUrl(array $item): ?string
    {
        if (!isset($item['url'])) {
            return null;
        }
        
        $url = $item['url'];
        
        // Handle simple string URL
        if (is_string($url)) {
            return $url;
        }
        
        // Handle Link object
        if (is_array($url) && isset($url['href'])) {
            return $url['href'];
        }
        
        // Handle array of Link objects (take first)
        if (is_array($url) && isset($url[0]['href'])) {
            return $url[0]['href'];
        }
        
        return null;
    }
    
    public function extractDuration(array $item): ?string
    {
        return $item['duration'] ?? null;
    }
    
    public function extractMediaType(array $item): ?string
    {
        // Check direct mediaType property
        if (isset($item['mediaType'])) {
            return $item['mediaType'];
        }
        
        // Check URL object for mediaType
        if (isset($item['url']) && is_array($item['url'])) {
            $url = $item['url'];
            
            if (isset($url['mediaType'])) {
                return $url['mediaType'];
            }
            
            if (isset($url[0]['mediaType'])) {
                return $url[0]['mediaType'];
            }
        }
        
        return null;
    }
    
    public function isCollection(array $item): bool
    {
        return isset($item['type']) && $item['type'] === 'Collection';
    }
    
    public function flattenItems(array $feedData, bool $includeCollections = false): array
    {
        $items = $this->extractItems($feedData);
        $flattened = [];
        
        foreach ($items as $item) {
            if ($this->isCollection($item)) {
                // Convert Collection to proper Activity (only one per collection)
                $activity = $this->convertCollectionToActivity($item);
                $flattened[] = $activity;
                
                // Don't create separate activities for nested items - they're part of the collection
            } else {
                // Convert standalone objects to activities
                $activity = $this->convertObjectToActivity($item);
                $flattened[] = $activity;
            }
        }
        
        return $flattened;
    }
    
    /**
     * Convert a Collection to a proper Activity
     */
    private function convertCollectionToActivity(array $collection): array
    {
        return [
            'type' => 'Create',
            'id' => ($collection['id'] ?? uniqid()) . '#activity',
            'actor' => $collection['attributedTo'][0] ?? [
                'type' => 'Person',
                'name' => 'Unknown Author'
            ],
            'object' => [
                'type' => 'Collection',
                'id' => $collection['id'] ?? uniqid(),
                'name' => $collection['name'] ?? 'Untitled Collection',
                'summary' => $collection['summary'] ?? null,
                'content' => $this->extractCollectionContent($collection),
                'totalItems' => $collection['totalItems'] ?? count($collection['items'] ?? []),
                'url' => $collection['id'] ?? null,
                'items' => $collection['items'] ?? []  // Preserve the nested items
            ],
            'published' => $this->extractOrGenerateDate($collection),
            'summary' => $collection['summary'] ?? $collection['name'] ?? 'New collection',
            'feedId' => $collection['feedId'] ?? 'unknown'
        ];
    }
    
    /**
     * Convert an Object to a proper Activity
     */
    private function convertObjectToActivity(array $object, array $parentCollection = null): array
    {
        return [
            'type' => 'Create',
            'id' => ($object['id'] ?? uniqid()) . '#activity',
            'actor' => $parentCollection['attributedTo'][0] ?? 
                      $object['attributedTo'][0] ?? [
                          'type' => 'Person',
                          'name' => 'Unknown Author'
                      ],
            'object' => $object,
            'published' => $this->extractOrGenerateDate($object, $parentCollection),
            'summary' => $object['summary'] ?? $object['name'] ?? 'New ' . strtolower($object['type'] ?? 'item'),
            'feedId' => $object['feedId'] ?? $parentCollection['feedId'] ?? 'unknown'
        ];
    }
    
    /**
     * Extract content from a collection's items
     */
    private function extractCollectionContent(array $collection): string
    {
        if (!isset($collection['items']) || !is_array($collection['items'])) {
            return $collection['summary'] ?? '';
        }
        
        $content = [];
        foreach ($collection['items'] as $item) {
            if (isset($item['content'])) {
                $content[] = $item['content'];
            } elseif (isset($item['summary'])) {
                $content[] = $item['summary'];
            }
        }
        
        return implode("\n\n", $content);
    }
    
    /**
     * Extract or generate a published date
     */
    private function extractOrGenerateDate(array $item, array $parentItem = null): string
    {
        // Try to find a date in the item
        $dateFields = ['published', 'updated', 'created', 'datePublished', 'dateCreated'];
        
        foreach ($dateFields as $field) {
            if (isset($item[$field])) {
                return $item[$field];
            }
        }
        
        // Try parent item
        if ($parentItem) {
            foreach ($dateFields as $field) {
                if (isset($parentItem[$field])) {
                    return $parentItem[$field];
                }
            }
        }
        
        // Generate current timestamp as fallback
        return date('c');
    }
    
    private function registerFeedSchemas(): void
    {
        $this->validator->registerSchema('activitystreams-feed', [
            'required' => ['@context', 'type'],
            'properties' => [
                '@context' => [
                    'type' => 'string',
                    'enum' => ['https://www.w3.org/ns/activitystreams']
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['Collection', 'OrderedCollection']
                ],
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'totalItems' => ['type' => 'integer', 'min' => 0],
                'published' => ['type' => 'string'],
                'updated' => ['type' => 'string'],
                'items' => [
                    'type' => 'array'
                ]
            ]
        ]);
        
        $this->validator->registerSchema('activitystreams-item', [
            'required' => ['type'],
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => ['Collection', 'Article', 'Note', 'Image', 'Video', 'Audio', 'Create', 'Announce', 'Event']
                ],
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'published' => ['type' => 'string'],
                'updated' => ['type' => 'string'],
                'mediaType' => ['type' => 'string'],
                'duration' => ['type' => 'string'],
                'url' => ['type' => 'mixed'],
                'attributedTo' => ['type' => 'mixed'],
                'tag' => ['type' => 'array'],
                'to' => ['type' => 'array'],
                'items' => ['type' => 'array']
            ]
        ]);
    }
}