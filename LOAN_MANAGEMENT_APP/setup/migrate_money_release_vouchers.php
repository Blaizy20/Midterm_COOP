<?php
// This script creates the money_release_vouchers table
// Run this from setup/migrate_money_release_vouchers.php or include it in your setup

require_once __DIR__ . '/../includes/db.php';

$conn = db();

$sql = "CREATE TABLE IF NOT EXISTS money_release_vouchers (
  voucher_id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  voucher_no VARCHAR(40) NOT NULL UNIQUE,
  release_date DATE NOT NULL,
  check_no VARCHAR(40) NULL,
  check_amount DECIMAL(12,2) NULL,
  explanation TEXT NULL,
  prepared_by INT NULL,
  approved_by INT NULL,
  audited_by INT NULL,
  received_by_name VARCHAR(120) NULL,
  received_by_date DATE NULL,
  voucher_data LONGTEXT NULL,
  status ENUM('DRAFT','RELEASED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_voucher_loan FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
  CONSTRAINT fk_voucher_prepared FOREIGN KEY (prepared_by) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT fk_voucher_approved FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT fk_voucher_audited FOREIGN KEY (audited_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_loan_id (loan_id),
  KEY idx_voucher_no (voucher_no),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
  echo "✓ money_release_vouchers table created successfully.";
} else {
  echo "✗ Error creating table: " . $conn->error;
}
?>
