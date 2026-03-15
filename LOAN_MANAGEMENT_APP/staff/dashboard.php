<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_helpers.php';
require_login();

$role = $_SESSION['role'] ?? '';

$counts = fetch_one(q("SELECT
  SUM(status='PENDING') AS pending,
  SUM(status='DENIED') AS denied,
  SUM(status='PENDING') AS ci_queue,
  SUM(status='CI_REVIEWED') AS manager_queue,
  SUM(status='ACTIVE') AS approved,
  SUM(status='ACTIVE') AS active,
  SUM(status='OVERDUE') AS overdue,
  SUM(status='CLOSED') AS closed
FROM loans"));

// Total transactions (payments)
$total_tx = fetch_one(q("SELECT IFNULL(SUM(amount),0) AS total FROM payments"));

// Total customers
$total_customers = fetch_one(q("SELECT COUNT(*) AS count FROM customers WHERE user_id IS NOT NULL"));

// Total staff
$total_staff = fetch_one(q("SELECT COUNT(*) AS count FROM users WHERE role <> 'CUSTOMER'"));

$applicants = fetch_all(q("SELECT l.reference_no, l.submitted_at, l.status, c.customer_no, CONCAT(c.first_name,' ',c.last_name) AS customer_name
  FROM loans l JOIN customers c ON c.customer_id=l.customer_id
  ORDER BY l.submitted_at DESC LIMIT 10"));

$staff = fetch_all(q("SELECT full_name, role, created_at FROM users WHERE role <> 'CUSTOMER' ORDER BY
  CASE role
    WHEN 'ADMIN' THEN 1
    WHEN 'MANAGER' THEN 2
    WHEN 'CREDIT_INVESTIGATOR' THEN 3
    WHEN 'LOAN_OFFICER' THEN 4
    WHEN 'CASHIER' THEN 5
    ELSE 99 END, created_at DESC"));

$title="Dashboard"; $active="dash";
include __DIR__ . '/_layout_top.php';
?>
<div class="row">
  <div class="col"><div class="card"><div class="small">Welcome Back</div><div style="font-size:20px;font-weight:800"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?> (<?= htmlspecialchars($_SESSION['role'] ?? '') ?>)</div></div></div>
</div>

<div style="height:14px"></div>

<div class="row">
  <?php if (in_array($role, ['CASHIER','CREDIT_INVESTIGATOR','MANAGER','ADMIN'], true)): ?>
  <div class="col"><div class="card"><div class="small">Total Transactions</div><div style="font-size:28px;font-weight:800">₱ <?= number_format($total_tx['total'] ?? 0, 2) ?></div></div></div>
  <?php endif; ?>
  
  <?php if (in_array($role, ['MANAGER','ADMIN','LOAN_OFFICER','CASHIER','CREDIT_INVESTIGATOR'], true)): ?>
  <div class="col"><div class="card"><div class="small">Total Customers</div><div style="font-size:28px;font-weight:800"><?= intval($total_customers['count'] ?? 0) ?></div></div></div>
  <?php endif; ?>
  
  <div class="col"><div class="card"><div class="small">Total Staff</div><div style="font-size:28px;font-weight:800"><?= intval($total_staff['count'] ?? 0) ?></div></div></div>
</div>

<div style="height:14px"></div>

<div class="row">
  <div class="col"><div class="card"><div class="small">Pending</div><div style="font-size:24px;font-weight:800"><?= intval($counts['pending'] ?? 0) ?></div></div></div>
  <div class="col"><div class="card"><div class="small">Denied</div><div style="font-size:24px;font-weight:800"><?= intval($counts['denied'] ?? 0) ?></div></div></div>
  <div class="col"><div class="card"><div class="small">CI Review Queue</div><div style="font-size:24px;font-weight:800"><?= intval($counts['ci_queue'] ?? 0) ?></div></div></div>
  <div class="col"><div class="card"><div class="small">Manager Approval</div><div style="font-size:24px;font-weight:800"><?= intval($counts['manager_queue'] ?? 0) ?></div></div></div>
  <div class="col"><div class="card"><div class="small">Approved</div><div style="font-size:24px;font-weight:800"><?= intval($counts['approved'] ?? 0) ?></div></div></div>
  <div class="col"><div class="card"><div class="small">Overdue</div><div style="font-size:24px;font-weight:800"><?= intval($counts['overdue'] ?? 0) ?></div></div></div>
  <div class="col"><div class="card"><div class="small">Closed</div><div style="font-size:24px;font-weight:800"><?= intval($counts['closed'] ?? 0) ?></div></div></div>
</div>

<div style="height:14px"></div>

<div class="row">
  <div class="col">
    <div class="card">
      <h3 style="margin-top:0">Recent Client Applications</h3>
      <table class="table">
        <thead><tr><th>Reference</th><th>Customer</th><th>Status</th><th>Submitted</th></tr></thead>
        <tbody>
        <?php foreach($applicants as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['reference_no']) ?></td>
            <td><?= htmlspecialchars($a['customer_name']) ?> <span class="small">(<?= htmlspecialchars($a['customer_no']) ?>)</span></td>
            <td><span class="badge <?= status_badge_class($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span></td>
            <td><?= htmlspecialchars($a['submitted_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(empty($applicants)): ?><tr><td colspan="4" class="small">No applications yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col">
    <div class="card">
      <h3 style="margin-top:0">Staff & Admin (Ranked)</h3>
      <table class="table">
        <thead><tr><th>Name</th><th>Role</th></tr></thead>
        <tbody>
        <?php foreach($staff as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['full_name']) ?></td>
            <td><?= htmlspecialchars(str_replace('_',' ', $s['role'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(empty($staff)): ?><tr><td colspan="2" class="small">No staff.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>