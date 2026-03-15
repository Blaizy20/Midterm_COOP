<?php
require_once __DIR__ . '/../includes/db.php';
// include auth which auto-defines APP_BASE (used below)
require_once __DIR__ . '/../includes/auth.php';

try {
  $conn = db();
  $sql = file_get_contents(__DIR__ . '/../schema.sql');
  // Execute multi statements safely
  $conn->multi_query($sql);
  do { 
    if ($res = $conn->store_result()) { $res->free(); }
  } while ($conn->more_results() && $conn->next_result());

  // Seed users (admin/manager/ci/cashier/loan officer)
  $seed = [
    ['admin','Admin User','ADMIN'],
    ['manager','Manager User','MANAGER'],
    ['ci','Credit Investigator','CREDIT_INVESTIGATOR'],
    ['cashier','Cashier User','CASHIER'],
    ['loanofficer','Loan Officer','LOAN_OFFICER']
  ];
  foreach ($seed as $u) {
    [$username,$name,$role] = $u;
    $hash = password_hash('Password123!', PASSWORD_DEFAULT);
    $stmt = q("INSERT IGNORE INTO users (username,password_hash,full_name,role) VALUES (?,?,?,?)", "ssss", [$username,$hash,$name,$role]);
  }

  // Insert default system settings if not exists
  $check_settings = fetch_one(q("SELECT setting_id FROM system_settings LIMIT 1"));
  if (!$check_settings) {
    q("INSERT INTO system_settings (system_name, logo_path, primary_color) VALUES (?,?,?)", 
      "sss", ['CredenceLend', APP_BASE . '/assets/img/logo.png', '#2c3ec5']);
  }

  echo "<h2>Setup Complete</h2>";
  echo "<p>Database <b>loan_management</b> created/reset and seeded.</p>";
  echo "<ul>
    <li>admin / Password123!</li>
    <li>manager / Password123!</li>
    <li>ci / Password123!</li>
    <li>cashier / Password123!</li>
    <li>loanofficer / Password123!</li>
  </ul>";
  echo '<p><a href="' . APP_BASE . '/staff/login.php">Go to Staff Login</a></p>';
} catch (Exception $e) {
  echo "<h2>Setup Failed</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>