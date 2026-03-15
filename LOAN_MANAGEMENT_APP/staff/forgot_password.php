<?php
require_once __DIR__ . '/../includes/auth.php';

$msg = ''; $err='';
$step = $_GET['step'] ?? '1'; // Step 1: Request reset, Step 2: Reset password

if ($step == '1' && $_SERVER['REQUEST_METHOD']==='POST') {
  // Step 1: User requests password reset via email
  $email = trim($_POST['email'] ?? '');
  
  if (empty($email)) {
    $err = 'Please enter your email address.';
  } else {
    $u = fetch_one(q("SELECT user_id, email, full_name, role FROM users WHERE email=? AND role != 'CUSTOMER'", "s", [$email]));
    
    if (!$u) {
      $err = 'No staff account found with this email.';
    } else {
      // Generate reset token (valid for 1 hour)
      $token = bin2hex(random_bytes(32));
      $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
      
      // Debug logging
      $debug_log = __DIR__ . '/../logs/token_debug.txt';
      @file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Generated token: " . htmlspecialchars($token) . " for email: {$email}\n", FILE_APPEND);
      @file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Token expiry: {$expiry}\n", FILE_APPEND);
      
      // Store token in database
      q("UPDATE users SET reset_token=?, reset_token_expiry=? WHERE user_id=?", "ssi", [$token, $expiry, $u['user_id']]);
      
// Send email with reset link (using PHP mail function)
      $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/forgot_password.php?step=2&token=" . $token;
      
      $to = $email;
      $subject = 'Password Reset Request - CredenceLend';
      
      $body = "
        <html><body>
        <h2>Password Reset Request</h2>
        <p>Hello " . htmlspecialchars($u['full_name']) . ",</p>
        <p>You have requested to reset your password. Click the link below to proceed:</p>
        <p><a href='{$resetLink}' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Reset Password</a></p>
        <p>Or copy this link: {$resetLink}</p>
        <p><strong>This link will expire in 1 hour.</strong></p>
        <p>If you did not request this reset, please ignore this email.</p>
        <p>Best regards,<br>CredenceLend</p>
        </body></html>
      ";
      
      // Send via PHP mail function
      $headers = "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=UTF-8\r\n";
      $headers .= "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
      
      $emailSent = @mail($to, $subject, $body, $headers);
      
      if ($emailSent) {
        $msg = 'A password reset email has been sent to <strong>' . htmlspecialchars($email) . '</strong>. Please check your inbox.';
      } else {
        // Try Gmail SMTP
        $emailSent = send_via_gmail($to, $subject, $body);
        
        if ($emailSent) {
          $msg = 'A password reset email has been sent to <strong>' . htmlspecialchars($email) . '</strong>. Please check your Gmail inbox.';
        } else {
          $err = 'Error sending email. Please try again later.';
        }
      }
    }
  }
} else if ($step == '2' && $_SERVER['REQUEST_METHOD']==='POST') {
  // Step 2: Staff resets password using token
  $token = trim($_POST['token'] ?? $_GET['token'] ?? '');
  $newpw = $_POST['new_password'] ?? '';
  $conf = $_POST['confirm_password'] ?? '';
  
  // Debug logging
  $debug_log = __DIR__ . '/../logs/token_debug.txt';
  @file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Received token: " . htmlspecialchars($token) . "\n", FILE_APPEND);
  
  if (empty($token)) {
    $err = 'Invalid reset token.';
  } else if ($newpw !== $conf) {
    $err = 'Passwords do not match.';
  } else if (!password_is_strong($newpw)) {
    $err = 'Password must be 8+ chars with upper, lower, number, special.';
  } else {
    // Verify token exists and is not expired
    $u = fetch_one(q("SELECT user_id FROM users WHERE reset_token=?", "s", [$token]));
    
    @file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Query result: " . ($u ? "Found user " . $u['user_id'] : "No user found") . "\n", FILE_APPEND);
    
    if (!$u) {
      $err = 'Invalid or expired reset token.';
    } else {
      // Update password and clear token
      $hash = password_hash($newpw, PASSWORD_DEFAULT);
      q("UPDATE users SET password_hash=?, reset_token=NULL, reset_token_expiry=NULL WHERE user_id=?", "si", [$hash, $u['user_id']]);
      $msg = 'Password has been reset successfully. You may now login.';
    }
  }
}

