<?php
/* ===================== Notification Details (Planner) ===================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

/* ---------- Convert mysqli to PDO if needed ---------- */
if (!isset($pdo)) {
  if (isset($connect) && $connect instanceof mysqli) {
    $host = 'localhost';
    $dbname = $connect->query("SELECT DATABASE()")->fetch_row()[0];
    $username = 'root';
    $password = '';
    
    try {
      $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
      );
    } catch (PDOException $e) {
      die("Database connection error: " . $e->getMessage());
    }
  } else {
    die("Database connection missing.");
  }
}

/* ---------- Auth: planner only ---------- */
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'planner')) {
  header('Location: ../login.php'); exit;
}

/* ---------- Helpers ---------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ---------- Get notification ID ---------- */
$notif_id = (int)($_GET['id'] ?? 0);
if ($notif_id <= 0) {
  header('Location: notifications.php');
  exit;
}

/* ---------- Fetch notification details ---------- */
try {
  $stmt = $pdo->prepare("
    SELECT 
      n.*,
      e.equipment_name,
      e.equipment_code,
      f.floc_name,
      f.floc_code,
      u.username as reporter_username,
      u.email as reporter_user_email
    FROM notification n
    LEFT JOIN equipment e ON e.equipment_id = n.equipment_id
    LEFT JOIN functional_location f ON f.floc_id = n.floc_id
    LEFT JOIN users u ON u.user_id = n.created_by_user_id
    WHERE n.notification_id = ?
  ");
  $stmt->execute([$notif_id]);
  $notification = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$notification) {
    header('Location: notifications.php');
    exit;
  }
} catch (Throwable $e) {
  die("Error loading notification: " . $e->getMessage());
}

/* ---------- Badge helpers ---------- */
function priorityBadge($p){
  $p = strtoupper((string)$p);
  $badges = [
    'URGENT' => '<span class="badge badge-urgent">URGENT</span>',
    'HIGH' => '<span class="badge badge-high">HIGH</span>',
    'MEDIUM' => '<span class="badge badge-medium">MEDIUM</span>',
    'LOW' => '<span class="badge badge-low">LOW</span>',
  ];
  return $badges[$p] ?? h($p);
}

function statusBadge($s){
  $s = strtoupper((string)$s);
  $badges = [
    'NEW' => '<span class="badge badge-new">NEW</span>',
    'SCREENED' => '<span class="badge badge-screened">SCREENED</span>',
    'APPROVED' => '<span class="badge badge-approved">APPROVED</span>',
    'REJECTED' => '<span class="badge badge-rejected">REJECTED</span>',
    'CLOSED' => '<span class="badge badge-closed">CLOSED</span>',
  ];
  return $badges[$s] ?? '<span class="badge badge-default">'.h($s).'</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notification Details - CMMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
  --bg:#f9fafb;
  --text:#0f172a;
  --muted:#64748b;
  --line:#e2e8f0;
  --primary:#2563eb;
  --success:#10b981;
  --danger:#ef4444;
}

*{box-sizing:border-box;margin:0;padding:0}

body{
  margin:0;
  background:var(--bg);
  color:var(--text);
  font:15px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
}

a{color:var(--primary);text-decoration:none}
a:hover{text-decoration:underline}

header{
  display:flex;
  align-items:center;
  gap:16px;
  padding:14px 18px;
  background:#fff;
  border-bottom:2px solid var(--line);
  box-shadow:0 1px 3px rgba(0,0,0,0.05);
}

.back{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:8px 16px;
  background:#fff;
  border:1px solid var(--line);
  border-radius:8px;
  font-weight:600;
  color:var(--text);
}

