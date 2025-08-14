<?php

namespace AnsyblSite\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected string $testDataPath;
    protected string $tempPath;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testDataPath = TEST_DATA_PATH;
        $this->tempPath = TEST_TMP_PATH;
        $this->ensureCleanTempDirectory();
    }
    
    protected function tearDown(): void
    {
        $this->cleanTempDirectory();
        parent::tearDown();
    }
    
    protected function getFixturePath(string $filename): string
    {
        return $this->testDataPath . '/' . $filename;
    }
    
    protected function createTempFile(string $content, string $filename = null): string
    {
        $filename = $filename ?: 'temp_' . uniqid() . '.json';
        $path = $this->tempPath . '/' . $filename;
        file_put_contents($path, $content);
        return $path;
    }
    
    protected function assertJsonFileEquals(array $expected, string $path): void
    {
        $this->assertFileExists($path);
        $actual = json_decode(file_get_contents($path), true);
        $this->assertEquals($expected, $actual);
    }
    
    private function ensureCleanTempDirectory(): void
    {
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }
    
    private function cleanTempDirectory(): void
    {
        $files = glob($this->tempPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}