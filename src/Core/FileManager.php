<?php

namespace AnsyblSite\Core;

use AnsyblSite\Exceptions\FileNotFoundException;
use AnsyblSite\Exceptions\FilePermissionException;
use AnsyblSite\Exceptions\FileSystemException;
use AnsyblSite\Exceptions\InvalidJsonException;

class FileManager 
{
    private string $dataPath;
    
    public function __construct(string $dataPath = './data')
    {
        $this->dataPath = rtrim($dataPath, '/');
    }
    
    public function read(string $filename): array
    {
        $path = $this->getFilePath($filename);
        
        if (!$this->exists($filename)) {
            throw new FileNotFoundException("File not found: {$filename}", 404, null, [
                'filename' => $filename,
                'path' => $path
            ]);
        }
        
        if (!is_readable($path)) {
            throw new FilePermissionException("Cannot read file: {$filename}", 403, null, [
                'filename' => $filename,
                'path' => $path,
                'permissions' => fileperms($path)
            ]);
        }
        
        $content = file_get_contents($path);
        if ($content === false) {
            throw new FileSystemException("Failed to read file: {$filename}");
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidJsonException("Invalid JSON in file: {$filename}", 400, null, [
                'json_error' => json_last_error_msg(),
                'filename' => $filename
            ]);
        }
        
        return $data;
    }
    
    public function write(string $filename, array $data): bool
    {
        $path = $this->getFilePath($filename);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            throw new InvalidJsonException("Cannot encode data to JSON");
        }
        
        $result = file_put_contents($path, $json, LOCK_EX);
        return $result !== false;
    }
    
    public function exists(string $filename): bool
    {
        return file_exists($this->getFilePath($filename));
    }
    
    public function delete(string $filename): bool
    {
        $path = $this->getFilePath($filename);
        
        if (!$this->exists($filename)) {
            return true; // Already deleted
        }
        
        return unlink($path);
    }
    
    public function getFilePath(string $filename): string
    {
        return $this->dataPath . '/' . $filename;
    }
}