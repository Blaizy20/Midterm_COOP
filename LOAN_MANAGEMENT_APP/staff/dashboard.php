<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
$settings = get_system_settings();

// Stats
$total_loans     = fetch_one(q("SELECT COUNT(*) AS cnt FROM loans"))['cnt'] ?? 0;
$active_loans    = fetch_one(q("SELECT COUNT(*) AS cnt FROM loans WHERE status = 'active'"))['cnt'] ?? 0;
$pending_loans   = fetch_one(q("SELECT COUNT(*) AS cnt FROM loans WHERE status = 'pending'"))['cnt'] ?? 0;
$total_customers = fetch_one(q("SELECT COUNT(*) AS cnt FROM customers WHERE is_active = 1"))['cnt'] ?? 0;

// Recent loan applications
$applicants = fetch_all(q(
    "SELECT l.reference_no, l.submitted_at, l.status,
            c.customer_no, CONCAT(c.first_name,' ',c.last_name) AS customer_name
     FROM loans l
     JOIN customers c ON c.customer_id = l.customer_id
     ORDER BY l.submitted_at DESC
     LIMIT 10"
));

// Staff list
$staff = fetch_all(q(
    "SELECT full_name, role, created_at
     FROM users
     WHERE role <> 'customer'
     ORDER BY CASE role
         WHEN 'admin'        THEN 1
         WHEN 'manager'      THEN 2
         WHEN 'ci'           THEN 3
         WHEN 'loan_officer' THEN 4
         WHEN 'cashier'      THEN 5
         ELSE 99
     END, created_at DESC"
));

$title  = 'Dashboard';
$active = 'dash';
include __DIR__ . '/_layout_top.php';
?>

<!-- STATS CARDS -->
<div class="grid2" style="margin-bottom:18px;">
    <div class="card">
        <div class="small">Total Loans</div>
        <h2 style="margin:8px 0 0;"><?php echo $total_loans; ?></h2>
    </div>
    <div class="card">
        <div class="small">Active Loans</div>
        <h2 style="margin:8px 0 0;"><?php echo $active_loans; ?></h2>
    </div>
    <div class="card">
        <div class="small">Pending Loans</div>
        <h2 style="margin:8px 0 0;"><?php echo $pending_loans; ?></h2>
    </div>
    <div class="card">
        <div class="small">Total Customers</div>
        <h2 style="margin:8px 0 0;"><?php echo $total_customers; ?></h2>
    </div>
</div>

<!-- RECENT APPLICATIONS -->
<div class="card" style="margin-bottom:18px;">
    <h3 style="margin:0 0 12px;">Recent Loan Applications</h3>
    <div style="overflow:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($applicants): ?>
                    <?php foreach ($applicants as $a): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a['reference_no']); ?></td>
                        <td><?php echo htmlspecialchars($a['customer_name']); ?>
                            <span class="small">(<?php echo htmlspecialchars($a['customer_no']); ?>)</span>
                        </td>
                        <td>
                            <span class="badge <?php echo $a['status'] === 'active' ? 'green' : ($a['status'] === 'pending' ? 'gray' : 'red'); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $a['status']))); ?>
                            </span>
                        </td>
                        <td class="small"><?php echo htmlspecialchars($a['submitted_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;" class="small">No applications yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- STAFF LIST -->
<div class="card">
    <h3 style="margin:0 0 12px;">Staff Members</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Role</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($staff): ?>
                <?php foreach ($staff as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $s['role']))); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="2" style="text-align:center;" class="small">No staff found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
