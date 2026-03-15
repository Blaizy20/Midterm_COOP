<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

try {
  $conn = db();
  
  // Update old APPLICATION_FORM requirement codes to SPECIMEN_SIGNATURES
  $stmt = q("UPDATE requirements SET requirement_code = 'SPECIMEN_SIGNATURES', requirement_name = 'Valid ID with 3 Specimen Signatures' 
             WHERE requirement_code = 'APPLICATION_FORM'", "");
  
  $affected = $conn->affected_rows;
  
  // Also update any by requirement_name
  $stmt2 = q("UPDATE requirements SET requirement_code = 'SPECIMEN_SIGNATURES', requirement_name = 'Valid ID with 3 Specimen Signatures' 
              WHERE requirement_name = 'Application Form'", "");
  
  $affected += $conn->affected_rows;
  
  echo "<h2>Migration Complete</h2>";
  echo "<p>Updated <b>$affected</b> requirement record(s) from 'Application Form' to 'Valid ID with 3 Specimen Signatures'.</p>";
  echo '<p><a href="' . APP_BASE . '/index.php">Go back to system</a></p>';
  
} catch (Exception $e) {
  echo "<h2>Migration Failed</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
