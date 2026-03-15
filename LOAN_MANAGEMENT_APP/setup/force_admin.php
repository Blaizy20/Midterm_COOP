<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';

try {
    $conn = db();
    
    // 1. Temporarily disable foreign key checks to allow TRUNCATE
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    // 2. Clear the users table
    $conn->query("TRUNCATE TABLE users");

    // 3. Re-enable foreign key checks for security
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    $username = 'admin';
    $password = 'Password123!';
    $name = 'Admin User';
    $role = 'ADMIN';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $hash, $name, $role);
    
    if ($stmt->execute()) {
        echo "<h2 style='color:green;'>Admin Created Successfully!</h2>";
        echo "<b>Username:</b> admin<br>";
        echo "<b>Password:</b> Password123!<br>";
        echo "<br><a href='../staff/login.php'>Go to Login Page</a>";
    } else {
        throw new Exception("Insert failed: " . $stmt->error);
    }

} catch (Exception $e) {
    echo "<h2 style='color:red;'>Error Detected:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>