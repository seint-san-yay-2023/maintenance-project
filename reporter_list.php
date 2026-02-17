<?php
// reporter_list.php — reporters only (role='reporter'): list + edit/delete; no Add form
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'planner')) { header('Location: login.php'); exit; }
$planner_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Planner';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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
$DATE_COL=null; foreach(['created_at','created_on','registered_at','register_date','createdDate'] as $c){ if(in_array($c,$cols,true)){ $DATE_COL=$c; break; } }

$flash=''; $err='';

// Delete
if (($_POST['action'] ?? '') === 'delete') {
  try{
    $pdo->prepare("DELETE FROM users WHERE user_id=? AND ".($HAS_ROLE?"role='reporter'":"1=1"))->execute([(int)$_POST['id']]);
    $flash='Reporter deleted.';
  }catch(Throwable $e){ $err='Delete failed: '.$e->getMessage(); }
}

// Update (edit in modal-less quick form row)
if (($_POST['action'] ?? '') === 'update') {
  try{
    $id=(int)($_POST['id'] ?? 0);
    if ($id<=0) throw new Exception('Invalid ID.');
    $user=trim($_POST['username'] ?? '');
    if ($user==='') throw new Exception('Username is required.');

    $email=$HAS_EMAIL ? trim($_POST['email'] ?? '') : null;
    $phone=$HAS_PHONE ? trim($_POST['phone'] ?? '') : null;
    $first=$HAS_FIRST ? trim($_POST['first_name'] ?? '') : null;
    $last =$HAS_LAST  ? trim($_POST['last_name']  ?? '') : null;
    $full =$HAS_FULL  ? trim($_POST['full_name']  ?? '') : null;
    $pass=trim($_POST['password'] ?? '');
    $active=$HAS_ACTIVE ? (int)($_POST['is_active'] ?? 1) : null;

    $q=$pdo->prepare("SELECT COUNT(*) FROM users WHERE username=? AND user_id<>?");
    $q->execute([$user,$id]);
    if ((int)$q->fetchColumn()>0) throw new Exception('Username already exists.');

    $fields=['username'=>$user];
    if($HAS_EMAIL)  $fields['email']=$email;
    if($HAS_PHONE)  $fields['phone']=$phone;
    if($HAS_FIRST)  $fields['first_name']=$first;
    if($HAS_LAST)   $fields['last_name']=$last;
    if($HAS_FULL)   $fields['full_name']=$full ?: (($HAS_FIRST||$HAS_LAST)?trim(($first?:'').' '.($last?:'')):null);
    if($HAS_ACTIVE) $fields['is_active']=$active;

    if($pass!==''){
      if($HAS_PASSH) $fields['password_hash']=password_hash($pass,PASSWORD_DEFAULT);
      elseif($HAS_PASS) $fields['password']=$pass;
      else throw new Exception('No password column.');
    }

    $set=[];$vals=[];
    foreach($fields as $k=>$v){ $set[]="$k=?"; $vals[]=$v; }
    $vals[]=$id;
    $pdo->prepare("UPDATE users SET ".implode(', ',$set)." WHERE user_id=?")->execute($vals);
    $flash='Reporter updated.';
  }catch(Throwable $e){ $err=$e->getMessage(); }
}

