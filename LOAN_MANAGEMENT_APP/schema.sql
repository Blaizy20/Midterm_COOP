
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS requirements;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  reset_token VARCHAR(255) NULL,
  reset_token_expiry DATETIME NULL,
  full_name VARCHAR(120) NOT NULL,
  role ENUM('ADMIN','MANAGER','CREDIT_INVESTIGATOR','LOAN_OFFICER','CASHIER','CUSTOMER') NOT NULL,
  contact_no VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE customers (
  customer_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  customer_no VARCHAR(30) NOT NULL UNIQUE,
  first_name VARCHAR(60) NOT NULL,
  last_name VARCHAR(60) NOT NULL,
  contact_no VARCHAR(30) NOT NULL,
  email VARCHAR(120) NULL,
  province VARCHAR(80) NULL,
  city VARCHAR(80) NULL,
  barangay VARCHAR(80) NULL,
  street VARCHAR(120) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT DEFAULT 1,
  CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE loans (
  loan_id INT AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(40) NOT NULL UNIQUE,
  customer_id INT NOT NULL,
  principal_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  payment_term VARCHAR(20) NULL,
  term_months INT NOT NULL DEFAULT 0,
  total_payable DECIMAL(12,2) NULL,
  remaining_balance DECIMAL(12,2) NULL,
  status ENUM('PENDING','CI_REVIEWED','APPROVED','DENIED','ACTIVE','OVERDUE','CLOSED') NOT NULL DEFAULT 'PENDING',
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ci_by INT NULL,
  ci_at TIMESTAMP NULL,
  manager_by INT NULL,
  manager_at TIMESTAMP NULL,
  loan_officer_id INT NULL,
  activated_at TIMESTAMP NULL,
  due_date DATE NULL,
  notes TEXT NULL,
  CONSTRAINT fk_loans_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
  CONSTRAINT fk_loans_ci FOREIGN KEY (ci_by) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT fk_loans_manager FOREIGN KEY (manager_by) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT fk_loans_officer FOREIGN KEY (loan_officer_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE requirements (
  requirement_id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  requirement_code VARCHAR(40) NOT NULL,
  requirement_name VARCHAR(120) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_by_role ENUM('CUSTOMER','STAFF') NOT NULL,
  uploaded_by_user INT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(255) NULL,
  KEY idx_requirement_code (requirement_code),
  KEY idx_requirement_name (requirement_name),
  CONSTRAINT fk_req_loan FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
  CONSTRAINT fk_req_user FOREIGN KEY (uploaded_by_user) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_date DATE NOT NULL,
  method VARCHAR(30) NOT NULL DEFAULT 'CASH',
  cheque_number VARCHAR(50) NULL,
  cheque_date DATE NULL,
  bank_name VARCHAR(100) NULL,
  account_holder VARCHAR(100) NULL,
  bank_reference_no VARCHAR(100) NULL,
  gcash_reference_no VARCHAR(100) NULL,
  or_no VARCHAR(40) NOT NULL UNIQUE,
  loan_officer_id INT NULL,
  received_by INT NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_loan FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_officer FOREIGN KEY (loan_officer_id) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_user FOREIGN KEY (received_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
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
  KEY idx_loan_id (loan_id),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_loan FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE money_release_vouchers (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS interest_rate_history (
  history_id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  old_rate DECIMAL(5,2) NOT NULL,
  new_rate DECIMAL(5,2) NOT NULL,
  changed_by INT NOT NULL,
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_irh_loan FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
  CONSTRAINT fk_irh_user FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_loan_id (loan_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_settings (
  setting_id INT AUTO_INCREMENT PRIMARY KEY,
  system_name VARCHAR(255) NOT NULL DEFAULT 'CredenceLend',
  logo_path VARCHAR(500),
  primary_color VARCHAR(7) NOT NULL DEFAULT '#2c3ec5',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_setting (setting_id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS=1;
