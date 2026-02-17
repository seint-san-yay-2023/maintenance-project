<?php
// vendors.php — Planner manages vendor master (with soft delete)
// Auto-detects column names for email/phone/address and hides fields if missing.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

// --- Auth: planner only ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'planner') {
  header('Location: login.php'); exit;
}
$plannerName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Planner';
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// --- CSRF ---
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t ?? ''); }

// --- Introspect vendor columns so we can adapt to your DB ---
$vendorCols = $pdo->query("DESCRIBE vendor")->fetchAll(PDO::FETCH_COLUMN);

// Required ones we assume exist:
$ID_COL   = in_array('vendor_id',   $vendorCols, true) ? 'vendor_id'   : 'id';
$NAME_COL = in_array('vendor_name', $vendorCols, true) ? 'vendor_name' : 'name';
$ACTIVE_COL = in_array('is_active', $vendorCols, true) ? 'is_active'   : null;

// Optional ones (auto-detect)
$EMAIL_COL = null;
foreach (['vendor_email','email','mail'] as $c) if (in_array($c,$vendorCols,true)) { $EMAIL_COL = $c; break; }

$PHONE_COL = null;
foreach (['vendor_phone','phone','tel','telephone','mobile'] as $c) if (in_array($c,$vendorCols,true)) { $PHONE_COL = $c; break; }

$ADDR_COL = null;
foreach (['vendor_address','address','addr','street'] as $c) if (in_array($c,$vendorCols,true)) { $ADDR_COL = $c; break; }

// --- Handle create/update/delete ---
$flash = ''; $flash_err = '';

try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add or edit vendor
    if (($_POST['action'] ?? '') === 'save_vendor' && csrf_ok($_POST['csrf'] ?? '')) {
      $vid  = (int)($_POST['vendor_id'] ?? 0);
      $vname = trim($_POST['vendor_name'] ?? $_POST['name'] ?? '');

      if ($vname === '') throw new Exception('Vendor name is required.');

      // Build dynamic field map based on what exists
      $fields = [$NAME_COL => $vname];

      if ($EMAIL_COL && isset($_POST['vendor_email'])) $fields[$EMAIL_COL] = trim($_POST['vendor_email']);
      if ($PHONE_COL && isset($_POST['vendor_phone'])) $fields[$PHONE_COL] = trim($_POST['vendor_phone']);
      if ($ADDR_COL  && isset($_POST['vendor_address'])) $fields[$ADDR_COL]  = trim($_POST['vendor_address']);
      if ($ACTIVE_COL && isset($_POST['is_active'])) $fields[$ACTIVE_COL] = (int)$_POST['is_active'];

      if ($vid > 0) {
        // UPDATE
        $set=[]; $vals=[];
        foreach($fields as $k=>$v){ $set[]="$k=?"; $vals[]=$v; }
        $vals[] = $vid;
        $sql = "UPDATE vendor SET ".implode(', ',$set)." WHERE $ID_COL=?";
        $pdo->prepare($sql)->execute($vals);
        $flash = "Vendor <strong>".h($vname)."</strong> updated.";
      } else {
        // INSERT
        $colsSql = implode(', ', array_keys($fields));
        $qs = implode(', ', array_fill(0,count($fields),'?'));
        $sql = "INSERT INTO vendor ($colsSql) VALUES ($qs)";
        $pdo->prepare($sql)->execute(array_values($fields));
        $flash = "Vendor <strong>".h($vname)."</strong> added.";
      }
    }

    // Soft delete
    if (($_POST['action'] ?? '') === 'delete_vendor' && csrf_ok($_POST['csrf'] ?? '')) {
      if (!$ACTIVE_COL) throw new Exception("Soft delete needs an 'is_active' column.");
      $vid = (int)($_POST['vendor_id'] ?? 0);
      $pdo->prepare("UPDATE vendor SET $ACTIVE_COL = 0 WHERE $ID_COL=?")->execute([$vid]);
      $flash = "Vendor deleted successfully.";
    }
  }
} catch (Throwable $e) {
  $flash_err = $e->getMessage();
}

