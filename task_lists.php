<?php
// task_lists.php – Manage Task Lists, Operations, and Materials (Planner)
// Task lists are reusable templates for common maintenance work

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

// ---- Auth: planner only ----
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'planner') {
  header('Location: login.php'); exit;
}
$planner_id   = (int)($_SESSION['user_id']);
$planner_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Planner';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function badge($text,$color){ return "<span style='background:$color;color:#fff;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600'>$text</span>"; }

// ---- Column detection ----
function table_has_col(PDO $pdo, $table, $col){
  static $cache = [];
  $key="$table::$col";
  if(isset($cache[$key])) return $cache[$key];
  try {
    $stmt=$pdo->prepare("DESCRIBE `$table`");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $cache[$key] = in_array($col,$cols,true);
  } catch (Throwable $e) {
    $cache[$key] = false;
  }
  return $cache[$key];
}

$TL_HAS_ACTIVE   = table_has_col($pdo,'task_list','is_active');
$TL_HAS_EST_HRS  = table_has_col($pdo,'task_list','estimated_hours');
$TL_HAS_CREATED  = table_has_col($pdo,'task_list','created_by_user_id');

// ---- Lookups ----
$equip = $pdo->query("SELECT equipment_id, equipment_code, equipment_name FROM equipment ORDER BY equipment_code")->fetchAll();
$wcs   = $pdo->query("SELECT work_center_id, wc_code, wc_name FROM work_center ORDER BY wc_code")->fetchAll();
$mats  = $pdo->query("SELECT material_id, material_code, material_name, unit_of_measure FROM material WHERE is_active=1 ORDER BY material_code")->fetchAll();

// ---- Flash messaging ----
$flash = ''; $flash_err = '';

// ---- Actions ----
$action = $_POST['action'] ?? '';

