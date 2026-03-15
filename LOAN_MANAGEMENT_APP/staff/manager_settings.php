<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_helpers.php';
require_login();
require_roles(['MANAGER']);

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $system_name = trim($_POST['system_name'] ?? '');
  $primary_color = trim($_POST['primary_color'] ?? '#2c3ec5');
  $logo_path = null;
  
  // Validate inputs
  if (empty($system_name) || strlen($system_name) > 255) {
    $error = 'System name is required and must be less than 255 characters.';
  } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $primary_color)) {
    $error = 'Invalid color format. Please use hex color (e.g., #2c3ec5).';
  } else {
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Logo upload failed. Error code: ' . $_FILES['logo']['error'];
      } else {
        $file_name = $_FILES['logo']['name'];
        $file_size = $_FILES['logo']['size'];
        $file_tmp = $_FILES['logo']['tmp_name'];
        
        // Validate file type
        $allowed_types = ['image/png', 'image/jpeg'];
        $file_type = mime_content_type($file_tmp);
        
        if (!in_array($file_type, $allowed_types)) {
          $error = 'Only PNG and JPG files are allowed.';
        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB max
          $error = 'Logo file size must not exceed 5MB.';
        } else {
          $upload_dir = __DIR__ . '/../uploads/logo/';
          if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
          }
          
          $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
          $new_file_name = 'logo_' . time() . '.' . strtolower($file_extension);
          $upload_path = $upload_dir . $new_file_name;
          
          if (move_uploaded_file($file_tmp, $upload_path)) {
            $logo_path = APP_BASE . '/uploads/logo/' . $new_file_name;
          } else {
            $error = 'Failed to save logo file.';
          }
        }
      }
    }
    
    // If no error, update database
    if (empty($error)) {
      try {
        $current_settings = fetch_one(q("SELECT setting_id, logo_path FROM system_settings LIMIT 1"));
        
        if ($logo_path === null && $current_settings) {
          $logo_path = $current_settings['logo_path'];
        }
        
        // Use LIMIT 1 to update the first record if setting_id doesn't exist
        if ($current_settings && isset($current_settings['setting_id'])) {
          q("UPDATE system_settings SET system_name=?, logo_path=?, primary_color=? WHERE setting_id=?", 
            "sssi", [$system_name, $logo_path, $primary_color, $current_settings['setting_id']]);
        } else {
          q("UPDATE system_settings SET system_name=?, logo_path=?, primary_color=? LIMIT 1", 
            "sss", [$system_name, $logo_path, $primary_color]);
        }
        
        // Refresh session cache
        $_SESSION['system_settings'] = [
          'system_name' => $system_name,
          'logo_path' => $logo_path,
          'primary_color' => $primary_color
        ];
        
        $message = 'Settings updated successfully!';
      } catch (Exception $e) {
        $error = 'Database error: ' . $e->getMessage();
        error_log("Settings update error: " . $e->getMessage());
      }
    }
  }
}

// Fetch current settings (refresh after potential update)
$settings = fetch_one(q("SELECT * FROM system_settings LIMIT 1"));
if (!$settings) {
  $settings = [
    'system_name' => 'CredenceLend',
    'logo_path' => APP_BASE . '/assets/img/logo.png',
    'primary_color' => '#2c3ec5'
  ];
} else {
  // Ensure all required fields exist
  if (empty($settings['logo_path'])) {
    $settings['logo_path'] = APP_BASE . '/assets/img/logo.png';
  }
  if (empty($settings['primary_color'])) {
    $settings['primary_color'] = '#2c3ec5';
  }
}

$title = "Manager Settings";
$active = "settings";
include __DIR__ . '/_layout_top.php';
?>

<div class="row">
  <div class="col">
    <div class="card">
      <h2 style="margin-bottom: 20px;">System Settings</h2>
      
      <?php if ($message): ?>
        <div class="alert alert-green" style="background:#dcfce7;color:#166534;padding:12px;border-radius:8px;margin-bottom:20px">
          ✓ <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>
      
      <?php if ($error): ?>
        <div class="alert alert-red" style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;margin-bottom:20px">
          ✗ <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
      
      <form method="POST" enctype="multipart/form-data">
        <div style="margin-bottom:20px">
          <label class="label">System Name</label>
          <input type="text" name="system_name" class="input" value="<?= htmlspecialchars($settings['system_name'] ?? '') ?>" required>
        </div>
        
        <div style="margin-bottom:20px">
          <label class="label">Primary Color</label>
          <div style="display:flex;gap:10px;align-items:center">
            <input type="color" id="color_picker" value="<?= htmlspecialchars($settings['primary_color'] ?? '#2c3ec5') ?>" style="width:80px;height:40px;border:1px solid #ddd;border-radius:4px;cursor:pointer">
            <input type="text" id="color_hex" name="primary_color" class="input" value="<?= htmlspecialchars($settings['primary_color'] ?? '#2c3ec5') ?>" placeholder="#2c3ec5" style="width:150px">
          </div>
          <div style="font-size:12px;color:#6B7280;margin-top:5px">Use color wheel or type hex code (e.g., #2c3ec5)</div>
        </div>
        
        <div style="margin-bottom:20px">
          <label class="label">System Logo</label>
          <div style="margin-bottom:10px">
            <?php if ($settings['logo_path']): ?>
              <img src="<?= htmlspecialchars($settings['logo_path']) ?>" alt="Current Logo" style="max-width:150px;max-height:150px;border-radius:4px;border:1px solid #ddd;padding:8px">
              <div style="font-size:12px;color:#6B7280;margin-top:8px">Current logo</div>
            <?php endif; ?>
          </div>
          <input type="file" name="logo" accept=".png,.jpg,.jpeg" style="padding:8px;border:1px solid #ddd;border-radius:4px;width:100%">
          <div style="font-size:12px;color:#6B7280;margin-top:5px">PNG or JPG only, max 5MB</div>
        </div>
        
        <div style="margin-top:30px">
          <button type="submit" class="btn btn-primary" style="background:#2c3ec5;padding:12px 24px;font-size:14px;color:white">Save Settings</button>
          <a href="<?php echo APP_BASE; ?>/staff/dashboard.php" class="btn btn-outline" style="padding:12px 24px;font-size:14px;margin-left:10px">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Sync color picker (visual) with hex input (form submission)
document.getElementById('color_picker').addEventListener('change', function(e) {
  document.getElementById('color_hex').value = e.target.value;
});

document.getElementById('color_picker').addEventListener('input', function(e) {
  document.getElementById('color_hex').value = e.target.value;
});

// Update color picker when hex input changes
document.getElementById('color_hex').addEventListener('input', function(e) {
  let value = e.target.value.trim();
  
  // Allow typing without # and auto-add it
  if (value && !value.startsWith('#')) {
    value = '#' + value;
    this.value = value;
  }
  
  // Update color picker if valid hex
  if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
    document.getElementById('color_picker').value = value;
  }
});
</script>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
