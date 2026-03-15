<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_helpers.php';
require_login();
require_roles(['ADMIN','MANAGER','CREDIT_INVESTIGATOR','LOAN_OFFICER','CASHIER']);

$title = "Reports";
$active = "rep";

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
        q("UPDATE loans SET interest_rate = ?, payment_term = NULL WHERE loan_id = ?", "di", [$interest_rate, $loan_id]);
        log_activity('Interest Rate Updated', 'Interest rate changed to ' . number_format($interest_rate, 2) . '%', $loan_id, $current_loan['customer_id'], $current_loan['reference_no']);
        recalc_loan($loan_id);
        header("Location: " . APP_BASE . "/staff/reports.php?status=" . urlencode($status) . "&from=" . urlencode($from) . "&to=" . urlencode($to) . "&method=" . urlencode($method) . "&officer_id=" . urlencode($officer_id));
        exit;
      }
    } catch (Exception $e) {
      $update_msg = '<div class="alert red">Update failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
  }
}

$status = $_GET['status'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$method = $_GET['method'] ?? '';
$officer_id = $_GET['officer_id'] ?? '';

// Fetch all loan officers
$loan_officers = fetch_all(q("SELECT user_id, full_name FROM users WHERE role='LOAN_OFFICER' ORDER BY full_name", ""));

$where = [];
$types = '';
$params = [];

$sql = "SELECT 
          l.loan_id,
          l.reference_no,
          l.status,
          l.principal_amount,
          l.interest_rate,
          l.payment_term,
          l.total_payable,
          l.remaining_balance,
          l.due_date,
          l.submitted_at,
          c.customer_no,
          CONCAT(c.first_name,' ',c.last_name) AS customer_name,
          u.full_name AS officer_name,
          MAX(p.method) AS method
        FROM loans l
        JOIN customers c ON c.customer_id = l.customer_id
        LEFT JOIN users u ON u.user_id = l.loan_officer_id
        LEFT JOIN payments p ON p.loan_id = l.loan_id";

if ($status !== '') { $where[] = "l.status = ?"; $types .= "s"; $params[] = trim($status); }
if ($from !== '') { $where[] = "DATE(l.submitted_at) >= ?"; $types .= "s"; $params[] = $from; }
if ($to !== '') { $where[] = "DATE(l.submitted_at) <= ?"; $types .= "s"; $params[] = $to; }
if ($method !== '' && in_array($method, ['CASH','GCASH','BANK','CHEQUE'], true)) { $where[] = "p.method = ?"; $types .= "s"; $params[] = $method; }
if ($officer_id !== '') { $where[] = "u.user_id = ?"; $types .= "i"; $params[] = intval($officer_id); }

if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " GROUP BY l.loan_id, l.reference_no, l.status, l.principal_amount, l.interest_rate, l.payment_term, l.total_payable, l.remaining_balance, l.due_date, l.submitted_at, c.customer_no, CONCAT(c.first_name,' ',c.last_name), u.full_name";
$sql .= " ORDER BY l.submitted_at DESC";

$err = '';
$rows = [];
try {
  $base_rows = fetch_all(q($sql, $types, $params));
  foreach ($base_rows as $r) {
    $paid = fetch_one(q("SELECT IFNULL(SUM(amount),0) AS total_paid, MAX(payment_date) AS last_payment_date, COUNT(payment_id) AS payments_count FROM payments WHERE loan_id=?", "i", [$r['loan_id']]));
    $r['total_paid'] = $paid['total_paid'] ?? 0;
    $r['last_payment_date'] = $paid['last_payment_date'] ?? null;
    $r['payments_count'] = $paid['payments_count'] ?? 0;
    $rows[] = $r;
  }
} catch (Exception $e) {
  $err = "Query error: " . $e->getMessage();
}

include __DIR__ . '/_layout_top.php';
?>
<div class="card">
  <h2 style="margin:0 0 10px 0">Reports</h2>
  <div class="small">Printable summary of loans and collections (payments).</div>

  <?php if ($update_msg): echo $update_msg; endif; ?>
  <?php if ($err): ?><div class="alert red" style="margin-top:12px"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div style="margin-bottom:12px;border-bottom:1px solid #ddd;padding-bottom:12px">
    <h3 style="margin:0 0 10px 0">Loans Report</h3>
  </div>

  <form method="get" class="grid2" style="margin-top:12px;gap:12px;align-items:end">
    <div>
      <label class="label">Status</label>
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
    <div>
      <label class="label">Payment Method</label>
      <select class="input" name="method">
        <option value="">All</option>
        <option value="CASH" <?= ($method === 'CASH') ? 'selected' : '' ?>>Cash</option>
        <option value="GCASH" <?= ($method === 'GCASH') ? 'selected' : '' ?>>GCash</option>
        <option value="BANK" <?= ($method === 'BANK') ? 'selected' : '' ?>>Bank Transfer</option>
        <option value="CHEQUE" <?= ($method === 'CHEQUE') ? 'selected' : '' ?>>Cheque</option>
      </select>
    </div>
    <div>
      <label class="label">Loan Officer</label>
      <select class="input" name="officer_id">
        <option value="">All Officers</option>
        <?php foreach ($loan_officers as $officer): ?>
          <option value="<?= intval($officer['user_id']) ?>" <?= ($officer_id === (string)$officer['user_id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($officer['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="label">From</label>
      <input class="input" type="date" name="from" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div>
      <label class="label">To</label>
      <input class="input" type="date" name="to" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div style="display:flex;gap:10px">
      <button class="btn btn-primary" type="submit" name="generate" value="1">Generate</button>
      <a class="btn btn-ghost" href="<?php echo APP_BASE; ?>/staff/reports.php">Reset</a>
    </div>
  </form>

  <div style="margin-top:20px" id="reportTableContainer">
    <table class="table">
      <thead>
        <tr>
          <th>Loan Ref#</th>
          <th>Customer</th>
          <th>Status</th>
          <th>Officer</th>
          <th>Method</th>
          <th>Principal</th>
          <th>Int%</th>
          <th>Total Pay</th>
          <th>Total Paid</th>
          <th>Remaining</th>
          <th>Due Date</th>
          <th>Last Pay</th>
          <th>Submitted</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
            $interest_rate = null;
            if (!empty($r['payment_term'])) {
              $rates = ['daily' => 2.75, 'weekly' => 3.0, 'semi_monthly' => 3.50, 'monthly' => 4.0];
              $interest_rate = $rates[$r['payment_term']] ?? null;
            }
            if ($interest_rate === null) {
              $interest_rate = (!empty($r['interest_rate']) && $r['interest_rate'] > 0) ? $r['interest_rate'] : null;
            }
            if ($interest_rate === null && $r['status'] === 'PENDING') {
              $interest_rate = 5.0;
            }
          ?>
          <tr>
            <td><?= htmlspecialchars($r['reference_no']) ?></td>
            <td><?= htmlspecialchars($r['customer_no']) ?> <br> <?= htmlspecialchars($r['customer_name']) ?></td>
            <td><span class="badge <?= htmlspecialchars(status_badge_class($r['status'])) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td><?= $r['officer_name'] ? htmlspecialchars($r['officer_name']) : '—' ?></td>
            <td><?= $r['method'] ? htmlspecialchars($r['method']) : '—' ?></td>
            <td>₱<?= number_format((float)$r['principal_amount'],2) ?></td>
            <td><?= number_format((float)$interest_rate,2) ?></td>
            <td><?= $r['total_payable']===null?'—':'₱'.number_format((float)$r['total_payable'],2) ?></td>
            <td>₱<?= number_format((float)$r['total_paid'],2) ?> <br><span class="small">(<?= (int)$r['payments_count'] ?>)</span></td>
            <td><?= $r['remaining_balance']===null?'—':'₱'.number_format((float)$r['remaining_balance'],2) ?></td>
            <td><?= $r['due_date'] ? htmlspecialchars($r['due_date']) : '—' ?></td>
            <td><?= $r['last_payment_date'] ? htmlspecialchars($r['last_payment_date']) : '—' ?></td>
            <td><?= htmlspecialchars($r['submitted_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="13" class="small" style="text-align:center;padding:20px">No results found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn btn-outline" onclick="openReportModal()">Print Report</button>
    <button class="btn btn-primary" onclick="openReceiptModal()">Print Receipt</button>
  </div>
</div>

<div id="reportModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div class="card" style="max-width:400px;width:90%">
    <h3 style="margin-top:0">Print Report</h3>
    
    <label class="label">Choose Format:</label>
    
    <div style="margin-top:15px;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" onclick="printReport()">Print</button>
      <button class="btn btn-primary" onclick="downloadReportPDF()">Save as PDF</button>
      <button class="btn btn-outline" onclick="closeReportModal()">Cancel</button>
    </div>
  </div>
</div>

<div id="receiptModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;flex-direction:column">
  <div class="card" style="max-width:500px;width:90%;max-height:90vh;overflow-y:auto">
    <h3 style="margin-top:0">Print Receipt</h3>
    
    <label class="label">Select Receipt Type:</label>
    <select class="input" id="receiptType" onchange="updateReceiptOptions()" style="width:100%;padding:8px;margin-bottom:15px;box-sizing:border-box">
      <option value="">Choose...</option>
      <option value="individual">Individual Receipt (Single Payment)</option>
      <option value="summary">Summary Receipt (All Payments by Client)</option>
    </select>

    <div id="individualOptions" style="display:none;margin-top:15px">
      <label class="label">Select Customer:</label>
      <select class="input" id="customerSelect" onchange="updatePaymentList()" style="width:100%;padding:8px;margin-bottom:10px;box-sizing:border-box">
        <option value="">Choose Customer...</option>
        <?php 
          $customers = fetch_all(q("SELECT DISTINCT c.customer_id, c.customer_no, CONCAT(c.first_name,' ',c.last_name) AS name FROM customers c JOIN loans l ON c.customer_id=l.customer_id ORDER BY c.first_name"));
          foreach ($customers as $cust) {
            echo '<option value="'.intval($cust['customer_id']).'">'.htmlspecialchars($cust['customer_no'].' - '.$cust['name']).'</option>';
          }
        ?>
      </select>

      <label class="label" style="margin-top:10px">Select Payment:</label>
      <select class="input" id="paymentSelect" style="width:100%;padding:8px;box-sizing:border-box">
        <option value="">Choose Payment...</option>
      </select>
    </div>

    <div id="summaryOptions" style="display:none;margin-top:15px">
      <label class="label">Select Customer:</label>
      <select class="input" id="summaryCustomerSelect" style="width:100%;padding:8px;box-sizing:border-box">
        <option value="">Choose Customer...</option>
        <?php 
          foreach ($customers as $cust) {
            echo '<option value="'.intval($cust['customer_id']).'">'.htmlspecialchars($cust['customer_no'].' - '.$cust['name']).'</option>';
          }
        ?>
      </select>
    </div>

    <div style="margin-top:15px;display:flex;gap:10px">
      <button class="btn btn-primary" onclick="printReceipt()">Print</button>
      <button class="btn btn-outline" onclick="closeReceiptModal()">Cancel</button>
    </div>
  </div>
</div>

<script>
const receipts = {
  <?php 
    $payments = fetch_all(q("SELECT p.payment_id, p.loan_id, p.or_no, p.amount, p.payment_date, c.customer_id, c.customer_no, CONCAT(c.first_name,' ',c.last_name) AS customer_name FROM payments p JOIN loans l ON p.loan_id=l.loan_id JOIN customers c ON l.customer_id=c.customer_id ORDER BY p.payment_date DESC"));
    foreach ($payments as $p) {
      echo intval($p['payment_id']) . ": {customer_id: ".intval($p['customer_id']).", loan_id: ".intval($p['loan_id']).", or_no: '".addslashes($p['or_no'])."', amount: ".floatval($p['amount']).", date: '".htmlspecialchars($p['payment_date'])."', customer_name: '".addslashes($p['customer_name'])."'},\n";
    }
  ?>
};

function openReceiptModal() {
  document.getElementById('receiptModal').style.display = 'flex';
  document.getElementById('receiptType').value = '';
  document.getElementById('customerSelect').value = '';
  document.getElementById('summaryCustomerSelect').value = '';
  document.getElementById('paymentSelect').innerHTML = '<option value="">Choose Payment...</option>';
  updateReceiptOptions();
}

function closeReceiptModal() {
  document.getElementById('receiptModal').style.display = 'none';
  document.getElementById('receiptType').value = '';
  updateReceiptOptions();
}

function updateReceiptOptions() {
  const type = document.getElementById('receiptType').value;
  document.getElementById('individualOptions').style.display = type === 'individual' ? 'block' : 'none';
  document.getElementById('summaryOptions').style.display = type === 'summary' ? 'block' : 'none';
}

function updatePaymentList() {
  const custId = parseInt(document.getElementById('customerSelect').value);
  const select = document.getElementById('paymentSelect');
  select.innerHTML = '<option value="">Choose Payment...</option>';
  
  for (let paymentId in receipts) {
    if (receipts[paymentId].customer_id === custId) {
      const opt = document.createElement('option');
      opt.value = paymentId;
      opt.textContent = receipts[paymentId].or_no + ' - ₱' + receipts[paymentId].amount.toFixed(2) + ' (' + receipts[paymentId].date + ')';
      select.appendChild(opt);
    }
  }
}

function printReceipt() {
  const type = document.getElementById('receiptType').value;
  
  if (type === 'individual') {
    const paymentId = document.getElementById('paymentSelect').value;
    if (!paymentId) {
      alert('Please select a payment');
      return;
    }
    window.open('<?php echo APP_BASE; ?>/staff/payment_receipt.php?id=' + paymentId, '_blank');
  } else if (type === 'summary') {
    const custId = document.getElementById('summaryCustomerSelect').value;
    if (!custId) {
      alert('Please select a customer');
      return;
    }
    window.open('<?php echo APP_BASE; ?>/staff/receipt_summary.php?customer_id=' + custId, '_blank');
  }
  
  closeReceiptModal();
}

// Close modal when clicking outside
document.getElementById('receiptModal').addEventListener('click', function(e) {
  if (e.target === this) closeReceiptModal();
});

function openReportModal() {
  document.getElementById('reportModal').style.display = 'flex';
}

function closeReportModal() {
  document.getElementById('reportModal').style.display = 'none';
}

function printReport() {
  const tableContainer = document.getElementById('reportTableContainer').innerHTML;
  
  const printWindow = window.open('', '_blank');
  const htmlContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>Loan Report</title>
      <style>
        @page { size: landscape; margin: 3mm; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 5px; }
        
        /* PHYSICAL PRINT SETTINGS
           - Zoom ensures it fits on paper
           - Padding ensures spacing (~8 rows/page)
        */
        .print-wrapper { width: 100%; zoom: 65%; }
        
        h2 { margin: 0 0 5px 0; font-size: 16px; }
        .small { font-size: 10px; color: #666; margin-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { 
            border: 1px solid #666; 
            padding: 8px 4px; /* INCREASED PADDING FOR SPACING */
            text-align: left; 
            vertical-align: top;
            white-space: nowrap; 
        }
        
        td:nth-child(2) { white-space: normal; width: 140px; }
        td:last-child { white-space: normal; } /* Wrap submitted column */
        
        th { background-color: #eee; font-weight: bold; font-size: 9px; }
        .badge { font-weight: bold; text-transform: uppercase; font-size: 8px; border:none; }
      </style>
    </head>
    <body>
      <h2>Loan Report Summary</h2>
      <div class="small">Generated: ${new Date().toLocaleString()}</div>
      <div class="print-wrapper">
        ${tableContainer}
      </div>
    </body>
    </html>
  `;
  
  printWindow.document.write(htmlContent);
  printWindow.document.close();
  
  setTimeout(() => {
    printWindow.focus();
    printWindow.print();
  }, 500);
  
  closeReportModal();
}

function downloadReportPDF() {
  const tableContainer = document.getElementById('reportTableContainer').cloneNode(true);
  
  const wrapper = document.createElement('div');
  wrapper.appendChild(tableContainer);
  
  const style = document.createElement('style');
  style.innerHTML = `
    table { 
        width: 98%; /* Prevent Right Edge Clipping */
        border-collapse: collapse; 
        font-size: 7px; /* Reduced Font to fit 13 cols */
        font-family: Arial, sans-serif; 
    }
    th, td { 
        border: 1px solid #888; 
        padding: 8px 4px; /* INCREASED PADDING: ~8 Rows Per Page */
        white-space: nowrap;
        vertical-align: top;
    }
    
    /* Column Optimization */
    td:nth-child(2) { white-space: normal; width: 13%; } /* Customer */
    td:nth-child(6), td:nth-child(8), td:nth-child(9) { width: 6%; } /* Money Columns */
    
    /* FIX: Force Submitted Column (Last one) to Wrap & Stay in Bounds */
    td:last-child {
        white-space: normal;
        word-wrap: break-word;
        width: 9%; 
    }
    
    th { background-color: #e8e8e8; font-weight: bold; }
    
    .badge { 
        font-size: 8px !important; 
        padding: 2px 4px !important; 
        font-weight: bold; 
        display: inline-block;
        border: 1px solid #ccc;
    }
    
    tr { page-break-inside: avoid; }
    thead { display: table-header-group; }
  `;
  wrapper.appendChild(style);
  
  const opt = {
    margin: [3, 3, 3, 3], 
    filename: 'loan_report_<?= date('Y-m-d') ?>.pdf',
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
    jsPDF: { unit: 'mm', format: 'legal', orientation: 'landscape' },
    pagebreak: { mode: ['css', 'legacy'] } 
  };
  
  if (typeof html2pdf === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
    script.onload = function() {
      html2pdf().set(opt).from(wrapper).save();
      closeReportModal();
    };
    document.head.appendChild(script);
  } else {
    html2pdf().set(opt).from(wrapper).save();
    closeReportModal();
  }
}

document.getElementById('reportModal').addEventListener('click', function(e) {
  if (e.target === this) closeReportModal();
});
</script>

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

document.getElementById('editModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});
</script>

<style>
/* Physical Print Styles */
@media print {
  @page { size: landscape; margin: 3mm; }
  body { margin: 0; padding: 0; font-size: 10px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .topbar, .sidebar, .layout > div:first-child, form, .btn, #editModal, #reportModal, #receiptModal, .card > div:last-child { display: none !important; }
  .card { box-shadow: none; border: none; padding: 0; margin: 0; width: 100%; }
  
  /* Zoom to fit physical paper */
  #reportTableContainer { display: block !important; width: 100% !important; overflow: visible !important; zoom: 65%; }
  
  #reportTableContainer table { width: 100%; border-collapse: collapse; font-size: 10px; }
  
  /* Increase padding for physical print too */
  #reportTableContainer th, #reportTableContainer td { border: 1px solid #666; padding: 8px 4px; white-space: nowrap; vertical-align: top; }
  
  #reportTableContainer td:nth-child(2) { white-space: normal; min-width: 100px; max-width: 160px; }
  #reportTableContainer th { background-color: #eee !important; color: #000 !important; }
  .badge { border: none; background: transparent !important; color: #000 !important; padding: 0; font-size: 9px; font-weight: bold; }
}
</style>

<?php include __DIR__ . '/_layout_bottom.php'; ?>