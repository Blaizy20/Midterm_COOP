<?php
/**
 * Migration: Add password reset token columns to users table
 * Access via browser: http://localhost/LOAN_MANAGEMENT_APP/setup/migrate_password_reset.php
 */

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'loan_management';

try {
  $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  
  if (!$conn) {
    die("✗ Connection failed: " . mysqli_connect_error());
  }
  
  mysqli_set_charset($conn, 'utf8mb4');
  
  // Check if columns already exist
  $result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'reset_token'");
  
  if (mysqli_num_rows($result) === 0) {
    // Add reset_token and reset_token_expiry columns
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL AFTER password_hash");
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN reset_token_expiry DATETIME NULL AFTER reset_token");
    echo "<h2>✓ Migration Successful!</h2>";
    echo "<p>Added password reset columns to users table.</p>";
  } else {
    echo "<h2>✓ Already Migrated</h2>";
    echo "<p>Password reset columns already exist.</p>";
  }
  
  mysqli_close($conn);
  
} catch (Exception $e) {
  echo "<h2>✗ Migration Failed</h2>";
  echo "<p>" . $e->getMessage() . "</p>";
}
?>