try {
  if ($action === 'save_tl') {
    $tlid   = ($_POST['task_list_id'] ?? '') !== '' ? (int)$_POST['task_list_id'] : null;
    $code   = trim($_POST['task_list_code'] ?? '');
    $title  = trim($_POST['title'] ?? '');
    $eq_id  = ($_POST['equipment_id'] ?? '') !== '' ? (int)$_POST['equipment_id'] : null;
    $wc_id  = ($_POST['work_center_id'] ?? '') !== '' ? (int)$_POST['work_center_id'] : null;
    $active = $TL_HAS_ACTIVE ? (int)($_POST['is_active'] ?? 1) : null;
    $esthrs = $TL_HAS_EST_HRS ? (float)($_POST['estimated_hours'] ?? 0) : null;

    if ($code==='' || $title==='') throw new Exception('Task List Code and Title are required.');

    // uniqueness: code
    if ($tlid) {
      $q = $pdo->prepare("SELECT COUNT(*) FROM task_list WHERE task_list_code=? AND task_list_id<>?");
      $q->execute([$code,$tlid]);
    } else {
      $q = $pdo->prepare("SELECT COUNT(*) FROM task_list WHERE task_list_code=?");
      $q->execute([$code]);
    }
    if ((int)$q->fetchColumn()>0) throw new Exception('Task List Code already exists.');

    if ($tlid) {
      // UPDATE
      $set = "task_list_code=?, title=?, equipment_id=?, work_center_id=?";
      $vals = [$code,$title,$eq_id,$wc_id];
      if ($TL_HAS_ACTIVE){ $set.=", is_active=?"; $vals[]=$active; }
      if ($TL_HAS_EST_HRS){ $set.=", estimated_hours=?"; $vals[]=$esthrs; }
      $vals[]=$tlid;
      $sql="UPDATE task_list SET $set, updated_at=NOW() WHERE task_list_id=?";
      $pdo->prepare($sql)->execute($vals);
      $flash = "Task List <strong>".h($code)."</strong> updated successfully.";
    } else {
      // INSERT
      $cols = ['task_list_code','title','equipment_id','work_center_id'];
      $qs   = ['?','?','?','?'];
      $vals = [$code,$title,$eq_id,$wc_id];
      if ($TL_HAS_ACTIVE){ $cols[]='is_active'; $qs[]='?'; $vals[]=$active; }
      if ($TL_HAS_EST_HRS){ $cols[]='estimated_hours'; $qs[]='?'; $vals[]=$esthrs; }
      if ($TL_HAS_CREATED){ $cols[]='created_by_user_id'; $qs[]='?'; $vals[]=$planner_id; }

      $sql="INSERT INTO task_list (".implode(',',$cols).") VALUES (".implode(',',$qs).")";
      $pdo->prepare($sql)->execute($vals);
      $flash = "Task List <strong>".h($code)."</strong> created successfully. Now add operations and materials below.";
      $new_id = (int)$pdo->lastInsertId();
      header("Location: task_lists.php?edit=".$new_id);
      exit;
    }
  }

  if ($action === 'add_op') {
    $tid = (int)$_POST['task_list_id'];
    $seq = (int)($_POST['op_seq'] ?? 10);
    $desc= trim($_POST['description'] ?? '');
    $min = (int)($_POST['std_time_min'] ?? 0);
    $sfty= trim($_POST['safety_notes'] ?? '');
    if ($desc==='') throw new Exception('Operation description is required.');
    
    // Check for duplicate sequence
    $check = $pdo->prepare("SELECT COUNT(*) FROM task_list_operation WHERE task_list_id=? AND op_seq=?");
    $check->execute([$tid,$seq]);
    if ((int)$check->fetchColumn() > 0) {
      throw new Exception("Sequence number $seq already exists. Use a different number.");
    }
    
    $pdo->prepare("INSERT INTO task_list_operation (task_list_id, op_seq, description, std_time_min, safety_notes) VALUES (?,?,?,?,?)")
        ->execute([$tid,$seq,$desc,$min,$sfty]);
    $flash="Operation added successfully.";
    header("Location: task_lists.php?edit=".$tid);
    exit;
  }

  if ($action === 'del_op') {
    $tid = (int)$_POST['task_list_id'];
    $seq = (int)$_POST['op_seq'];
    $pdo->prepare("DELETE FROM task_list_operation WHERE task_list_id=? AND op_seq=?")->execute([$tid,$seq]);
    $flash="Operation removed.";
    header("Location: task_lists.php?edit=".$tid);
    exit;
  }

  if ($action === 'add_mat') {
    $tid = (int)$_POST['task_list_id'];
    $mid = (int)$_POST['material_id'];
    $qty = (float)($_POST['quantity'] ?? 1);
    if ($mid<=0) throw new Exception('Please select a material.');
    if ($qty<=0) throw new Exception('Quantity must be greater than 0.');
    
    // Check for duplicate material
    $check = $pdo->prepare("SELECT COUNT(*) FROM task_list_material WHERE task_list_id=? AND material_id=?");
    $check->execute([$tid,$mid]);
    if ((int)$check->fetchColumn() > 0) {
      throw new Exception('This material is already added to this task list.');
    }
    
    $pdo->prepare("INSERT INTO task_list_material (task_list_id, material_id, quantity) VALUES (?,?,?)")
        ->execute([$tid,$mid,$qty]);
    $flash="Material added successfully.";
    header("Location: task_lists.php?edit=".$tid);
    exit;
  }

  if ($action === 'del_mat') {
    $tid = (int)$_POST['task_list_id'];
    $mid = (int)$_POST['material_id'];
    $pdo->prepare("DELETE FROM task_list_material WHERE task_list_id=? AND material_id=?")->execute([$tid,$mid]);
    $flash="Material removed.";
    header("Location: task_lists.php?edit=".$tid);
    exit;
  }

  if ($action === 'duplicate_tl') {
    $src_id = (int)$_POST['task_list_id'];
    
    // Load source task list
    $src = $pdo->prepare("SELECT * FROM task_list WHERE task_list_id=?")->execute([$src_id]);
    $src = $pdo->query("SELECT * FROM task_list WHERE task_list_id=$src_id")->fetch();
    if (!$src) throw new Exception('Task list not found.');
    
    // Create new code
    $new_code = $src['task_list_code'] . '-COPY';
    $counter = 1;
    while (true) {
      $check = $pdo->prepare("SELECT COUNT(*) FROM task_list WHERE task_list_code=?");
      $check->execute([$new_code]);
      if ((int)$check->fetchColumn() === 0) break;
      $counter++;
      $new_code = $src['task_list_code'] . '-COPY' . $counter;
    }
    
    // Insert new task list
    $cols = ['task_list_code','title','equipment_id','work_center_id'];
    $vals = [$new_code, $src['title'] . ' (Copy)', $src['equipment_id'], $src['work_center_id']];
    if ($TL_HAS_ACTIVE){ $cols[]='is_active'; $vals[]=$src['is_active'] ?? 1; }
    if ($TL_HAS_EST_HRS){ $cols[]='estimated_hours'; $vals[]=$src['estimated_hours']; }
    if ($TL_HAS_CREATED){ $cols[]='created_by_user_id'; $vals[]=$planner_id; }
    
    $sql="INSERT INTO task_list (".implode(',',$cols).") VALUES (".str_repeat('?,',count($vals)-1)."?)";
    $pdo->prepare($sql)->execute($vals);
    $new_id = (int)$pdo->lastInsertId();
    
    // Copy operations
    $pdo->prepare("
      INSERT INTO task_list_operation (task_list_id, op_seq, description, std_time_min, safety_notes)
      SELECT ?, op_seq, description, std_time_min, safety_notes
      FROM task_list_operation WHERE task_list_id=?
    ")->execute([$new_id, $src_id]);
    
    // Copy materials
    $pdo->prepare("
      INSERT INTO task_list_material (task_list_id, material_id, quantity)
      SELECT ?, material_id, quantity
      FROM task_list_material WHERE task_list_id=?
    ")->execute([$new_id, $src_id]);
    
    $flash = "Task list duplicated successfully as <strong>".h($new_code)."</strong>";
    header("Location: task_lists.php?edit=".$new_id);
    exit;
  }

} catch (Throwable $e) {
  $flash_err = $e->getMessage();
}

// ---- Filters/Search ----
$q = trim($_GET['q'] ?? '');
$only_active = $TL_HAS_ACTIVE ? ($_GET['active'] ?? '1') : '';
$filter_wc = isset($_GET['wc']) ? (int)$_GET['wc'] : 0;
$filter_eq = isset($_GET['eq']) ? (int)$_GET['eq'] : 0;

$where=[];$params=[];
if ($q!==''){ 
  $where[]="(tl.task_list_code LIKE ? OR tl.title LIKE ? OR e.equipment_name LIKE ? OR wc.wc_name LIKE ?)"; 
  $params[]="%$q%";$params[]="%$q%";$params[]="%$q%";$params[]="%$q%"; 
}
if ($TL_HAS_ACTIVE && ($only_active==='1' || $only_active==='0')){ 
  $where[]="COALESCE(tl.is_active,1)=?"; $params[]=(int)$only_active; 
}
if ($filter_wc > 0) {
  $where[]="tl.work_center_id=?"; $params[]=$filter_wc;
}
if ($filter_eq > 0) {
  $where[]="tl.equipment_id=?"; $params[]=$filter_eq;
}

$sql = "
SELECT tl.task_list_id, tl.task_list_code, tl.title,
       e.equipment_name, wc.wc_name,
       (SELECT COUNT(*) FROM task_list_operation WHERE task_list_id=tl.task_list_id) as op_count,
       (SELECT COUNT(*) FROM task_list_material WHERE task_list_id=tl.task_list_id) as mat_count".
       ($TL_HAS_ACTIVE ? ", COALESCE(tl.is_active,1) AS is_active" : "").
       ($TL_HAS_EST_HRS ? ", tl.estimated_hours" : "")."
FROM task_list tl
LEFT JOIN equipment e ON e.equipment_id = tl.equipment_id
LEFT JOIN work_center wc ON wc.work_center_id = tl.work_center_id
".(count($where) ? "WHERE ".implode(' AND ',$where) : "")."
ORDER BY tl.task_list_code
LIMIT 500";
$st=$pdo->prepare($sql);$st->execute($params);
$rows=$st->fetchAll();

// ---- Edit mode ----
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit = null; $ops=[]; $tl_mats=[];
if ($edit_id>0) {
  $s=$pdo->prepare("SELECT * FROM task_list WHERE task_list_id=? LIMIT 1");
  $s->execute([$edit_id]);
  $edit=$s->fetch();

  $s=$pdo->prepare("SELECT * FROM task_list_operation WHERE task_list_id=? ORDER BY op_seq");
  $s->execute([$edit_id]);
  $ops=$s->fetchAll();

  $s=$pdo->prepare("
    SELECT tlm.material_id, tlm.quantity, m.material_code, m.material_name, m.unit_of_measure
    FROM task_list_material tlm
    JOIN material m ON m.material_id=tlm.material_id
    WHERE tlm.task_list_id=?
    ORDER BY m.material_code
  ");
  $s->execute([$edit_id]);
  $tl_mats=$s->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Task Lists - CMMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--bg:#f8fafc;--text:#0f172a;--muted:#64748b;--line:#e2e8f0;--primary:#2563eb;--success:#10b981;--danger:#ef4444}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--text);font:15px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,sans-serif}
  a{color:var(--primary);text-decoration:none}
  a:hover{text-decoration:underline}
  .wrap{max-width:1400px;margin:0 auto;padding:20px}
  
  /* Header */
  header{display:flex;align-items:center;gap:16px;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid var(--line)}
  header h1{font-size:28px;font-weight:700}
  .back{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#fff;border:1px solid var(--line);border-radius:8px;font-weight:600;color:var(--text)}
  .back:hover{background:#f1f5f9;text-decoration:none}
  .spacer{flex:1}
  .who{color:var(--muted);font-size:14px}
  
  /* Cards */
  .card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
  .card h3{font-size:18px;font-weight:700;margin-bottom:16px;color:var(--text)}
  .card h3 .mono{color:var(--primary)}
  
  /* Forms */
  .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:16px}
  .form-group{display:flex;flex-direction:column}
  label{font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
  label .req{color:var(--danger)}
  select,input[type=text],input[type=number],textarea{
    width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;
    font-size:14px;font-family:inherit;transition:border-color 0.2s
  }
  select:focus,input:focus,textarea:focus{outline:none;border-color:var(--primary)}
  textarea{resize:vertical;min-height:80px}
  .help-text{font-size:12px;color:var(--muted);margin-top:4px}
  
  /* Buttons */
  .btn-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .btn{
    display:inline-flex;align-items:center;gap:6px;padding:10px 18px;
    background:#fff;border:1px solid var(--line);border-radius:8px;
    font-size:14px;font-weight:600;cursor:pointer;transition:all 0.2s;color:var(--text)
  }
  .btn:hover{background:#f8fafc;text-decoration:none}
  .btn.primary{background:var(--primary);color:#fff;border-color:var(--primary)}
  .btn.primary:hover{background:#1d4ed8}
  .btn.success{background:var(--success);color:#fff;border-color:var(--success)}
  .btn.success:hover{background:#059669}
  .btn.danger{background:var(--danger);color:#fff;border-color:var(--danger)}
  .btn.danger:hover{background:#dc2626}
  .btn.sm{padding:6px 12px;font-size:13px}
  
  /* Alerts */
  .alert{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:14px}
  .alert.ok{background:#d1fae5;border:1px solid #a7f3d0;color:#065f46}
  .alert.err{background:#fee2e2;border:1px solid #fecaca;color:#991b1b}
  
  /* Tables */
  table{width:100%;border-collapse:collapse}
  thead th{
    padding:12px;background:#f8fafc;border-bottom:2px solid var(--line);
    text-align:left;font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.5px
  }
  tbody td{padding:14px 12px;border-bottom:1px solid var(--line);vertical-align:top}
  tbody tr:hover{background:#f8fafc}
  .right{text-align:right}
  .center{text-align:center}
  .mono{font-family:ui-monospace,'Cascadia Code','Source Code Pro',Menlo,Consolas,monospace}
  .muted{color:var(--muted)}
  .empty{text-align:center;padding:40px;color:var(--muted);font-style:italic}
  
  /* Badges */
  .badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600}
  
  /* Checkbox */
  .checkbox{display:flex;align-items:center;gap:8px;padding-top:28px}
  .checkbox input{width:auto;margin:0}
  .checkbox label{margin:0;font-weight:normal;cursor:pointer}
  
  /* Info box */
  .info-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px;margin-bottom:20px;font-size:14px;color:#1e40af}
  .info-box strong{color:#1e3a8a}
  
  /* Stats */
  .stats{display:flex;gap:8px;font-size:12px;color:var(--muted)}
  .stats span{background:#f1f5f9;padding:4px 8px;border-radius:6px}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <a href="planner_dashboard.php" class="back">← Dashboard</a>
    <h1>Task Lists</h1>
    <div class="spacer"></div>
    <div class="who"><?= h($planner_name) ?> · <a href="logout.php">Logout</a></div>
  </header>

  <?php if (!$edit_id): ?>
  <div class="info-box">
    <strong>💡 What are Task Lists?</strong><br>
    Task lists are reusable templates that define the standard procedures for common maintenance work. Each task list contains:
    <strong>operations</strong> (step-by-step instructions) and required <strong>materials</strong> (parts/supplies needed).
    When creating a work order, you can select a task list to automatically copy all operations and materials.
  </div>
  <?php endif; ?>

  <?php if ($flash): ?><div class="alert ok"><?= $flash ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert err"><strong>Error:</strong> <?= h($flash_err) ?></div><?php endif; ?>

  <!-- Search / Filters -->
  <?php if (!$edit_id): ?>
  <div class="card">
    <form method="get" class="form-grid">
      <div class="form-group">
        <label>Search</label>
        <input type="text" name="q" placeholder="Code, title, equipment..." value="<?= h($q) ?>">
      </div>
      
      <div class="form-group">
        <label>Work Center</label>
        <select name="wc">
          <option value="">All Work Centers</option>
          <?php foreach($wcs as $w): ?>
          <option value="<?= $w['work_center_id'] ?>" <?= $filter_wc==$w['work_center_id']?'selected':'' ?>>
            <?= h($w['wc_code'].' - '.$w['wc_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-group">
        <label>Equipment</label>
        <select name="eq">
          <option value="">All Equipment</option>
          <?php foreach($equip as $e): ?>
          <option value="<?= $e['equipment_id'] ?>" <?= $filter_eq==$e['equipment_id']?'selected':'' ?>>
            <?= h($e['equipment_code'].' - '.$e['equipment_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($TL_HAS_ACTIVE): ?>
      <div class="form-group">
        <label>Status</label>
        <select name="active">
          <option value="">All</option>
          <option value="1" <?= $only_active==='1'?'selected':'' ?>>Active</option>
          <option value="0" <?= $only_active==='0'?'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <?php endif; ?>
      
      <div class="form-group" style="justify-content:flex-end">
        <label>&nbsp;</label>
        <div class="btn-row">
          <button class="btn primary">Search</button>
          <a class="btn" href="task_lists.php">Reset</a>
        </div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Create / Edit Task List -->
  <div class="card">
    <h3><?= $edit ? '✏️ Edit Task List' : '➕ Create New Task List' ?></h3>
    <form method="post">
      <input type="hidden" name="action" value="save_tl">
      <?php if ($edit): ?><input type="hidden" name="task_list_id" value="<?= (int)$edit['task_list_id'] ?>"><?php endif; ?>

      <div class="form-grid">
        <div class="form-group">
          <label>Task List Code <span class="req">*</span></label>
          <input type="text" name="task_list_code" required maxlength="50" 
                 value="<?= h($edit['task_list_code'] ?? '') ?>"
                 placeholder="e.g., TL-AC-PM, TL-ELEV-INSPECT">
          <small class="help-text">Unique identifier for this task list</small>
        </div>
        
        <div class="form-group" style="grid-column:span 2">
          <label>Title <span class="req">*</span></label>
          <input type="text" name="title" required maxlength="200" 
                 value="<?= h($edit['title'] ?? '') ?>"
                 placeholder="e.g., Air Conditioner Preventive Maintenance, Elevator Monthly Inspection">
          <small class="help-text">Clear description of the maintenance task</small>
        </div>

        <div class="form-group">
          <label>Equipment (Optional)</label>
          <select name="equipment_id">
            <option value="">Generic (all equipment)</option>
            <?php foreach($equip as $e): ?>
            <option value="<?= (int)$e['equipment_id'] ?>" 
                    <?= isset($edit['equipment_id']) && (int)$edit['equipment_id']===$e['equipment_id']?'selected':''; ?>>
              <?= h($e['equipment_code'].' - '.$e['equipment_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <small class="help-text">Leave blank for generic task lists</small>
        </div>

        <div class="form-group">
          <label>Work Center (Optional)</label>
          <select name="work_center_id">
            <option value="">Any work center</option>
            <?php foreach($wcs as $w): ?>
            <option value="<?= (int)$w['work_center_id'] ?>" 
                    <?= isset($edit['work_center_id']) && (int)$edit['work_center_id']===$w['work_center_id']?'selected':''; ?>>
              <?= h($w['wc_code'].' - '.$w['wc_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <small class="help-text">Which team handles this work</small>
        </div>

        <?php if ($TL_HAS_EST_HRS): ?>
        <div class="form-group">
          <label>Estimated Hours</label>
          <input type="number" step="0.25" min="0" name="estimated_hours" 
                 value="<?= isset($edit['estimated_hours']) ? h($edit['estimated_hours']) : '' ?>"
                 placeholder="e.g., 2.5">
          <small class="help-text">Expected time to complete</small>
        </div>
        <?php endif; ?>

        <?php if ($TL_HAS_ACTIVE): ?>
        <div class="checkbox">
          <input type="checkbox" name="is_active" id="is_active" value="1" 
                 <?= isset($edit['is_active']) ? ((int)$edit['is_active']===1?'checked':'') : 'checked' ?>>
          <label for="is_active">Active (available for work orders)</label>
        </div>
        <?php endif; ?>
      </div>

      <div class="btn-row" style="margin-top:20px">
        <button class="btn primary"><?= $edit ? '💾 Update Task List' : '➕ Create Task List' ?></button>
        <?php if ($edit): ?>
          <a class="btn" href="task_lists.php">Cancel</a>
          <form method="post" style="display:inline;margin-left:auto">
            <input type="hidden" name="action" value="duplicate_tl">
            <input type="hidden" name="task_list_id" value="<?= (int)$edit['task_list_id'] ?>">
            <button class="btn" type="submit">📋 Duplicate</button>
          </form>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <?php if ($edit): ?>
  <!-- Operations -->
  <div class="card">
    <h3>🔧 Operations — <span class="mono"><?= h($edit['task_list_code']) ?></span></h3>
    <p class="muted" style="margin-bottom:16px">Define step-by-step procedures. Each operation should be a clear, actionable task.</p>
    
    <form method="post" class="form-grid" style="margin-bottom:20px;background:#f8fafc;padding:16px;border-radius:8px">
      <input type="hidden" name="action" value="add_op">
      <input type="hidden" name="task_list_id" value="<?= (int)$edit['task_list_id'] ?>">
      
      <div class="form-group">
        <label>Sequence <span class="req">*</span></label>
        <input type="number" name="op_seq" required min="1" step="10"
               value="<?= !empty($ops)? (int)end($ops)['op_seq']+10 : 10 ?>">
        <small class="help-text">Order of execution (10, 20, 30...)</small>
      </div>
      
      <div class="form-group" style="grid-column:span 2">
        <label>Description <span class="req">*</span></label>
        <input type="text" name="description" required maxlength="255"
               placeholder="e.g., Inspect air filter for dust buildup and damage">
        <small class="help-text">Clear instruction for the technician</small>
      </div>
      
      <div class="form-group">
        <label>Time (minutes)</label>
        <input type="number" name="std_time_min" min="0" value="0"
               placeholder="e.g., 15">
        <small class="help-text">Estimated time for this step</small>
      </div>
      
      <div class="form-group" style="grid-column:span 2">
        <label>Safety Notes</label>
        <input type="text" name="safety_notes" maxlength="255"
               placeholder="e.g., Ensure power is disconnected before servicing">
        <small class="help-text">Important safety precautions</small>
      </div>
      
      <div class="form-group" style="align-items:flex-end">
        <button class="btn success">➕ Add Operation</button>
      </div>
    </form>
    
    <?php if (!empty($ops)): ?>
    <table>
      <thead>
        <tr>
          <th style="width:80px">Seq</th>
          <th>Description</th>
          <th style="width:100px">Time (min)</th>
          <th style="width:200px">Safety Notes</th>
          <th class="right" style="width:100px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($ops as $op): ?>
        <tr>
          <td class="mono center" style="font-weight:700"><?= (int)$op['op_seq'] ?></td>
          <td><?= h($op['description']) ?></td>
          <td class="mono center"><?= (int)$op['std_time_min'] ?></td>
          <td class="muted" style="font-size:13px"><?= h($op['safety_notes'] ?? '—') ?></td>
          <td class="right">
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="del_op">
              <input type="hidden" name="task_list_id" value="<?= (int)$edit['task_list_id'] ?>">
              <input type="hidden" name="op_seq" value="<?= (int)$op['op_seq'] ?>">
              <button class="btn sm danger" onclick="return confirm('Remove this operation?')">🗑️ Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty">
      <strong>No operations yet.</strong><br>
      Add operations above to define the step-by-step procedure.
    </div>
    <?php endif; ?>
  </div>

  <!-- Materials -->
  <div class="card">
    <h3>📦 Materials — <span class="mono"><?= h($edit['task_list_code']) ?></span></h3>
    <p class="muted" style="margin-bottom:16px">Specify parts and supplies needed for this task.</p>
    
    <form method="post" class="form-grid" style="margin-bottom:20px;background:#f8fafc;padding:16px;border-radius:8px">
      <input type="hidden" name="action" value="add_mat">
      <input type="hidden" name="task_list_id" value="<?= (int)$edit['task_list_id'] ?>">
      
      <div class="form-group" style="grid-column:span 2">
        <label>Material <span class="req">*</span></label>
        <select name="material_id" required>
          <option value="">Select material...</option>
          <?php foreach($mats as $m): ?>
          <option value="<?= (int)$m['material_id'] ?>">
            <?= h($m['material_code'].' - '.$m['material_name'].' ('.$m['unit_of_measure'].')') ?>
          </option>
          <?php endforeach; ?>
        </select>
        <small class="help-text">Part or supply required for this task</small>
      </div>
      
      <div class="form-group">
        <label>Quantity <span class="req">*</span></label>
        <input type="number" step="0.001" name="quantity" value="1" min="0.001" required>
        <small class="help-text">Amount needed per job</small>
      </div>
      
      <div class="form-group" style="align-items:flex-end">
        <button class="btn success">➕ Add Material</button>
      </div>
    </form>
    
    <?php if (!empty($tl_mats)): ?>
    <table>
      <thead>
        <tr>
          <th style="width:150px">Code</th>
          <th>Material Name</th>
          <th style="width:120px" class="center">Quantity</th>
          <th style="width:80px" class="center">Unit</th>
          <th class="right" style="width:100px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($tl_mats as $m): ?>
        <tr>
          <td class="mono"><?= h($m['material_code']) ?></td>
          <td><?= h($m['material_name']) ?></td>
          <td class="mono center" style="font-weight:700"><?= h($m['quantity']) ?></td>
          <td class="center"><?= h($m['unit_of_measure']) ?></td>
          <td class="right">
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="del_mat">
              <input type="hidden" name="task_list_id" value="<?= (int)$edit['task_list_id'] ?>">
              <input type="hidden" name="material_id" value="<?= (int)$m['material_id'] ?>">
              <button class="btn sm danger" onclick="return confirm('Remove this material?')">🗑️ Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty">
      <strong>No materials yet.</strong><br>
      Add materials above to specify what parts/supplies are needed.
    </div>
    <?php endif; ?>
  </div>
  
  <div style="text-align:center;margin:30px 0">
    <a href="task_lists.php" class="btn primary" style="padding:12px 32px;font-size:16px">✓ Done - Back to Task Lists</a>
  </div>
  <?php endif; ?>

  <!-- Task Lists Table -->
  <?php if (!$edit_id): ?>
  <div class="card">
    <h3>📋 All Task Lists (<?= count($rows) ?>)</h3>
    <?php if (!empty($rows)): ?>
    <table>
      <thead>
        <tr>
          <th style="width:140px">Code</th>
          <th>Title</th>
          <th style="width:180px">Equipment</th>
          <th style="width:160px">Work Center</th>
          <?php if ($TL_HAS_EST_HRS): ?><th style="width:80px" class="center">Hours</th><?php endif; ?>
          <?php if ($TL_HAS_ACTIVE): ?><th style="width:90px" class="center">Status</th><?php endif; ?>
          <th class="right" style="width:100px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td class="mono" style="font-weight:700;color:var(--primary)"><?= h($r['task_list_code']) ?></td>
          <td><?= h($r['title']) ?></td>
          <td class="muted" style="font-size:13px"><?= h($r['equipment_name'] ?? 'Generic') ?></td>
          <td class="muted" style="font-size:13px"><?= h($r['wc_name'] ?? 'Any') ?></td>
      
          <?php if ($TL_HAS_EST_HRS): ?>
          <td class="mono center"><?= $r['estimated_hours'] ? h($r['estimated_hours']) : '—' ?></td>
          <?php endif; ?>
          <?php if ($TL_HAS_ACTIVE): ?>
          <td class="center">
            <?= ((int)($r['is_active'] ?? 1)===1) ? badge('ACTIVE','#10b981') : badge('INACTIVE','#6b7280') ?>
          </td>
          <?php endif; ?>
          <td class="right">
            <a class="btn sm primary" href="task_lists.php?edit=<?= (int)$r['task_list_id'] ?>">✏️ Edit</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty">
      <strong>No task lists found.</strong><br>
      Create your first task list above to get started!
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<!-- Examples Modal Trigger (Optional) -->
<?php if (!$edit_id): ?>
<div style="position:fixed;bottom:20px;right:20px">
  <button class="btn" onclick="document.getElementById('examples').style.display='block'" style="box-shadow:0 4px 6px rgba(0,0,0,0.1)">
    💡 View Examples
  </button>
</div>

<div id="examples" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;padding:40px;overflow:auto" onclick="if(event.target===this) this.style.display='none'">
  <div class="card" style="max-width:900px;margin:0 auto;max-height:90vh;overflow:auto">
    <h3>📚 Task List Examples</h3>
    <p class="muted">Here are some common task lists you might create for your facility:</p>
    
    <div style="margin-top:20px">
      <h4 style="color:var(--primary);margin-bottom:8px">1. Air Conditioner Preventive Maintenance (TL-AC-PM)</h4>
      <p style="margin-bottom:8px"><strong>Operations:</strong></p>
      <ul style="margin-left:20px;margin-bottom:12px;line-height:1.8">
        <li>10 - Turn off power and lockout equipment (5 min) ⚠️ LOTO required</li>
        <li>20 - Remove and inspect air filters (10 min)</li>
        <li>30 - Clean or replace filters as needed (15 min)</li>
        <li>40 - Check condenser coils for dirt/damage (10 min)</li>
        <li>50 - Verify refrigerant levels and pressure (15 min)</li>
        <li>60 - Test thermostat operation (10 min)</li>
        <li>70 - Restore power and verify cooling (15 min)</li>
      </ul>
      <p><strong>Materials:</strong> Air Filter (2 units), Cleaning Solution (0.5 L), Refrigerant (as needed)</p>
      
      <hr style="margin:20px 0;border:none;border-top:1px solid var(--line)">
      
      <h4 style="color:var(--primary);margin-bottom:8px">2. Elevator Monthly Inspection (TL-ELEV-INSPECT)</h4>
      <p style="margin-bottom:8px"><strong>Operations:</strong></p>
      <ul style="margin-left:20px;margin-bottom:12px;line-height:1.8">
        <li>10 - Visual inspection of car interior and doors (10 min)</li>
        <li>20 - Test door sensors and safety edges (15 min)</li>
        <li>30 - Inspect guide rails for wear (15 min)</li>
        <li>40 - Check cable tension and condition (20 min) ⚠️ Fall protection required</li>
        <li>50 - Lubricate moving parts (20 min)</li>
        <li>60 - Test emergency systems and alarms (15 min)</li>
        <li>70 - Complete inspection log (10 min)</li>
      </ul>
      <p><strong>Materials:</strong> Elevator Lubricant (2 bottles), Cleaning Rags (5 units)</p>
      
      <hr style="margin:20px 0;border:none;border-top:1px solid var(--line)">
      
      <h4 style="color:var(--primary);margin-bottom:8px">3. Fire Alarm System Test (TL-FIRE-TEST)</h4>
      <p style="margin-bottom:8px"><strong>Operations:</strong></p>
      <ul style="margin-left:20px;margin-bottom:12px;line-height:1.8">
        <li>10 - Notify security/occupants of test (5 min)</li>
        <li>20 - Test control panel operation (10 min)</li>
        <li>30 - Activate each detector zone (30 min)</li>
        <li>40 - Verify all alarm lights and sirens (15 min)</li>
        <li>50 - Replace any defective bulbs/batteries (20 min)</li>
        <li>60 - Test manual pull stations (15 min)</li>
        <li>70 - Document results and notify completion (10 min)</li>
      </ul>
      <p><strong>Materials:</strong> 9V Batteries (10 units), Fire Alarm Bulbs (5 units)</p>
      
      <hr style="margin:20px 0;border:none;border-top:1px solid var(--line)">
      
      <h4 style="color:var(--primary);margin-bottom:8px">4. CCTV Camera Maintenance (TL-CCTV-MAINT)</h4>
      <p style="margin-bottom:8px"><strong>Operations:</strong></p>
      <ul style="margin-left:20px;margin-bottom:12px;line-height:1.8">
        <li>10 - Clean camera lenses and housings (20 min)</li>
        <li>20 - Check cable connections and weatherproofing (15 min)</li>
        <li>30 - Verify video quality and focus (15 min)</li>
        <li>40 - Test pan/tilt/zoom functions (10 min)</li>
        <li>50 - Check recording system and storage (15 min)</li>
        <li>60 - Update firmware if needed (30 min)</li>
      </ul>
      <p><strong>Materials:</strong> Lens Cleaning Solution (0.2 L), Microfiber Cloths (3 units)</p>
      
      <hr style="margin:20px 0;border:none;border-top:1px solid var(--line)">
      
      <h4 style="color:var(--primary);margin-bottom:8px">5. Emergency Lighting Test (TL-EMERG-LIGHT)</h4>
      <p style="margin-bottom:8px"><strong>Operations:</strong></p>
      <ul style="margin-left:20px;margin-bottom:12px;line-height:1.8">
        <li>10 - Simulate power failure at breaker (5 min)</li>
        <li>20 - Verify all emergency lights activate (20 min)</li>
        <li>30 - Check illumination levels with meter (30 min)</li>
        <li>40 - Inspect battery backup units (15 min)</li>
        <li>50 - Replace failed lamps/batteries (30 min)</li>
        <li>60 - Restore normal power (5 min)</li>
        <li>70 - Document test results (10 min)</li>
      </ul>
      <p><strong>Materials:</strong> Emergency Light Bulbs (10 units), Backup Batteries (5 units)</p>
    </div>
    
    <div style="margin-top:30px;padding-top:20px;border-top:2px solid var(--line)">
      <h4 style="margin-bottom:12px">💡 Best Practices:</h4>
      <ul style="margin-left:20px;line-height:2">
        <li><strong>Use sequential numbering:</strong> 10, 20, 30... allows inserting steps later</li>
        <li><strong>Be specific:</strong> "Inspect air filter" is better than "Check AC"</li>
        <li><strong>Include safety:</strong> Note LOTO, PPE, fall protection, etc.</li>
        <li><strong>Estimate time:</strong> Helps with scheduling and planning</li>
        <li><strong>List all materials:</strong> Prevents delays from missing parts</li>
        <li><strong>Keep it generic:</strong> Use equipment-specific only when necessary</li>
      </ul>
    </div>
    
    <div class="btn-row" style="margin-top:30px;justify-content:center">
      <button class="btn primary" onclick="document.getElementById('examples').style.display='none'">Close</button>
    </div>
  </div>
</div>
<?php endif; ?>

</body>
</html>