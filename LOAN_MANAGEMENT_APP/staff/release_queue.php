<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_helpers.php';
require_login();
require_roles(['LOAN_OFFICER','CASHIER','MANAGER','ADMIN']);

$search = trim($_GET['search'] ?? '');
$where = "1=1";
$types = '';
$params = [];

if ($search) {
  $where .= " AND (l.reference_no LIKE ? OR c.customer_no LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ?)";
  $search_param = '%' . $search . '%';
  $types = 'sss';
  $params = [$search_param, $search_param, $search_param];
}

$loans = fetch_all(q(
  "SELECT l.loan_id, l.reference_no, l.principal_amount, l.interest_rate, l.payment_term, 
          l.total_payable, l.activated_at, l.status,
          c.customer_id, c.customer_no, c.first_name, c.last_name, c.street, c.barangay, c.city, c.province, c.contact_no,
          u.full_name AS officer_name
   FROM loans l
   JOIN customers c ON c.customer_id = l.customer_id
   LEFT JOIN users u ON u.user_id = l.loan_officer_id
   WHERE $where
   ORDER BY l.activated_at DESC",
  $types, $params
));

$title = "Release Queue";
$active = "release_queue";
include __DIR__ . '/_layout_top.php';
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
    <h2 style="margin:0">Money Release Queue</h2>
    <form method="get" style="display:flex;gap:8px;align-items:center">
      <input class="input" type="text" name="search" placeholder="Search by Reference, Customer No, or Name" value="<?= htmlspecialchars($search) ?>" style="min-width:280px">
      <button class="btn btn-primary" type="submit" style="white-space:nowrap">Search</button>
      <?php if($search): ?>
        <a class="btn btn-outline" href="<?php echo APP_BASE; ?>/staff/release_queue.php">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <div style="margin-top:14px;overflow-x:auto">
    <table class="table">
      <thead>
        <tr>
          <th>Reference No</th>
          <th>Customer</th>
          <th>Principal</th>
          <th>Interest Rate</th>
          <th>Total Payable</th>
          <th>Loan Officer</th>
          <th>Approved Date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($loans as $loan): ?>
          <tr>
            <td><strong><?= htmlspecialchars($loan['reference_no']) ?></strong></td>
            <td>
              <div><?= htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']) ?></div>
              <div class="small"><?= htmlspecialchars($loan['customer_no']) ?></div>
            </td>
            <td>₱<?= number_format($loan['principal_amount'], 2) ?></td>
            <td><?= htmlspecialchars($loan['interest_rate'] ?? '—') ?>%</td>
            <td>₱<?= number_format($loan['total_payable'] ?? 0, 2) ?></td>
            <td><?= htmlspecialchars($loan['officer_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($loan['activated_at'] ?? '—') ?></td>
            <td><span class="badge <?= status_badge_class($loan['status']) ?>"><?= htmlspecialchars($loan['status']) ?></span></td>
            <td style="display:flex;gap:8px">
              <?php $loan_id = isset($loan['loan_id']) ? intval($loan['loan_id']) : 0; ?>
              <a class="btn btn-primary" href="<?php echo APP_BASE; ?>/staff/release_voucher.php?id=<?= $loan_id ?>" style="padding:6px 10px;font-size:12px">View Voucher</a>
              <a class="btn btn-primary" href="<?php echo APP_BASE; ?>/staff/release_voucher.php?id=<?= $loan_id ?>&edit=1" style="padding:6px 10px;font-size:12px">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(empty($loans)): ?>
          <tr><td colspan="9" class="small">No approved loans ready for release.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
