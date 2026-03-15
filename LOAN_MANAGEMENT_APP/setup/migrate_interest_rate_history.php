<?php
// Migration: Create interest rate history table
require_once __DIR__ . '/../includes/db.php';

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$sql = "CREATE TABLE IF NOT EXISTS interest_rate_history (
  history_id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  old_rate DECIMAL(5,2) NOT NULL,
  new_rate DECIMAL(5,2) NOT NULL,
  changed_by INT NOT NULL,
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_irh_loan FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
  CONSTRAINT fk_irh_user FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_loan_id (loan_id)
) ENGINE=InnoDB";

if ($conn->query($sql) === TRUE) {
    echo "Table 'interest_rate_history' created successfully!";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
