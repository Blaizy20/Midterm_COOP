<?php
require_once __DIR__ . '/../includes/db.php';

try {
  // Check if columns exist before adding
  $result = db()->query("SHOW COLUMNS FROM payments LIKE 'bank_reference_no'");
  if ($result->num_rows === 0) {
    db()->query("ALTER TABLE payments ADD COLUMN bank_reference_no VARCHAR(100) NULL AFTER account_holder");
    echo "✓ Added bank_reference_no column to payments table<br>";
  } else {
    echo "- bank_reference_no column already exists<br>";
  }

  $result = db()->query("SHOW COLUMNS FROM payments LIKE 'gcash_reference_no'");
  if ($result->num_rows === 0) {
    db()->query("ALTER TABLE payments ADD COLUMN gcash_reference_no VARCHAR(100) NULL AFTER bank_reference_no");
    echo "✓ Added gcash_reference_no column to payments table<br>";
  } else {
    echo "- gcash_reference_no column already exists<br>";
  }

  echo "<p style='color:green'><strong>Migration completed successfully!</strong></p>";
} catch (Exception $e) {
  echo "<p style='color:red'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
