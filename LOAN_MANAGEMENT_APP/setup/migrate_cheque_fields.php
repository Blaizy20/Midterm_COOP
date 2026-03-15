<?php
// Migration: Add cheque fields to payments table
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'loan_management';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Check if cheque fields already exist
$result = $conn->query("SHOW COLUMNS FROM payments LIKE 'cheque_number'");
if ($result->num_rows > 0) {
    echo "Cheque fields already exist in payments table.";
    $conn->close();
    exit;
}

// Add cheque fields
$sql = "ALTER TABLE payments ADD COLUMN cheque_number VARCHAR(50) NULL AFTER method,
                               ADD COLUMN cheque_date DATE NULL AFTER cheque_number,
                               ADD COLUMN bank_name VARCHAR(100) NULL AFTER cheque_date,
                               ADD COLUMN account_holder VARCHAR(100) NULL AFTER bank_name";

if ($conn->query($sql) === TRUE) {
    echo "Cheque fields added successfully to payments table!";
} else {
    echo "Error adding cheque fields: " . $conn->error;
}

$conn->close();
?>
