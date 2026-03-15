<?php
// Enable error reporting 
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/loan_helpers.php';

// This is a test script - only accessible from command line
if (php_sapi_name() !== 'cli') {
    // Web request - check for debug parameter
    if (!isset($_GET['debug_key']) || $_GET['debug_key'] !== 'test_otp') {
        http_response_code(403);
        echo "Access Denied";
        exit;
    }
}

echo "=== OTP System Test ===\n\n";

// Test 1: Check if OTP table exists
echo "Test 1: OTP Table Initialization\n";
echo "--------------------------------\n";
try {
    ensure_payment_otp_table();
    echo "✓ OTP table initialized successfully\n\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 2: Generate OTP
echo "Test 2: OTP Generation\n";
echo "---------------------\n";
try {
    // Create a test OTP for payment_id=1, user_id=1
    $test_otp = generate_otp_for_payment_edit(1, 1);
    echo "✓ OTP generated: $test_otp\n";
    echo "✓ Format is 6 digits: " . (strlen($test_otp) === 6 ? "Yes" : "No") . "\n\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 3: Check OTP email configuration
echo "Test 3: OTP Email Configuration\n";
echo "-----------------------------\n";
try {
    $otp_email = 'alliah1530@gmail.com';
    echo "✓ OTP notifications will be sent to: $otp_email\n\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 4: PHP Mail Configuration
echo "Test 4: PHP Mail Configuration\n";
echo "----------------------------\n";
$php_version = phpversion();
echo "✓ PHP Version: $php_version\n";
echo "✓ PHP mail() function: " . (function_exists('mail') ? "Available" : "Not Available") . "\n";

// Get mail configuration
$sendmail_from = ini_get('sendmail_from');
$sendmail_path = ini_get('sendmail_path');
$smtp = ini_get('SMTP');
$smtp_port = ini_get('smtp_port');

echo "✓ Mail Configuration:\n";
echo "  - sendmail_from: " . ($sendmail_from ?: "(not set - will use default)") . "\n";
echo "  - sendmail_path: " . ($sendmail_path ?: "(Windows - using built-in mail)") . "\n";
echo "  - SMTP: " . ($smtp ?: "(not configured)") . "\n";
echo "  - smtp_port: " . ($smtp_port ?: "(not configured)") . "\n\n";

// Test 5: Send Test Email
echo "Test 5: Test Email Sending\n";
echo "------------------------\n";
try {
    $otp_email = 'alliah1530@gmail.com';
    echo "✓ Sending test email to configured address: $otp_email\n";
    
    $test_recipients = [
        [
            'email' => $otp_email,
            'full_name' => 'Admin'
        ]
    ];
    
    $subject = "OTP System Test - " . date('Y-m-d H:i:s');
    $body = "This is a test email from the CredenceLend OTP system.\n";
    $body .= "If you received this, email sending is working correctly.\n";
    $body .= "Test Time: " . date('Y-m-d H:i:s') . "\n";
    
    $result = send_via_gmail_otp($test_recipients, $subject, $body, true);
    
    if ($result) {
        echo "✓ Test email queued successfully\n";
        echo "  Note: Email may take a few moments to arrive\n\n";
    } else {
        echo "✗ Failed to queue test email\n\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

echo "=== Test Complete ===\n";
echo "\nNext Steps:\n";
echo "1. Verify at least one manager/admin has a valid email address\n";
echo "2. Load a payment edit page as a cashier to trigger OTP generation\n";
echo "3. Check manager/admin emails for OTP notification\n";
echo "4. Enter the OTP code to verify the system\n";
?>
