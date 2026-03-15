<?php
// Migration: Create activity logs table
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'loan_management';

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$sql = "CREATE TABLE IF NOT EXISTS activity_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  user_role VARCHAR(50) NOT NULL,
  action VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  loan_id INT NULL,
  customer_id INT NULL,
  reference_no VARCHAR(40) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created_at (created_at),
  KEY idx_user_id (user_id),
  KEY idx_action (action),
  KEY idx_loan_id (loan_id)
) ENGINE=InnoDB";

if ($conn->query($sql) === TRUE) {
    echo "Table 'activity_logs' created successfully!";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
