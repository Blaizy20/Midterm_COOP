<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_helpers.php';
require_login();

// FIXED: Added 'CASHIER' to the allowed roles list
require_roles(['ADMIN','MANAGER','CREDIT_INVESTIGATOR','LOAN_OFFICER','CASHIER']);

$err = '';
$ok = '';

// Handle Delete Customer (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
  require_roles(['ADMIN']);
  $customer_id = intval($_POST['delete_customer'] ?? 0);
  if ($customer_id > 0) {
    $customer = fetch_one(q("SELECT customer_no, first_name, last_name FROM customers WHERE customer_id=?", "i", [$customer_id]));
    q("UPDATE customers SET is_active=0 WHERE customer_id=?", "i", [$customer_id]);
    if ($customer) {
      log_activity('Customer Deactivated', 'Customer ' . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . ' deactivated', null, $customer_id, $customer['customer_no']);
    }
    $ok = "Customer account deactivated.";
  } else {
    $err = "Cannot delete this account.";
  }
}

// Handle Activate Customer (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_customer'])) {
  require_roles(['ADMIN']);
  $customer_id = intval($_POST['activate_customer'] ?? 0);
  if ($customer_id > 0) {
    $customer = fetch_one(q("SELECT customer_no, first_name, last_name FROM customers WHERE customer_id=?", "i", [$customer_id]));
    q("UPDATE customers SET is_active=1 WHERE customer_id=?", "i", [$customer_id]);
    if ($customer) {
      log_activity('Customer Activated', 'Customer ' . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . ' activated', null, $customer_id, $customer['customer_no']);
    }
    $ok = "Customer account activated.";
  } else {
    $err = "Cannot activate this account.";
  }
}

// Handle Permanently Delete Customer (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permanent_delete_customer'])) {
  require_roles(['ADMIN']);
  $customer_id = intval($_POST['permanent_delete_customer'] ?? 0);
  if ($customer_id > 0) {
    $customer = fetch_one(q("SELECT customer_no, first_name, last_name FROM customers WHERE customer_id=?", "i", [$customer_id]));
    q("DELETE FROM customers WHERE customer_id=?", "i", [$customer_id]);
    if ($customer) {
      log_activity('Customer Permanently Deleted', 'Customer ' . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . ' permanently deleted', null, $customer_id, $customer['customer_no']);
    }
    $ok = "Customer account permanently deleted.";
  } else {
    $err = "Cannot delete this account.";
  }
}

