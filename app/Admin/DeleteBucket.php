<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Helpers/Security.php';
require_once __DIR__ . '/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin');
    exit;
}

if (!validate_csrf()) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$name = $_POST['name'] ?? '';

if (empty($name) || !valid_bucket_name($name)) {
    exit('Invalid bucket name.');
}

if (!get_bucket($name)) {
    exit('Bucket not found.');
}

// Remove from buckets.json
$buckets = load_buckets();
$new = array_filter($buckets, fn($b) => $b['name'] !== $name);
save_buckets(array_values($new));

// Recursively delete bucket folder and all contents
$bucket_dir = BASE_DIR . $name;
if (is_dir($bucket_dir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($bucket_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->isDir()) rmdir($file->getRealPath());
        else unlink($file->getRealPath());
    }
    rmdir($bucket_dir);
}

header('Location: /admin');
exit;
