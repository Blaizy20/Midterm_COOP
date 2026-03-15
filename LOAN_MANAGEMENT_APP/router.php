<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf'];
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

if (in_array($ext, $staticExtensions) && file_exists(__DIR__ . $uri)) {
    return false; // let PHP's built‑in server serve /assets/...
}

// fallback to index.php (or staff/login.php if that’s your main)
require __DIR__ . '/index.php';
