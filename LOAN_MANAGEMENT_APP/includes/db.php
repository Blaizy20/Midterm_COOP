<?php
// Detect HTTPS correctly behind Railway's proxy
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$protocol = $isHttps ? 'https' : 'http';
define('APP_BASE', $protocol . '://' . $_SERVER['HTTP_HOST']);

// Database Config (Railway env vars with localhost fallback)
$DB_HOST = getenv('yamabiko.proxy.rlwy.net')     ?: 'localhost';
$DB_PORT = (int)(getenv('27377') ?: 3306);
$DB_USER = getenv('root')     ?: 'root';
$DB_PASS = getenv('WrLSrSxuzKAnSEJlrqjKYhrDohWxoIQo') ?: '';
$DB_NAME = getenv('railway') ?: 'loan_management';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS);

function db() {
  global $DB_HOST, $DB_PORT, $DB_USER, $DB_PASS, $DB_NAME;
  static $conn = null;

  if ($conn === null) {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, '', $DB_PORT);
    $conn->set_charset('utf8mb4');
    $conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($DB_NAME);
  }
  return $conn;
}

function _bind_params($stmt, $types, $params) {
  if ($types === '' || $params === null || count($params) === 0) return;
  $bind = [];
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
