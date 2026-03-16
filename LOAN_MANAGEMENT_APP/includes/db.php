<?php
// ============================================================
// ENVIRONMENT DETECTION
// ============================================================
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isHttps ? 'https' : 'http';
define('APP_BASE', $protocol . '://' . $_SERVER['HTTP_HOST']);

// ============================================================
// DATABASE CONFIG
// ============================================================
if (getenv('MYSQLHOST')) {
    // Railway
    $DB_HOST = getenv('yamabiko.proxy.rlwy.net');
    $DB_PORT = (int)(getenv('MYSQLPORT') ?: 27377);
    $DB_USER = getenv('root');
    $DB_PASS = getenv('WrLSrSxuzKAnSEJlrqjKYhrDohWxoIQo');
    $DB_NAME = getenv('MYSQLDATABASE') ?: 'railway';

} elseif (getenv('DB_HOST')) {
    // Web host (cPanel, Hostinger, etc.)
    $DB_HOST = getenv('DB_HOST');
    $DB_PORT = (int)(getenv('DB_PORT') ?: 3306);
    $DB_USER = getenv('DB_USER');
    $DB_PASS = getenv('DB_PASS');
    $DB_NAME = getenv('DB_NAME');

} else {
    // Local (XAMPP)
    $DB_HOST = '127.0.0.1';
    $DB_PORT = 3306;
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_NAME = 'cooperative_loan_db';
}

// ============================================================
// SINGLETON CONNECTION
// ============================================================
function db() {
    global $DB_HOST, $DB_PORT, $DB_USER, $DB_PASS, $DB_NAME;
    static $conn = null;

    if ($conn === null) {
        $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, '', $DB_PORT);
        if ($conn->connect_error) {
            throw new Exception("DB connection failed: " . $conn->connect_error);
        }
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
