<?php
/**
 * Test runner for SchemaValidator class
 */

require_once 'vendor/autoload.php';

use AnsyblSite\Core\SchemaValidator;
use AnsyblSite\Exceptions\SchemaValidationException;

echo "Running SchemaValidator Tests...\n";
echo "===============================\n\n";

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
$validator = new SchemaValidator();

// Register test schema
$validator->registerSchema('test', [
    'required' => ['name', 'type'],
    'properties' => [
        'name' => ['type' => 'string', 'maxLength' => 50],
        'type' => ['type' => 'string', 'enum' => ['user', 'admin']],
        'age' => ['type' => 'integer', 'min' => 0, 'max' => 150],
        'email' => ['type' => 'string', 'format' => 'email']
    ]
]);

// Test 1: Validates valid data
test("Validates valid data", function() use ($validator) {
    $validData = [
        'name' => 'John Doe',
        'type' => 'user',
        'age' => 30,
        'email' => 'john@example.com'
    ];
    
    $result = $validator->validate($validData, 'test');
    assert($result === true, 'Should validate valid data');
    assert(empty($validator->getErrors()), 'Should have no errors');
});

// Test 2: Fails when required field missing
test("Fails when required field missing", function() use ($validator) {
    $invalidData = [
        'name' => 'John Doe'
        // missing 'type' field
    ];
    
    $result = $validator->validate($invalidData, 'test');
    assert($result === false, 'Should fail validation');
    
    $errors = $validator->getErrors();
    assert(!empty($errors), 'Should have errors');
    assert(str_contains($errors[0]['message'], 'type'), 'Error should mention missing type field');
});

// Test 3: Validates field types
test("Validates field types", function() use ($validator) {
    $invalidData = [
        'name' => 123, // should be string
        'type' => 'user',
        'age' => 'thirty' // should be integer
    ];
    
    $result = $validator->validate($invalidData, 'test');
    assert($result === false, 'Should fail validation');
    
    $errors = $validator->getErrors();
    assert(count($errors) === 2, 'Should have 2 type errors');
});

// Test 4: Validates string length
test("Validates string length", function() use ($validator) {
    $invalidData = [
        'name' => str_repeat('a', 51), // exceeds maxLength of 50
        'type' => 'user'
    ];
    
    $result = $validator->validate($invalidData, 'test');
    assert($result === false, 'Should fail validation');
    
    $errors = $validator->getErrors();
    assert(str_contains($errors[0]['message'], 'maxLength'), 'Error should mention maxLength');
});

// Test 5: Validates enum values
test("Validates enum values", function() use ($validator) {
    $invalidData = [
        'name' => 'John Doe',
        'type' => 'invalid' // not in enum ['user', 'admin']
    ];
    
    $result = $validator->validate($invalidData, 'test');
    assert($result === false, 'Should fail validation');
    
    $errors = $validator->getErrors();
    assert(str_contains($errors[0]['message'], 'Must be one of'), 'Error should mention enum values');
});

// Test 6: Validates integer range
test("Validates integer range", function() use ($validator) {
    $invalidData = [
        'name' => 'John Doe',
        'type' => 'user',
        'age' => 200 // exceeds max of 150
    ];
    
    $result = $validator->validate($invalidData, 'test');
    assert($result === false, 'Should fail validation');
    
    $errors = $validator->getErrors();
    assert(str_contains($errors[0]['message'], 'no more than'), 'Error should mention max value');
});

// Test 7: Validates email format
test("Validates email format", function() use ($validator) {
    $invalidData = [
        'name' => 'John Doe',
        'type' => 'user',
        'email' => 'invalid-email'
    ];
    
    $result = $validator->validate($invalidData, 'test');
    assert($result === false, 'Should fail validation');
    
    $errors = $validator->getErrors();
    assert(str_contains($errors[0]['message'], 'email'), 'Error should mention email format');
});

// Test 8: Can register and use schemas
test("Can register and use schemas", function() use ($validator) {
    $schema = [
        'required' => ['id'],
        'properties' => [
            'id' => ['type' => 'string']
        ]
    ];
    
    $validator->registerSchema('simple', $schema);
    assert($validator->hasSchema('simple'), 'Should have registered schema');
    
    $result = $validator->validate(['id' => 'test'], 'simple');
    assert($result === true, 'Should validate with registered schema');
});

// Test 9: Throws exception for unknown schema
test("Throws exception for unknown schema", function() use ($validator) {
    try {
        $validator->validate(['test' => true], 'unknown-schema');
        assert(false, 'Should have thrown SchemaValidationException');
    } catch (SchemaValidationException $e) {
        assert(str_contains($e->getMessage(), 'Unknown schema'), 'Exception should mention unknown schema');
    }
});

// Test 10: Clears errors between validations
test("Clears errors between validations", function() use ($validator) {
    // First validation with errors
    $validator->validate(['name' => 123], 'test');
    assert(!empty($validator->getErrors()), 'Should have errors from first validation');
    
    // Clear errors
    $validator->clearErrors();
    assert(empty($validator->getErrors()), 'Should have no errors after clearing');
    
    // Second validation (successful)
    $validator->validate(['name' => 'John', 'type' => 'user'], 'test');
    assert(empty($validator->getErrors()), 'Should have no errors from successful validation');
});

echo "\nTest Results:\n";
echo "============\n";
echo "Passed: {$passedCount}/{$testCount}\n";

if ($passedCount === $testCount) {
    echo "🎉 All SchemaValidator tests passed!\n";
    exit(0);
} else {
    echo "❌ Some SchemaValidator tests failed!\n";
    exit(1);
}