// Handle Update Customer (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
  require_roles(['ADMIN']);
  $customer_id = intval($_POST['customer_id'] ?? 0);
  $first = trim($_POST['first_name'] ?? '');
  $last = trim($_POST['last_name'] ?? '');
  $contact = trim($_POST['contact_no'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $username = trim($_POST['username'] ?? '');
  $prov = trim($_POST['province'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $brgy = trim($_POST['barangay'] ?? '');
  $street = trim($_POST['street'] ?? '');

  if ($first === '' || $last === '') $err = "Please enter first and last name.";
  else if ($contact === '') $err = "Please enter contact number.";
  else if ($username === '') $err = "Please enter username.";
  else if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = "Invalid email format.";
  else {
    $customer = fetch_one(q("SELECT customer_no, user_id FROM customers WHERE customer_id=?", "i", [$customer_id]));
    if (!$customer || !$customer['user_id']) {
      $err = "Customer not found.";
    } else {
      // Check if username is already taken by another user
      $username_exists = fetch_one(q("SELECT user_id FROM users WHERE LOWER(username) = LOWER(?) AND user_id != ?", "si", [$username, intval($customer['user_id'])]));
      if ($username_exists) {
        $err = "Username already taken.";
      } else {
        q("UPDATE customers SET first_name=?, last_name=?, contact_no=?, email=?, province=?, city=?, barangay=?, street=? WHERE customer_id=?", 
          "ssssssssi", [$first, $last, $contact, $email, $prov, $city, $brgy, $street, $customer_id]);
        q("UPDATE users SET username=?, full_name=? WHERE user_id=?", "ssi", [$username, ($first.' '.$last), $customer['user_id']]);
        if ($customer) {
          log_activity('Customer Updated', 'Customer account information updated for ' . htmlspecialchars($first . ' ' . $last), null, $customer_id, $customer['customer_no']);
        }
        // Redirect to clear the success message and reset page state
        header("Location: " . APP_BASE . "/staff/customers.php");
        exit;
      }
    }
  }
}

// Handle Register Customer (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_customer'])) {
  require_roles(['ADMIN']);
  
  $first = trim($_POST['first_name'] ?? '');
  $last = trim($_POST['last_name'] ?? '');
  $contact = trim($_POST['contact_no'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $prov = trim($_POST['province'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $brgy = trim($_POST['barangay'] ?? '');
  $street = trim($_POST['street'] ?? '');
  $username = trim($_POST['username'] ?? '');
  $pw = $_POST['password'] ?? '';
  $pw2 = $_POST['confirm_password'] ?? '';

  if ($first==='' || $last==='' || $contact==='' || $username==='') $err="Please complete all required fields.";
  else if ($pw !== $pw2) $err="Passwords do not match.";
  else if (!password_is_strong($pw)) $err="Password must be 8+ chars with upper, lower, number, special.";
  else if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = "Invalid email format.";
  else {
    $conn = db();
    try {
      $conn->begin_transaction();
      // Check if username already exists
      $existing = fetch_one(q("SELECT user_id FROM users WHERE LOWER(username) = LOWER(?)", "s", [$username]));
      if ($existing) {
        $conn->rollback();
        $err = "Username already taken.";
      } else if (fetch_one(q("SELECT customer_id FROM customers WHERE contact_no=?", "s", [$contact]))) {
        $conn->rollback();
        $err = "Contact number already registered.";
      } else if ($email !== '' && fetch_one(q("SELECT customer_id FROM customers WHERE email=?", "s", [$email]))) {
        $conn->rollback();
        $err = "Email already registered.";
      } else {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        q("INSERT INTO users (username,password_hash,full_name,role) VALUES (?,?,?,?)", "ssss",
          [$username,$hash,($first.' '.$last),'CUSTOMER']);
        $user_id = intval($conn->insert_id);

        $customer_no = generate_customer_no();
        q("INSERT INTO customers (customer_no, user_id, first_name, last_name, contact_no, email, province, city, barangay, street)
            VALUES (?,?,?,?,?,?,?,?,?,?)",
          "sissssssss", [$customer_no, $user_id, $first, $last, $contact, $email, $prov, $city, $brgy, $street]);

        $conn->commit();
        log_activity('Customer Registered', 'New customer ' . htmlspecialchars($first . ' ' . $last) . ' registered', null, $user_id, $customer_no);
        $ok = "Customer account created successfully.";
      }
    } catch (mysqli_sql_exception $e) {
      try { $conn->rollback(); } catch (Exception $ex) {}
      $err = "Registration failed: " . htmlspecialchars($e->getMessage());
    }
  }
}

$rows = fetch_all(q("SELECT c.customer_id, c.customer_no, c.first_name, c.last_name, c.contact_no, c.email, c.province, c.city, c.barangay, c.street, c.created_at, c.is_active, u.username FROM customers c LEFT JOIN users u ON u.user_id=c.user_id ORDER BY c.created_at DESC"));
$title="Customers"; $active="cust";
include __DIR__ . '/_layout_top.php';
?>
<div class="card">
  <h2 style="margin-top:0">Customers</h2>

  <?php if ($err): ?><div class="alert red" style="margin-top:12px"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="alert green" style="margin-top:12px"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

  <div style="overflow:auto;margin-top:14px">
    <table class="table">
      <thead><tr><th>Customer No</th><th>Name</th><th>Contact</th><th>Email</th><th>Status</th><?php if ($_SESSION['role'] === 'ADMIN'): ?><th>Action</th><?php endif; ?><th>Created</th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['customer_no']) ?></td>
            <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
            <td><?= htmlspecialchars($r['contact_no']) ?></td>
            <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
            <td><span class="badge <?= ($r['is_active'] ?? 1) ? 'green' : 'red' ?>"><?= ($r['is_active'] ?? 1) ? 'Active' : 'Inactive' ?></span></td>
            <?php if ($_SESSION['role'] === 'ADMIN'): ?>
            <td style="white-space:nowrap">
              <a class="btn btn-primary" href="#" onclick="editCustomer(<?= intval($r['customer_id']) ?>, '<?= htmlspecialchars($r['first_name']) ?>', '<?= htmlspecialchars($r['last_name']) ?>', '<?= htmlspecialchars($r['contact_no']) ?>', '<?= htmlspecialchars($r['email'] ?? '') ?>', '<?= htmlspecialchars($r['username'] ?? '') ?>', '<?= htmlspecialchars($r['province'] ?? '') ?>', '<?= htmlspecialchars($r['city'] ?? '') ?>', '<?= htmlspecialchars($r['barangay'] ?? '') ?>', '<?= htmlspecialchars($r['street'] ?? '') ?>')">Edit</a>
              <form style="display:inline" method="post" onsubmit="return confirm('Permanently delete this customer? This cannot be undone.')">
                <input type="hidden" name="permanent_delete_customer" value="<?= intval($r['customer_id']) ?>">
                <button class="btn btn-primary" type="submit">Delete</button>
              </form>
            </td>
            <?php endif; ?>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(empty($rows)): ?><tr><td colspan="7" class="small">No customers.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($_SESSION['role'] === 'ADMIN'): ?>

<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div class="card" style="max-width:900px;width:95%">
    <h3 style="margin-top:0">Edit Customer Account</h3>
    <form method="post">
      <input type="hidden" id="edit_customer_id" name="customer_id">
      
      <div class="grid2">
        <div>
          <label class="label">First Name</label>
          <input class="input" id="edit_first_name" name="first_name" required>
        </div>
        <div>
          <label class="label">Last Name</label>
          <input class="input" id="edit_last_name" name="last_name" required>
        </div>
      </div>

      <div class="grid2" style="margin-top:10px">
        <div>
          <label class="label">Contact No.</label>
          <input class="input" id="edit_contact_no" name="contact_no" required>
        </div>
        <div>
          <label class="label">Username</label>
          <input class="input" id="edit_username" name="username" required>
        </div>
      </div>

      <div class="grid2" style="margin-top:10px">
        <div>
          <label class="label">Email</label>
          <input class="input" type="email" id="edit_email" name="email">
        </div>
        <div>
          <label class="label">Barangay</label>
          <input class="input" id="edit_barangay" name="barangay">
        </div>
      </div>

      <div class="grid2" style="margin-top:10px">
        <div>
          <label class="label">Province</label>
          <input class="input" id="edit_province" name="province">
        </div>
        <div>
          <label class="label">City</label>
          <input class="input" id="edit_city" name="city">
        </div>
      </div>

      <div class="grid2" style="margin-top:10px">
        <div>
          <label class="label">Street</label>
          <input class="input" id="edit_street" name="street">
        </div>
      </div>

      <div style="margin-top:14px;display:flex;gap:10px">
        <button class="btn btn-primary" type="submit" name="update_customer" value="1">Update</button>
        <button class="btn btn-outline" type="button" onclick="closeEdit()">Cancel</button>
      </div>
    </form>
  </div>
</div>

    <div style="height:20px"></div>
    <h3>Register New Customer</h3>

    <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>" style="margin-top:14px">
      <div class="grid2">
        <div>
          <label class="label">First Name</label>
          <input class="input" name="first_name" required value="<?= (isset($_POST['register_customer']) ? htmlspecialchars($_POST['first_name'] ?? '') : '') ?>">
        </div>
        <div>
          <label class="label">Last Name</label>
          <input class="input" name="last_name" required value="<?= (isset($_POST['register_customer']) ? htmlspecialchars($_POST['last_name'] ?? '') : '') ?>">
        </div>
      </div>

      <div class="grid2" style="margin-top:10px">
        <div>
          <label class="label">Contact No.</label>
          <input class="input" name="contact_no" required value="<?= (isset($_POST['register_customer']) ? htmlspecialchars($_POST['contact_no'] ?? '') : '') ?>">
        </div>
        <div>
          <label class="label">Email</label>
          <input class="input" type="email" name="email" value="<?= (isset($_POST['register_customer']) ? htmlspecialchars($_POST['email'] ?? '') : '') ?>">
        </div>
      </div>

      <div class="grid2" style="margin-top:10px">
        <div>
          <label class="label">Province</label>
          <input class="input" name="province" value="<?= (isset($_POST['register_customer']) ? htmlspecialchars($_POST['province'] ?? '') : '') ?>">
        </div>
        <div>
          <label class="label">City</label>
          <input class="input" name="city" value="<?= (isset($_POST['register_customer']) ? htmlspecialchars($_POST['city'] ?? '') : '') ?>">
        </div>
      </div>

      <div class="grid2" style="margin-top:10px">
        <div>
          <label class="label">Barangay</label>
          <input class="input" name="barangay" value="<?= (isset($_POST['register_customer']) ? htmlspecialchars($_POST['barangay'] ?? '') : '') ?>">
        </div>
        <div>
          <label class="label">Street</label>
          <input class="input" name="street" value="<?= (isset($_POST['register_customer']) ? htmlspecialchars($_POST['street'] ?? '') : '') ?>">
        </div>
      </div>

      <div style="margin-top:10px">
        <label class="label">Username</label>
        <input class="input" name="username" required value="<?= (isset($_POST['register_customer']) ? htmlspecialchars($_POST['username'] ?? '') : '') ?>">
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
        <button class="btn btn-primary" type="submit" name="register_customer" value="1">Register</button>
        <a class="btn btn-ghost" href="<?php echo APP_BASE; ?>/staff/customers.php">Back</a>
      </div>
    </form>

    <script>
    function editCustomer(customerId, firstName, lastName, contact, email, username, province, city, barangay, street) {
      document.getElementById('edit_customer_id').value = customerId;
      document.getElementById('edit_first_name').value = firstName;
      document.getElementById('edit_last_name').value = lastName;
      document.getElementById('edit_contact_no').value = contact;
      document.getElementById('edit_email').value = email;
      document.getElementById('edit_username').value = username;
      document.getElementById('edit_province').value = province;
      document.getElementById('edit_city').value = city;
      document.getElementById('edit_barangay').value = barangay;
      document.getElementById('edit_street').value = street;
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
  <?php endif; ?>
</div>
<?php include __DIR__ . '/_layout_bottom.php'; ?>