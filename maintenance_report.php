<?php
// maintenance_report.php - For maintenance staff to report completed work
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

// ---- Auth: technician only ----
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'technician') {
  header('Location: login.php'); exit;
}
$tech_id = (int)$_SESSION['user_id'];
$tech_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Technician';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- CSRF ----
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok($t){ return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); }

// ---- Generate notification number ----
function make_notif_no(PDO $pdo){
  $year = date('Y');
  $stmt = $pdo->prepare("SELECT MAX(notif_no) FROM notification WHERE notif_no LIKE ?");
  $stmt->execute(["N{$year}-%"]);
  $max = $stmt->fetchColumn();
  $seq = 0;
  if ($max && preg_match("/N{$year}-(\\d{4})$/", $max, $m)) $seq = (int)$m[1];
  return "N{$year}-".str_pad($seq+1, 4, '0', STR_PAD_LEFT);
}

// ---- Lookups ----
$equip = $pdo->query("
  SELECT equipment_id, equipment_code, equipment_name 
  FROM equipment 
  WHERE status='ACTIVE' 
  ORDER BY equipment_code
")->fetchAll();

$flocs = $pdo->query("
  SELECT floc_id, floc_code, floc_name 
  FROM functional_location 
  WHERE is_active=1 
  ORDER BY floc_code
")->fetchAll();

// ---- Handle form submission ----
$flash = ''; $flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_report') {
  if (!csrf_ok($_POST['csrf'] ?? '')) {
    $flash_error = 'Invalid request. Please try again.';
  } else {
    try {
      $equipment_id = ($_POST['equipment_id'] ?? '') !== '' ? (int)$_POST['equipment_id'] : null;
      $floc_id = ($_POST['floc_id'] ?? '') !== '' ? (int)$_POST['floc_id'] : null;
      $description = trim($_POST['description'] ?? '');
      $work_details = trim($_POST['work_details'] ?? '');
      
      if (empty($description)) {
        throw new Exception('Work summary is required.');
      }
      
      if (!$equipment_id && !$floc_id) {
        throw new Exception('Please select equipment or location.');
      }
      
      // Create notification
      $notif_no = make_notif_no($pdo);
      
      $stmt = $pdo->prepare("
        INSERT INTO notification 
          (notif_no, reported_at, status, priority, description, 
           reporter_name, reporter_email, equipment_id, floc_id, created_by_user_id)
        VALUES 
          (?, NOW(), 'APPROVED', 'LOW', ?, ?, NULL, ?, ?, ?)
      ");
      
      $stmt->execute([
        $notif_no,
        $description,
        $tech_name,
        $equipment_id,
        $floc_id,
        $tech_id
      ]);
      
      $notif_id = (int)$pdo->lastInsertId();
      
      // Add work details as comment if provided
      if ($work_details !== '') {
        $pdo->prepare("
          INSERT INTO notification_comment (notification_id, author_user_id, comment_text)
          VALUES (?, ?, ?)
        ")->execute([$notif_id, $tech_id, $work_details]);
      }
      
      $flash = "Work completion report <strong>$notif_no</strong> submitted successfully!";
      
      // Clear form
      $_POST = [];
      
    } catch (Throwable $e) {
      $flash_error = $e->getMessage();
    }
  }
}

// ---- Load technician's previous reports ----
$my_reports = $pdo->prepare("
  SELECT n.*, e.equipment_name, f.floc_name
  FROM notification n
  LEFT JOIN equipment e ON e.equipment_id = n.equipment_id
  LEFT JOIN functional_location f ON f.floc_id = n.floc_id
  WHERE n.created_by_user_id = ?
  ORDER BY n.reported_at DESC
  LIMIT 20
");
$my_reports->execute([$tech_id]);
$reports = $my_reports->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Submit Work Report</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--bg:#fff;--text:#111;--muted:#5b6b7b;--line:#e9edf2;--success:#16a34a;}
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto}
    a{color:#0d6efd;text-decoration:none}
    .wrap{max-width:1000px;margin:24px auto;padding:0 16px}
    header{display:flex;align-items:center;gap:12px;margin-bottom:16px}
    .back{border:1px solid var(--line);padding:8px 12px;border-radius:10px}
    .spacer{flex:1}
    .who{color:var(--muted);font-size:14px}
    .card{border:1px solid var(--line);border-radius:14px;background:#fff;margin-bottom:16px}
    .pad{padding:20px}
    h2,h3{margin:0}
    .form-group{margin-bottom:16px}
    label{display:block;font-size:14px;color:var(--muted);margin-bottom:6px;font-weight:600}
    .required{color:#dc2626}
    input[type=text],select,textarea{width:100%;padding:12px;border:1px solid var(--line);border-radius:10px;background:#fff;font-size:14px}
    textarea{min-height:100px;resize:vertical;font-family:inherit}
    .btn{border:1px solid #d0d7e2;background:#f8fafc;border-radius:10px;padding:12px 20px;cursor:pointer;font-size:15px;font-weight:600}
    .btn.primary{background:var(--success);border-color:var(--success);color:#fff}
    .btn.primary:hover{background:#15803d}
    .alert{padding:14px 16px;border-radius:10px;margin-bottom:16px}
    .alert.ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
    .alert.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
    table{width:100%;border-collapse:collapse}
    th,td{padding:12px;border-bottom:1px solid var(--line);vertical-align:top;text-align:left}
    th{background:#fafafa;font-size:14px;font-weight:700}
    .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600}
    .badge.green{background:#d1fae5;color:#065f46}
    .badge.gray{background:#f3f4f6;color:#374151}
    .small{font-size:13px;color:var(--muted)}
    .info-box{background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:14px;margin-bottom:20px}
    .info-box strong{color:#0369a1}
  </style>
</head>
<body>
  <div class="wrap">
    <header>
      <a href="staff_dashboard.php" class="back">← Back</a>
      <h2>Submit Work Completion Report</h2>
      <div class="spacer"></div>
      <div class="who"><?= h($tech_name) ?> · <a href="logout.php">Logout</a></div>
    </header>

    <?php if ($flash): ?><div class="alert ok"><?= $flash ?></div><?php endif; ?>
    <?php if ($flash_error): ?><div class="alert err"><?= h($flash_error) ?></div><?php endif; ?>

    <div class="info-box">
      <strong>ℹ️ About Work Reports:</strong><br>
      Use this form to report completed maintenance work. Your report will be reviewed by the planner and helps maintain records of all maintenance activities.
    </div>

    <!-- Submit Report Form -->
    <div class="card pad">
      <h3 style="margin-bottom:20px">📋 New Work Report</h3>
      
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="submit_report">
        
        <div class="form-group">
          <label>Equipment <span class="required">*</span></label>
          <select name="equipment_id" id="equipment_id">
            <option value="">— Select Equipment —</option>
            <?php foreach($equip as $e): ?>
              <option value="<?= (int)$e['equipment_id'] ?>" <?= ($_POST['equipment_id'] ?? '')==$e['equipment_id']?'selected':'' ?>>
                <?= h($e['equipment_code'].' - '.$e['equipment_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Location <span class="required">*</span></label>
          <select name="floc_id" id="floc_id">
            <option value="">— Select Location —</option>
            <?php foreach($flocs as $f): ?>
              <option value="<?= (int)$f['floc_id'] ?>" <?= ($_POST['floc_id'] ?? '')==$f['floc_id']?'selected':'' ?>>
                <?= h($f['floc_code'].' - '.$f['floc_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="small" style="margin-top:4px">Select equipment OR location (or both)</div>
        </div>

        <div class="form-group">
          <label>Work Summary <span class="required">*</span></label>
          <input type="text" name="description" placeholder="e.g., Replaced air filter and cleaned condenser" 
                 value="<?= h($_POST['description'] ?? '') ?>" required maxlength="500">
          <div class="small" style="margin-top:4px">Brief summary of the work performed</div>
        </div>

        <div class="form-group">
          <label>Detailed Work Description (Optional)</label>
          <textarea name="work_details" placeholder="Provide detailed information about the work completed, tools used, observations, recommendations, etc."><?= h($_POST['work_details'] ?? '') ?></textarea>
          <div class="small" style="margin-top:4px">Additional details, observations, or recommendations</div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end">
          <a href="staff_dashboard.php" class="btn">Cancel</a>
          <button type="submit" class="btn primary">✓ Submit Report</button>
        </div>
      </form>
    </div>

    <!-- Previous Reports -->
    <?php if (count($reports) > 0): ?>
    <div class="card pad">
      <h3 style="margin-bottom:16px">📝 My Recent Reports (<?= count($reports) ?>)</h3>
      
      <table>
        <thead>
          <tr>
            <th style="width:110px">Report No.</th>
            <th style="width:140px">Date</th>
            <th>Work Summary</th>
            <th style="width:200px">Equipment/Location</th>
            <th style="width:100px">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($reports as $r): ?>
          <tr>
            <td><strong><?= h($r['notif_no']) ?></strong></td>
            <td class="small"><?= date('M d, Y H:i', strtotime($r['reported_at'])) ?></td>
            <td><?= h($r['description']) ?></td>
            <td class="small">
              <?php if ($r['equipment_name']): ?>
                🔧 <?= h($r['equipment_name']) ?><br>
              <?php endif; ?>
              <?php if ($r['floc_name']): ?>
                📍 <?= h($r['floc_name']) ?>
              <?php endif; ?>
            </td>
            <td>
              <?php 
                $status = $r['status'];
                if ($status === 'APPROVED' || $status === 'CLOSED') {
                  echo '<span class="badge green">'.h($status).'</span>';
                } else {
                  echo '<span class="badge gray">'.h($status).'</span>';
                }
              ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <script>
    // Auto-select location when equipment is selected
    document.getElementById('equipment_id').addEventListener('change', function() {
      if (this.value) {
        // If equipment selected, location becomes optional
        document.getElementById('floc_id').removeAttribute('required');
      }
    });
    
    document.getElementById('floc_id').addEventListener('change', function() {
      if (this.value) {
        // If location selected, equipment becomes optional
        document.getElementById('equipment_id').removeAttribute('required');
      }
    });
  </script>
</body>
</html>