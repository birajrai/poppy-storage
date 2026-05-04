<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Helpers/Security.php';

header('Content-Type: application/json');

$bucket = $_GET['bucket'] ?? '';
$file = $_GET['f'] ?? '';
$key = $_GET['key'] ?? '';

if (!valid_bucket($bucket) || !auth_ok($bucket, $key)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$file = sanitize_path($file);
if (!valid_file_path($bucket, $file)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Invalid file path']));
}

$path = bucket_path($bucket) . $file;
if (!file_exists($path)) {
    http_response_code(404);
    exit(json_encode(['error' => 'File not found']));
}

// Delete file and update files.json
unlink($path);
remove_file_entry($bucket, $file);

// Clean up empty shard directory
$shard_dir = dirname($path);
if (is_dir($shard_dir) && count(glob($shard_dir . '/*')) === 0) {
    rmdir($shard_dir);
}

echo json_encode(['success' => true]);
