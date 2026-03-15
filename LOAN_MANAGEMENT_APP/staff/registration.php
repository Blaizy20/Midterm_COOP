<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_helpers.php';
require_login();
require_roles(['ADMIN']); // Only Admin registers staff

$title = "Staff Registration";
$active = "reg";

$err = '';
$ok = '';

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
  $user_id = intval($_POST['delete_user'] ?? 0);
  if ($user_id > 0 && $user_id !== $_SESSION['user_id']) { // prevent self-delete
    // Get staff details before deletion for logging
    $staff_to_delete = fetch_one(q("SELECT user_id, full_name, role FROM users WHERE user_id=?", "i", [$user_id]));
    q("DELETE FROM users WHERE user_id=?", "i", [$user_id]);
    if ($staff_to_delete) {
      log_activity('Staff Permanently Deleted', 'Staff account ' . htmlspecialchars($staff_to_delete['full_name']) . ' (' . htmlspecialchars($staff_to_delete['role']) . ') deleted', null, null, null);
    }
    $ok = "Staff account deleted successfully.";
  } else {
    $err = "Cannot delete this account.";
  }
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
  $user_id = intval($_POST['user_id'] ?? 0);
  $full = trim($_POST['full_name'] ?? '');
  $role = $_POST['role'] ?? '';
  $email = trim($_POST['email'] ?? '');
  $pw = $_POST['password'] ?? '';

  $allowed = ['ADMIN','MANAGER','CREDIT_INVESTIGATOR','LOAN_OFFICER','CASHIER'];
  if ($full === '') $err = "Please enter full name.";
  else if (count(explode(' ', $full)) < 2) $err = "Full name must include first and last name.";
  else if (!in_array($role, $allowed, true)) $err = "Invalid role.";
  else if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = "Invalid email format.";
  else if ($pw && !password_is_strong($pw)) $err = "Password must be 8+ chars with upper, lower, number, special.";
  else {
    if ($pw) {
      $hash = password_hash($pw, PASSWORD_DEFAULT);
      q("UPDATE users SET full_name=?, role=?, email=?, password_hash=? WHERE user_id=?", "ssssi", [$full, $role, $email, $hash, $user_id]);
    } else {
      q("UPDATE users SET full_name=?, role=?, email=? WHERE user_id=?", "sssi", [$full, $role, $email, $user_id]);
    }
    $ok = "Staff account updated.";
  }
}

// Handle Register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_staff'])) {
  $full = trim($_POST['full_name'] ?? '');
  $username = trim($_POST['username'] ?? '');
  $role = $_POST['role'] ?? '';
  $email = trim($_POST['email'] ?? '');
  $pw = $_POST['password'] ?? '';
  $pw2 = $_POST['confirm_password'] ?? '';

  $allowed = ['ADMIN','MANAGER','CREDIT_INVESTIGATOR','LOAN_OFFICER','CASHIER'];
  if ($full === '' || $username === '') $err = "Please complete all required fields.";
  else if (count(explode(' ', $full)) < 2) $err = "Full name must include first and last name.";
  else if (!in_array($role, $allowed, true)) $err = "Invalid role.";
  else if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = "Invalid email format.";
  else if ($pw !== $pw2) $err = "Passwords do not match.";
  else if (!password_is_strong($pw)) $err = "Password must be 8+ chars with upper, lower, number, special.";
  else {
    $conn = db();
    try {
      // start transaction to avoid race condition on username
      $conn->begin_transaction();

      // lock any existing row with this username (case-insensitive)
      $existing = fetch_one(q("SELECT user_id FROM users WHERE LOWER(username) = LOWER(?) FOR UPDATE", "s", [$username]));
      if ($existing) {
        $conn->rollback();
        $err = "Username already exists.";
      } else {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        q("INSERT INTO users (username,password_hash,full_name,role,email) VALUES (?,?,?,?,?)", "sssss", [$username,$hash,$full,$role,$email]);
        $conn->commit();
        log_activity('Staff Created', 'Staff account created - ' . htmlspecialchars($full) . ' (' . htmlspecialchars($role) . ')', null, null, null);
        $ok = "Staff account created successfully.";
      }
    } catch (mysqli_sql_exception $e) {
      try { $conn->rollback(); } catch (Exception $ex) {}
      if (strpos(strtolower($e->getMessage()), 'duplicate') !== false) {
        $err = "Username already exists.";
      } else {
        $err = "Registration failed: " . $e->getMessage();
      }
    }
  }
}

