<?php
// work_centers.php — CRUD for Work Centers (supports wc_code / wc_name)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'planner')) {
  header('Location: login.php'); exit;
}
$planner_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Planner';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ---------- Table & column discovery ---------- */
$TABLE = null; $cols = []; $desc = [];
try {
  $q = $pdo->query("DESCRIBE work_center");
  $desc = $q->fetchAll(PDO::FETCH_ASSOC);
  $cols = array_column($desc, 'Field');
  $TABLE = 'work_center';
} catch(Throwable $e) {
  try {
    $q = $pdo->query("DESCRIBE work_centers");
    $desc = $q->fetchAll(PDO::FETCH_ASSOC);
    $cols = array_column($desc, 'Field');
    $TABLE = 'work_centers';
  } catch(Throwable $e2){ die("Table 'work_center' or 'work_centers' not found."); }
}

$PK =
  (in_array('work_center_id',$cols,true) ? 'work_center_id' :
  (in_array('id',$cols,true) ? 'id' : null));
if (!$PK) die('Primary key not found (expect work_center_id or id).');

// Prefer your schema (wc_code / wc_name), then fallbacks:
$COL_CODE = in_array('wc_code',$cols,true) ? 'wc_code'
          : (in_array('work_center_code',$cols,true) ? 'work_center_code'
          : (in_array('code',$cols,true) ? 'code' : null));

$COL_NAME = in_array('wc_name',$cols,true) ? 'wc_name'
          : (in_array('name',$cols,true) ? 'name'
          : (in_array('title',$cols,true) ? 'title' : null));

$COL_DESC   = in_array('description',$cols,true) ? 'description' : null;
$COL_ACTIVE = in_array('is_active',$cols,true)   ? 'is_active'   : null;

$COL_CREATED = null;
foreach (['created_at','created_on','createdDate'] as $c) {
  if (in_array($c,$cols,true)) { $COL_CREATED = $c; break; }
}

/* ---------- Actions ---------- */
$flash=''; $err='';
$action = $_POST['action'] ?? null;

if ($action === 'create') {
  try {
    $fields = [];
    if ($COL_CODE)   $fields[$COL_CODE] = trim($_POST['code'] ?? '');
    if ($COL_NAME)   $fields[$COL_NAME] = trim($_POST['name'] ?? '');
    if ($COL_DESC)   $fields[$COL_DESC] = trim($_POST['description'] ?? '');
    if ($COL_ACTIVE) $fields[$COL_ACTIVE] = isset($_POST['is_active']) ? 1 : 1; // default active

    if ($COL_NAME && $fields[$COL_NAME] === '') throw new Exception('Name is required.');
    $colsSql = implode(', ', array_keys($fields));
    $qs      = implode(', ', array_fill(0, count($fields), '?'));
    $pdo->prepare("INSERT INTO $TABLE ($colsSql) VALUES ($qs)")->execute(array_values($fields));
    $flash = 'Work Center created.';
  } catch (Throwable $e) { $err = $e->getMessage(); }
}

if ($action === 'update') {
  try {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('Invalid ID.');

    $fields = [];
    if ($COL_CODE)   $fields[$COL_CODE] = trim($_POST['code'] ?? '');
    if ($COL_NAME)   $fields[$COL_NAME] = trim($_POST['name'] ?? '');
    if ($COL_DESC)   $fields[$COL_DESC] = trim($_POST['description'] ?? '');
    if ($COL_ACTIVE) $fields[$COL_ACTIVE] = isset($_POST['is_active']) ? 1 : 0;

    if ($COL_NAME && $fields[$COL_NAME] === '') throw new Exception('Name is required.');

    $set=[]; $vals=[];
    foreach ($fields as $k=>$v){ $set[]="$k=?"; $vals[]=$v; }
    $vals[] = $id;

    $pdo->prepare("UPDATE $TABLE SET ".implode(', ', $set)." WHERE $PK=?")->execute($vals);
    $flash = 'Work Center updated.';
  } catch (Throwable $e) { $err = $e->getMessage(); }
}

if ($action === 'delete') {
  try {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('Invalid ID.');
    $pdo->prepare("DELETE FROM $TABLE WHERE $PK=?")->execute([$id]);
    $flash = 'Work Center deleted.';
  } catch (Throwable $e) { $err = 'Delete failed: '.$e->getMessage(); }
}

/* ---------- Data ---------- */
$selectCols = "$PK";
if ($COL_CODE)   $selectCols .= ", $COL_CODE";
if ($COL_NAME)   $selectCols .= ", $COL_NAME";
if ($COL_DESC)   $selectCols .= ", $COL_DESC";
if ($COL_ACTIVE) $selectCols .= ", $COL_ACTIVE";
if ($COL_CREATED)$selectCols .= ", $COL_CREATED AS created";

