<?php
/**
 * PHPUnit Bootstrap File
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Define test constants
define('TEST_DATA_PATH', __DIR__ . '/fixtures');
define('TEST_TMP_PATH', __DIR__ . '/tmp');

// Ensure temp directory exists and is clean
if (!is_dir(TEST_TMP_PATH)) {
    mkdir(TEST_TMP_PATH, 0755, true);
}

// Clean temp directory before tests
$files = glob(TEST_TMP_PATH . '/*');
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}