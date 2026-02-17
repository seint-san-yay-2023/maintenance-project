<?php
// equipment.php – Add/Edit/View Equipment with Stock Tracking (Planner)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

// ---- Auth: planner only ----
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'planner') {
  header('Location: login.php'); exit;
}
$planner_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Planner';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- CSRF ----
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok($t){ return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); }

// ---- Auto-generate Equipment Code ----
function make_equipment_code(PDO $pdo){
  $year = date('Y');
  $stmt = $pdo->prepare("SELECT MAX(equipment_code) FROM equipment WHERE equipment_code LIKE ?");
  $stmt->execute(["EQ{$year}-%"]);
  $max = $stmt->fetchColumn();
  $seq = 0;
  if ($max && preg_match('/EQ'.$year.'-(\d{4})$/', $max, $m)) $seq = (int)$m[1];
  return "EQ{$year}-".str_pad($seq+1, 4, '0', STR_PAD_LEFT);
}

// ---- Lookups ----
$workcenters = $pdo->query("
  SELECT work_center_id, wc_code, wc_name
  FROM work_center
  ORDER BY wc_code, wc_name
")->fetchAll();

$flocs = $pdo->query("
  SELECT floc_id, floc_code, floc_name
  FROM functional_location
  ORDER BY floc_code, floc_name
")->fetchAll();

$vendors = $pdo->query("
  SELECT vendor_id, vendor_code, vendor_name
  FROM vendor
  WHERE is_active = 1
  ORDER BY vendor_code
")->fetchAll();

$materials = $pdo->query("
  SELECT material_id, material_code, material_name, on_hand_qty, unit_of_measure
  FROM material
  WHERE is_active = 1
  ORDER BY material_code
")->fetchAll();

// ---- Handle create / update ----
$flash = ''; $flash_error = '';

if (($_POST['action'] ?? '') === 'save_equipment' && csrf_ok($_POST['csrf'] ?? '')) {
  try {
    $eid = isset($_POST['equipment_id']) && $_POST['equipment_id'] !== '' ? (int)$_POST['equipment_id'] : null;
    
    // Auto-generate code for new equipment
    if ($eid) {
      $code = trim($_POST['equipment_code'] ?? '');
      if ($code === '') throw new Exception('Equipment Code is required.');
    } else {
      $code = make_equipment_code($pdo);
    }
    
    $name = trim($_POST['equipment_name'] ?? '');
    $floc = ($_POST['floc_id'] ?? '') !== '' ? (int)$_POST['floc_id'] : null;
    $wc   = ($_POST['work_center_id'] ?? '') !== '' ? (int)$_POST['work_center_id'] : null;
    $vendor = ($_POST['vendor_id'] ?? '') !== '' ? (int)$_POST['vendor_id'] : null;
    $serial = trim($_POST['serial_no'] ?? '');
    $model = trim($_POST['model_no'] ?? '');
    $install_date = $_POST['install_date'] ?? null;
    $criticality = $_POST['criticality'] ?? 'LOW';
    $status = $_POST['status'] ?? 'ACTIVE';

    if ($name === '') throw new Exception('Equipment Name is required.');

    // Ensure unique code (for updates only)
    if ($eid) {
      $q = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE equipment_code = ? AND equipment_id <> ?");
      $q->execute([$code, $eid]);
      if ((int)$q->fetchColumn() > 0) throw new Exception('Equipment Code already exists.');
    }

    if ($eid) {
      // UPDATE
      $stmt = $pdo->prepare("
        UPDATE equipment
        SET equipment_code=?, equipment_name=?, floc_id=?, work_center_id=?,
            vendor_id=?, serial_no=?, model_no=?, install_date=?, 
            criticality=?, status=?
        WHERE equipment_id=?
      ");
      $stmt->execute([$code, $name, $floc, $wc, $vendor, $serial, $model, 
                      $install_date, $criticality, $status, $eid]);
      $flash = "Equipment <strong>".h($code)."</strong> updated.";
    } else {
      // INSERT
      $stmt = $pdo->prepare("
        INSERT INTO equipment 
          (equipment_code, equipment_name, floc_id, work_center_id, vendor_id, 
           serial_no, model_no, install_date, criticality, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([$code, $name, $floc, $wc, $vendor, $serial, $model, 
                      $install_date, $criticality, $status]);
      $new_id = (int)$pdo->lastInsertId();
      $flash = "Equipment <strong>".h($code)."</strong> created successfully!";
      
      // Redirect to edit mode to manage materials
      header("Location: equipment.php?edit=$new_id");
      exit;
    }
  } catch (Throwable $e) {
    $flash_error = $e->getMessage();
  }
}

// Add material to equipment BOM
if (($_POST['action'] ?? '') === 'add_material' && csrf_ok($_POST['csrf'] ?? '')) {
  try {
    $eid = (int)$_POST['equipment_id'];
    $mid = (int)$_POST['material_id'];
    $qty = (float)$_POST['quantity'];
    $notes = trim($_POST['notes'] ?? '');
    
    if ($mid <= 0) throw new Exception('Select a material.');
    if ($qty <= 0) throw new Exception('Quantity must be greater than 0.');
    
    $pdo->prepare("
      INSERT INTO equipment_bom (equipment_id, material_id, quantity, notes)
      VALUES (?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE quantity=?, notes=?
    ")->execute([$eid, $mid, $qty, $notes, $qty, $notes]);
    
    $flash = "Material added to equipment BOM.";
  } catch (Throwable $e) {
    $flash_error = $e->getMessage();
  }
}

// Remove material from equipment BOM
if (($_POST['action'] ?? '') === 'remove_material' && csrf_ok($_POST['csrf'] ?? '')) {
  try {
    $eid = (int)$_POST['equipment_id'];
    $mid = (int)$_POST['material_id'];
    
    $pdo->prepare("DELETE FROM equipment_bom WHERE equipment_id=? AND material_id=?")
        ->execute([$eid, $mid]);
    
    $flash = "Material removed from equipment BOM.";
  } catch (Throwable $e) {
    $flash_error = $e->getMessage();
  }
}

// ---- Filters / search ----
$q = trim($_GET['q'] ?? '');
$f_wc = ($_GET['wc'] ?? '') !== '' ? (int)$_GET['wc'] : null;
$f_fl = ($_GET['fl'] ?? '') !== '' ? (int)$_GET['fl'] : null;
$f_status = $_GET['status'] ?? 'ALL';
$f_crit = $_GET['crit'] ?? 'ALL';

$where = []; $params = [];
if ($q !== '') {
  $where[] = "(e.equipment_code LIKE ? OR e.equipment_name LIKE ? OR e.serial_no LIKE ? OR fl.floc_name LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($f_wc) { $where[] = "e.work_center_id = ?"; $params[] = $f_wc; }
if ($f_fl) { $where[] = "e.floc_id = ?"; $params[] = $f_fl; }
if ($f_status !== 'ALL') { $where[] = "e.status = ?"; $params[] = $f_status; }
if ($f_crit !== 'ALL') { $where[] = "e.criticality = ?"; $params[] = $f_crit; }

$sql = "
SELECT e.equipment_id, e.equipment_code, e.equipment_name, e.serial_no, e.model_no,
       e.install_date, e.criticality, e.status,
       fl.floc_code, fl.floc_name,
       wc.wc_code, wc.wc_name,
       v.vendor_code, v.vendor_name
FROM equipment e
LEFT JOIN functional_location fl ON fl.floc_id = e.floc_id
LEFT JOIN work_center wc ON wc.work_center_id = e.work_center_id
LEFT JOIN vendor v ON v.vendor_id = e.vendor_id
".(count($where) ? "WHERE ".implode(" AND ", $where) : "")."
ORDER BY e.equipment_code
LIMIT 500";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// ---- Edit mode ----
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_row = null;
$bom_materials = [];

if ($edit_id > 0) {
  $s = $pdo->prepare("SELECT * FROM equipment WHERE equipment_id = ? LIMIT 1");
  $s->execute([$edit_id]);
  $edit_row = $s->fetch();
  
  // Load BOM materials
  $bom = $pdo->prepare("
    SELECT eb.*, m.material_code, m.material_name, m.on_hand_qty, m.unit_of_measure
    FROM equipment_bom eb
    JOIN material m ON m.material_id = eb.material_id
    WHERE eb.equipment_id = ?
    ORDER BY m.material_code
  ");
  $bom->execute([$edit_id]);
  $bom_materials = $bom->fetchAll();
}

// ---- Badge helpers ----
function criticality_badge($crit) {
  $colors = ['LOW'=>'#6b7280','MEDIUM'=>'#f59e0b','HIGH'=>'#ef4444'];
  $color = $colors[$crit] ?? '#6b7280';
  return "<span style='background:$color;color:#fff;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600'>$crit</span>";
}

function status_badge($status) {
  $colors = ['ACTIVE'=>'#16a34a','INACTIVE'=>'#6b7280','RETIRED'=>'#dc2626'];
  $color = $colors[$status] ?? '#6b7280';
  return "<span style='background:$color;color:#fff;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600'>$status</span>";
}

function stock_status($qty, $required) {
  if ($qty >= $required) {
    return "<span style='color:#16a34a;font-weight:600'>✓ Available</span>";
  } else if ($qty > 0) {
    return "<span style='color:#f59e0b;font-weight:600'>⚠ Low Stock</span>";
  } else {
    return "<span style='color:#ef4444;font-weight:600'>✗ Out of Stock</span>";
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CMMS · Equipment</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --bg:#ffffff;
    --text:#0f172a;
    --muted:#64748b;
    --line:#e5e7eb;
    --primary:#0d6efd;
    --success:#16a34a;
    --card-bg:#ffffff;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{
    background:#f8fafb;
    color:var(--text);
    font:16px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
  }
  a{color:var(--primary);text-decoration:none}
  a:hover{text-decoration:underline}
  
  /* Layout */
  .wrap{max-width:1600px;margin:24px auto;padding:0 20px}
  
  /* Header */
  header{
    display:flex;
    align-items:center;
    gap:16px;
    margin-bottom:20px;
    background:var(--card-bg);
    padding:16px 20px;
    border-radius:12px;
    box-shadow:0 1px 3px rgba(0,0,0,0.1);
  }
  .back{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 14px;
    border:1px solid var(--line);
    border-radius:8px;
    color:var(--text);
    font-weight:500;
    transition:all 0.2s;
    color:white;
    background:blue
  }
  .back:hover{background:#f8fafc;text-decoration:none}
  .spacer{flex:1}
  .who{color:var(--muted);font-size:14px}
  
  /* Cards */
  .card{
    background:var(--card-bg);
    border:1px solid var(--line);
    border-radius:12px;
    box-shadow:0 1px 3px rgba(0,0,0,0.05);
    margin-bottom:20px;
  }
  .pad{padding:20px}
  
  /* Forms */
  .form-row{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:16px;
    margin-bottom:16px;
  }
  .form-group{display:flex;flex-direction:column}
  label{
    font-size:13px;
    font-weight:600;
    color:#374151;
    margin-bottom:6px;
  }
  .required{color:#ef4444}
  input[type=text],
  input[type=date],
  input[type=number],
  select,
  textarea{
    width:100%;
    padding:10px 12px;
    border:1px solid var(--line);
    border-radius:8px;
    background:#fff;
    font-size:14px;
    transition:border-color 0.2s,box-shadow 0.2s;
  }
  textarea{min-height:80px;resize:vertical}
  input:focus,select:focus,textarea:focus{
    outline:none;
    border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(13,110,253,0.1);
  }
  input:read-only{
    background:#f3f4f6;
    color:#6b7280;
    cursor:not-allowed;
  }
  
  /* Buttons */
  .btn{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:10px 18px;
    border:1px solid #d1d5db;
    border-radius:8px;
    background:#fff;
    color:var(--text);
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    transition:all 0.2s;
  }
  .btn:hover{background:#f8fafc;text-decoration:none}
  .btn.primary{
    background:var(--primary);
    border-color:var(--primary);
    color:#fff;
  }
  .btn.primary:hover{background:#0b5ed7}
  .btn.success{
    background:#16a34a;
    border-color:#16a34a;
    color:#fff;
  }
  .btn.success:hover{background:#15803d}
  .btn.danger{
    background:#ef4444;
    border-color:#ef4444;
    color:#fff;
  }
  .btn.danger:hover{background:#dc2626}
  .btn.sm{padding:6px 12px;font-size:13px}
  
  /* Alerts */
  .alert{
    padding:14px 16px;
    border-radius:10px;
    margin-bottom:16px;
    font-size:14px;
  }
  .alert.ok{
    background:#ecfdf5;
    border:1px solid #a7f3d0;
    color:#065f46;
  }
  .alert.err{
    background:#fef2f2;
    border:1px solid #fecaca;
    color:#991b1b;
  }
  .info-box{
    background:#f0f9ff;
    border:1px solid #bae6fd;
    padding:12px 16px;
    border-radius:8px;
    font-size:14px;
    color:#0369a1;
    margin-bottom:16px;
  }
  
  /* Table */
  table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
  }
  thead{background:#fafbfc}
  th,td{
    padding:14px 12px;
    text-align:left;
    border-bottom:1px solid var(--line);
  }
  th{
    font-size:13px;
    font-weight:700;
    color:#374151;
    text-transform:uppercase;
    letter-spacing:0.5px;
  }
  tbody tr{transition:background 0.15s}
  tbody tr:hover{background:#f9fafb}
  td{font-size:14px}
  
  /* Utilities */
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
  .text-muted{color:var(--muted)}
  .text-center{text-align:center}
  .actions{display:flex;gap:8px;justify-content:flex-end}
  
  /* Stock indicator */
  .stock-low{color:#f59e0b;font-weight:600}
  .stock-out{color:#ef4444;font-weight:600}
  .stock-ok{color:#16a34a;font-weight:600}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <a href="planner_dashboard.php" class="back">← Back to Dashboard</a>
    <h2> Equipment</h2>
    <div class="spacer"></div>
    <div class="who">Planner: <?= h($planner_name) ?> · <a href="logout.php">Logout</a></div>
  </header>

  <?php if ($flash): ?><div class="alert ok">✓ <?= $flash ?></div><?php endif; ?>
  <?php if ($flash_error): ?><div class="alert err">✗ <?= h($flash_error) ?></div><?php endif; ?>

  <!-- Search / Filters -->
  <div class="card pad">
    <h3 style="margin-bottom:16px;font-size:17px">🔍 Search & Filter</h3>
    <form method="get">
      <div class="form-row">
        <div class="form-group">
          <label>Search</label>
          <input type="text" name="q" placeholder="Code, name, serial..." value="<?= h($q) ?>">
        </div>
        <div class="form-group">
          <label>Work Center</label>
          <select name="wc">
            <option value="">All</option>
            <?php foreach($workcenters as $wc): ?>
            <option value="<?= (int)$wc['work_center_id'] ?>" <?= $f_wc===$wc['work_center_id']?'selected':''; ?>>
              <?= h($wc['wc_code'].' - '.$wc['wc_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Location</label>
          <select name="fl">
            <option value="">All</option>
            <?php foreach($flocs as $fl): ?>
            <option value="<?= (int)$fl['floc_id'] ?>" <?= $f_fl===$fl['floc_id']?'selected':''; ?>>
              <?= h($fl['floc_code'].' - '.$fl['floc_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <option value="ALL">All</option>
            <option value="ACTIVE" <?= $f_status==='ACTIVE'?'selected':''; ?>>Active</option>
            <option value="INACTIVE" <?= $f_status==='INACTIVE'?'selected':''; ?>>Inactive</option>
            <option value="RETIRED" <?= $f_status==='RETIRED'?'selected':''; ?>>Retired</option>
          </select>
        </div>
        <div class="form-group">
          <label>Criticality</label>
          <select name="crit">
            <option value="ALL">All</option>
            <option value="HIGH" <?= $f_crit==='HIGH'?'selected':''; ?>>High</option>
            <option value="MEDIUM" <?= $f_crit==='MEDIUM'?'selected':''; ?>>Medium</option>
            <option value="LOW" <?= $f_crit==='LOW'?'selected':''; ?>>Low</option>
          </select>
        </div>
        <div class="form-group" style="justify-content:flex-end">
          <label>&nbsp;</label>
          <div class="actions">
            <button class="btn primary">Apply</button>
            <a class="btn" href="equipment.php">Reset</a>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Add / Edit Equipment -->
  <div class="card pad">
    <h3 style="margin-bottom:16px;font-size:17px">
      <?= $edit_row ? '✏️ Edit Equipment' : '➕ Add Equipment' ?>
    </h3>
    
    <?php if (!$edit_row): ?>
      <div class="info-box">
        <strong>ℹ️ Equipment Code:</strong> Will be auto-generated (e.g., EQ2025-0001)
      </div>
    <?php endif; ?>
    
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="save_equipment">
      <?php if ($edit_row): ?>
        <input type="hidden" name="equipment_id" value="<?= (int)$edit_row['equipment_id'] ?>">
      <?php endif; ?>

      <div class="form-row">
        <?php if ($edit_row): ?>
          <div class="form-group">
            <label>Equipment Code</label>
            <input type="text" name="equipment_code" value="<?= h($edit_row['equipment_code'] ?? '') ?>" readonly>
          </div>
        <?php endif; ?>
        
        <div class="form-group" style="grid-column:span 2">
          <label>Equipment Name <span class="required">*</span></label>
          <input type="text" name="equipment_name" required maxlength="150"
                 value="<?= h($edit_row['equipment_name'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Serial Number</label>
          <input type="text" name="serial_no" maxlength="100"
                 value="<?= h($edit_row['serial_no'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Model Number</label>
          <input type="text" name="model_no" maxlength="100"
                 value="<?= h($edit_row['model_no'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Installation Date</label>
          <input type="date" name="install_date"
                 value="<?= h($edit_row['install_date'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Location</label>
          <select name="floc_id">
            <option value="">—</option>
            <?php foreach($flocs as $fl): ?>
            <option value="<?= (int)$fl['floc_id'] ?>" 
                    <?= isset($edit_row['floc_id']) && (int)$edit_row['floc_id']===$fl['floc_id'] ? 'selected':''; ?>>
              <?= h($fl['floc_code'].' - '.$fl['floc_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Work Center</label>
          <select name="work_center_id">
            <option value="">—</option>
            <?php foreach($workcenters as $wc): ?>
            <option value="<?= (int)$wc['work_center_id'] ?>" 
                    <?= isset($edit_row['work_center_id']) && (int)$edit_row['work_center_id']===$wc['work_center_id'] ? 'selected':''; ?>>
              <?= h($wc['wc_code'].' - '.$wc['wc_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Vendor</label>
          <select name="vendor_id">
            <option value="">—</option>
            <?php foreach($vendors as $v): ?>
            <option value="<?= (int)$v['vendor_id'] ?>" 
                    <?= isset($edit_row['vendor_id']) && (int)$edit_row['vendor_id']===$v['vendor_id'] ? 'selected':''; ?>>
              <?= h($v['vendor_code'].' - '.$v['vendor_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Criticality</label>
          <select name="criticality">
            <option value="LOW" <?= isset($edit_row['criticality']) && $edit_row['criticality']==='LOW'?'selected':''; ?>>Low</option>
            <option value="MEDIUM" <?= isset($edit_row['criticality']) && $edit_row['criticality']==='MEDIUM'?'selected':''; ?>>Medium</option>
            <option value="HIGH" <?= isset($edit_row['criticality']) && $edit_row['criticality']==='HIGH'?'selected':''; ?>>High</option>
          </select>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <option value="ACTIVE" <?= isset($edit_row['status']) && $edit_row['status']==='ACTIVE'?'selected':'selected'; ?>>Active</option>
            <option value="INACTIVE" <?= isset($edit_row['status']) && $edit_row['status']==='INACTIVE'?'selected':''; ?>>Inactive</option>
            <option value="RETIRED" <?= isset($edit_row['status']) && $edit_row['status']==='RETIRED'?'selected':''; ?>>Retired</option>
          </select>
        </div>
      </div>

      <div class="actions">
        <?php if ($edit_row): ?>
          <a class="btn" href="equipment.php">Cancel</a>
        <?php endif; ?>
        <button class="btn primary"><?= $edit_row ? 'Update Equipment' : 'Create Equipment' ?></button>
      </div>
    </form>
  </div>

  <?php if ($edit_row): ?>
  <!-- Material BOM for Equipment -->
  <div class="card pad">
    <h3 style="margin-bottom:16px;font-size:17px">📦 Required Materials (BOM)</h3>
    
    <div class="info-box" style="margin-bottom:20px">
      <strong>ℹ️ About Equipment BOM:</strong> Define which materials are needed for this equipment. When a work order is released, these materials will be deducted from stock.
    </div>
    
    <!-- Add Material Form -->
    <form method="post" style="margin-bottom:20px">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="add_material">
      <input type="hidden" name="equipment_id" value="<?= (int)$edit_row['equipment_id'] ?>">
      
      <div class="form-row">
        <div class="form-group" style="grid-column:span 2">
          <label>Material <span class="required">*</span></label>
          <select name="material_id" required>
            <option value="">Select Material...</option>
            <?php foreach($materials as $m): ?>
            <option value="<?= (int)$m['material_id'] ?>">
              <?= h($m['material_code'].' - '.$m['material_name'].' (Stock: '.$m['on_hand_qty'].' '.$m['unit_of_measure'].')') ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label>Quantity Required <span class="required">*</span></label>
          <input type="number" name="quantity" step="0.001" min="0.001" required value="1">
        </div>
        
        <div class="form-group">
          <label>Notes</label>
          <input type="text" name="notes" placeholder="Optional notes">
        </div>
        
        <div class="form-group" style="justify-content:flex-end">
          <label>&nbsp;</label>
          <button class="btn success">Add Material</button>
        </div>
      </div>
    </form>
    
    <!-- Materials Table -->
    <table>
      <thead>
        <tr>
          <th>Material Code</th>
          <th>Material Name</th>
          <th style="width:120px">Required Qty</th>
          <th style="width:120px">Stock Qty</th>
          <th style="width:80px">Unit</th>
          <th style="width:140px">Status</th>
          <th>Notes</th>
          <th style="width:100px;text-align:center">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($bom_materials)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:40px">No materials added yet. Add materials to define the BOM for this equipment.</td></tr>
        <?php else: ?>
          <?php foreach($bom_materials as $bm): ?>
          <tr>
            <td class="mono"><?= h($bm['material_code']) ?></td>
            <td><?= h($bm['material_name']) ?></td>
            <td class="mono"><?= h($bm['quantity']) ?></td>
            <td class="mono <?= $bm['on_hand_qty'] < $bm['quantity'] ? 'stock-low' : 'stock-ok' ?>">
              <?= h($bm['on_hand_qty']) ?>
            </td>
            <td><?= h($bm['unit_of_measure']) ?></td>
            <td><?= stock_status($bm['on_hand_qty'], $bm['quantity']) ?></td>
            <td class="text-muted"><?= h($bm['notes'] ?? '—') ?></td>
            <td class="text-center">
              <form method="post" style="display:inline" onsubmit="return confirm('Remove this material from BOM?')">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="remove_material">
                <input type="hidden" name="equipment_id" value="<?= (int)$edit_row['equipment_id'] ?>">
                <input type="hidden" name="material_id" value="<?= (int)$bm['material_id'] ?>">
                <button class="btn sm danger">Remove</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Equipment Table -->
  <div class="card">
    <div class="pad">
      <h3 style="margin-bottom:16px;font-size:17px">📦 All Equipment (<?= count($rows) ?>)</h3>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Serial / Model</th>
              <th>Location</th>
              <th>Work Center</th>
              <th>Criticality</th>
              <th>Status</th>
              <th style="text-align:center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $r): ?>
            <tr>
              <td><strong class="mono"><?= h($r['equipment_code']) ?></strong></td>
              <td><?= h($r['equipment_name']) ?></td>
              <td class="text-muted" style="font-size:13px">
                <?php if (!empty($r['serial_no']) || !empty($r['model_no'])): ?>
                  <?php if (!empty($r['serial_no'])): ?>SN: <?= h($r['serial_no']) ?><br><?php endif; ?>
                  <?php if (!empty($r['model_no'])): ?>Model: <?= h($r['model_no']) ?><?php endif; ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="text-muted" style="font-size:13px">
                <?= !empty($r['floc_name']) ? '📍 '.h($r['floc_name']) : '—' ?>
              </td>
              <td class="text-muted" style="font-size:13px">
                <?= !empty($r['wc_name']) ? h($r['wc_name']) : '—' ?>
              </td>
              <td><?= criticality_badge($r['criticality']) ?></td>
              <td><?= status_badge($r['status']) ?></td>
              <td class="text-center">
                <a class="btn sm" href="equipment.php?edit=<?= (int)$r['equipment_id'] ?>">Edit</a>
              </td>
            </tr>
            <?php endforeach; if(empty($rows)): ?>
              <tr><td colspan="8" class="text-center text-muted" style="padding:40px">No equipment found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>