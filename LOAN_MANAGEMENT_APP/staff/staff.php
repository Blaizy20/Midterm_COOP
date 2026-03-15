<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_roles(['ADMIN','MANAGER']);

$filter_role = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT user_id, username, full_name, role, contact_no, email, created_at FROM users WHERE role <> 'CUSTOMER' AND is_active=1";
$types = '';
$params = [];

if ($filter_role) {
  $sql .= " AND role = ?";
  $types .= 's';
  $params[] = $filter_role;
}

if ($search) {
  $sql .= " AND (full_name LIKE ? OR username LIKE ? OR contact_no LIKE ? OR email LIKE ?)";
  $types .= 'ssss';
  $search_term = '%' . $search . '%';
  $params[] = $search_term;
  $params[] = $search_term;
  $params[] = $search_term;
  $params[] = $search_term;
}

$sql .= " ORDER BY full_name ASC";

$rows = fetch_all(q($sql, $types, $params));
$title="Staff Management"; $active="staff";
include __DIR__ . '/_layout_top.php';
?>
<div class="card">
  <h2 style="margin-top:0">Staff Members</h2>
  
  <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <label class="label" style="margin-bottom:6px">Filter by Role</label>
      <select class="input" onchange="location.href='?role='+this.value+'&search='+document.getElementById('search_box').value" style="min-width:150px">
        <option value="">All Roles</option>
        <option value="ADMIN" <?= $filter_role==='ADMIN'?'selected':'' ?>>Admin</option>
        <option value="MANAGER" <?= $filter_role==='MANAGER'?'selected':'' ?>>Manager</option>
        <option value="CREDIT_INVESTIGATOR" <?= $filter_role==='CREDIT_INVESTIGATOR'?'selected':'' ?>>Credit Investigator</option>
        <option value="LOAN_OFFICER" <?= $filter_role==='LOAN_OFFICER'?'selected':'' ?>>Loan Officer</option>
        <option value="CASHIER" <?= $filter_role==='CASHIER'?'selected':'' ?>>Cashier</option>
      </select>
    </div>
    
    <div style="flex:1;min-width:200px">
      <label class="label" style="margin-bottom:6px">Search (Name, Username, Contact, Email)</label>
      <div style="display:flex;gap:8px">
        <input class="input" id="search_box" type="text" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="flex:1">
        <button class="btn btn-primary" onclick="location.href='?role='+document.querySelector('select').value+'&search='+document.getElementById('search_box').value">Search</button>
        <a class="btn btn-outline" href="?" style="text-decoration:none">Clear</a>
      </div>
    </div>
  </div>

  <div style="overflow:auto;margin-top:14px">
    <table class="table">
      <thead>
        <tr>
          <th>Username</th>
          <th>Name</th>
          <th>Role</th>
          <th>Contact</th>
          <th>Email</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['username']) ?></td>
            <td><?= htmlspecialchars($r['full_name']) ?></td>
            <td><span class="badge green"><?= htmlspecialchars($r['role']) ?></span></td>
            <td><?= htmlspecialchars($r['contact_no'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(empty($rows)): ?><tr><td colspan="6" class="small">No staff found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
