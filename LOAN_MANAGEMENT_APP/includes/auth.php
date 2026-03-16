<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('LOAN_STAFF_SESSION');
    session_start();
}

if (!defined('APP_BASE')) {
    $proto = 'https';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]);
    } elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
        $proto = $_SERVER['REQUEST_SCHEME'];
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $proto = 'https';
    }
    define('APP_BASE', $proto . '://' . $_SERVER['HTTP_HOST']);
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['return_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . APP_BASE . "/staff/login.php");
        exit;
    }
}

function require_roles($roles) {
    if (!is_logged_in()) {
        header("Location: " . APP_BASE . "/staff/login.php");
        exit;
    }
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, $roles, true)) {
        http_response_code(403);
        echo "<h2>403 Forbidden</h2>";
        echo "<p>You do not have permission to access this resource.</p>";
        echo "<p><a href='" . APP_BASE . "/staff/dashboard.php'>Return to Dashboard</a></p>";
        exit;
    }
}

function require_admin()               { require_roles(['ADMIN']); }
function require_manager()             { require_roles(['ADMIN', 'MANAGER']); }
function require_credit_investigator() { require_roles(['ADMIN', 'MANAGER', 'CREDIT_INVESTIGATOR']); }

function login_user($user) {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['user_id'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['full_name']  = $user['full_name'];
    $_SESSION['email']      = $user['email'] ?? '';
    $_SESSION['contact_no'] = $user['contact_no'] ?? '';
    $_SESSION['login_time'] = time();
}

function logout_user() {
    session_unset();
    session_destroy();
}

function get_role_display_name($role) {
    $names = [
        'ADMIN'               => 'Administrator',
        'MANAGER'             => 'Manager',
        'CREDIT_INVESTIGATOR' => 'Credit Investigator',
        'LOAN_OFFICER'        => 'Loan Officer',
        'CASHIER'             => 'Cashier',
        'CUSTOMER'            => 'Customer (Mobile Only)',
    ];
    return $names[$role] ?? $role;
}

function get_role_rank($role) {
    $ranks = [
        'ADMIN'               => 1,
        'MANAGER'             => 2,
        'CREDIT_INVESTIGATOR' => 3,
        'LOAN_OFFICER'        => 4,
        'CASHIER'             => 5,
        'CUSTOMER'            => 99
    ];
    return $ranks[$role] ?? 99;
}

function role_rank($role)     { return get_role_rank($role); }
function user_outranks($role) { return get_role_rank($_SESSION['role'] ?? '') < get_role_rank($role); }

function get_system_settings() {
    if (isset($_SESSION['system_settings'])) return $_SESSION['system_settings'];
    try {
        $conn = db();
        $check = $conn->query("SHOW TABLES LIKE 'system_settings'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
                setting_id    INT AUTO_INCREMENT PRIMARY KEY,
                system_name   VARCHAR(255) NOT NULL DEFAULT 'CredenceLend',
                logo_path     VARCHAR(500),
                primary_color VARCHAR(7) NOT NULL DEFAULT '#2c3ec5',
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            $conn->query("INSERT IGNORE INTO system_settings (system_name, primary_color)
                          VALUES ('CredenceLend', '#2c3ec5')");
        }
        $stmt = $conn->prepare("SELECT system_name, logo_path, primary_color FROM system_settings LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if (!$result) $result = ['system_name' => 'CredenceLend', 'primary_color' => '#2c3ec5', 'logo_path' => ''];
        $_SESSION['system_settings'] = $result;
        return $result;
    } catch (Exception $e) {
        error_log("System settings error: " . $e->getMessage());
        return ['system_name' => 'CredenceLend', 'primary_color' => '#2c3ec5', 'logo_path' => ''];
    }
}

function check_session_timeout($timeout = 1800) {
    if (!is_logged_in()) return;
    $now = time();
    if (($now - ($_SESSION['last_activity'] ?? $now)) > $timeout) {
        logout_user();
        header("Location: " . APP_BASE . "/staff/login.php");
        exit;
    }
    $_SESSION['last_activity'] = $now;
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token']))
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token()) . '">';
}

function password_is_strong($pw) {
    return strlen($pw) >= 8
        && preg_match('/[A-Z]/', $pw)
        && preg_match('/[a-z]/', $pw)
        && preg_match('/[0-9]/', $pw)
        && preg_match('/[\W_]/', $pw);
}

function log_activity($action, $description, $p1 = null, $p2 = null, $p3 = null) {
    try {
        $conn = db();
        $user_id = $_SESSION['user_id'] ?? null;
        $conn->query("CREATE TABLE IF NOT EXISTS activity_log (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT,
            action      VARCHAR(100),
            description TEXT,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?,?,?)");
        $stmt->bind_param("iss", $user_id, $action, $description);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("log_activity failed: " . $e->getMessage());
    }
}
?>
