<?php
// staff_list.php — technicians only (role='technician')
// Planner can approve, edit, delete. Shows register date if a column exists.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'planner')) { header('Location: login.php'); exit; }
$planner_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Planner';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Discover columns
$cols=[]; try{$cols=$pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable $e){}
$HAS_FIRST=in_array('first_name',$cols,true);
$HAS_LAST=in_array('last_name',$cols,true);
$HAS_FULL=in_array('full_name',$cols,true);
$HAS_EMAIL=in_array('email',$cols,true);
$HAS_PHONE=in_array('phone',$cols,true);
$HAS_ACTIVE=in_array('is_active',$cols,true);
$HAS_ROLE=in_array('role',$cols,true);
$HAS_PASSH=in_array('password_hash',$cols,true);
$HAS_PASS=in_array('password',$cols,true);
$DATE_COL = null;
foreach (['created_at','created_on','registered_at','register_date','createdDate'] as $c) {
  if (in_array($c,$cols,true)) { $DATE_COL = $c; break; }
}

$flash=''; $err='';

// Actions
if (($_POST['action'] ?? '') === 'approve') {
  try{
    if(!$HAS_ACTIVE) throw new Exception('is_active column missing.');
    $pdo->prepare("UPDATE users SET is_active=1 WHERE user_id=?")->execute([(int)$_POST['id']]);
    $flash='Staff approved.';
  }catch(Throwable $e){ $err=$e->getMessage(); }
}
if (($_POST['action'] ?? '') === 'deactivate') {
  try{
    if(!$HAS_ACTIVE) throw new Exception('is_active column missing.');
    $pdo->prepare("UPDATE users SET is_active=0 WHERE user_id=?")->execute([(int)$_POST['id']]);
    $flash='Staff deactivated.';
  }catch(Throwable $e){ $err=$e->getMessage(); }
}
if (($_POST['action'] ?? '') === 'delete') {
  try{
    $pdo->prepare("DELETE FROM users WHERE user_id=? AND ".($HAS_ROLE?"role='technician'":"1=1"))->execute([(int)$_POST['id']]);
    $flash='Staff deleted.';
  }catch(Throwable $e){ $err='Delete failed: '.$e->getMessage(); }
}
if (($_POST['action'] ?? '') === 'save') { // create/update
  try{
    $id   = ($_POST['id'] ?? '') !== '' ? (int)$_POST['id'] : null;
    $user = trim($_POST['username'] ?? '');
    $email= $HAS_EMAIL ? trim($_POST['email'] ?? '') : null;
    $phone= $HAS_PHONE ? trim($_POST['phone'] ?? '') : null;
    $first= $HAS_FIRST ? trim($_POST['first_name'] ?? '') : null;
    $last = $HAS_LAST  ? trim($_POST['last_name']  ?? '') : null;
    $full = $HAS_FULL  ? trim($_POST['full_name']  ?? '') : null;
    $pass = trim($_POST['password'] ?? '');
    $active = $HAS_ACTIVE ? (int)($_POST['is_active'] ?? 0) : null; // staff default pending

    if ($user==='') throw new Exception('Username is required.');

    if ($id){
      $q=$pdo->prepare("SELECT COUNT(*) FROM users WHERE username=? AND user_id<>?");
      $q->execute([$user,$id]);
    } else {
      $q=$pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
      $q->execute([$user]);
    }
    if ((int)$q->fetchColumn() > 0) throw new Exception('Username already exists.');

    $fields=['username'=>$user];
    if($HAS_ROLE)   $fields['role']='technician';
    if($HAS_EMAIL)  $fields['email']=$email;
    if($HAS_PHONE)  $fields['phone']=$phone;
    if($HAS_FIRST)  $fields['first_name']=$first;
    if($HAS_LAST)   $fields['last_name']=$last;
    if($HAS_FULL)   $fields['full_name']=$full ?: (($HAS_FIRST||$HAS_LAST)?trim(($first?:'').' '.($last?:'')):null);
    if($HAS_ACTIVE) $fields['is_active']=$active;

    if($pass!==''){
      if($HAS_PASSH) $fields['password_hash']=password_hash($pass, PASSWORD_DEFAULT);
      elseif($HAS_PASS) $fields['password']=$pass;
      else throw new Exception('No password column to store password.');
    }

    if ($id){
      $set=[];$vals=[];
      foreach($fields as $k=>$v){ $set[]="$k=?"; $vals[]=$v; }
      $vals[]=$id;
      $pdo->prepare("UPDATE users SET ".implode(', ',$set)." WHERE user_id=?")->execute($vals);
      $flash='Staff updated.';
    } else {
      if($HAS_ACTIVE && !isset($fields['is_active'])) $fields['is_active']=0; // default pending
      if($HAS_ROLE   && !isset($fields['role']))      $fields['role']='technician';
      $colsSql=implode(', ',array_keys($fields));
      $qs=implode(', ',array_fill(0,count($fields),'?'));
      $pdo->prepare("INSERT INTO users ($colsSql) VALUES ($qs)")->execute(array_values($fields));
      $flash='Staff created (pending).';
    }
  }catch(Throwable $e){ $err=$e->getMessage(); }
}

