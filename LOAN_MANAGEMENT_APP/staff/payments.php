<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_helpers.php';
require_login();
require_roles(['CASHIER','ADMIN','MANAGER']);

$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$method = trim($_GET['method'] ?? '');

// Query to get payments with loan and customer information
$sql = "SELECT p.payment_id, p.amount, p.payment_date, p.method, p.or_no, p.notes, 
        l.loan_id, l.reference_no, l.status, l.remaining_balance,
        c.customer_no, CONCAT(c.first_name,' ',c.last_name) AS customer_name,
        u.full_name AS received_by_name
        FROM payments p
        JOIN loans l ON l.loan_id=p.loan_id
        JOIN customers c ON c.customer_id=l.customer_id
        LEFT JOIN users u ON u.user_id=p.received_by
        WHERE 1=1";

$types=''; $params=[];

// Add status filter if provided
if ($status !== '' && in_array($status, ['ACTIVE','OVERDUE'], true)) {
  $sql .= " AND l.status = ?";
  $types .= "s"; $params[] = $status;
}

// Add payment method filter if provided
if ($method !== '' && in_array($method, ['CASH','GCASH','BANK','CHEQUE'], true)) {
  $sql .= " AND p.method = ?";
  $types .= "s"; $params[] = $method;
}

// Add search filter if provided
if ($search !== '') {
  $sql .= " AND (l.reference_no=? OR c.customer_no=? OR CONCAT(c.first_name,' ',c.last_name) LIKE CONCAT('%',?,'%'))";
  $types .= "sss"; $params[] = $search; $params[] = $search; $params[] = $search;
}

$sql .= " ORDER BY p.payment_date DESC, p.payment_id DESC";
$rows = fetch_all(q($sql, $types, $params));

$title="Payments"; $active="pay";
include __DIR__ . '/_layout_top.php';
?>
<div class="card">
  <h2 style="margin-top:0">Payments</h2>
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0;align-items:end">
    <div style="flex:1;min-width:240px">
      <input class="input" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search reference/customer no/name">
    </div>
    <div style="min-width:120px">
      <label class="label" style="margin-bottom:4px">Loan Status</label>
      <select class="input" name="status">
        <option value="">All</option>
        <option value="ACTIVE" <?= ($status === 'ACTIVE') ? 'selected' : '' ?>>ACTIVE</option>
        <option value="OVERDUE" <?= ($status === 'OVERDUE') ? 'selected' : '' ?>>OVERDUE</option>
      </select>
    </div>
    <div style="min-width:120px">
      <label class="label" style="margin-bottom:4px">Method</label>
      <select class="input" name="method">
        <option value="">All</option>
        <option value="CASH" <?= ($method === 'CASH') ? 'selected' : '' ?>>Cash</option>
        <option value="GCASH" <?= ($method === 'GCASH') ? 'selected' : '' ?>>GCash</option>
        <option value="BANK" <?= ($method === 'BANK') ? 'selected' : '' ?>>Bank Transfer</option>
        <option value="CHEQUE" <?= ($method === 'CHEQUE') ? 'selected' : '' ?>>Cheque</option>
      </select>
    </div>
    <button class="btn btn-primary">Search</button>
    <a class="btn btn-ghost" href="<?php echo APP_BASE; ?>/staff/payments.php">Reset</a>
  </form>
  <table class="table">
    <thead><tr><th>OR No</th><th>Reference</th><th>Customer</th><th>Payment Date</th><th>Amount</th><th>Method</th><th>Received By</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['or_no']) ?></td>
          <td><a href="<?php echo APP_BASE; ?>/staff/loan_view.php?id=<?= intval($r['loan_id']) ?>"><?= htmlspecialchars($r['reference_no']) ?></a></td>
          <td><?= htmlspecialchars($r['customer_name']) ?> <span class="small">(<?= htmlspecialchars($r['customer_no']) ?>)</span></td>
          <td><?= htmlspecialchars($r['payment_date']) ?></td>
          <td>₱<?= number_format($r['amount'], 2) ?></td>
          <td><?= htmlspecialchars($r['method'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['received_by_name'] ?? '') ?></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <a class="btn btn-primary" href="<?php echo APP_BASE; ?>/staff/payment_edit.php?id=<?= intval($r['payment_id']) ?>" style="font-size:12px">Edit</a>
            <a class="btn btn-primary" href="<?php echo APP_BASE; ?>/staff/loan_view.php?id=<?= intval($r['loan_id']) ?>" style="font-size:12px">View Loan</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($rows)): ?><tr><td colspan="8" class="small">No payments found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
