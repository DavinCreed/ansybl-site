<?php

namespace AnsyblSite\Tests\Unit\Core;

use AnsyblSite\Tests\TestCase;
use AnsyblSite\Core\FileManager;
use AnsyblSite\Exceptions\FileNotFoundException;
use AnsyblSite\Exceptions\FilePermissionException;
use AnsyblSite\Exceptions\InvalidJsonException;

class FileManagerTest extends TestCase
{
    private FileManager $fileManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->fileManager = new FileManager($this->tempPath);
    }
    
    public function testCanReadExistingJsonFile(): void
    {
        $testData = ['test' => 'value', 'number' => 42];
        $filename = 'test.json';
        $this->createTempFile(json_encode($testData), $filename);
        
        $result = $this->fileManager->read($filename);
        
        $this->assertEquals($testData, $result);
    }
    
    public function testReadNonExistentFileThrowsException(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('File not found: missing.json');
        
        $this->fileManager->read('missing.json');
    }
    
    public function testCanWriteJsonData(): void
    {
        $data = ['test' => 'value', 'array' => [1, 2, 3]];
        $filename = 'output.json';
        
        $result = $this->fileManager->write($filename, $data);
        
        $this->assertTrue($result);
        $this->assertFileExists($this->tempPath . '/' . $filename);
        $this->assertJsonFileEquals($data, $this->tempPath . '/' . $filename);
    }
    
    public function testExistsReturnsTrueForExistingFile(): void
    {
        $filename = 'existing.json';
        $this->createTempFile('{"test": true}', $filename);
        
        $this->assertTrue($this->fileManager->exists($filename));
    }
    
    public function testExistsReturnsFalseForMissingFile(): void
    {
        $this->assertFalse($this->fileManager->exists('missing.json'));
    }
    
    public function testCanDeleteExistingFile(): void
    {
        $filename = 'delete-me.json';
        $this->createTempFile('{"test": true}', $filename);
        
        $result = $this->fileManager->delete($filename);
        
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tempPath . '/' . $filename);
    }
    
    public function testReadInvalidJsonThrowsException(): void
    {
        $filename = 'invalid.json';
        $this->createTempFile('{"invalid": json}', $filename);
        
        $this->expectException(InvalidJsonException::class);
        
        $this->fileManager->read($filename);
    }
    
    public function testGetFilePathReturnsCorrectPath(): void
    {
        $filename = 'test.json';
        $expectedPath = $this->tempPath . '/' . $filename;
        
        $actualPath = $this->fileManager->getFilePath($filename);
        
        $this->assertEquals($expectedPath, $actualPath);
    }
}