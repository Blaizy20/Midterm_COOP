<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$staticExtensions = ['css','js','png','jpg','jpeg','gif','svg','ico','woff','woff2','ttf'];
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

if (in_array($ext, $staticExtensions) && file_exists(__DIR__ . $uri)) {
    return false;
}

// Simple fallback to staff/login.php for now
require __DIR__ . '/staff/login.php';
