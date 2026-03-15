<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_helpers.php';
require_login();
require_roles(['ADMIN']);

$title = "Activity History";
$active = "history";

$filter_action = $_GET['action'] ?? '';
$filter_role = $_GET['role'] ?? '';
$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';

$where = [];
$types = '';
$params = [];

$sql = "SELECT al.*, u.full_name AS user_name FROM activity_logs al 
        LEFT JOIN users u ON u.user_id = al.user_id";

if ($filter_action !== '') {
  $where[] = "al.action = ?";
  $types .= "s";
  $params[] = $filter_action;
}
if ($filter_role !== '') {
  $where[] = "al.user_role = ?";
  $types .= "s";
  $params[] = $filter_role;
}
if ($filter_from !== '') {
  $where[] = "DATE(al.created_at) >= ?";
  $types .= "s";
  $params[] = $filter_from;
}
if ($filter_to !== '') {
  $where[] = "DATE(al.created_at) <= ?";
  $types .= "s";
  $params[] = $filter_to;
}

if (!empty($where)) {
  $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY al.created_at DESC LIMIT 500";

$logs = fetch_all(q($sql, $types, $params));

include __DIR__ . '/_layout_top.php';
?>
<div class="card">
  <h2 style="margin:0 0 10px 0">Activity History</h2>
  <div class="small">Audit log of all system activities by role.</div>

  <form method="get" class="grid2" style="margin-top:12px;gap:12px;align-items:end">
    <div>
      <label class="label">Action Type</label>
      <select class="input" name="action">
        <option value="">All</option>
        <?php
          $actions = ['Payment Recorded', 'Payment Updated', 'Interest Rate Updated', 'Payment Term Updated', 'Loan Approved', 'Loan Denied', 'CI Review', 'Loan Officer Assigned', 'Customer Registered', 'Customer Updated', 'Customer Permanently Deleted', 'Staff Created', 'Staff Permanently Deleted', 'Money Release Voucher Created', 'Money Release Voucher Updated'];
          foreach ($actions as $a) {
            $sel = ($filter_action === $a) ? 'selected' : '';
            echo '<option '.$sel.' value="'.htmlspecialchars($a).'">'.htmlspecialchars($a).'</option>';
          }
        ?>
      </select>
    </div>
    <div>
      <label class="label">User Role</label>
      <select class="input" name="role">
        <option value="">All</option>
        <?php
          $roles = ['ADMIN','MANAGER','CREDIT_INVESTIGATOR','LOAN_OFFICER','CASHIER'];
          foreach ($roles as $r) {
            $sel = ($filter_role === $r) ? 'selected' : '';
            echo '<option '.$sel.' value="'.htmlspecialchars($r).'">'.htmlspecialchars($r).'</option>';
          }
        ?>
      </select>
    </div>
    <div>
      <label class="label">From</label>
      <input class="input" type="date" name="from" value="<?= htmlspecialchars($filter_from) ?>">
    </div>
    <div>
      <label class="label">To</label>
      <input class="input" type="date" name="to" value="<?= htmlspecialchars($filter_to) ?>">
    </div>
    <div style="display:flex;gap:10px">
      <button class="btn btn-primary" type="submit">Filter</button>
      <a class="btn btn-ghost" href="<?php echo APP_BASE; ?>/staff/history.php">Reset</a>
    </div>
  </form>

  <div style="overflow:auto;margin-top:14px">
    <table class="table" style="font-size:13px">
      <thead>
        <tr>
          <th>Date & Time</th>
          <th>User</th>
          <th>Role</th>
          <th>Action</th>
          <th>Reference</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td><?= htmlspecialchars($log['created_at']) ?></td>
            <td><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
            <td><span class="badge gray"><?= htmlspecialchars($log['user_role']) ?></span></td>
            <td><?= htmlspecialchars($log['action']) ?></td>
            <td><?= $log['reference_no'] ? '<a href="'.APP_BASE.'/staff/loan_view.php?id='.intval($log['loan_id']).'">'.htmlspecialchars($log['reference_no']).'</a>' : '—' ?></td>
            <td style="max-width:300px;word-break:break-word"><?= htmlspecialchars($log['description']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
          <tr><td colspan="6" class="small" style="text-align:center;padding:20px">No activity logs found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