// --- Fetch vendors (active only by default) ---
$q = trim($_GET['q'] ?? '');
$where = [];
$params = [];

if ($ACTIVE_COL) { $where[] = "COALESCE(v.$ACTIVE_COL,1)=1"; }
if ($q !== '') {
  $like = "(v.$NAME_COL LIKE ?"
        . ($EMAIL_COL ? " OR v.$EMAIL_COL LIKE ?" : "")
        . ")";
  $where[] = $like;
  $params[] = "%$q%";
  if ($EMAIL_COL) $params[] = "%$q%";
}

$sel = "v.$ID_COL AS vendor_id, v.$NAME_COL AS vendor_name";
if ($EMAIL_COL) $sel .= ", v.$EMAIL_COL AS vendor_email";
if ($PHONE_COL) $sel .= ", v.$PHONE_COL AS vendor_phone";
if ($ADDR_COL)  $sel .= ", v.$ADDR_COL  AS vendor_address";
if ($ACTIVE_COL) $sel .= ", v.$ACTIVE_COL AS is_active";

$sql = "SELECT $sel FROM vendor v ".(count($where) ? "WHERE ".implode(' AND ',$where) : "")." ORDER BY v.$NAME_COL";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// --- Editing vendor ---
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_row = null;
if ($edit_id > 0) {
  $psel = $sel; // same projection as list
  $s = $pdo->prepare("SELECT $psel FROM vendor v WHERE v.$ID_COL=? LIMIT 1");
  $s->execute([$edit_id]);
  $edit_row = $s->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Management</title>
<style>
:root{--bg:#fff;--text:#111;--muted:#5b6b7b;--line:#e9edf2;--primary:#0d6efd;--danger:#dc2626;}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto;background:var(--bg);color:var(--text);}
.wrap{max-width:1100px;margin:24px auto;padding:0 16px;}
header{display:flex;align-items:center;gap:10px;margin-bottom:18px;}
header h2{margin:0;font-size:22px;}
.back{border:1px solid var(--line);padding:6px 10px;border-radius:8px;text-decoration:none;color:white;background:blue}
.spacer{flex:1}
.card{border:1px solid var(--line);border-radius:12px;background:#fff;margin-bottom:16px;}
.pad{padding:16px;}
input[type=text],input[type=email]{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;}
button,.btn{border:1px solid var(--line);background:#f8fafc;border-radius:8px;padding:7px 12px;cursor:pointer;}
.btn.primary{background:var(--primary);color:#fff;border-color:var(--primary);}
.btn.danger{background:var(--danger);color:#fff;border-color:var(--danger);}
.alert{padding:10px 14px;border-radius:8px;margin-bottom:12px;}
.alert.ok{background:#e8f6ee;border:1px solid #b7ebcd;}
.alert.err{background:#fde7ea;border:1px solid #f3c2c8;}
table{width:100%;border-collapse:collapse;margin-top:12px;}
th,td{padding:10px;border-bottom:1px solid var(--line);}
th{text-align:left;color:#1b2a3a;}
.row{display:flex;flex-wrap:wrap;gap:12px;}
.right{text-align:right;}
</style>
</head>
<body>
<div class="wrap">
<header>
  <a href="planner_dashboard.php" class="back">← Back to Dashboard</a>
  <h2>Vendor Management</h2>
  <div class="spacer"></div>
  <div class="who">Planner: <?=h($plannerName)?> · <a href="logout.php">Logout</a></div>
</header>

<?php if($flash):?><div class="alert ok"><?=$flash?></div><?php endif;?>
<?php if($flash_err):?><div class="alert err"><?=h($flash_err)?></div><?php endif;?>

<div class="card pad">
  <h3 style="margin:0 0 8px 0;"><?= $edit_row ? 'Edit Vendor' : 'Add Vendor' ?></h3>
  <form method="post" class="row">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
    <input type="hidden" name="action" value="save_vendor">
    <?php if($edit_row):?>
      <input type="hidden" name="vendor_id" value="<?=$edit_row['vendor_id']?>">
    <?php endif;?>

    <div style="flex:1;min-width:220px">
      <label>Vendor Name *</label>
      <input type="text" name="vendor_name" required value="<?=h($edit_row['vendor_name'] ?? '')?>">
    </div>

    <?php if ($EMAIL_COL): ?>
    <div style="flex:1;min-width:220px">
      <label>Email</label>
      <input type="email" name="vendor_email" value="<?=h($edit_row['vendor_email'] ?? '')?>">
    </div>
    <?php endif; ?>

    <?php if ($PHONE_COL): ?>
    <div style="flex:1;min-width:180px">
      <label>Phone</label>
      <input type="text" name="vendor_phone" value="<?=h($edit_row['vendor_phone'] ?? '')?>">
    </div>
    <?php endif; ?>

    <?php if ($ADDR_COL): ?>
    <div style="flex:1;min-width:300px">
      <label>Address</label>
      <input type="text" name="vendor_address" value="<?=h($edit_row['vendor_address'] ?? '')?>">
    </div>
    <?php endif; ?>

    <div class="right" style="flex:1 0 auto">
      <label>&nbsp;</label><br>
      <button class="btn primary"><?= $edit_row ? 'Update' : 'Add' ?></button>
      <?php if($edit_row):?><a class="btn" href="vendors.php">Cancel</a><?php endif;?>
    </div>
  </form>
</div>

<div class="card pad">
  <h3 style="margin:0 0 10px 0;">Active Vendors</h3>
  <form method="get" style="margin-bottom:10px;">
    <input type="text" name="q" placeholder="Search by name or email..." value="<?=h($q)?>" style="padding:8px 10px;width:260px;border:1px solid var(--line);border-radius:8px;">
    <button class="btn">Search</button>
    <a href="vendors.php" class="btn">Reset</a>
  </form>

  <table>
    <thead>
      <tr>
        <th style="width:60px">#</th>
        <th>Name</th>
        <?php if ($EMAIL_COL): ?><th>Email</th><?php endif; ?>
        <?php if ($PHONE_COL): ?><th>Phone</th><?php endif; ?>
        <?php if ($ADDR_COL):  ?><th>Address</th><?php endif; ?>
        <th style="width:170px" class="right">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
      if(!$rows){
        echo "<tr><td colspan='6' style='text-align:center;color:#777'>No active vendors found.</td></tr>";
      } else {
        $i=1;
        foreach($rows as $r){
          echo "<tr>";
          echo "<td>".($i++)."</td>";
          echo "<td>".h($r['vendor_name'])."</td>";
          if ($EMAIL_COL) echo "<td>".h($r['vendor_email'] ?? '')."</td>";
          if ($PHONE_COL) echo "<td>".h($r['vendor_phone'] ?? '')."</td>";
          if ($ADDR_COL)  echo "<td>".h($r['vendor_address'] ?? '')."</td>";
          echo "<td class='right'>
            <a class='btn' href='vendors.php?edit=".h($r['vendor_id'])."'>Edit</a>
            ".($ACTIVE_COL ? "
            <form method='post' action='vendors.php' style='display:inline' onsubmit='return confirm(\"Delete this vendor?\")'>
              <input type='hidden' name='csrf' value='".h($_SESSION['csrf'])."'>
              <input type='hidden' name='action' value='delete_vendor'>
              <input type='hidden' name='vendor_id' value='".h($r['vendor_id'])."'>
              <button class='btn danger'>Delete</button>
            </form>" : "<!-- Soft delete disabled: no is_active column -->")."
          </td>";
          echo "</tr>";
        }
      }
      ?>
    </tbody>
  </table>
</div>
</div>
</body>
</html>
