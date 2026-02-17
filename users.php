<?php
// users.php — Planner hub: shows planner details + two buttons to lists
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'planner')) { header('Location: login.php'); exit; }

$planner_id   = (int)$_SESSION['user_id'];
$planner_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Planner';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Discover columns & load planner row
$cols = [];
try { $cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}

$HAS_FIRST=in_array('first_name',$cols,true);
$HAS_LAST =in_array('last_name',$cols,true);
$HAS_FULL =in_array('full_name',$cols,true);
$HAS_EMAIL=in_array('email',$cols,true);
$HAS_PHONE=in_array('phone',$cols,true);
$HAS_ROLE =in_array('role',$cols,true);

$sel = "user_id, username";
if ($HAS_FULL)  $sel .= ", full_name";
if ($HAS_FIRST) $sel .= ", first_name";
if ($HAS_LAST)  $sel .= ", last_name";
if ($HAS_EMAIL) $sel .= ", email";
if ($HAS_PHONE) $sel .= ", phone";
if ($HAS_ROLE)  $sel .= ", role";

$planner = null;
try {
  $st = $pdo->prepare("SELECT $sel FROM users WHERE user_id = ? LIMIT 1");
  $st->execute([$planner_id]);
  $planner = $st->fetch();
} catch (Throwable $e) {
  $planner = null;
}

// derive display name
$display_name = $planner_name;
if ($planner) {
  if ($HAS_FULL && !empty($planner['full_name'])) {
    $display_name = $planner['full_name'];
  } elseif ($HAS_FIRST || $HAS_LAST) {
    $n = trim(($planner['first_name'] ?? '').' '.($planner['last_name'] ?? ''));
    if ($n !== '') $display_name = $n;
  }
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><title>CMMS · Users</title><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{--bg:#fff;--line:#e9edf2;--muted:#667085}
  *{box-sizing:border-box}
  body{margin:0;background:#fff;color:#111;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto}
  a{color:#0d6efd;text-decoration:none}
  .wrap{max-width:1100px;margin:24px auto;padding:0 16px}
  header{display:flex;align-items:center;gap:12px;margin-bottom:16px}
  .back{border:1px solid var(--line);padding:8px 12px;border-radius:10px;color:white;background:blue}
  .spacer{flex:1}
  .who{color:var(--muted);font-size:14px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .card{border:1px solid var(--line);border-radius:16px;background:#fff;padding:20px}
  .btn{display:inline-block;padding:16px 22px;border-radius:12px;border:1px solid #0d6efd;background:#0d6efd;color:#fff;font-weight:600}
  .btn.secondary{border-color:#334155;background:#334155}
  .small{color:#64748b}
  .rows{display:grid;grid-template-columns:160px 1fr;row-gap:8px;column-gap:12px;margin-top:10px}
  .label{color:#6b7280}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <a class="back" href="planner_dashboard.php">← Back to Dashboard</a>
    <h2 style="margin:0">Manage User</h2>
    <div class="spacer"></div>
    <div class="who">Planner: <?= h($display_name) ?> · <a href="logout.php">Logout</a></div>
  </header>

  <!-- Planner box -->
  <div class="card" style="margin-bottom:16px">
    <h3 style="margin:0 0 6px">Planner</h3>
    <div class="small">Current signed-in planner’s details</div>
    <div class="rows">
      <div class="label">Name</div>
      <div>
        <?php
          echo h($display_name);
          if ($HAS_ROLE && !empty($planner['role'])) echo " · ".h(ucfirst($planner['role']));
        ?>
      </div>

      <div class="label">Username</div>
      <div><?= h($planner['username'] ?? ($_SESSION['username'] ?? '')) ?></div>

      <?php if ($HAS_EMAIL): ?>
        <div class="label">Email</div>
        <div><?= !empty($planner['email']) ? h($planner['email']) : '—' ?></div>
      <?php endif; ?>

      <?php if ($HAS_PHONE): ?>
        <div class="label">Phone</div>
        <div><?= !empty($planner['phone']) ? h($planner['phone']) : '—' ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Two boxes -->
  <div class="grid">
    <div class="card">
      <h3 style="margin:0 0 4px">Reporter</h3>
      <p class="small" style="margin:.25rem 0 0">Manage reporter accounts (students & professors). List, edit, delete.</p>
      <div style="margin-top:12px"><a class="btn" href="reporter_list.php">Open Reporter List</a></div>
    </div>
    <div class="card">
      <h3 style="margin:0 0 4px">Maintenance Staff</h3>
      <p class="small" style="margin:.25rem 0 0">Approve new staff, edit details, deactivate or delete.</p>
      <div style="margin-top:12px"><a class="btn secondary" href="staff_list.php">Open Staff List</a></div>
    </div>
  </div>
</div>
</body>
</html>
