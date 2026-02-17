<?php
// materials.php — Manage Materials (Planner)
// Pure PHP + HTML + CSS, white background.
// Works with your current schema: auto-detects optional stock columns.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

// ---- Auth: planner only ----
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'planner') {
  header('Location: login.php'); exit;
}
$planner_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Planner';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ----- Detect stock columns dynamically -----
$cols = $pdo->query("DESCRIBE material")->fetchAll(PDO::FETCH_COLUMN);
$qtyColCandidates     = ['qty_on_hand','on_hand','stock_qty','quantity','qty'];
$reorderColCandidates = ['reorder_point','reorder_level','min_qty','min_stock'];

$QTY_COL = null;
$REO_COL = null;
foreach ($qtyColCandidates as $c) if (in_array($c,$cols,true)) { $QTY_COL = $c; break; }
foreach ($reorderColCandidates as $c) if (in_array($c,$cols,true)) { $REO_COL = $c; break; }

// Optional active flag
$ACTIVE_COL = in_array('is_active',$cols,true) ? 'is_active' : null;

// ---- Handle create / update ----
$flash = ''; $flash_error = '';

if (($_POST['action'] ?? '') === 'save_material') {
  try {
    $mid  = isset($_POST['material_id']) && $_POST['material_id'] !== '' ? (int)$_POST['material_id'] : null;
    $code = trim($_POST['material_code'] ?? '');
    $name = trim($_POST['material_name'] ?? '');

    if ($code === '' || $name === '') throw new Exception('Material Code and Name are required.');

    // uniqueness check for code
    if ($mid) {
      $q = $pdo->prepare("SELECT COUNT(*) FROM material WHERE material_code = ? AND material_id <> ?");
      $q->execute([$code,$mid]);
    } else {
      $q = $pdo->prepare("SELECT COUNT(*) FROM material WHERE material_code = ?");
      $q->execute([$code]);
    }
    if ((int)$q->fetchColumn() > 0) throw new Exception('Material Code already exists.');

    // Build dynamic insert/update
    $fields = ['material_code'=>$code,'material_name'=>$name];

    if ($QTY_COL !== null && $_POST[$QTY_COL] !== '') {
      $fields[$QTY_COL] = (float)$_POST[$QTY_COL];
    }
    if ($REO_COL !== null && $_POST[$REO_COL] !== '') {
      $fields[$REO_COL] = (float)$_POST[$REO_COL];
    }
    if ($ACTIVE_COL !== null && isset($_POST[$ACTIVE_COL])) {
      $fields[$ACTIVE_COL] = (int)($_POST[$ACTIVE_COL] ? 1 : 0);
    }

    if ($mid) {
      // UPDATE
      $set = [];
      $vals = [];
      foreach ($fields as $k=>$v) { $set[] = "$k = ?"; $vals[] = $v; }
      $vals[] = $mid;
      $sql = "UPDATE material SET ".implode(', ',$set)." WHERE material_id = ?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($vals);
      $flash = "Material <strong>".h($code)."</strong> updated.";
    } else {
      // INSERT
      $colsSql = implode(', ', array_keys($fields));
      $qs = implode(', ', array_fill(0, count($fields), '?'));
      $sql = "INSERT INTO material ($colsSql) VALUES ($qs)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(array_values($fields));
      $flash = "Material <strong>".h($code)."</strong> created.";
    }
  } catch (Throwable $e) {
    $flash_error = $e->getMessage();
  }
}

// ---- Filters / search ----
$q = trim($_GET['q'] ?? '');
$only_low = isset($_GET['low']) && $_GET['low'] === '1';

$where = []; $params = [];
if ($q !== '') {
  $where[] = "(m.material_code LIKE ? OR m.material_name LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%";
}
if ($ACTIVE_COL !== null) {
  $where[] = "COALESCE(m.$ACTIVE_COL,1) = 1"; // show active by default
}
if ($only_low && $QTY_COL !== null && $REO_COL !== null) {
  $where[] = "(COALESCE(m.$QTY_COL,0) <= COALESCE(m.$REO_COL,0))";
}

