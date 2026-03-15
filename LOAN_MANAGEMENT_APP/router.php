<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly (css, js, images, fonts)
$staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf'];
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

if (in_array($ext, $staticExtensions) && file_exists(__DIR__ . $uri)) {
    return false; // Let PHP's built-in server serve the file
}

// Route everything else to index.php
require_once __DIR__ . '/index.php';
