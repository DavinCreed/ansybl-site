<?php

namespace AnsyblSite\Core;

use AnsyblSite\Exceptions\FileSystemException;
use AnsyblSite\Exceptions\ValidationException;

class ConcurrentFileManager extends FileManager
{
    private array $activeLocks = [];
    
    public function safeRead(string $filename): array
    {
        $path = $this->getFilePath($filename);
        $lock = new FileLock($path);
        
        try {
            $lock->acquire();
            $this->activeLocks[$filename] = $lock;
            
            return parent::read($filename);
            
        } finally {
            if (isset($this->activeLocks[$filename])) {
                $this->activeLocks[$filename]->release();
                unset($this->activeLocks[$filename]);
            }
        }
    }
    
    public function atomicWrite(string $filename, array $data): bool
    {
        $path = $this->getFilePath($filename);
        $tempPath = $path . '.tmp.' . uniqid();
        $lock = new FileLock($path);
        
        try {
            // Acquire exclusive lock
            $lock->acquire();
            
            // Write to temporary file first
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new FileSystemException("Cannot encode data to JSON");
            }
            
            if (file_put_contents($tempPath, $json, LOCK_EX) === false) {
                throw new FileSystemException("Failed to write temporary file: {$tempPath}");
            }
            
            // Validate written data
            $written = json_decode(file_get_contents($tempPath), true);
            if (!$this->dataMatches($data, $written)) {
                throw new FileSystemException("Data validation failed after write");
            }
            
            // Atomic move (rename is atomic on most filesystems)
            if (!rename($tempPath, $path)) {
                throw new FileSystemException("Failed to move temporary file to final location");
            }
            
            return true;
            
        } finally {
            // Cleanup
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            $lock->release();
        }
    }
    
    public function transactionalUpdate(string $filename, callable $updater): bool
    {
        $path = $this->getFilePath($filename);
        $lock = new FileLock($path);
        
        try {
            $lock->acquire();
            
            // Read current data
            $currentData = $this->exists($filename) ? parent::read($filename) : [];
            
            // Apply updates
            $updatedData = $updater($currentData);
            
            // Validate updates
            if (!is_array($updatedData)) {
                throw new ValidationException("Updater must return array");
            }
            
            // Write directly (we already have the lock)
            return parent::write($filename, $updatedData);
            
        } finally {
            $lock->release();
        }
    }
    
    private function dataMatches(array $original, array $written): bool
    {
        return json_encode($original) === json_encode($written);
    }
}