<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

try {
  $conn = db();
  
  // Check if payment_term column already exists
  $result = $conn->query("SHOW COLUMNS FROM loans LIKE 'payment_term'");
  
  if ($result && $result->num_rows == 0) {
    // Column doesn't exist, add it
    $conn->query("ALTER TABLE loans ADD COLUMN payment_term VARCHAR(20) NULL AFTER interest_rate");
    echo "<h2>Migration Complete</h2>";
    echo "<p>Added <b>payment_term</b> column to loans table.</p>";
  } else {
    echo "<h2>Migration Already Done</h2>";
    echo "<p>The <b>payment_term</b> column already exists in the loans table.</p>";
  }
  
  echo '<p><a href="' . APP_BASE . '/index.php">Go back to system</a></p>';
  
} catch (Exception $e) {
  echo "<h2>Migration Failed</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
