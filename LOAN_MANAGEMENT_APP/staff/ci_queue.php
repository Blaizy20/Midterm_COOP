<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
// CI queue access: Credit Investigators, Manager, and Admin
require_roles(['CREDIT_INVESTIGATOR','MANAGER','ADMIN']);

$rows = fetch_all(q("SELECT l.loan_id, l.reference_no, l.submitted_at, c.customer_no, CONCAT(c.first_name,' ',c.last_name) AS customer_name
  FROM loans l JOIN customers c ON c.customer_id=l.customer_id
  WHERE l.status='PENDING' ORDER BY l.submitted_at ASC"));

 $title="CI Queue"; $active="ci";
include __DIR__ . '/_layout_top.php';
?>
<div class="card">
  <h2 style="margin-top:0">CI Review Queue</h2>
  <div class="small">Review client requirements and mark as CI Reviewed.</div>
  <table class="table">
    <thead><tr><th>Reference</th><th>Customer</th><th>Submitted</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['reference_no']) ?></td>
          <td><?= htmlspecialchars($r['customer_name']) ?> <span class="small">(<?= htmlspecialchars($r['customer_no']) ?>)</span></td>
          <td><?= htmlspecialchars($r['submitted_at']) ?></td>
          <td><a class="btn btn-outline" href="<?php echo APP_BASE; ?>/staff/loan_view.php?id=<?= intval($r['loan_id']) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($rows)): ?><tr><td colspan="4" class="small">No pending applications.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
