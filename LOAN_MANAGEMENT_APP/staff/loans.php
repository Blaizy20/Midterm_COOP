<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_helpers.php';
require_login();

// FIXED: Added 'CASHIER' to the allowed roles list
require_roles(['ADMIN','MANAGER','CREDIT_INVESTIGATOR','LOAN_OFFICER','CASHIER']);

// Handle interest rate update
$update_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_interest'])) {
  require_roles(['ADMIN','MANAGER']);
  $loan_id = isset($_POST['loan_id']) ? (int)$_POST['loan_id'] : 0;
  $interest_rate = isset($_POST['interest_rate']) ? (float)$_POST['interest_rate'] : 0;
  
  if ($loan_id > 0 && $interest_rate > 0) {
    try {
      $current_loan = fetch_one(q("SELECT interest_rate, reference_no, customer_id FROM loans WHERE loan_id = ?", "i", [$loan_id]));
      if ($current_loan) {
        q("UPDATE loans SET interest_rate = ? WHERE loan_id = ?", "di", [$interest_rate, $loan_id]);
        log_activity('Interest Rate Updated', 'Interest rate changed to ' . number_format($interest_rate, 2) . '%', $loan_id, $current_loan['customer_id'], $current_loan['reference_no']);
        recalc_loan($loan_id);
        // Redirect to refresh the page with updated data
        header("Location: " . APP_BASE . "/staff/loans.php?q=" . urlencode($search) . "&status=" . urlencode($status));
        exit;
      }
    } catch (Exception $e) {
      $update_msg = '<div class="alert red">Update failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
  }
}

$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$params=[]; $types='';
$sql = "SELECT l.loan_id, l.reference_no, l.status, l.principal_amount, l.interest_rate, l.payment_term, l.remaining_balance, l.submitted_at,
        c.customer_no, CONCAT(c.first_name,' ',c.last_name) AS customer_name, u.full_name AS officer_name
        FROM loans l 
        JOIN customers c ON c.customer_id=l.customer_id
        LEFT JOIN users u ON u.user_id=l.loan_officer_id";

$where = [];
if ($search !== '') {
  $where[] = "(l.reference_no=? OR l.reference_no LIKE CONCAT('%',?) OR c.customer_no=? OR CONCAT(c.first_name,' ',c.last_name) LIKE CONCAT('%',?,'%'))";
  $types .= "ssss"; $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search;
}
if ($status !== '') {
  $where[] = "l.status = ?";
  $types .= "s"; $params[] = $status;
}

if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY l.submitted_at DESC";

$rows = fetch_all(q($sql, $types, $params));

$title="Loans"; $active="loans";
include __DIR__ . '/_layout_top.php';
?>
<div class="card">
  <h2 style="margin-top:0">Loans</h2>
  <?php if ($update_msg): echo $update_msg; endif; ?>
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0;align-items:end">
    <div style="flex:1;min-width:240px">
      <input class="input" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search reference/customer no/name">
    </div>
    <div style="min-width:150px">
      <label class="label" style="margin-bottom:4px">Status</label>
      <select class="input" name="status">
        <option value="">All</option>
        <?php
          $opts = ['PENDING','CI_REVIEWED','DENIED','ACTIVE','OVERDUE','CLOSED'];
          foreach ($opts as $o) {
            $sel = ($status === $o) ? 'selected' : '';
            echo '<option '.$sel.' value="'.htmlspecialchars($o).'">'.htmlspecialchars($o).'</option>';
          }
        ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Search</button>
    <a class="btn btn-ghost" href="<?php echo APP_BASE; ?>/staff/loans.php">Reset</a>
  </form>
  <table class="table">
    <thead><tr><th>Reference</th><th>Customer</th><th>Status</th><th>Officer</th><th>Requested</th><th>Payment Term</th><th>Interest</th><th>Remaining</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <?php if (in_array($r['status'], ['ACTIVE','OVERDUE'], true)) recalc_loan($r['loan_id']); ?>
      <?php
        // Calculate interest rate: prioritize custom rate if payment_term is null
        $interest_rate = null;
        // Only use payment_term rate if payment_term is explicitly set AND is not null
        if (!empty($r['payment_term'])) {
          $rates = ['daily' => 2.75, 'weekly' => 3.0, 'semi_monthly' => 3.50, 'monthly' => 4.0];
          $interest_rate = $rates[$r['payment_term']] ?? null;
        }
        // If no interest rate from payment_term, use database interest_rate
        if ($interest_rate === null) {
          $interest_rate = (!empty($r['interest_rate']) && floatval($r['interest_rate']) > 0) ? floatval($r['interest_rate']) : null;
        }
        // Default to 5% for PENDING applications with 0 or no interest set
        if ($interest_rate === null && $r['status'] === 'PENDING') {
          $interest_rate = 5.0;
        }
      ?>
      <tr>
        <td><?= htmlspecialchars($r['reference_no']) ?></td>
        <td><?= htmlspecialchars($r['customer_name']) ?> <span class="small">(<?= htmlspecialchars($r['customer_no']) ?>)</span></td>
        <td><span class="badge <?= status_badge_class($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
        <td><?= $r['officer_name'] ? htmlspecialchars($r['officer_name']) : '—' ?></td>
        <td>₱<?= number_format($r['principal_amount'], 2) ?></td>
        <td><?= $r['payment_term'] ? htmlspecialchars(ucfirst(str_replace('_', ' ', $r['payment_term']))) : '—' ?></td>
        <td>
          <?= $interest_rate !== null ? number_format((float)$interest_rate, 2) : '—' ?>%
        </td>
        <td><?= $r['remaining_balance']===null ? '—' : '₱' . number_format($r['remaining_balance'], 2) ?></td>
        <td><a class="btn btn-outline" href="<?php echo APP_BASE; ?>/staff/loan_view.php?id=<?= intval($r['loan_id']) ?>">View</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if(empty($rows)): ?><tr><td colspan="9" class="small">No loans found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;display:none">
  <div class="card" style="max-width:400px;width:90%">
    <h3 style="margin-top:0">Update Interest Rate</h3>
    <form method="post">
      <input type="hidden" id="modalLoanId" name="loan_id">
      <div style="margin-bottom:12px">
        <label class="label">Loan: <span id="modalRefNo"></span></label>
      </div>
      <div style="margin-bottom:12px">
        <label class="label">New Interest Rate (%)</label>
        <input class="input" type="number" step="0.01" id="modalInterestRate" name="interest_rate" required>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-primary" type="submit" name="update_interest" value="1">Update</button>
        <button class="btn btn-outline" type="button" onclick="closeEditModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(loanId, currentRate, refNo) {
  document.getElementById('modalLoanId').value = loanId;
  document.getElementById('modalInterestRate').value = currentRate;
  document.getElementById('modalRefNo').textContent = refNo;
  document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('editModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});
</script>

<?php include __DIR__ . '/_layout_bottom.php'; ?>