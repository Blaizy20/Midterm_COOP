<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_helpers.php';

$settings = get_system_settings();
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
        header('Location: ' . APP_BASE . '/staff/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Login – <?php echo htmlspecialchars($settings['system_name'] ?? 'CredenceLend'); ?></title>
    <link rel="stylesheet" href="<?php echo APP_BASE; ?>/assets/css/theme.css">
    <style>
        .topbar-logo { height:34px; width:auto; max-width:120px; border-radius:8px; background:white; padding:3px; }
        .login-logo  { display:block; height:56px; width:auto; max-width:180px; margin:0 auto 12px; border-radius:14px; background:white; padding:6px; }
    </style>
</head>
<body>

    <!-- TOP BAR -->
    <div class="topbar">
        <div class="brand">
            <img class="topbar-logo"
                 src="<?php echo APP_BASE; ?>/assets/img/logo.png"
                 alt="Logo">
            <div>
                <div style="font-weight:800;line-height:1.2;">
                    <?php echo htmlspecialchars($settings['system_name'] ?? 'CredenceLend'); ?>
                </div>
                <div class="small" style="color:#fde8ec;">Staff Portal</div>
            </div>
        </div>
    </div>

    <!-- LOGIN CARD -->
    <div class="center-wrap">
        <div class="card auth-card">

            <img class="login-logo"
                 src="<?php echo APP_BASE; ?>/assets/img/logo.png"
                 alt="Logo">

            <h2 style="text-align:center;margin:0 0 4px;">Staff Login</h2>
            <p class="small" style="text-align:center;margin:0 0 16px;">
                Admin / Manager / Credit Investigator / Loan Officer / Cashier
            </p>

            <?php if ($error): ?>
                <div class="alert err"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <label class="label">Username</label>
                <input class="input" name="username" required autocomplete="username">

                <label class="label">Password</label>
                <input class="input" type="password" id="pw" name="password" required autocomplete="current-password">

                <label style="display:flex;gap:8px;align-items:center;margin-top:10px;">
                    <input type="checkbox" onclick="document.getElementById('pw').type = this.checked ? 'text' : 'password';">
                    <span class="small">Show password</span>
                </label>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary" style="width:100%;">Login</button>
                </div>
            </form>

            <div style="margin-top:12px;text-align:center;">
                <a class="small" href="<?php echo APP_BASE; ?>/forgot-password.php">Forgot password?</a>
            </div>

        </div>
    </div>

</body>
</html>
