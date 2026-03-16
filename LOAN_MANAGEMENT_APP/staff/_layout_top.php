<?php
// Ensure auth and settings are available
if (!isset($settings)) $settings = get_system_settings();
$role = $_SESSION['role'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

$roleNames = [
    'admin'        => 'Admin Portal',
    'manager'      => 'Manager Portal',
    'ci'           => 'Credit Investigator Portal',
    'loan_officer' => 'Loan Officer Portal',
    'cashier'      => 'Cashier Portal'
];
$portalLabel = htmlspecialchars($roleNames[$role] ?? 'Staff Portal');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title ?? 'CredenceLend'); ?> – <?php echo htmlspecialchars($settings['system_name'] ?? 'CredenceLend'); ?></title>
    <link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="brand">
        <img src="/assets/img/logo.png" alt="Logo"
             style="height:34px;width:auto;max-width:120px;border-radius:8px;background:white;padding:3px;">
        <div>
            <div style="font-weight:800;line-height:1.2;">
                <?php echo htmlspecialchars($settings['system_name'] ?? 'CredenceLend'); ?>
            </div>
            <div class="small" style="color:#fde8ec;"><?php echo $portalLabel; ?></div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <span class="small" style="color:white;">
            <?php echo htmlspecialchars($full_name); ?>
            (<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $role))); ?>)
        </span>
        <a href="/staff/logout.php"
           class="btn"
           style="background:rgba(255,255,255,0.2);color:white;padding:6px 14px;">
            Logout
        </a>
    </div>
</div>

<!-- LAYOUT -->
<div class="layout">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h3>Menu</h3>

        <a href="/staff/dashboard.php"
           <?php if (($active ?? '') === 'dash') echo 'class="active"'; ?>>
            Dashboard
        </a>

        <a href="/staff/loans.php"
           <?php if (($active ?? '') === 'loans') echo 'class="active"'; ?>>
            Loans
        </a>

        <a href="/staff/customers.php"
           <?php if (($active ?? '') === 'customers') echo 'class="active"'; ?>>
            Customers
        </a>

        <a href="/staff/payments.php"
           <?php if (($active ?? '') === 'payments') echo 'class="active"'; ?>>
            Payments
        </a>

        <a href="/staff/vouchers.php"
           <?php if (($active ?? '') === 'vouchers') echo 'class="active"'; ?>>
            Money Release
        </a>

        <?php if (in_array($role, ['admin', 'manager', 'ci'])): ?>
        <a href="/staff/ci_review.php"
           <?php if (($active ?? '') === 'ci') echo 'class="active"'; ?>>
            CI Review
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['admin', 'manager'])): ?>
        <a href="/staff/manager_approval.php"
           <?php if (($active ?? '') === 'approval') echo 'class="active"'; ?>>
            Manager Approval
        </a>
        <?php endif; ?>

        <a href="/staff/reports.php"
           <?php if (($active ?? '') === 'reports') echo 'class="active"'; ?>>
            Reports
        </a>

        <?php if ($role === 'admin'): ?>
        <h3>Admin</h3>
        <a href="/staff/users.php"
           <?php if (($active ?? '') === 'staff') echo 'class="active"'; ?>>
            Staff
        </a>
        <a href="/staff/register_staff.php"
           <?php if (($active ?? '') === 'register') echo 'class="active"'; ?>>
            Register Staff
        </a>
        <a href="/staff/history.php"
           <?php if (($active ?? '') === 'history') echo 'class="active"'; ?>>
            History
        </a>
        <a href="/staff/settings.php"
           <?php if (($active ?? '') === 'settings') echo 'class="active"'; ?>>
            Settings
        </a>
        <?php endif; ?>
    </div>

    <!-- MAIN CONTENT STARTS HERE -->
    <div class="main">