$sql = "SELECT m.material_id, m.material_code, m.material_name".
       ($QTY_COL ? ", m.$QTY_COL AS qty" : "").
       ($REO_COL ? ", m.$REO_COL AS reorder_pt" : "").
       ($ACTIVE_COL ? ", m.$ACTIVE_COL AS is_active" : "").
       " FROM material m ".
       (count($where) ? "WHERE ".implode(' AND ', $where) : "").
       " ORDER BY m.material_code LIMIT 500";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// ---- Edit mode ----
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_row = null;
if ($edit_id > 0) {
  $sel = "material_id, material_code, material_name".
         ($QTY_COL ? ", $QTY_COL AS qty" : "").
         ($REO_COL ? ", $REO_COL AS reorder_pt" : "").
         ($ACTIVE_COL ? ", $ACTIVE_COL AS is_active" : "");
  $s = $pdo->prepare("SELECT $sel FROM material WHERE material_id = ? LIMIT 1");
  $s->execute([$edit_id]);
  $edit_row = $s->fetch();
}

// ---- Helpers (badges) ----
function badge($text,$color){ return "<span style='background:$color;color:#fff;padding:4px 8px;border-radius:999px;font-size:12px'>$text</span>"; }
function lowBadge(){ return badge('LOW','linear-gradient(90deg,#dc2626,#f97316)'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CMMS · Materials</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--bg:#fff;--text:#111;--muted:#5b6b7b;--line:#e9edf2;}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto}
  a{color:#0d6efd;text-decoration:none}
  .wrap{max-width:1200px;margin:24px auto;padding:0 16px}
  header{display:flex;align-items:center;gap:12px;margin-bottom:12px}
  .back{border:1px solid var(--line);padding:6px 10px;border-radius:8px;color:white;background:blue}
  .spacer{flex:1}
  .who{color:var(--muted);font-size:14px}
  .card{border:1px solid var(--line);border-radius:14px;background:#fff}
  .pad{padding:16px}
  .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
  label{font-size:14px;color:var(--muted)}
  input[type=text],input[type=number],select{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:#fff}
  .btn{border:1px solid #d0d7e2;background:#f8fafc;border-radius:10px;padding:9px 14px;cursor:pointer}
  .btn.primary{background:#0d6efd;border-color:#0d6efd;color:#fff}
  .btn.sm{padding:6px 10px;border-radius:8px;font-size:14px}
  .alert{padding:12px 14px;border-radius:10px;margin-bottom:14px}
  .alert.ok{background:#e8f6ee;border:1px solid #b7ebcd}
  .alert.err{background:#fde7ea;border:1px solid #f3c2c8}
  table{width:100%;border-collapse:collapse}
  th,td{padding:12px 10px;border-bottom:1px solid var(--line);vertical-align:top}
  thead th{font-size:14px;color:#1b2a3a;text-align:left}
  .right{text-align:right}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <a href="planner_dashboard.php" class="back">← Back to Dashboard </a>
    <h2 style="margin:0">Materials</h2>
    <div class="spacer"></div>
    <div class="who">Planner: <?= h($planner_name) ?> · <a href="logout.php">Logout</a></div>
  </header>

  <?php if ($flash): ?><div class="alert ok"><?= $flash ?></div><?php endif; ?>
  <?php if ($flash_error): ?><div class="alert err"><?= h($flash_error) ?></div><?php endif; ?>

  <!-- Search / Filters -->
  <div class="card pad" style="margin-bottom:16px">
    <form method="get" class="row">
      <div style="min-width:280px;flex:1">
        <label>Search</label>
        <input type="text" name="q" placeholder="Code or name..." value="<?= h($q) ?>">
      </div>
      <div style="min-width:220px">
        <label>Show</label>
        <select name="low">
          <option value="">All materials</option>
          <option value="1" <?= $only_low?'selected':''; ?> <?= ($QTY_COL && $REO_COL)?'':'disabled'; ?>>Low stock only</option>
        </select>
      </div>
      <div class="right" style="flex:1 0 auto">
        <label>&nbsp;</label>
        <div><button class="btn primary">Apply</button> <a class="btn" href="materials.php">Reset</a></div>
      </div>
    </form>
    <?php if (!$QTY_COL || !$REO_COL): ?>
      <div class="alert" style="background:#fff7ed;border:1px solid #fed7aa;margin-top:12px">
        <strong>Note:</strong> Quantity / Reorder columns were not found in your <code>material</code> table.
        The page will still work for Code & Name. If you later add stock columns
        (e.g., <code>qty_on_hand</code> and <code>reorder_point</code>), this page will pick them up automatically.
      </div>
    <?php endif; ?>
  </div>

  <!-- Add / Edit Material -->
  <div class="card pad" style="margin-bottom:16px">
    <h3 style="margin:0 0 8px 0;font-size:18px"><?= $edit_row ? 'Edit Material' : 'Add Material' ?></h3>
    <form method="post" class="row" style="row-gap:10px">
      <input type="hidden" name="action" value="save_material">
      <?php if ($edit_row): ?>
        <input type="hidden" name="material_id" value="<?= (int)$edit_row['material_id'] ?>">
      <?php endif; ?>

      <div style="min-width:220px">
        <label>Material Code <span style="color:#dc2626">*</span></label>
        <input type="text" name="material_code" required maxlength="50"
               value="<?= h($edit_row['material_code'] ?? '') ?>">
      </div>

      <div style="min-width:320px;flex:1">
        <label>Material Name <span style="color:#dc2626">*</span></label>
        <input type="text" name="material_name" required maxlength="200"
               value="<?= h($edit_row['material_name'] ?? '') ?>">
      </div>

      <?php if ($QTY_COL): ?>
      <div style="min-width:180px">
        <label><?= h(str_replace('_',' ', strtoupper($QTY_COL))) ?></label>
        <input type="number" step="0.001" name="<?= h($QTY_COL) ?>"
               value="<?= isset($edit_row['qty']) ? h($edit_row['qty']) : '' ?>">
      </div>
      <?php endif; ?>

      <?php if ($REO_COL): ?>
      <div style="min-width:180px">
        <label><?= h(str_replace('_',' ', strtoupper($REO_COL))) ?></label>
        <input type="number" step="0.001" name="<?= h($REO_COL) ?>"
               value="<?= isset($edit_row['reorder_pt']) ? h($edit_row['reorder_pt']) : '' ?>">
      </div>
      <?php endif; ?>

      <?php if ($ACTIVE_COL): ?>
      <div style="min-width:160px">
        <label>Status</label>
        <select name="<?= h($ACTIVE_COL) ?>">
          <option value="1" <?= isset($edit_row['is_active']) ? ((int)$edit_row['is_active']===1?'selected':'') : 'selected' ?>>Active</option>
          <option value="0" <?= isset($edit_row['is_active']) && (int)$edit_row['is_active']===0 ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <?php endif; ?>

      <div class="right" style="flex:1 0 auto">
        <label>&nbsp;</label>
        <button class="btn primary"><?= $edit_row ? 'Update' : 'Create' ?></button>
        <?php if ($edit_row): ?><a class="btn" href="materials.php">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Materials Table -->
  <div class="card">
    <div class="pad">
      <table>
        <thead>
          <tr>
            <th style="width:140px">Code</th>
            <th>Name</th>
            <?php if ($QTY_COL): ?><th style="width:140px"><?= h(str_replace('_',' ', strtoupper($QTY_COL))) ?></th><?php endif; ?>
            <?php if ($REO_COL): ?><th style="width:140px"><?= h(str_replace('_',' ', strtoupper($REO_COL))) ?></th><?php endif; ?>
            <?php if ($QTY_COL && $REO_COL): ?><th style="width:110px">Status</th><?php endif; ?>
            <th class="right" style="width:120px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): 
            $isLow = false;
            if ($QTY_COL && $REO_COL) {
              $qv = (float)($r['qty'] ?? 0);
              $rv = (float)($r['reorder_pt'] ?? 0);
              $isLow = $qv <= $rv;
            }
          ?>
          <tr>
            <td class="mono"><?= h($r['material_code']) ?></td>
            <td><?= h($r['material_name']) ?></td>
            <?php if ($QTY_COL): ?><td class="mono"><?= isset($r['qty']) ? h($r['qty']) : '—' ?></td><?php endif; ?>
            <?php if ($REO_COL): ?><td class="mono"><?= isset($r['reorder_pt']) ? h($r['reorder_pt']) : '—' ?></td><?php endif; ?>
            <?php if ($QTY_COL && $REO_COL): ?>
              <td><?= $isLow ? lowBadge() : '<span style="color:#6b7280">OK</span>' ?></td>
            <?php endif; ?>
            <td class="right">
              <a class="btn sm" href="materials.php?edit=<?= (int)$r['material_id'] ?>">Edit</a>
            </td>
          </tr>
          <?php endforeach; if (empty($rows)): ?>
          <tr><td colspan="<?= 3 + (int)(bool)$QTY_COL + (int)(bool)$REO_COL + (int)(bool)($QTY_COL && $REO_COL) ?>" style="text-align:center;color:#6b7280">No materials found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
