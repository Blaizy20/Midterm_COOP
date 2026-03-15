<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_helpers.php';

// Get system settings
$settings = get_system_settings();

// Handle login form
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = q("SELECT * FROM users WHERE username = ? AND is_active = 1", "s", [$username]);
    $user = fetch_one($stmt);

    if (!$user || $user['role'] === 'CUSTOMER' || !password_verify($password, $user['password_hash'])) {
        $error = 'Invalid credentials (staff only).';
    } else {
        login_user($user);
        header('Location: ' . APP_BASE . 'staff/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Login</title>
    <link rel="stylesheet" href="<?php echo APP_BASE; ?>/assets/css/theme.css">
</head>
<body>
    <div class="topbar" style="background-color: <?php echo htmlspecialchars($settings['primary_color'] ?? '#2c3ec5'); ?> !important;">
        <div class="brand">
            <img 
                src="<?php echo htmlspecialchars($settings['logo_path'] ?? (APP_BASE . '/assets/img/logo.png')); ?>" 
                alt="Logo"
            >
            <div style="font-weight:800;line-height:1;">
                <?php echo htmlspecialchars($settings['system_name'] ?? 'CredenceLend'); ?>
            </div>
            <div class="small" style="color:#fde8ec;">Staff Portal</div>
        </div>
    </div>

    <div class="center-wrap">
        <div class="card auth-card">
            <div style="text-align: center;">
                <img 
                    src="<?php echo htmlspecialchars($settings['logo_path'] ?? (APP_BASE . '/assets/img/logo.png')); ?>" 
                    alt="Logo" 
                    style="height:56px;border-radius:14px;background:white;padding:6px;"
                >
            </div>
            <h2 style="margin:10px 0 4px;">Staff Login</h2>
            <div class="small">Admin / Manager / Credit Investigator / Loan Officer / Cashier</div>

            <?php if ($error): ?>
                <div class="alert err"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <label class="label">Username</label>
                <input class="input" name="username" required>

                <label class="label">Password</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input class="input" type="password" id="pw" name="password" required>
                    <div style="width:24px;height:24px;"></div>
                </div>

                <label style="display:flex;gap:8px;align-items:center;margin-top:10px;">
                    <input type="checkbox" onclick="document.getElementById('pw').type = this.checked ? 'text' : 'password';">
                    <span class="small">Show password</span>
                </label>

                <div style="margin-top:14px;">
                    <button class="btn btn-primary" style="width:100%;">Login</button>
                </div>
            </form>

            <div style="margin-top:10px;">
                <a class="small" href="<?php echo APP_BASE; ?>forgot-password.php">Forgot password?</a>
            </div>
        </div>
    </div>
</body>
</html>