// Fetch all staff
$staff = fetch_all(q("SELECT user_id, username, full_name, role, email, is_active FROM users WHERE role IN ('ADMIN','MANAGER','CREDIT_INVESTIGATOR','LOAN_OFFICER','CASHIER') ORDER BY full_name", ""));
include __DIR__ . '/_layout_top.php';
?>
<div class="card">
  <h2 style="margin:0 0 10px 0">Manage Staff Accounts</h2>

  <?php if ($err): ?><div class="alert red" style="margin-top:12px"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="alert green" style="margin-top:12px"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

  <div style="overflow:auto;margin-top:14px">
    <table class="table">
      <thead>
        <tr>
          <th>Username</th>
          <th>Full Name</th>
          <th>Email</th>
          <th></th>Role</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($staff as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['username']) ?></td>
            <td><?= htmlspecialchars($s['full_name']) ?></td>
            <td><?= htmlspecialchars($s['email'] ?? '-') ?></td>
            <td><?= htmlspecialchars($s['role']) ?></td>
            <td><span class="badge <?= $s['is_active'] ? 'green' : 'red' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td>
              <a class="btn btn-primary" href="#" onclick="editStaff(<?= intval($s['user_id']) ?>, '<?= htmlspecialchars($s['full_name']) ?>', '<?= htmlspecialchars($s['role']) ?>', '<?= htmlspecialchars($s['email'] ?? '') ?>')">Edit</a>
              <?php if ($s['user_id'] !== $_SESSION['user_id']): ?>
                <form style="display:inline" method="post" onsubmit="return confirm('Permanently delete this account?')">
                  <input type="hidden" name="delete_user" value="<?= intval($s['user_id']) ?>">
                  <button class="btn btn-primary" type="submit">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div style="height:20px"></div>
  <h3>Register New Staff Account</h3>

  <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>" style="margin-top:14px">
    <div class="grid2">
      <div>
        <label class="label">Full Name</label>
        <input class="input" name="full_name" required value="<?= (!$ok && isset($_POST['full_name'])) ? htmlspecialchars($_POST['full_name']) : '' ?>">
      </div>
      <div>
        <label class="label">Username</label>
        <input class="input" name="username" required value="<?= (!$ok && isset($_POST['username'])) ? htmlspecialchars($_POST['username']) : '' ?>">
      </div>
    </div>

    <div class="grid2" style="margin-top:10px">
      <div>
        <label class="label">Role</label>
        <select class="input" name="role" required>
          <?php
            $roles = ['ADMIN'=>'Admin','MANAGER'=>'Manager','CREDIT_INVESTIGATOR'=>'Credit Investigator','LOAN_OFFICER'=>'Loan Officer','CASHIER'=>'Cashier'];
            $sel = (!$ok && isset($_POST['role'])) ? $_POST['role'] : '';
            foreach ($roles as $k=>$v) {
              echo '<option value="'.htmlspecialchars($k).'" '.($sel===$k?'selected':'').'>'.htmlspecialchars($v).'</option>';
            }
          ?>
        </select>
      </div>
      <div>
        <label class="label">Email

        </label>
        <input class="input" type="email" name="email" value="<?= (!$ok && isset($_POST['email'])) ? htmlspecialchars($_POST['email']) : '' ?>">
      </div>
    </div>

    <div class="grid2" style="margin-top:10px">
      <div>
        <label class="label">Password</label>
        <input class="input" id="pw" type="password" name="password" required>
        <div class="small" style="margin-top:6px">8+ chars, with uppercase, lowercase, number, special.</div>
      </div>
      <div>
        <label class="label">Confirm Password</label>
        <input class="input" id="pw2" type="password" name="confirm_password" required>
      </div>
    </div>

    <div style="margin-top:10px">
      <label class="small" style="display:flex;gap:8px;align-items:center">
        <input type="checkbox" onclick="togglePw()">
        Show password
      </label>
    </div>

    <div style="margin-top:14px;display:flex;gap:10px;align-items:center">
      <button class="btn btn-primary" type="submit" name="register_staff" value="1">Register</button>
      <a class="btn btn-ghost" href="<?php echo APP_BASE; ?>/staff/dashboard.php">Back</a>
    </div>
  </form>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div class="card" style="max-width:500px;width:90%">
    <h3 style="margin-top:0">Edit Staff Account</h3>
    <form method="post">
      <input type="hidden" id="edit_user_id" name="user_id">
      <div>
        <label class="label">Full Name</label>
        <input class="input" id="edit_full_name" name="full_name" required>
      </div>
      <div style="margin-top:10px">
        <label class="label">Email</label>
        <input class="input" type="email" id="edit_email" name="email">
      </div>
      <div style="margin-top:10px">
        <label class="label">Role</label>
        <select class="input" id="edit_role" name="role" required>
          <?php
            $roles = ['ADMIN'=>'Admin','MANAGER'=>'Manager','CREDIT_INVESTIGATOR'=>'Credit Investigator','LOAN_OFFICER'=>'Loan Officer','CASHIER'=>'Cashier'];
            foreach ($roles as $k=>$v) {
              echo '<option value="'.htmlspecialchars($k).'">'.htmlspecialchars($v).'</option>';
            }
          ?>
        </select>
      </div>
      <div style="margin-top:10px">
        <label class="label">New Password (leave blank to keep current)</label>
        <input class="input" type="password" name="password" id="edit_password">
        <div class="small" style="margin-top:6px">8+ chars, upper, lower, number, special (if changing)</div>
      </div>
      <div style="margin-top:14px;display:flex;gap:10px">
        <button class="btn btn-primary" type="submit" name="update_user" value="1">Update</button>
        <button class="btn btn-outline" type="button" onclick="closeEdit()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function editStaff(userId, fullName, role, email) {
  document.getElementById('edit_user_id').value = userId;
  document.getElementById('edit_full_name').value = fullName;
  document.getElementById('edit_role').value = role;
  document.getElementById('edit_email').value = email;
  document.getElementById('edit_password').value = '';
  document.getElementById('editModal').style.display = 'flex';
}

function closeEdit() {
  document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEdit();
});

function togglePw(){
  const a=document.getElementById('pw');
  const b=document.getElementById('pw2');
  const t=a.type==='password'?'text':'password';
  a.type=t; b.type=t;
}
</script>
<?php include __DIR__ . '/_layout_bottom.php'; ?>