<?php
/**
 * Test runner for FeedParser class
 */

require_once 'vendor/autoload.php';

use AnsyblSite\Core\FeedParser;
use AnsyblSite\Exceptions\InvalidJsonException;
use AnsyblSite\Exceptions\SchemaValidationException;

echo "Running FeedParser Tests...\n";
echo "==========================\n\n";

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
$parser = new FeedParser();

// Test 1: Can parse basic feed
test("Can parse basic feed", function() use ($parser) {
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
    
    $result = $parser->parse($feedJson);
    
    assert(is_array($result), 'Result should be array');
    assert($result['type'] === 'Collection', 'Type should be Collection');
    assert($result['name'] === 'Test Feed', 'Name should match');
    assert(count($result['items']) === 1, 'Should have 1 item');
});

// Test 2: Can parse real podcast feed
test("Can parse real podcast feed", function() use ($parser) {
    $podcastJson = file_get_contents('./examples/basic_post_feed.ansybl');
    
    $result = $parser->parse($podcastJson);
    
    assert($result['type'] === 'Collection', 'Should be Collection');
    assert($result['id'] === 'https://utify.com/feed', 'ID should match');
    assert($result['totalItems'] === 12, 'Should have 12 items');
    assert(count($result['items']) === 12, 'Items array should have 12 items');
    
    // Check first complex item
    $firstItem = $result['items'][0];
    assert($firstItem['type'] === 'Collection', 'First item should be Collection');
    assert(count($firstItem['items']) === 2, 'Should have 2 nested items');
    
    $article = $firstItem['items'][0];
    $audio = $firstItem['items'][1];
    
    assert($article['type'] === 'Article', 'First nested should be Article');
    assert($audio['type'] === 'Audio', 'Second nested should be Audio');
    assert($audio['duration'] === 'PT22M', 'Duration should be PT22M');
});

// Test 3: Can parse image feed
test("Can parse image feed", function() use ($parser) {
    $imageJson = file_get_contents('./examples/basic_post_with_image.ansybl');
    
    $result = $parser->parse($imageJson);
    
    assert($result['type'] === 'Collection', 'Should be Collection');
    assert($result['totalItems'] === 2, 'Should have 2 items');
    
    $firstImage = $result['items'][0];
    assert($firstImage['type'] === 'Image', 'Should be Image type');
    assert($firstImage['name'] === 'Futuristic Cityscape', 'Name should match');
    assert(str_contains($firstImage['content'], 'futuristic city'), 'Content should contain expected text');
});

// Test 4: Validates feed structure
test("Validates feed structure", function() use ($parser) {
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
    
    $result = $parser->validate($validFeed);
    assert($result === true, 'Valid feed should pass validation');
});

// Test 5: Rejects invalid feed structure
test("Rejects invalid feed structure", function() use ($parser) {
    $invalidFeed = [
        // Missing required '@context'
        'type' => 'Collection',
        'items' => []
    ];
    
    $result = $parser->validate($invalidFeed);
    assert($result === false, 'Invalid feed should fail validation');
    
    $errors = $parser->getValidationErrors();
    assert(!empty($errors), 'Should have validation errors');
});

// Test 6: Extracts metadata
test("Extracts metadata", function() use ($parser) {
    $feedData = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Collection',
        'id' => 'https://example.com/feed',
        'name' => 'Test Feed',
        'summary' => 'A test feed',
        'totalItems' => 5,
        'items' => []
    ];
    
    $metadata = $parser->extractMetadata($feedData);
    
    assert($metadata['id'] === 'https://example.com/feed', 'ID should match');
    assert($metadata['name'] === 'Test Feed', 'Name should match');
    assert($metadata['summary'] === 'A test feed', 'Summary should match');
    assert($metadata['totalItems'] === 5, 'Total items should match');
    assert($metadata['type'] === 'Collection', 'Type should match');
});

// Test 7: Extracts authors
test("Extracts authors", function() use ($parser) {
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
    
    $authors = $parser->extractAuthors($item);
    
    assert(count($authors) === 1, 'Should have 1 author');
    assert($authors[0]['name'] === 'John Doe', 'Author name should match');
    assert($authors[0]['id'] === 'https://example.com/john', 'Author ID should match');
});

// Test 8: Extracts tags
test("Extracts tags", function() use ($parser) {
    $item = [
        'type' => 'Article',
        'tag' => [
            ['type' => 'Hashtag', 'name' => '#technology'],
            ['type' => 'Hashtag', 'name' => '#programming']
        ]
    ];
    
    $tags = $parser->extractTags($item);
    
    assert(count($tags) === 2, 'Should have 2 tags');
    assert($tags[0] === '#technology', 'First tag should match');
    assert($tags[1] === '#programming', 'Second tag should match');
});

// Test 9: Handles invalid JSON
test("Handles invalid JSON", function() use ($parser) {
    try {
        $parser->parse('{"invalid": json}');
        assert(false, 'Should have thrown InvalidJsonException');
    } catch (InvalidJsonException $e) {
        assert(str_contains($e->getMessage(), 'Invalid JSON'), 'Exception should mention invalid JSON');
    }
});

// Test 10: Flattens nested collections
test("Flattens nested collections", function() use ($parser) {
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
            ],
            [
                'type' => 'Note',
                'content' => 'Simple note'
            ]
        ]
    ];
    
    $flattened = $parser->flattenItems($feedData);
    
    assert(count($flattened) === 3, 'Should flatten to 3 items'); // Article + Audio + Note
    assert($flattened[0]['type'] === 'Article', 'First should be Article');
    assert($flattened[1]['type'] === 'Audio', 'Second should be Audio');
    assert($flattened[2]['type'] === 'Note', 'Third should be Note');
});

echo "\nTest Results:\n";
echo "============\n";
echo "Passed: {$passedCount}/{$testCount}\n";

if ($passedCount === $testCount) {
    echo "🎉 All FeedParser tests passed!\n";
    exit(0);
} else {
    echo "❌ Some FeedParser tests failed!\n";
    exit(1);
}