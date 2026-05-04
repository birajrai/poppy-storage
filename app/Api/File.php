<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Helpers/Security.php';

$bucket = $_GET['bucket'] ?? '';
$file = $_GET['f'] ?? '';

if (!valid_bucket($bucket)) {
    http_response_code(404);
    exit;
}

$file = sanitize_path($file);
if (!valid_file_path($bucket, $file)) {
    http_response_code(403);
    exit;
}

$path = bucket_path($bucket) . $file;
if (!file_exists($path)) {
    http_response_code(404);
    exit;
}

// Detect MIME and serve file
$f = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($f, $path);
finfo_close($f);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: "' . md5_file($path) . '"');
header('X-Content-Type-Options: nosniff');

if ($mime === 'application/pdf') {
    header('Content-Disposition: inline');
}

readfile($path);
