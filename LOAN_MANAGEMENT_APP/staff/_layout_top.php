<?php
require_once __DIR__ . '/../includes/auth.php';
$user = current_user();
$settings = get_system_settings();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= htmlspecialchars($title ?? 'Loan Management') ?></title>
  <link rel="stylesheet" href="<?php echo APP_BASE; ?>/assets/css/theme.css">
</head>
<body>
<div class="topbar" style="background-color:<?= htmlspecialchars($settings['primary_color'] ?? '#2c3ec5') ?> !important;">
  <div class="brand">
    <img src="<?php echo htmlspecialchars($settings['logo_path'] ?? APP_BASE . '/assets/img/logo.png'); ?>" alt="Logo"/>
    <div>
      <div style="font-weight:800;line-height:1"><?= htmlspecialchars($settings['system_name'] ?? 'CredenceLend') ?></div>
      <div class="small" style="color:#fde8ec"><?php 
        $role = $_SESSION['role'] ?? '';
        $roleNames = [
          'ADMIN' => 'Admin Portal',
          'MANAGER' => 'Manager Portal',
          'CREDIT_INVESTIGATOR' => 'Credit Investigator Portal',
          'LOAN_OFFICER' => 'Loan Officer Portal',
          'CASHIER' => 'Cashier Portal'
        ];
        echo htmlspecialchars($roleNames[$role] ?? 'Staff Portal');
      ?></div>
    </div>
  </div>
  <div>
    <span class="small" style="color:#fde8ec"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?> (<?= htmlspecialchars($_SESSION['role'] ?? '') ?>)</span>
    &nbsp; <a class="btn btn-outline" style="color:white;border-color:#ffd0d8" href="<?php echo APP_BASE; ?>/logout.php">Logout</a>
  </div>
</div>
<div class="layout">
  <div class="sidebar">
    <h3>Menu</h3>
    <a href="<?php echo APP_BASE; ?>/staff/dashboard.php" class="<?= ($active??'')==='dash'?'active':''?>">Dashboard</a>
    
    <?php if (in_array($_SESSION['role'] ?? '', ['ADMIN','MANAGER','CREDIT_INVESTIGATOR','LOAN_OFFICER','CASHIER'], true)): ?>
      <a href="<?php echo APP_BASE; ?>/staff/loans.php" class="<?= ($active??'')==='loans'?'active':''?>">Loans</a>
      <a href="<?php echo APP_BASE; ?>/staff/customers.php" class="<?= ($active??'')==='cust'?'active':''?>">Customers</a>
    <?php endif; ?>
    
    <?php if (in_array($_SESSION['role'] ?? '', ['CASHIER','ADMIN','MANAGER'], true)): ?>
      <a href="<?php echo APP_BASE; ?>/staff/payments.php" class="<?= ($active??'')==='pay'?'active':''?>">Payments</a>
    <?php endif; ?>
    <?php if (in_array($_SESSION['role'] ?? '', ['LOAN_OFFICER','CASHIER','MANAGER','ADMIN'], true)): ?>
      <a href="<?php echo APP_BASE; ?>/staff/release_queue.php" class="<?= ($active??'')==='release_queue'?'active':''?>">Money Release</a>
    <?php endif; ?>
    <?php if (in_array($_SESSION['role'] ?? '', ['CREDIT_INVESTIGATOR','MANAGER','ADMIN'], true)): ?>
      <a href="<?php echo APP_BASE; ?>/staff/ci_queue.php" class="<?= ($active??'')==='ci'?'active':''?>">CI Review</a>
    <?php endif; ?>
    <?php if (in_array($_SESSION['role'] ?? '', ['MANAGER','ADMIN'], true)): ?>
      <a href="<?php echo APP_BASE; ?>/staff/manager_queue.php" class="<?= ($active??'')==='mgr'?'active':''?>">Manager Approval</a>
    <?php endif; ?>
    <a href="<?php echo APP_BASE; ?>/staff/reports.php" class="<?= ($active??'')==='rep'?'active':''?>">Reports</a>
    <?php if (in_array($_SESSION['role'] ?? '', ['ADMIN','MANAGER'], true)): ?>
      <a href="<?php echo APP_BASE; ?>/staff/staff.php" class="<?= ($active??'')==='staff'?'active':''?>">Staff</a>
    <?php endif; ?>
    <?php if (in_array($_SESSION['role'] ?? '', ['ADMIN'], true)): ?>
      <a href="<?php echo APP_BASE; ?>/staff/registration.php" class="<?= ($active??'')==='reg'?'active':''?>">Register Staff</a>
    <?php endif; ?>
    <?php if (in_array($_SESSION['role'] ?? '', ['ADMIN'], true)): ?>
      <a href="<?php echo APP_BASE; ?>/staff/history.php" class="<?= ($active??'')==='history'?'active':''?>">History</a>
    <?php endif; ?>
    <?php if (in_array($_SESSION['role'] ?? '', ['MANAGER'], true)): ?>
      <a href="<?php echo APP_BASE; ?>/staff/manager_settings.php" class="<?= ($active??'')==='settings'?'active':''?>">Settings</a>
    <?php endif; ?>
  </div>
  <div class="main">
