<?php

// Validate bucket name (strict alphanumeric + underscore/hyphen)
function valid_bucket_name($name) {
    return preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1;
}

// Sanitize file path to prevent traversal
function sanitize_path($path) {
    $path = str_replace(['..', '\\', '//'], '', $path);
    return ltrim($path, '/');
}

// Validate file path stays within bucket directory
function valid_file_path($bucket, $file_path) {
    $bucket_dir = bucket_path($bucket);
    $full_path = $bucket_dir . ltrim($file_path, '/');
    $real_bucket = realpath($bucket_dir);
    $real_file = realpath($full_path);
    return $real_bucket && $real_file && strpos($real_file, $real_bucket) === 0;
}

// Validate uploaded file
function validate_upload($file) {
    global $ALLOWED_TYPES;
    
    if ($file['size'] > MAX_SIZE) return 'File too large (max 10MB)';
    
    $mime = detect_mime($file['tmp_name']);
    if (!isset($ALLOWED_TYPES[$mime])) return 'Invalid file type (only JPG, PNG, WebP, PDF allowed)';
    
    return true;
}
