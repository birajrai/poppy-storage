<?php

$user = getenv('ADMIN_USER') ?: 'admin';
$pass_hash = getenv('ADMIN_PASS') ?: '';

if (!isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] !== $user ||
    !password_verify($_SERVER['PHP_AUTH_PW'] ?? '', $pass_hash)) {

    header('WWW-Authenticate: Basic realm="Poppy Storage Admin"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Unauthorized');
}

// CSRF token generation
function csrf_token() {
    $secret = getenv('CSRF_SECRET') ?: '';
    return hash_hmac('sha256', 'poppy_admin', $secret);
}

function validate_csrf() {
    $token = $_POST['_csrf'] ?? $_GET['_csrf'] ?? '';
    return hash_equals(csrf_token(), $token);
}
