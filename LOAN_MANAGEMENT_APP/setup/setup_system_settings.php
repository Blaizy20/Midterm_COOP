<?php
/**
 * Setup script for system_settings table
 * Run this script once to create the system_settings table
 */
require_once __DIR__ . '/../includes/db.php';

try {
  // Create system_settings table
  $sql = "CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    system_name VARCHAR(255) NOT NULL DEFAULT 'CredenceLend',
    logo_path VARCHAR(500),
    primary_color VARCHAR(7) NOT NULL DEFAULT '#2c3ec5',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_setting (setting_id)
  )";
  
  $stmt = q($sql);
  
  // Check if default row exists
  $check = fetch_one(q("SELECT setting_id FROM system_settings LIMIT 1"));
  
  if (!$check) {
    // Insert default row
    q("INSERT INTO system_settings (system_name, logo_path, primary_color) 
      VALUES (?, ?, ?)", 
      "sss", 
      ['CredenceLend', '/LOAN_MANAGEMENT_APP/assets/img/logo.png', '#2c3ec5']);
  }
  
  echo "✓ system_settings table created/verified successfully!";
  echo "<br>You can now access Settings from Admin/Manager menu.";
  
} catch (Exception $e) {
  echo "✗ Error: " . htmlspecialchars($e->getMessage());
}
?>