// Function to send email via Gmail SMTP
function send_via_gmail($to, $subject, $body) {
  $gmail_user = 'alliah1530@gmail.com';
  $gmail_pass = 'mjnz fexk mofy cgxw';
  $smtp_host = 'smtp.gmail.com';
  $smtp_port = 465;
  
  try {
    // Connect to SMTP server with SSL
    $socket = @fsockopen('ssl://' . $smtp_host, $smtp_port, $errno, $errstr, 5);
    
    if (!$socket) {
      return false;
    }
    
    stream_set_timeout($socket, 5);
    
    // Read greeting
    $response = @fgets($socket, 1024);
    if (!$response || strpos($response, '220') === false) {
      @fclose($socket);
      return false;
    }
    
    // Send EHLO
    @fputs($socket, "EHLO localhost\r\n");
    $response = @fgets($socket, 1024);
    while ($response && strpos($response, '250 ') === false) {
      $response = @fgets($socket, 1024);
    }
    
    // Authenticate
    @fputs($socket, "AUTH LOGIN\r\n");
    @fgets($socket, 1024);
    
    @fputs($socket, base64_encode($gmail_user) . "\r\n");
    @fgets($socket, 1024);
    
    @fputs($socket, base64_encode($gmail_pass) . "\r\n");
    $response = @fgets($socket, 1024);
    
    if (!$response || strpos($response, '235') === false) {
      @fclose($socket);
      return false;
    }
    
    // Send MAIL FROM
    @fputs($socket, "MAIL FROM: <" . $gmail_user . ">\r\n");
    @fgets($socket, 1024);
    
    // Send RCPT TO
    @fputs($socket, "RCPT TO: <" . $to . ">\r\n");
    @fgets($socket, 1024);
    
    // Send DATA
    @fputs($socket, "DATA\r\n");
    @fgets($socket, 1024);
    
    // Prepare email
    $headers = "From: " . $gmail_user . "\r\n";
    $headers .= "To: " . $to . "\r\n";
    $headers .= "Subject: " . $subject . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "\r\n";
    
    $message = $headers . $body . "\r\n.\r\n";
    @fputs($socket, $message);
    
    $response = @fgets($socket, 1024);
    
    // Send QUIT
    @fputs($socket, "QUIT\r\n");
    @fclose($socket);
    
    return true;
  } catch (Exception $e) {
    return false;
  }
}

// Deprecated
function send_email_via_gmail($to, $subject, $body) {
  // This is now deprecated - mail() is called directly above
  return false;
}
?>
<!doctype html>
<html><head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Staff Password Reset</title>
  <link rel="stylesheet" href="<?php echo APP_BASE; ?>/assets/css/theme.css">
</head>
<body>
<div class="center-wrap">
  <div class="card auth-card">
    <div style="text-align:center">
      <img src="<?php echo APP_BASE; ?>/assets/img/logo.png" alt="Logo" style="height:56px;border-radius:14px;background:white;padding:6px"/>
      <h2 style="margin:10px 0 4px">Staff Password Reset</h2>
      <div class="small"><?php echo ($step == '1') ? 'Enter your email to receive reset link' : 'Create a new password'; ?></div>
    </div>
    <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert ok"><?= $msg ?></div><?php endif; ?>
    <form method="post">
      <?php if ($step == '1'): ?>
        <!-- Step 1: Email Request -->
        <label class="label">Email Address</label>
        <input class="input" type="email" name="email" required placeholder="your-email@example.com">
        <div style="margin-top:14px">
          <button class="btn btn-primary" style="width:100%">Send Reset Link</button>
        </div>
      <?php else: ?>
        <!-- Step 2: Password Reset -->
        <?php if (!isset($_GET['token']) || empty($_GET['token'])): ?>
          <div class="alert err">Invalid or missing reset token.</div>
          <a class="btn btn-primary" href="<?php echo APP_BASE; ?>/staff/forgot_password.php?step=1" style="width:100%;text-align:center;display:block;padding:10px">Back to Email Request</a>
        <?php else: ?>
          <label class="label">New Password</label>
          <input class="input" type="password" id="pw" name="new_password" required>
          <label class="label">Confirm Password</label>
          <input class="input" type="password" name="confirm_password" required>
          <label style="display:flex;gap:8px;align-items:center;margin-top:10px">
            <input type="checkbox" onclick="document.getElementById('pw').type=this.checked?'text':'password'"> <span class="small">Show password</span>
          </label>
          <div style="margin-top:14px">
            <button class="btn btn-primary" style="width:100%">Reset Password</button>
          </div>
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
        <?php endif; ?>
      <?php endif; ?>
      <div style="margin-top:10px;text-align:center">
        <a class="small" href="<?php echo APP_BASE; ?>/staff/login.php">Back to login</a>
      </div>
    </form>
  </div>
</div>
</body></html>