// List
$rows=[];
$sel="user_id,username"; if($HAS_EMAIL)$sel.=",email"; if($HAS_PHONE)$sel.=",phone";
if($HAS_FIRST)$sel.=",first_name"; if($HAS_LAST)$sel.=",last_name"; if($HAS_FULL)$sel.=",full_name";
if($HAS_ACTIVE)$sel.=",COALESCE(is_active,1) AS is_active";
if($DATE_COL)$sel.=", $DATE_COL AS reg_date";
$sql="SELECT $sel FROM users WHERE ".($HAS_ROLE?"role='reporter'":"1=1")." ORDER BY username";
$rows=$pdo->query($sql)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><title>CMMS · Reporters</title><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{--bg:#fff;--line:#e9edf2;--muted:#667085}
  *{box-sizing:border-box} body{margin:0;background:#fff;color:#111;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto}
  a{color:#0d6efd;text-decoration:none}
  .wrap{max-width:1200px;margin:24px auto;padding:0 16px}
  header{display:flex;gap:12px;align-items:center;margin-bottom:16px}
  .back{border:1px solid var(--line);padding:8px 12px;border-radius:10px}
  .spacer{flex:1} .who{color:var(--muted);font-size:14px}
  .card{border:1px solid var(--line);border-radius:16px;background:#fff;padding:16px}
  table{width:100%;border-collapse:collapse} th,td{padding:12px 10px;border-bottom:1px solid var(--line);vertical-align:top}
  input,select{width:100%;padding:8px;border:1px solid var(--line);border-radius:8px}
  .rowform{display:grid;grid-template-columns:160px 160px 140px 140px 120px 140px 1fr;gap:8px}
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
    <h2 style="margin:0">CMMS · Reporters</h2>
    <div class="spacer"></div>
    <div class="who">Planner: <?= h($planner_name) ?> · <a href="logout.php">Logout</a></div>
  </header>

  <?php if($flash): ?><div class="card" style="border-color:#b7ebcd;background:#e8f6ee;margin-bottom:12px"><?= $flash ?></div><?php endif; ?>
  <?php if($err): ?><div class="card" style="border-color:#f3c2c8;background:#fde7ea;margin-bottom:12px"><?= h($err) ?></div><?php endif; ?>

  <div class="card">
    <h3 style="margin:0 0 8px">All Reporters</h3>

    <table>
      <thead>
        <tr>
          <th>Username</th>
          <th>Name</th>
          <?php if($HAS_EMAIL):?><th>Email</th><?php endif; ?>
          <?php if($HAS_PHONE):?><th>Phone</th><?php endif; ?>
          <th>Status</th>
          <th>Register Date</th>
          
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $u): ?>
          <tr>
            <form method="post">
              <input type="hidden" name="id" value="<?= (int)$u['user_id'] ?>">
              <td>
                <input name="username" value="<?= h($u['username']) ?>">
              </td>
              <td>
                <?php if($HAS_FULL): ?>
                  <input name="full_name" value="<?= h($u['full_name'] ?? '') ?>">
                <?php else: ?>
                  <div class="rowform" style="grid-template-columns:1fr 1fr;">
                    <?php if($HAS_FIRST): ?><input name="first_name" placeholder="First" value="<?= h($u['first_name'] ?? '') ?>"><?php endif; ?>
                    <?php if($HAS_LAST):  ?><input name="last_name"  placeholder="Last"  value="<?= h($u['last_name']  ?? '') ?>"><?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <?php if($HAS_EMAIL):?><td><input name="email" value="<?= h($u['email'] ?? '') ?>"></td><?php endif; ?>
              <?php if($HAS_PHONE):?><td><input name="phone" value="<?= h($u['phone'] ?? '') ?>"></td><?php endif; ?>
              <td>
                <?php if($HAS_ACTIVE): ?>
                  <select name="is_active">
                    <option value="1" <?= ((int)($u['is_active'] ?? 1)===1)?'selected':''; ?>>Active</option>
                    <option value="0" <?= ((int)($u['is_active'] ?? 1)===0)?'selected':''; ?>>Inactive</option>
                  </select>
                <?php else: ?>
                  Active
                <?php endif; ?>
              </td>
              <td><?= $DATE_COL && !empty($u['reg_date']) ? h(substr($u['reg_date'],0,10)) : '—' ?></td>
             
            </form>
          </tr>
        <?php endforeach; if(empty($rows)): ?>
          <tr><td colspan="<?= 4 + ($HAS_EMAIL?1:0) + ($HAS_PHONE?1:0) ?>" class="muted" style="text-align:center">No reporters found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
