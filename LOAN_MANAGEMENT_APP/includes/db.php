<?php
// ============================================================
// ENVIRONMENT DETECTION
// ============================================================

// Detect HTTPS correctly (works behind Railway/cPanel proxies)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isHttps ? 'https' : 'http';
define('APP_BASE', $protocol . '://' . $_SERVER['HTTP_HOST']);

// ============================================================
// DATABASE CONFIG
// Automatically detects: Railway → Web Host → Localhost
// ============================================================

if (getenv('MYSQLHOST')) {
    // --- RAILWAY ---
    $DB_HOST = getenv('MYSQLHOST');
    $DB_PORT = (int)(getenv('MYSQLPORT') ?: 3306);
    $DB_USER = getenv('MYSQLUSER');
    $DB_PASS = getenv('MYSQLPASSWORD');
    $DB_NAME = getenv('MYSQLDATABASE') ?: 'railway';

} elseif (getenv('DB_HOST')) {
    // --- ANY WEB HOST (cPanel, Hostinger, etc.) ---
    // Set these in your hosting control panel's environment variables
    // OR replace the getenv() with your actual credentials below
    $DB_HOST = getenv('yamabiko.proxy.rlwy.net');
    $DB_PORT = (int)(getenv('DB_PORT') ?: 27377);
    $DB_USER = getenv('root');
    $DB_PASS = getenv('WrLSrSxuzKAnSEJlrqjKYhrDohWxoIQo');
    $DB_NAME = getenv('railway');

} else {
    // --- LOCAL (XAMPP / WAMP) ---
    $DB_HOST = '127.0.0.1';
    $DB_PORT = 3306;
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_NAME = 'cooperative_loan_db';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ============================================================
// SINGLETON CONNECTION
// ============================================================
function db() {
    global $DB_HOST, $DB_PORT, $DB_USER, $DB_PASS, $DB_NAME;
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, '', $DB_PORT);
        $conn->set_charset('utf8mb4');
        $conn->select_db($DB_NAME);
    }
    return $conn;
}

// ============================================================
// QUERY HELPERS
// ============================================================
function _bind_params($stmt, $types, $params) {
    if ($types === '' || $params === null || count($params) === 0) return;
    $bind   = [];
    $bind[] = $types;
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function q($sql, $types = '', $params = []) {
    $conn = db();
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    _bind_params($stmt, $types, $params);
    $stmt->execute();
    return $stmt;
}

function fetch_all($stmt) {
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_one($stmt) {
    $res = $stmt->get_result();
    return $res ? $res->fetch_assoc() : null;
}
?>
