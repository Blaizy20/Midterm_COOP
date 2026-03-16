<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
$staticExtensions = ['css','js','png','jpg','jpeg','gif','svg','ico','woff','woff2','ttf'];
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
if (in_array($ext, $staticExtensions) && file_exists(__DIR__ . $uri)) {
    return false;
}

// Temporary: allow genhash directly
if ($uri === '/genhash.php' && file_exists(__DIR__ . '/genhash.php')) {
    require __DIR__ . '/genhash.php';
    exit;
}

// Route to actual PHP file
$file = __DIR__ . rtrim($uri, '/');
if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    require $file; exit;
}
if (is_file($file . '.php')) {
    require $file . '.php'; exit;
}
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/staff/login.php'; exit;
}

http_response_code(404);
echo '404 - Page not found';
