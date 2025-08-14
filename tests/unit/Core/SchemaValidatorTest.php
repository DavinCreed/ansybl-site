<?php

namespace AnsyblSite\Tests\Unit\Core;

use AnsyblSite\Tests\TestCase;
use AnsyblSite\Core\SchemaValidator;
use AnsyblSite\Exceptions\SchemaValidationException;

class SchemaValidatorTest extends TestCase
{
    private SchemaValidator $validator;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SchemaValidator();
        
        // Register test schemas
        $this->validator->registerSchema('test', [
            'required' => ['name', 'type'],
            'properties' => [
                'name' => ['type' => 'string', 'maxLength' => 50],
                'type' => ['type' => 'string', 'enum' => ['user', 'admin']],
                'age' => ['type' => 'integer', 'min' => 0, 'max' => 150],
                'email' => ['type' => 'string', 'format' => 'email']
            ]
        ]);
    }
    
    public function testValidatesValidData(): void
    {
        $validData = [
            'name' => 'John Doe',
            'type' => 'user',
            'age' => 30,
            'email' => 'john@example.com'
        ];
        
        $result = $this->validator->validate($validData, 'test');
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }
    
    public function testFailsWhenRequiredFieldMissing(): void
    {
        $invalidData = [
            'name' => 'John Doe'
            // missing 'type' field
        ];
        
        $result = $this->validator->validate($invalidData, 'test');
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('type', $errors[0]['message']);
    }
    
    public function testValidatesFieldTypes(): void
    {
        $invalidData = [
            'name' => 123, // should be string
            'type' => 'user',
            'age' => 'thirty' // should be integer
        ];
        
        $result = $this->validator->validate($invalidData, 'test');
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(2, $errors); // Two type errors
    }
    
    public function testValidatesStringLength(): void
    {
        $invalidData = [
            'name' => str_repeat('a', 51), // exceeds maxLength of 50
            'type' => 'user'
        ];
        
        $result = $this->validator->validate($invalidData, 'test');
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('maxLength', $errors[0]['message']);
    }
    
    public function testValidatesEnumValues(): void
    {
        $invalidData = [
            'name' => 'John Doe',
            'type' => 'invalid' // not in enum ['user', 'admin']
        ];
        
        $result = $this->validator->validate($invalidData, 'test');
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('enum', $errors[0]['message']);
    }
    
    public function testValidatesIntegerRange(): void
    {
        $invalidData = [
            'name' => 'John Doe',
            'type' => 'user',
            'age' => 200 // exceeds max of 150
        ];
        
        $result = $this->validator->validate($invalidData, 'test');
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('max', $errors[0]['message']);
    }
    
    public function testValidatesEmailFormat(): void
    {
        $invalidData = [
            'name' => 'John Doe',
            'type' => 'user',
            'email' => 'invalid-email'
        ];
        
        $result = $this->validator->validate($invalidData, 'test');
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('email', $errors[0]['message']);
    }
    
    public function testCanRegisterAndUseSchemas(): void
    {
        $schema = [
            'required' => ['id'],
            'properties' => [
                'id' => ['type' => 'string']
            ]
        ];
        
        $this->validator->registerSchema('simple', $schema);
        $this->assertTrue($this->validator->hasSchema('simple'));
        
        $result = $this->validator->validate(['id' => 'test'], 'simple');
        $this->assertTrue($result);
    }
    
    public function testThrowsExceptionForUnknownSchema(): void
    {
        $this->expectException(SchemaValidationException::class);
        $this->expectExceptionMessage('Unknown schema');
        
        $this->validator->validate(['test' => true], 'unknown-schema');
    }
    
    public function testClearsErrorsBetweenValidations(): void
    {
        // First validation with errors
        $this->validator->validate(['name' => 123], 'test');
        $this->assertNotEmpty($this->validator->getErrors());
        
        // Clear errors
        $this->validator->clearErrors();
        $this->assertEmpty($this->validator->getErrors());
        
        // Second validation
        $this->validator->validate(['name' => 'John', 'type' => 'user'], 'test');
        $this->assertEmpty($this->validator->getErrors());
    }
}