// Edit mode load
$edit=null;
if (isset($_GET['edit'])) {
  $id=(int)$_GET['edit'];
  $sel="user_id,username"; if($HAS_EMAIL)$sel.=",email"; if($HAS_PHONE)$sel.=",phone";
  if($HAS_FIRST)$sel.=",first_name"; if($HAS_LAST)$sel.=",last_name"; if($HAS_FULL)$sel.=",full_name";
  if($HAS_ACTIVE)$sel.=",COALESCE(is_active,0) AS is_active";
  $s=$pdo->prepare("SELECT $sel FROM users WHERE user_id=? AND ".($HAS_ROLE?"role='technician'":"1=1")." LIMIT 1");
  $s->execute([$id]); $edit=$s->fetch();
}

// Data: pending + all
$pending=[];
if ($HAS_ACTIVE) {
  $sel="user_id,username"; if($HAS_EMAIL)$sel.=",email"; if($HAS_FIRST)$sel.=",first_name"; if($HAS_LAST)$sel.=",last_name"; if($HAS_FULL)$sel.=",full_name";
  if($DATE_COL) $sel.=", $DATE_COL AS reg_date";
  $sql="SELECT $sel FROM users WHERE ".($HAS_ROLE?"role='technician' AND ":"")."COALESCE(is_active,0)=0 ORDER BY username";
  $pending=$pdo->query($sql)->fetchAll();
}
$all=[];
$sel="user_id,username"; if($HAS_EMAIL)$sel.=",email"; if($HAS_PHONE)$sel.=",phone";
if($HAS_FIRST)$sel.=",first_name"; if($HAS_LAST)$sel.=",last_name"; if($HAS_FULL)$sel.=",full_name";
if($HAS_ACTIVE)$sel.=",COALESCE(is_active,0) AS is_active";
if($DATE_COL) $sel.=", $DATE_COL AS reg_date";
$sql="SELECT $sel FROM users WHERE ".($HAS_ROLE?"role='technician'":"1=1")." ORDER BY username";
$all=$pdo->query($sql)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><title>CMMS · Staff</title><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{--bg:#fff;--line:#e9edf2;--muted:#667085}
  *{box-sizing:border-box} body{margin:0;background:#fff;color:#111;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto}
  a{color:#0d6efd;text-decoration:none}
  .wrap{max-width:1200px;margin:24px auto;padding:0 16px}
  header{display:flex;gap:12px;align-items:center;margin-bottom:16px}
  .back{border:1px solid var(--line);padding:8px 12px;border-radius:10px}
  .spacer{flex:1} .who{color:var(--muted);font-size:14px}
  .card{border:1px solid var(--line);border-radius:16px;background:#fff;padding:16px}
  .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
  input,select{width:100%;padding:10px;border:1px solid var(--line);border-radius:10px}
  label{font-size:14px;color:#606e7b}
  table{width:100%;border-collapse:collapse} th,td{padding:12px 10px;border-bottom:1px solid var(--line);vertical-align:top}
  .btn{border:1px solid #d0d7e2;background:#f8fafc;border-radius:10px;padding:8px 12px;cursor:pointer}
  .btn.primary{background:#0d6efd;border-color:#0d6efd;color:#fff}
  .btn.danger{background:#ef4444;border-color:#ef4444;color:#fff}
  .right{text-align:right} .muted{color:#64748b}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <a class="back" href="users.php">← Back</a>
    <h2 style="margin:0">CMMS · Maintenance Staff</h2>
    <div class="spacer"></div>
    <div class="who">Planner: <?= h($planner_name) ?> · <a href="logout.php">Logout</a></div>
  </header>

  <?php if($flash): ?><div class="card" style="border-color:#b7ebcd;background:#e8f6ee;margin-bottom:12px"><?= $flash ?></div><?php endif; ?>
  <?php if($err): ?><div class="card" style="border-color:#f3c2c8;background:#fde7ea;margin-bottom:12px"><?= h($err) ?></div><?php endif; ?>

  <!-- Pending approvals -->
  <div class="card" style="margin-bottom:16px">
    <h3 style="margin:0 0 8px">Pending Approvals</h3>
    <table>
      <thead><tr><th>Username</th><th>Name</th><?php if($HAS_EMAIL):?><th>Email</th><?php endif; ?><th>Register Date</th><th class="right">Action</th></tr></thead>
      <tbody>
        <?php foreach($pending as $p): ?>
          <tr>
            <td><?= h($p['username']) ?></td>
            <td><?php
              $nm=''; if($HAS_FULL && !empty($p['full_name'])) $nm=$p['full_name'];
              elseif($HAS_FIRST||$HAS_LAST) $nm=trim(($p['first_name']??'').' '.($p['last_name']??''));
              echo $nm!==''?h($nm):'<span class="muted">—</span>';
            ?></td>
            <?php if($HAS_EMAIL):?><td><?= !empty($p['email'])?h($p['email']):'<span class="muted">—</span>' ?></td><?php endif; ?>
            <td><?= $DATE_COL && !empty($p['reg_date']) ? h(substr($p['reg_date'],0,10)) : '—' ?></td>
            <td class="right">
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$p['user_id'] ?>">
                <button class="btn primary">Approve</button>
              </form>
            </td>
          </tr>
        <?php endforeach; if(empty($pending)): ?>
          <tr><td colspan="<?= 3 + ($HAS_EMAIL?1:0) + 1 ?>" class="muted" style="text-align:center">No pending staff.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Create / Edit form -->
  <div class="card" style="margin-bottom:16px">
    <h3 style="margin:0 0 8px"><?= $edit?'Edit Staff':'Add Staff' ?></h3>
    <form method="post" class="row">
      <input type="hidden" name="action" value="save"><?php if($edit):?><input type="hidden" name="id" value="<?= (int)$edit['user_id'] ?>"><?php endif; ?>
      <div style="min-width:240px;flex:1"><label>Username *</label><input name="username" required value="<?= h($edit['username'] ?? '') ?>"></div>
      <?php if($HAS_EMAIL): ?><div style="min-width:240px;flex:1"><label>Email</label><input name="email" value="<?= h($edit['email'] ?? '') ?>"></div><?php endif; ?>
      <?php if($HAS_PHONE): ?><div style="min-width:200px"><label>Phone</label><input name="phone" value="<?= h($edit['phone'] ?? '') ?>"></div><?php endif; ?>
      <?php if($HAS_FIRST): ?><div style="min-width:200px"><label>First Name</label><input name="first_name" value="<?= h($edit['first_name'] ?? '') ?>"></div><?php endif; ?>
      <?php if($HAS_LAST):  ?><div style="min-width:200px"><label>Last Name</label><input name="last_name"  value="<?= h($edit['last_name']  ?? '') ?>"></div><?php endif; ?>
      <?php if($HAS_FULL):  ?><div style="min-width:260px;flex:1"><label>Full Name</label><input name="full_name" value="<?= h($edit['full_name'] ?? '') ?>"></div><?php endif; ?>
      <div style="min-width:220px"><label>Password</label><input name="password" type="password" placeholder="<?= $HAS_PASSH?'Will be securely hashed':'Stored as-is' ?>"></div>
      <?php if($HAS_ACTIVE): ?><div style="min-width:180px"><label>Status</label>
        <select name="is_active">
          <option value="0" <?= isset($edit['is_active']) && (int)$edit['is_active']===0?'selected':''; ?>>Pending</option>
          <option value="1" <?= isset($edit['is_active']) && (int)$edit['is_active']===1?'selected':''; ?>>Active</option>
        </select>
      </div><?php endif; ?>
      <div style="flex:1;text-align:right"><label>&nbsp;</label><div>
        <button class="btn primary"><?= $edit?'Update':'Create' ?></button>
        <?php if($edit): ?><a class="btn" href="staff_list.php">Cancel</a><?php endif; ?>
      </div></div>
    </form>
  </div>

  <!-- All staff -->
  <div class="card">
    <h3 style="margin:0 0 8px">All Staff</h3>
    <table>
      <thead><tr><th>Username</th><th>Name</th><?php if($HAS_EMAIL):?><th>Email</th><?php endif; ?><?php if($HAS_PHONE):?><th>Phone</th><?php endif; ?><th>Status</th><th>Register Date</th><th class="right">Actions</th></tr></thead>
      <tbody>
        <?php foreach($all as $u): ?>
          <tr>
            <td><?= h($u['username']) ?></td>
            <td><?php
              $nm=''; if($HAS_FULL && !empty($u['full_name'])) $nm=$u['full_name'];
              elseif($HAS_FIRST||$HAS_LAST) $nm=trim(($u['first_name']??'').' '.($u['last_name']??''));
              echo $nm!==''?h($nm):'<span class="muted">—</span>';
            ?></td>
            <?php if($HAS_EMAIL):?><td><?= !empty($u['email'])?h($u['email']):'<span class="muted">—</span>' ?></td><?php endif; ?>
            <?php if($HAS_PHONE):?><td><?= !empty($u['phone'])?h($u['phone']):'<span class="muted">—</span>' ?></td><?php endif; ?>
            <td><?= ((int)($u['is_active'] ?? 0)===1)?'Active':'Pending' ?></td>
            <td><?= $DATE_COL && !empty($u['reg_date']) ? h(substr($u['reg_date'],0,10)) : '—' ?></td>
            <td class="right">
              <a class="btn" href="staff_list.php?edit=<?= (int)$u['user_id'] ?>">Edit</a>
              <?php if($HAS_ACTIVE): ?>
                <?php if((int)($u['is_active'] ?? 0)===1): ?>
                  <form method="post" style="display:inline"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= (int)$u['user_id'] ?>"><button class="btn">Deactivate</button></form>
                <?php else: ?>
                  <form method="post" style="display:inline"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$u['user_id'] ?>"><button class="btn primary">Approve</button></form>
                <?php endif; ?>
              <?php endif; ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this staff?');">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['user_id'] ?>">
                <button class="btn danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; if(empty($all)): ?>
          <tr><td colspan="<?= 4 + ($HAS_EMAIL?1:0) + ($HAS_PHONE?1:0) ?>" class="muted" style="text-align:center">No staff found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
