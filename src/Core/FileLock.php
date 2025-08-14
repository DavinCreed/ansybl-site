<?php

namespace AnsyblSite\Core;

use AnsyblSite\Exceptions\FileLockException;

class FileLock
{
    private string $lockFile;
    private $lockHandle;
    private int $timeout;
    
    public function __construct(string $filename, int $timeout = 10)
    {
        $this->lockFile = $filename . '.lock';
        $this->timeout = $timeout;
    }
    
    public function acquire(): bool
    {
        $startTime = time();
        
        while (time() - $startTime < $this->timeout) {
            $this->lockHandle = fopen($this->lockFile, 'w');
            
            if ($this->lockHandle && flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
                // Write process info to lock file
                fwrite($this->lockHandle, json_encode([
                    'pid' => getmypid(),
                    'timestamp' => time(),
                    'hostname' => gethostname()
                ]));
                fflush($this->lockHandle);
                return true;
            }
            
            if ($this->lockHandle) {
                fclose($this->lockHandle);
                $this->lockHandle = null;
            }
            
            usleep(100000); // Wait 100ms before retry
        }
        
        throw new FileLockException("Cannot acquire lock for: {$this->lockFile}");
    }
    
    public function release(): bool
    {
        if ($this->lockHandle) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
            
            if (file_exists($this->lockFile)) {
                unlink($this->lockFile);
            }
            return true;
        }
        return false;
    }
    
    public function isLocked(): bool
    {
        if (!file_exists($this->lockFile)) {
            return false;
        }
        
        // Check if lock is stale (process died)
        $lockContent = file_get_contents($this->lockFile);
        if (!$lockContent) {
            return false;
        }
        
        $lockInfo = json_decode($lockContent, true);
        if (!$lockInfo || !isset($lockInfo['timestamp'], $lockInfo['pid'])) {
            // Invalid lock file, clean it up
            unlink($this->lockFile);
            return false;
        }
        
        // Check if lock is stale (older than 5 minutes)
        if (time() - $lockInfo['timestamp'] > 300) {
            unlink($this->lockFile);
            return false;
        }
        
        // Check if process is still running (Unix-like systems)
        if (function_exists('posix_kill') && !posix_kill($lockInfo['pid'], 0)) {
            unlink($this->lockFile);
            return false;
        }
        
        return true;
    }
}