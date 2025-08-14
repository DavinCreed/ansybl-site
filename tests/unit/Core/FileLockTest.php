<?php

namespace AnsyblSite\Tests\Unit\Core;

use AnsyblSite\Tests\TestCase;
use AnsyblSite\Core\FileLock;
use AnsyblSite\Exceptions\FileLockException;

class FileLockTest extends TestCase
{
    private string $testFile;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testFile = $this->tempPath . '/test-file.json';
        
        // Create test file
        file_put_contents($this->testFile, '{"test": true}');
    }
    
    public function testCanAcquireLock(): void
    {
        $lock = new FileLock($this->testFile);
        
        $result = $lock->acquire();
        
        $this->assertTrue($result);
        $this->assertTrue($lock->isLocked());
        
        $lock->release();
    }
    
    public function testCanReleaseLock(): void
    {
        $lock = new FileLock($this->testFile);
        $lock->acquire();
        
        $result = $lock->release();
        
        $this->assertTrue($result);
        $this->assertFalse($lock->isLocked());
    }
    
    public function testLockFileCreatedWithCorrectInfo(): void
    {
        $lock = new FileLock($this->testFile);
        $lock->acquire();
        
        $lockFile = $this->testFile . '.lock';
        $this->assertFileExists($lockFile);
        
        $lockInfo = json_decode(file_get_contents($lockFile), true);
        $this->assertArrayHasKey('pid', $lockInfo);
        $this->assertArrayHasKey('timestamp', $lockInfo);
        $this->assertArrayHasKey('hostname', $lockInfo);
        $this->assertEquals(getmypid(), $lockInfo['pid']);
        
        $lock->release();
    }
    
    public function testCannotAcquireAlreadyLockedFile(): void
    {
        $lock1 = new FileLock($this->testFile, 1); // 1 second timeout
        $lock2 = new FileLock($this->testFile, 1);
        
        $lock1->acquire();
        
        $this->expectException(FileLockException::class);
        $this->expectExceptionMessage('Cannot acquire lock');
        
        $lock2->acquire();
        
        $lock1->release();
    }
    
    public function testIsLockedReturnsFalseForNonExistentLock(): void
    {
        $lock = new FileLock($this->testFile);
        
        $this->assertFalse($lock->isLocked());
    }
    
    public function testDetectsStaleLocks(): void
    {
        $lockFile = $this->testFile . '.lock';
        
        // Create stale lock (5+ minutes old)
        $staleLockInfo = [
            'pid' => 99999, // Non-existent PID
            'timestamp' => time() - 400, // 400 seconds ago
            'hostname' => 'test-host'
        ];
        file_put_contents($lockFile, json_encode($staleLockInfo));
        
        $lock = new FileLock($this->testFile);
        
        // Should detect stale lock and return false
        $this->assertFalse($lock->isLocked());
        
        // Lock file should be cleaned up
        $this->assertFileDoesNotExist($lockFile);
    }
    
    public function testReleaseReturnsFalseWhenNoLockHeld(): void
    {
        $lock = new FileLock($this->testFile);
        
        $result = $lock->release();
        
        $this->assertFalse($result);
    }
}