<?php

namespace AnsyblSite\Core;

use AnsyblSite\Exceptions\SchemaValidationException;

class SchemaValidator 
{
    private array $schemas = [];
    private array $errors = [];
    
    public function validate(array $data, string $schemaName): bool
    {
        $this->clearErrors();
        
        if (!$this->hasSchema($schemaName)) {
            throw new SchemaValidationException("Unknown schema: {$schemaName}");
        }
        
        $schema = $this->schemas[$schemaName];
        
        // Validate required fields
        if (isset($schema['required'])) {
            $this->validateRequired($data, $schema['required']);
        }
        
        // Validate properties
        if (isset($schema['properties'])) {
            $this->validateProperties($data, $schema['properties']);
        }
        
        return empty($this->errors);
    }
    
    public function validateField(mixed $value, array $rules): bool
    {
        $this->clearErrors();
        
        // Type validation
        if (isset($rules['type'])) {
            if (!$this->validateType($value, $rules['type'])) {
                $this->addError('', $value, 'type', "Expected type {$rules['type']}");
                return false;
            }
        }
        
        // String validations
        if (is_string($value)) {
            if (isset($rules['maxLength']) && !$this->validateMaxLength($value, $rules['maxLength'])) {
                $this->addError('', $value, 'maxLength', "Must be {$rules['maxLength']} characters or less");
                return false;
            }
            
            if (isset($rules['minLength']) && !$this->validateMinLength($value, $rules['minLength'])) {
                $this->addError('', $value, 'minLength', "Must be at least {$rules['minLength']} characters");
                return false;
            }
            
            if (isset($rules['format']) && !$this->validateFormat($value, $rules['format'])) {
                $this->addError('', $value, 'format', "Invalid {$rules['format']} format");
                return false;
            }
        }
        
        // Integer validations
        if (is_int($value)) {
            if (isset($rules['min']) && !$this->validateMin($value, $rules['min'])) {
                $this->addError('', $value, 'min', "Must be at least {$rules['min']}");
                return false;
            }
            
            if (isset($rules['max']) && !$this->validateMax($value, $rules['max'])) {
                $this->addError('', $value, 'max', "Must be no more than {$rules['max']}");
                return false;
            }
        }
        
        // Enum validation
        if (isset($rules['enum']) && !$this->validateEnum($value, $rules['enum'])) {
            $allowedValues = implode(', ', $rules['enum']);
            $this->addError('', $value, 'enum', "Must be one of: {$allowedValues}");
            return false;
        }
        
        return true;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function clearErrors(): void
    {
        $this->errors = [];
    }
    
    public function registerSchema(string $name, array $schema): void
    {
        $this->schemas[$name] = $schema;
    }
    
    public function hasSchema(string $name): bool
    {
        return isset($this->schemas[$name]);
    }
    
    public function validateType(mixed $value, string $type): bool
    {
        return match($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value), // JSON objects become arrays in PHP
            'number' => is_numeric($value),
            'mixed' => true, // Accept any type
            default => false
        };
    }
    
    private function validateRequired(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $this->addError($field, null, 'required', "Field '{$field}' is required");
            }
        }
    }
    
    private function validateProperties(array $data, array $properties): void
    {
        foreach ($properties as $field => $rules) {
            if (isset($data[$field])) {
                $this->validateFieldWithPath($field, $data[$field], $rules);
            }
        }
    }
    
    private function validateFieldWithPath(string $field, mixed $value, array $rules): void
    {
        $originalErrors = $this->errors;
        $this->clearErrors();
        
        $this->validateField($value, $rules);
        
        // Update error paths
        foreach ($this->errors as &$error) {
            $error['field'] = $field;
        }
        
        $this->errors = array_merge($originalErrors, $this->errors);
    }
    
    private function validateMaxLength(string $value, int $max): bool
    {
        return strlen($value) <= $max;
    }
    
    private function validateMinLength(string $value, int $min): bool
    {
        return strlen($value) >= $min;
    }
    
    private function validateMin(int $value, int $min): bool
    {
        return $value >= $min;
    }
    
    private function validateMax(int $value, int $max): bool
    {
        return $value <= $max;
    }
    
    private function validateEnum(mixed $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }
    
    private function validateFormat(string $value, string $format): bool
    {
        return match($format) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            default => true
        };
    }
    
    private function addError(string $field, mixed $value, string $rule, string $message): void
    {
        $this->errors[] = [
            'field' => $field,
            'value' => $value,
            'rule' => $rule,
            'message' => $message
        ];
    }
}