$orderBy = $COL_CODE ?: ($COL_NAME ?: $PK);
$list = $pdo->query("SELECT $selectCols FROM $TABLE ORDER BY $orderBy")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CMMS · Work Centers</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{--bg:#fff;--line:#e9edf2;--muted:#667085}
  *{box-sizing:border-box}
  body{margin:0;background:#fff;color:#111;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto}
  a{color:#0d6efd;text-decoration:none}
  .wrap{max-width:1200px;margin:24px auto;padding:0 16px}
  header{display:flex;align-items:center;gap:12px;margin-bottom:16px}
  .back{border:1px solid var(--line);padding:8px 12px;border-radius:10px;color:white;background:blue}
  .spacer{flex:1}
  .who{color:var(--muted);font-size:14px}
  .card{border:1px solid var(--line);border-radius:16px;background:#fff;padding:16px}
  .row{display:flex;gap:12px;flex-wrap:wrap}
  input,textarea,select{width:100%;padding:10px;border:1px solid var(--line);border-radius:10px}
  textarea{min-height:70px;resize:vertical}
  table{width:100%;border-collapse:collapse}
  th,td{padding:12px 10px;border-bottom:1px solid var(--line);vertical-align:top}
  .btn{border:1px solid #d0d7e2;background:#f8fafc;border-radius:10px;padding:8px 12px;cursor:pointer}
  .btn.primary{background:#0d6efd;border-color:#0d6efd;color:#fff}
  .btn.danger{background:#ef4444;border-color:#ef4444;color:#fff}
  .right{text-align:right}
  .muted{color:#64748b}
  .w140{min-width:140px}
  .w220{min-width:220px}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <a class="back" href="planner_dashboard.php">← Back to Dashboard</a>
    <h2 style="margin:0"> Work Centers</h2>
    <div class="spacer"></div>
    <div class="who">Planner: <?= h($planner_name) ?> · <a href="logout.php">Logout</a></div>
  </header>

  <?php if($flash): ?><div class="card" style="border-color:#b7ebcd;background:#e8f6ee;margin-bottom:12px"><?= h($flash) ?></div><?php endif; ?>
  <?php if($err):   ?><div class="card" style="border-color:#f3c2c8;background:#fde7ea;margin-bottom:12px"><?= h($err)   ?></div><?php endif; ?>

  <!-- Add Work Center -->
  <div class="card" style="margin-bottom:16px">
    <h3 style="margin:0 0 8px">Add Work Center</h3>
    <form method="post" class="row">
      <input type="hidden" name="action" value="create">
      <?php if($COL_CODE): ?><div class="w140"><label>Code</label><input name="code" placeholder="e.g., WC01"></div><?php endif; ?>
      <?php if($COL_NAME): ?><div class="w220" style="flex:1"><label>Name *</label><input name="name" required placeholder="e.g., Electrical"></div><?php endif; ?>
      <?php if($COL_ACTIVE):?><div class="w140" style="display:flex;align-items:end;gap:8px"><input type="checkbox" name="is_active" checked id="a1"><label for="a1">Active</label></div><?php endif; ?>
      <?php if($COL_DESC): ?><div style="width:100%"><label>Description</label><textarea name="description" placeholder="Notes / scope of this work center"></textarea></div><?php endif; ?>
      <div style="flex:1;text-align:right"><button class="btn primary">Create</button></div>
    </form>
  </div>

  <!-- List -->
  <div class="card">
    <h3 style="margin:0 0 8px">All Work Centers</h3>
    <table>
      <thead>
        <tr>
          <?php if($COL_CODE):   ?><th>Code</th><?php endif; ?>
          <?php if($COL_NAME):   ?><th>Name</th><?php endif; ?>
          <?php if($COL_DESC):   ?><th>Description</th><?php endif; ?>
          <?php if($COL_ACTIVE): ?><th>Status</th><?php endif; ?>
          <?php if($COL_CREATED):?><th>Created</th><?php endif; ?>
          <th class="right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($list as $row): ?>
        <tr>
          <form method="post">
            <input type="hidden" name="id" value="<?= (int)$row[$PK] ?>">
            <?php if($COL_CODE): ?>
              <td><input name="code" value="<?= h($row[$COL_CODE] ?? '') ?>"></td>
            <?php endif; ?>
            <?php if($COL_NAME): ?>
              <td><input name="name" value="<?= h($row[$COL_NAME] ?? '') ?>"></td>
            <?php endif; ?>
            <?php if($COL_DESC): ?>
              <td><textarea name="description"><?= h($row[$COL_DESC] ?? '') ?></textarea></td>
            <?php endif; ?>
            <?php if($COL_ACTIVE): ?>
              <td>
                <label><input type="checkbox" name="is_active" <?= ((int)($row[$COL_ACTIVE] ?? 1)===1)?'checked':''; ?>> Active</label>
              </td>
            <?php endif; ?>
            <?php if($COL_CREATED): ?>
              <td><?= !empty($row['created']) ? h(substr($row['created'],0,10)) : '—' ?></td>
            <?php endif; ?>
            <td class="right">
              <button class="btn primary" name="action" value="update">Save</button>
              <button class="btn danger"  name="action" value="delete" onclick="return confirm('Delete this work center?')">Delete</button>
            </td>
          </form>
        </tr>
      <?php endforeach; if(empty($list)): ?>
        <tr><td colspan="6" class="muted" style="text-align:center">No work centers found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
