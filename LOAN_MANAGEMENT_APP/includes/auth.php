<?php
/**
 * Refactored Authentication & Authorization for Staff-Only Web Portal
 * 
 * This file handles staff authentication. Customer login is NO LONGER available
 * on the web portal. All customer-related functionality has been moved to mobile app.
 * 
 * The database still contains customer/user data for future mobile API integration.
 * This system is modular and ready for API gateway integration.
 */

require_once __DIR__ . '/pdo_db.php';
require_once __DIR__ . '/AuthService.php';
require_once __DIR__ . '/db.php'; // Keep for backward compatibility with existing code

// Only staff/web portal sessions are managed here
// Session name for web staff portal
if (!isset($_SESSION)) {
    session_name('LOAN_STAFF_SESSION');
    session_start();
}

// Auto-detect app base URL
if (!defined('APP_BASE')) {
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    // Strip if running in subfolder
    $dir = preg_replace('#/(staff|setup)$#', '', $dir);
    if ($dir === '') $dir = '/';
    define('APP_BASE', $dir);
}

function app_base() { 
    return APP_BASE; 
}

function url_for($path = '') {
    if ($path === '' || $path === null) return APP_BASE;
    if ($path[0] !== '/') $path = '/' . $path;
    return rtrim(APP_BASE, '/') . $path;
}

/**
 * Check if user is logged in
 */
function is_logged_in() { 
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']); 
}

/**
 * Get current logged-in user
 */
function current_user() {
    if (!is_logged_in()) return null;
    
    try {
        $authService = new AuthService();
        $user = $authService->getUserById($_SESSION['user_id']);
        return $user;
    } catch (Exception $e) {
        error_log("Current user error: " . $e->getMessage());
        return null;
    }
}

/**
 * Require staff login (redirects to staff login if not logged in)
 */
function require_login() {
    if (!is_logged_in()) {
        // Store return URL for redirect after login
        $_SESSION['return_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . APP_BASE . "/staff/login.php");
        exit;
    }
}

/**
 * Require customer login (alias for require_login for customer pages)
 * Note: Customer login functionality is maintained for backward compatibility
 */
function require_login_customer() {
    require_login();
}

/**
 * Require specific roles
 * @param array $roles Array of allowed roles (e.g., ['ADMIN', 'MANAGER'])
 */
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

/**
 * Require admin role specifically
 */
function require_admin() {
    require_roles(['ADMIN']);
}

/**
 * Require manager or admin
 */
function require_manager() {
    require_roles(['ADMIN', 'MANAGER']);
}

/**
 * Require credit investigator or higher
 */
function require_credit_investigator() {
    require_roles(['ADMIN', 'MANAGER', 'CREDIT_INVESTIGATOR']);
}

/**
 * Login a staff user (sets session variables)
 */
function login_user($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['contact_no'] = $user['contact_no'] ?? '';
    $_SESSION['login_time'] = time();
}

/**
 * Logout current user
 */
function logout_user() {
    session_unset();
    session_destroy();
}

/**
 * Get role display name
 */
function get_role_display_name($role) {
    $roleNames = [
        'ADMIN' => 'Administrator',
        'MANAGER' => 'Manager',
        'CREDIT_INVESTIGATOR' => 'Credit Investigator',
        'LOAN_OFFICER' => 'Loan Officer',
        'CASHIER' => 'Cashier',
        'CUSTOMER' => 'Customer (Mobile Only)' // Informational only
    ];
    return $roleNames[$role] ?? $role;
}

/**
 * Get role rank for hierarchical comparisons
 * Lower number = higher authority
 */
function get_role_rank($role) {
    $ranks = [
        'ADMIN' => 1,
        'MANAGER' => 2,
        'CREDIT_INVESTIGATOR' => 3,
        'LOAN_OFFICER' => 4,
        'CASHIER' => 5,
        'CUSTOMER' => 99 // Not applicable for web
    ];
    return $ranks[$role] ?? 99;
}

/**
 * Check if current user has greater authority than specified role
 */
function user_outranks($role) {
    $userRole = $_SESSION['role'] ?? '';
    return get_role_rank($userRole) < get_role_rank($role);
}

/**
 * Backward compatibility: use get_role_rank
 */
function role_rank($role) {
    return get_role_rank($role);
}

/**
 * Get system settings (cached in session when possible)
 */
function get_system_settings() {
    // Try to use session cache
    if (isset($_SESSION['system_settings'])) {
        return $_SESSION['system_settings'];
    }
    
    // Otherwise query database (create table if needed)
    try {
        $conn = db();
        
        // Ensure table exists with correct structure
        $check_table = $conn->query("SHOW TABLES LIKE 'system_settings'");
        if (!$check_table || $check_table->num_rows === 0) {
            $sql = "CREATE TABLE IF NOT EXISTS system_settings (
                        setting_id INT AUTO_INCREMENT PRIMARY KEY,
                        system_name VARCHAR(255) NOT NULL DEFAULT 'CredenceLend',
                        logo_path VARCHAR(500),
                        primary_color VARCHAR(7) NOT NULL DEFAULT '#2c3ec5',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_setting (setting_id)
                    ) ENGINE=InnoDB";
            $conn->query($sql);
            
            // Insert default row
            $conn->query("INSERT IGNORE INTO system_settings (system_name, logo_path, primary_color) 
                         VALUES ('CredenceLend', '" . APP_BASE . "/assets/img/logo.png', '#2c3ec5')");
        }
        
        $sql = "SELECT system_name, logo_path, primary_color FROM system_settings LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            $result = [
                'system_name' => 'CredenceLend',
                'primary_color' => '#2c3ec5',
                'logo_path' => APP_BASE . '/assets/img/logo.png'
            ];
        }
        
        $_SESSION['system_settings'] = $result;
        return $result;
    } catch (Exception $e) {
        error_log("System settings error: " . $e->getMessage());
        return [
            'system_name' => 'CredenceLend',
            'primary_color' => '#2c3ec5',
            'logo_path' => APP_BASE . '/assets/img/logo.png'
        ];
    }
}

/**
 * Update system setting
 */
function update_system_setting($key, $value) {
    try {
        $db = Database::getInstance();
        $jsonValue = is_array($value) ? json_encode($value) : $value;
        
        $sql = "INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?";
        $db->exec($sql, [$key, $jsonValue, $jsonValue]);
        
        // Clear session cache
        unset($_SESSION['system_settings']);
        
        return true;
    } catch (Exception $e) {
        error_log("Update setting error: " . $e->getMessage());
        return false;
    }
}

/**
 * Security: Check session timeout (30 minutes of inactivity)
 * Call this in critical pages
 */
function check_session_timeout($timeout = 1800) {
    if (!is_logged_in()) return;
    
    $currentTime = time();
    $lastActivity = $_SESSION['last_activity'] ?? $currentTime;
    
    if (($currentTime - $lastActivity) > $timeout) {
        logout_user();
        $_SESSION['timeout_message'] = "Your session has expired. Please login again.";
        header("Location: " . APP_BASE . "/staff/login.php");
        exit;
    }
    
    $_SESSION['last_activity'] = $currentTime;
}

/**
 * Generate CSRF token for forms
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF input field HTML
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token()) . '">';
}

/**
 * Password strength validation
 */
function password_is_strong($pw) {
    return AuthService::isPasswordStrong($pw);
}
?>