<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_roles(['MANAGER','ADMIN']);

$rows = fetch_all(q("SELECT l.loan_id, l.reference_no, l.status, l.submitted_at, c.customer_no, CONCAT(c.first_name,' ',c.last_name) AS customer_name
  FROM loans l JOIN customers c ON c.customer_id=l.customer_id
  WHERE l.status='CI_REVIEWED' ORDER BY l.submitted_at ASC"));

$title="Manager Approval"; $active="mgr";
include __DIR__ . '/_layout_top.php';
?>
<div class="card">
  <h2 style="margin-top:0">Manager Approval Queue</h2>
  <div class="small">Set interest rate and approve/deny applications (customer-submitted included).</div>
  <table class="table">
    <thead><tr><th>Reference</th><th>Customer</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['reference_no']) ?></td>
          <td><?= htmlspecialchars($r['customer_name']) ?> <span class="small">(<?= htmlspecialchars($r['customer_no']) ?>)</span></td>
          <td><?= htmlspecialchars($r['status']) ?></td>
          <td><?= htmlspecialchars($r['submitted_at']) ?></td>
          <td><a class="btn btn-outline" href="<?php echo APP_BASE; ?>/staff/loan_view.php?id=<?= intval($r['loan_id']) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($rows)): ?><tr><td colspan="5" class="small">No applications for approval.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
