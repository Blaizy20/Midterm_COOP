<?php
require_once __DIR__ . '/includes/db.php';

$conn = db();

// Check if activity_logs table exists
$result = $conn->query("SHOW TABLES LIKE 'activity_logs'");

if ($result && $result->num_rows > 0) {
    echo "✓ activity_logs table EXISTS<br>";
    
    // Show table structure
    $struct = $conn->query("DESCRIBE activity_logs");
    echo "<pre>";
    while ($row = $struct->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
    
    // Show sample data
    $data = $conn->query("SELECT * FROM activity_logs ORDER BY log_id DESC LIMIT 5");
    echo "<h3>Recent Activity Logs:</h3>";
    echo "<pre>";
    while ($row = $data->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "✗ activity_logs table DOES NOT EXIST<br>";
    echo "Creating table now...<br>";
    
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
        echo "✓ Table 'activity_logs' created successfully!";
    } else {
        echo "✗ Error creating table: " . $conn->error;
    }
}
?>
