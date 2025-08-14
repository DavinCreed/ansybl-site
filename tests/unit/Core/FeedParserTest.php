<?php

namespace AnsyblSite\Tests\Unit\Core;

use AnsyblSite\Tests\TestCase;
use AnsyblSite\Core\FeedParser;
use AnsyblSite\Core\SchemaValidator;
use AnsyblSite\Exceptions\InvalidJsonException;
use AnsyblSite\Exceptions\SchemaValidationException;

class FeedParserTest extends TestCase
{
    private FeedParser $parser;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FeedParser();
    }
    
    public function testCanParseBasicFeed(): void
    {
        $feedJson = '{
            "@context": "https://www.w3.org/ns/activitystreams",
            "type": "Collection",
            "id": "https://example.com/feed",
            "name": "Test Feed",
            "totalItems": 1,
            "items": [
                {
                    "type": "Note",
                    "id": "https://example.com/note1",
                    "name": "Test Note",
                    "content": "This is a test note."
                }
            ]
        }';
        
        $result = $this->parser->parse($feedJson);
        
        $this->assertIsArray($result);
        $this->assertEquals('Collection', $result['type']);
        $this->assertEquals('Test Feed', $result['name']);
        $this->assertCount(1, $result['items']);
    }
    
    public function testCanParseRealWorldPodcastFeed(): void
    {
        $podcastJson = file_get_contents(__DIR__ . '/../../../examples/basic_post_feed.ansybl');
        
        $result = $this->parser->parse($podcastJson);
        
        $this->assertEquals('Collection', $result['type']);
        $this->assertEquals('https://utify.com/feed', $result['id']);
        $this->assertEquals(12, $result['totalItems']);
        $this->assertCount(12, $result['items']);
        
        // Check first item (complex collection with audio and text)
        $firstItem = $result['items'][0];
        $this->assertEquals('Collection', $firstItem['type']);
        $this->assertEquals('episode - 1 - Passenger, by Chris Banyas', $firstItem['name']);
        $this->assertCount(2, $firstItem['items']);
        
        // Check nested article and audio
        $article = $firstItem['items'][0];
        $audio = $firstItem['items'][1];
        
        $this->assertEquals('Article', $article['type']);
        $this->assertEquals('text/markdown', $article['mediaType']);
        
        $this->assertEquals('Audio', $audio['type']);
        $this->assertEquals('PT22M', $audio['duration']);
    }
    
    public function testCanParseImageFeed(): void
    {
        $imageJson = file_get_contents(__DIR__ . '/../../../examples/basic_post_with_image.ansybl');
        
        $result = $this->parser->parse($imageJson);
        
        $this->assertEquals('Collection', $result['type']);
        $this->assertEquals(2, $result['totalItems']);
        
        $firstImage = $result['items'][0];
        $this->assertEquals('Image', $firstImage['type']);
        $this->assertEquals('Futuristic Cityscape', $firstImage['name']);
        $this->assertStringContainsString('futuristic city', $firstImage['content']);
    }
    
    public function testValidatesFeedStructure(): void
    {
        $validFeed = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Collection',
            'items' => [
                [
                    'type' => 'Note',
                    'content' => 'Test content'
                ]
            ]
        ];
        
        $result = $this->parser->validate($validFeed);
        $this->assertTrue($result);
    }
    
    public function testRejectsInvalidFeedStructure(): void
    {
        $invalidFeed = [
            // Missing required '@context'
            'type' => 'Collection',
            'items' => []
        ];
        
        $result = $this->parser->validate($invalidFeed);
        $this->assertFalse($result);
        
        $errors = $this->parser->getValidationErrors();
        $this->assertNotEmpty($errors);
    }
    
    public function testExtractsMetadata(): void
    {
        $feedData = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Collection',
            'id' => 'https://example.com/feed',
            'name' => 'Test Feed',
            'summary' => 'A test feed',
            'totalItems' => 5,
            'items' => []
        ];
        
        $metadata = $this->parser->extractMetadata($feedData);
        
        $this->assertEquals('https://example.com/feed', $metadata['id']);
        $this->assertEquals('Test Feed', $metadata['name']);
        $this->assertEquals('A test feed', $metadata['summary']);
        $this->assertEquals(5, $metadata['totalItems']);
        $this->assertEquals('Collection', $metadata['type']);
    }
    
    public function testExtractsItems(): void
    {
        $feedData = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Collection',
            'items' => [
                [
                    'type' => 'Note',
                    'id' => 'note1',
                    'content' => 'First note'
                ],
                [
                    'type' => 'Article',
                    'id' => 'article1',
                    'name' => 'First Article',
                    'content' => 'Article content'
                ]
            ]
        ];
        
        $items = $this->parser->extractItems($feedData);
        
        $this->assertCount(2, $items);
        $this->assertEquals('Note', $items[0]['type']);
        $this->assertEquals('Article', $items[1]['type']);
    }
    
    public function testHandlesNestedCollections(): void
    {
        $feedData = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Collection',
            'items' => [
                [
                    'type' => 'Collection',
                    'name' => 'Episode 1',
                    'items' => [
                        ['type' => 'Article', 'content' => 'Show notes'],
                        ['type' => 'Audio', 'url' => 'audio.mp3']
                    ]
                ]
            ]
        ];
        
        $items = $this->parser->extractItems($feedData);
        $this->assertCount(1, $items);
        
        $episode = $items[0];
        $this->assertEquals('Collection', $episode['type']);
        $this->assertCount(2, $episode['items']);
    }
    
    public function testExtractsAuthors(): void
    {
        $item = [
            'type' => 'Article',
            'attributedTo' => [
                [
                    'type' => 'Person',
                    'name' => 'John Doe',
                    'id' => 'https://example.com/john'
                ]
            ]
        ];
        
        $authors = $this->parser->extractAuthors($item);
        
        $this->assertCount(1, $authors);
        $this->assertEquals('John Doe', $authors[0]['name']);
        $this->assertEquals('https://example.com/john', $authors[0]['id']);
    }
    
    public function testExtractsTags(): void
    {
        $item = [
            'type' => 'Article',
            'tag' => [
                ['type' => 'Hashtag', 'name' => '#technology'],
                ['type' => 'Hashtag', 'name' => '#programming']
            ]
        ];
        
        $tags = $this->parser->extractTags($item);
        
        $this->assertCount(2, $tags);
        $this->assertEquals('#technology', $tags[0]);
        $this->assertEquals('#programming', $tags[1]);
    }
    
    public function testThrowsExceptionForInvalidJson(): void
    {
        $this->expectException(InvalidJsonException::class);
        
        $this->parser->parse('{"invalid": json}');
    }
    
    public function testThrowsExceptionForMissingContext(): void
    {
        $invalidFeed = json_encode([
            'type' => 'Collection',
            'items' => []
        ]);
        
        $this->expectException(SchemaValidationException::class);
        
        $this->parser->parse($invalidFeed);
    }
}