.back:hover{background:#f1f5f9;text-decoration:none}

header h1{font-size:24px;font-weight:700}

.wrap{max-width:1000px;margin:20px auto;padding:0 20px}

.card{
  background:#fff;
  border:1px solid var(--line);
  border-radius:12px;
  padding:24px;
  margin-bottom:20px;
  box-shadow:0 1px 3px rgba(0,0,0,0.05);
}

.card h2{
  font-size:18px;
  font-weight:700;
  margin-bottom:16px;
  padding-bottom:12px;
  border-bottom:2px solid var(--line);
}

.detail-row{
  display:flex;
  padding:12px 0;
  border-bottom:1px solid var(--line);
}

.detail-row:last-child{border-bottom:none}

.detail-label{
  font-weight:600;
  color:var(--muted);
  width:200px;
  flex-shrink:0;
}

.detail-value{
  flex:1;
  color:var(--text);
}

.badge{
  display:inline-block;
  padding:4px 10px;
  border-radius:12px;
  font-size:12px;
  font-weight:600;
}

.badge-urgent{background:#fee2e2;color:#991b1b}
.badge-high{background:#fed7aa;color:#9a3412}
.badge-medium{background:#dbeafe;color:#1e40af}
.badge-low{background:#d1fae5;color:#065f46}
.badge-new{background:#f3f4f6;color:#374151}
.badge-screened{background:#dbeafe;color:#1e40af}
.badge-approved{background:#d1fae5;color:#065f46}
.badge-rejected{background:#fee2e2;color:#991b1b}
.badge-closed{background:#1f2937;color:#fff}
.badge-default{background:#f3f4f6;color:#374151}

.description-box{
  background:#f8fafc;
  padding:16px;
  border-radius:8px;
  margin-top:8px;
  line-height:1.8;
}

.mono{
  font-family:ui-monospace,'Cascadia Code',monospace;
  font-size:13px;
  background:#f1f5f9;
  padding:2px 6px;
  border-radius:4px;
}

.btn-action{
  display:inline-block;
  padding:12px 24px;
  border-radius:8px;
  font-weight:600;
  text-decoration:none;
  transition:all 0.2s;
  border:none;
  cursor:pointer;
  font-size:15px;
}

.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:#1d4ed8;text-decoration:none}

.btn-success{background:var(--success);color:#fff}
.btn-success:hover{background:#059669;text-decoration:none}

.btn-danger{background:var(--danger);color:#fff}
.btn-danger:hover{background:#dc2626;text-decoration:none}
</style>
</head>
<body>

<header>
  <a href="notifications.php" class="back">← Back to Notifications</a>
  <h1>📄 Notification Details</h1>
</header>

<div class="wrap">
  
  <div class="card">
    <h2>Notification Information</h2>
    
    <div class="detail-row">
      <div class="detail-label">Notification No:</div>
      <div class="detail-value"><strong class="mono"><?= h($notification['notif_no']) ?></strong></div>
    </div>
    
    <div class="detail-row">
      <div class="detail-label">Status:</div>
      <div class="detail-value"><?= statusBadge($notification['status']) ?></div>
    </div>
    
    <div class="detail-row">
      <div class="detail-label">Priority:</div>
      <div class="detail-value"><?= priorityBadge($notification['priority']) ?></div>
    </div>
    
    <div class="detail-row">
      <div class="detail-label">Reported At:</div>
      <div class="detail-value"><?= $notification['reported_at'] ? date('F d, Y H:i', strtotime($notification['reported_at'])) : '—' ?></div>
    </div>
    
    <div class="detail-row">
      <div class="detail-label">Reporter Name:</div>
      <div class="detail-value"><?= h($notification['reporter_name'] ?: '—') ?></div>
    </div>
    
    <div class="detail-row">
      <div class="detail-label">Reporter Email:</div>
      <div class="detail-value"><?= h($notification['reporter_email'] ?: '—') ?></div>
    </div>
    
    <?php if ($notification['reporter_username']): ?>
    <div class="detail-row">
      <div class="detail-label">Reporter Username:</div>
      <div class="detail-value"><?= h($notification['reporter_username']) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Equipment & Location</h2>
    
    <div class="detail-row">
      <div class="detail-label">Equipment:</div>
      <div class="detail-value">
        <?php if ($notification['equipment_name']): ?>
          <strong><?= h($notification['equipment_name']) ?></strong>
          <?php if ($notification['equipment_code']): ?>
            <span class="mono"><?= h($notification['equipment_code']) ?></span>
          <?php endif; ?>
        <?php else: ?>
          —
        <?php endif; ?>
      </div>
    </div>
    
    <div class="detail-row">
      <div class="detail-label">Functional Location:</div>
      <div class="detail-value">
        <?php if ($notification['floc_name']): ?>
          <strong><?= h($notification['floc_name']) ?></strong>
          <?php if ($notification['floc_code']): ?>
            <span class="mono"><?= h($notification['floc_code']) ?></span>
          <?php endif; ?>
        <?php else: ?>
          —
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>Problem Description</h2>
    <div class="description-box">
      <?= nl2br(h($notification['description'] ?: 'No description provided.')) ?>
    </div>
  </div>

  <?php
  $status = strtoupper($notification['status']);
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  $CSRF = $_SESSION['csrf'];
  ?>
  
  <?php if ($status === 'NEW'): ?>
    <div class="card">
      <h2>Actions</h2>
      <form method="post" action="notifications.php" style="display:inline">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="action" value="screen">
        <input type="hidden" name="notif_id" value="<?= $notif_id ?>">
        <button type="submit" class="btn-action btn-primary" onclick="return confirm('Mark as SCREENED?')">
          👁 Screen This Notification
        </button>
      </form>
    </div>
  
  <?php elseif ($status === 'SCREENED'): ?>
    <div class="card">
      <h2>Actions</h2>
      <form method="post" action="notifications.php" style="display:inline;margin-right:10px">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="action" value="approve">
        <input type="hidden" name="notif_id" value="<?= $notif_id ?>">
        <button type="submit" class="btn-action btn-success" onclick="return confirm('Approve this notification?')">
          ✓ Approve
        </button>
      </form>
      <form method="post" action="notifications.php" style="display:inline">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="notif_id" value="<?= $notif_id ?>">
        <button type="submit" class="btn-action btn-danger" onclick="return confirm('Reject this notification?')">
          ✕ Reject
        </button>
      </form>
    </div>
  
  <?php elseif ($status === 'APPROVED'): ?>
    <div class="card">
      <h2>Actions</h2>
      <a href="create_work_order.php?notification_id=<?= $notif_id ?>" class="btn-action btn-primary">
        🔧 Create Work Order
      </a>
    </div>
  <?php endif; ?>

</div>

</body>
</html>