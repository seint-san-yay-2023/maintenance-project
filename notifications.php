<?php
/* ===================== Notifications (Planner) ===================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

/* ---------- Convert mysqli to PDO if needed ---------- */
if (!isset($pdo)) {
  if (isset($connect) && $connect instanceof mysqli) {
    // Get database info from mysqli connection
    $host = 'localhost';
    $dbname = $connect->query("SELECT DATABASE()")->fetch_row()[0];
    $username = 'root';  // Default XAMPP username
    $password = '';      // Default XAMPP password (empty)
    
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
    die("Database connection missing. Please check config.php");
  }
}

/* ---------- Auth: planner only ---------- */
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'planner')) {
  header('Location: ../login.php'); 
  exit;
}
$planner_id = (int)($_SESSION['user_id'] ?? 0);

/* ---------- Helpers ---------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* ---------- Handle Status Updates ---------- */
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $csrf = $_POST['csrf'] ?? '';
  if ($csrf !== $CSRF) {
    $message = 'Invalid security token';
    $messageType = 'error';
  } else {
    $action = $_POST['action'];
    $notif_id = (int)($_POST['notif_id'] ?? 0);
    
    try {
      if ($action === 'screen') {
        $stmt = $pdo->prepare("UPDATE notification SET status = 'SCREENED' WHERE notification_id = ? AND status = 'NEW'");
        $stmt->execute([$notif_id]);
        $message = 'Notification screened successfully';
        $messageType = 'success';
        
      } elseif ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE notification SET status = 'APPROVED' WHERE notification_id = ? AND status = 'SCREENED'");
        $stmt->execute([$notif_id]);
        $message = 'Notification approved successfully';
        $messageType = 'success';
        
      } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE notification SET status = 'REJECTED' WHERE notification_id = ? AND status = 'SCREENED'");
        $stmt->execute([$notif_id]);
        $message = 'Notification rejected';
        $messageType = 'warning';
      }
    } catch (Throwable $e) {
      $message = 'Error updating notification: ' . $e->getMessage();
      $messageType = 'error';
    }
  }
}

/* ---------- Filters ---------- */
$tab = strtolower(trim($_GET['tab'] ?? 'all'));
if (!in_array($tab, ['all','reports','completed'])) $tab = 'all';

$notifStatuses = ['ALL','NEW','SCREENED','APPROVED','REJECTED','CLOSED'];
$selected_status = strtoupper(trim($_GET['status'] ?? 'ALL'));
if (!in_array($selected_status, $notifStatuses)) $selected_status = 'ALL';

$selected_date = trim($_GET['date'] ?? '');

/* ---------- Counters ---------- */
try {
  $count_problem_reports = (int)$pdo->query("
    SELECT COUNT(*)
    FROM notification n
    LEFT JOIN users u ON u.user_id = n.created_by_user_id
    WHERE (u.role IS NULL OR u.role <> 'technician')
  ")->fetchColumn();

  $count_completed = (int)$pdo->query("
    SELECT COUNT(*) FROM work_order WHERE status='COMPLETED'
  ")->fetchColumn();

  $count_all_notif = $count_problem_reports + $count_completed;
} catch (Throwable $e) {
  $count_all_notif = $count_problem_reports = $count_completed = 0;
}

/* ---------- Data query ---------- */
$params = []; 

if ($tab === 'all') {
  $sql = "
    (
      SELECT
        n.notification_id            AS row_id,
        n.notif_no                   AS notif_no,
        n.reported_at                AS reported_at,
        n.status                     AS status,
        n.priority                   AS priority,
        n.reporter_name              AS reporter_name,
        n.reporter_email             AS reporter_email,
        n.created_by_user_id         AS created_by_user_id,
        n.description                AS description,
        e.equipment_name             AS equipment_name,
        f.floc_name                  AS floc_name,
        CASE WHEN (u.role IS NULL OR u.role <> 'technician')
             THEN 'problem' ELSE 'technician_note' END AS row_type
      FROM notification n
      LEFT JOIN equipment e           ON e.equipment_id = n.equipment_id
      LEFT JOIN functional_location f ON f.floc_id      = n.floc_id
      LEFT JOIN users u               ON u.user_id      = n.created_by_user_id
      WHERE 1=1
  ";
  
  if ($selected_status !== 'ALL') {
    $sql .= " AND n.status = ? ";
    $params[] = $selected_status;
  }
  if ($selected_date !== '') {
    $sql .= " AND DATE(n.reported_at) = ? ";
    $params[] = $selected_date;
  }
  
  $sql .= "
    )
    UNION ALL
    (
      SELECT
        w.work_order_id              AS row_id,
        w.wo_no                      AS notif_no,
        w.actual_end                 AS reported_at,
        w.status                     AS status,
        w.priority                   AS priority,
        NULL                         AS reporter_name,
        NULL                         AS reporter_email,
        NULL                         AS created_by_user_id,
        COALESCE(w.resolution_note, w.problem_note, '') AS description,
        e.equipment_name             AS equipment_name,
        f.floc_name                  AS floc_name,
        'maintenance'                AS row_type
      FROM work_order w
      LEFT JOIN equipment e           ON e.equipment_id = w.equipment_id
      LEFT JOIN functional_location f ON f.floc_id      = w.floc_id
      WHERE w.status = 'COMPLETED'
  ";
  
  if ($selected_date !== '') {
    $sql .= " AND DATE(w.actual_end) = ? ";
    $params[] = $selected_date;
  }
  
  $sql .= "
    )
    ORDER BY reported_at DESC
  ";
  
} elseif ($tab === 'completed') {
  $sql = "
    SELECT
      w.work_order_id              AS row_id,
      w.wo_no                      AS notif_no,
      w.actual_end                 AS reported_at,
      w.status                     AS status,
      w.priority                   AS priority,
      NULL                         AS reporter_name,
      NULL                         AS reporter_email,
      NULL                         AS created_by_user_id,
      COALESCE(w.resolution_note, w.problem_note, '') AS description,
      e.equipment_name             AS equipment_name,
      f.floc_name                  AS floc_name,
      'maintenance'                AS row_type
    FROM work_order w
    LEFT JOIN equipment e           ON e.equipment_id = w.equipment_id
    LEFT JOIN functional_location f ON f.floc_id      = w.floc_id
    WHERE w.status = 'COMPLETED'
  ";
  if ($selected_date !== '') {
    $sql .= " AND DATE(w.actual_end) = ? ";
    $params[] = $selected_date;
  }
  $sql .= " ORDER BY w.actual_end DESC, w.work_order_id DESC";
  
} else {
  $sql = "
    SELECT
      n.notification_id            AS row_id,
      n.notif_no                   AS notif_no,
      n.reported_at                AS reported_at,
      n.status                     AS status,
      n.priority                   AS priority,
      n.reporter_name              AS reporter_name,
      n.reporter_email             AS reporter_email,
      n.created_by_user_id         AS created_by_user_id,
      n.description                AS description,
      e.equipment_name             AS equipment_name,
      f.floc_name                  AS floc_name,
      CASE WHEN (u.role IS NULL OR u.role <> 'technician')
           THEN 'problem' ELSE 'technician_note' END AS row_type
    FROM notification n
    LEFT JOIN equipment e           ON e.equipment_id = n.equipment_id
    LEFT JOIN functional_location f ON f.floc_id      = n.floc_id
    LEFT JOIN users u               ON u.user_id      = n.created_by_user_id
    WHERE (u.role IS NULL OR u.role <> 'technician')
  ";
  
  if ($selected_status !== 'ALL') {
    $sql .= " AND n.status = ? ";
    $params[] = $selected_status;
  }
  if ($selected_date !== '') {
    $sql .= " AND DATE(n.reported_at) = ? ";
    $params[] = $selected_date;
  }
  $sql .= " ORDER BY n.reported_at DESC, n.notification_id DESC";
}

$rows = [];
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $rows = [];
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
    'CREATED' => '<span class="badge badge-created">CREATED</span>',
    'RELEASED' => '<span class="badge badge-released">RELEASED</span>',
    'IN_PROGRESS' => '<span class="badge badge-progress">IN PROGRESS</span>',
    'WAITING_PARTS' => '<span class="badge badge-waiting">WAITING PARTS</span>',
    'COMPLETED' => '<span class="badge badge-completed">COMPLETED</span>',
    'CANCELLED' => '<span class="badge badge-cancelled">CANCELLED</span>',
  ];
  return $badges[$s] ?? '<span class="badge badge-default">'.h($s).'</span>';
}

/* ---------- Planner name ---------- */
$plannerName = 'Planner';
try {
  $st = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
  $st->execute([$planner_id]);
  if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
    foreach (['full_name','first_name','name','display_name','username','email'] as $k) {
      if (!empty($u[$k])) { $plannerName = $u[$k]; break; }
    }
  }
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications - CMMS</title>
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
  --warning:#f59e0b;
}

*{box-sizing:border-box;margin:0;padding:0}

body{
  margin:0;
  background:var(--bg);
  color:var(--text);
  font:15px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,sans-serif;
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
  position:sticky;
  top:0;
  z-index:100;
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

.spacer{flex:1}

.who{color:var(--muted);font-size:14px}

.wrap{max-width:1400px;margin:0 auto;padding:20px}

.alert{
  padding:14px 18px;
  border-radius:8px;
  margin-bottom:20px;
  font-weight:500;
  display:flex;
  align-items:center;
  gap:10px;
}

.alert-success{background:#d1fae5;color:#065f46;border:1px solid #10b981}
.alert-warning{background:#fed7aa;color:#9a3412;border:1px solid #f59e0b}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #ef4444}

.toggle-bar{
  display:flex;
  gap:8px;
  margin-bottom:20px;
  flex-wrap:wrap;
}

.toggle-btn{
  flex:1;
  min-width:200px;
  padding:14px 20px;
  background:#fff;
  border:2px solid var(--line);
  border-radius:10px;
  font-size:15px;
  font-weight:600;
  color:var(--muted);
  cursor:pointer;
  transition:all 0.2s;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  text-align:center;
  text-decoration:none;
}

.toggle-btn:hover{
  border-color:#cbd5e1;
  background:#f8fafc;
  text-decoration:none;
}

.toggle-btn.active{
  background:var(--primary);
  color:#fff;
  border-color:var(--primary);
  box-shadow:0 4px 6px rgba(37,99,235,0.2);
}

.toggle-count{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:28px;
  height:28px;
  padding:0 10px;
  background:rgba(0,0,0,0.1);
  border-radius:999px;
  font-size:13px;
  font-weight:700;
}

.toggle-btn.active .toggle-count{
  background:rgba(255,255,255,0.25);
  color:#fff;
}

.card{
  background:#fff;
  border:1px solid var(--line);
  border-radius:12px;
  margin-bottom:20px;
  box-shadow:0 1px 3px rgba(0,0,0,0.05);
}

.toolbar{
  display:flex;
  gap:12px;
  align-items:flex-end;
  flex-wrap:wrap;
  padding:16px;
  background:#fff;
  border:1px solid var(--line);
  border-radius:10px;
  margin-bottom:20px;
}

.form-group{display:flex;flex-direction:column;min-width:160px}

label{
  font-size:13px;
  font-weight:600;
  color:#374151;
  margin-bottom:6px;
}

select,input[type="date"]{
  width:100%;
  padding:10px 12px;
  border:1px solid var(--line);
  border-radius:8px;
  font-size:14px;
  font-family:inherit;
  background:#fff;
}

select:focus,input:focus{outline:none;border-color:var(--primary)}

.btn{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:10px 18px;
  background:#fff;
  border:1px solid var(--line);
  border-radius:8px;
  font-size:14px;
  font-weight:600;
  cursor:pointer;
  transition:all 0.2s;
  color:var(--text);
  text-decoration:none;
}

.btn:hover{background:#f8fafc;text-decoration:none}

.btn.primary{background:var(--primary);color:#fff;border-color:var(--primary)}
.btn.primary:hover{background:#1d4ed8}

.btn.success{background:var(--success);color:#fff;border-color:var(--success)}
.btn.success:hover{background:#059669}

.btn.danger{background:var(--danger);color:#fff;border-color:var(--danger)}
.btn.danger:hover{background:#dc2626}

.btn.sm{padding:6px 12px;font-size:13px}

table{width:100%;border-collapse:collapse}

thead th{
  padding:12px;
  background:#f8fafc;
  border-bottom:2px solid var(--line);
  text-align:left;
  font-size:13px;
  font-weight:700;
  color:#475569;
  text-transform:uppercase;
  letter-spacing:0.5px;
}

tbody td{
  padding:14px 12px;
  border-bottom:1px solid var(--line);
  vertical-align:middle;
  font-size:14px;
}

tbody tr:hover{background:#f8fafc}

.empty{
  text-align:center;
  padding:40px;
  color:var(--muted);
  font-style:italic;
}

.badge{
  display:inline-block;
  padding:4px 10px;
  border-radius:12px;
  font-size:12px;
  font-weight:600;
  white-space:nowrap;
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
.badge-created{background:#f3f4f6;color:#374151}
.badge-released{background:#dbeafe;color:#1e40af}
.badge-progress{background:#fef3c7;color:#92400e}
.badge-waiting{background:#fed7aa;color:#9a3412}
.badge-completed{background:#d1fae5;color:#065f46}
.badge-cancelled{background:#1f2937;color:#fff}
.badge-default{background:#f3f4f6;color:#374151}

.type-badge{
  display:inline-block;
  padding:4px 10px;
  border-radius:6px;
  font-size:11px;
  font-weight:700;
  text-transform:uppercase;
}

.type-problem{background:#fee2e2;color:#991b1b}
.type-note{background:#dbeafe;color:#1e40af}
.type-maintenance{background:#d1fae5;color:#065f46}

.actions{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}

.info-box{
  background:#f0f9ff;
  border:1px solid #bfdbfe;
  border-radius:8px;
  padding:14px;
  margin-top:20px;
  font-size:14px;
  color:#1e40af;
}

.info-box strong{color:#1e3a8a}

.workflow-box{
  background:#fef3c7;
  border:1px solid #fbbf24;
  border-radius:8px;
  padding:14px;
  margin-bottom:20px;
  font-size:14px;
  color:#92400e;
}

.workflow-box strong{color:#78350f}

.mono{
  font-family:ui-monospace,'Cascadia Code','Source Code Pro',Menlo,Consolas,monospace;
  font-size:13px;
}

@media (max-width: 768px) {
  .toggle-bar{flex-direction:column}
  .toggle-btn{min-width:100%}
  .toolbar{flex-direction:column;align-items:stretch}
  .form-group{width:100%}
  table{font-size:13px}
  thead th,tbody td{padding:10px 8px}
  .actions{flex-direction:column}
}
</style>
</head>
<body>

<header>
  <a href="planner_dashboard.php" class="back">← Dashboard</a>
  <h1>📋 Notifications</h1>
  <div class="spacer"></div>
  <div class="who"><?=h($plannerName)?> · <a href="../logout.php">Logout</a></div>
</header>

<div class="wrap">

  <?php if ($message): ?>
    <div class="alert alert-<?= h($messageType) ?>">
      <?php if ($messageType === 'success'): ?>✓<?php elseif ($messageType === 'warning'): ?>⚠<?php else: ?>✕<?php endif; ?>
      <?= h($message) ?>
    </div>
  <?php endif; ?>

  <div class="workflow-box">
    <strong>📌 Notification Workflow:</strong> 
    NEW → Screen → SCREENED → Approve/Reject → APPROVED → Create Work Order
  </div>

  <div class="toggle-bar">
    <a class="toggle-btn <?= $tab==='all'?'active':'' ?>" 
       href="?tab=all<?= $selected_status!=='ALL' ? '&status='.urlencode($selected_status):'' ?><?= $selected_date?'&date='.urlencode($selected_date):'' ?>">
      <span>All Notifications</span>
      <span class="toggle-count"><?= (int)$count_all_notif ?></span>
    </a>
    <a class="toggle-btn <?= $tab==='reports'?'active':'' ?>" 
       href="?tab=reports<?= $selected_status!=='ALL' ? '&status='.urlencode($selected_status):'' ?><?= $selected_date?'&date='.urlencode($selected_date):'' ?>">
      <span>📝 Problem Reports</span>
      <span class="toggle-count"><?= (int)$count_problem_reports ?></span>
    </a>
    <a class="toggle-btn <?= $tab==='completed'?'active':'' ?>" 
       href="?tab=completed<?= $selected_date?'&date='.urlencode($selected_date):'' ?>">
      <span>✓ Work Completed</span>
      <span class="toggle-count"><?= (int)$count_completed ?></span>
    </a>
  </div>

  <form class="toolbar" method="get">
    <input type="hidden" name="tab" value="<?=h($tab)?>">
    
    <div class="form-group">
      <label>Status</label>
      <select name="status" <?= $tab==='completed' ? 'disabled' : '' ?>>
        <?php foreach ($notifStatuses as $s): ?>
          <option value="<?=h($s)?>" <?= $selected_status===$s?'selected':'' ?>><?=h($s)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="form-group">
      <label>Date</label>
      <input type="date" name="date" value="<?=h($selected_date)?>">
    </div>
    
    <div style="flex:1 0 auto">
      <button class="btn primary" type="submit">Apply Filters</button>
      <a class="btn" href="?tab=<?=h($tab)?>">Reset</a>
    </div>
  </form>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th style="width:120px">Notif No.</th>
          <th style="width:140px">Type</th>
          <th style="width:160px">Reported</th>
          <th style="width:100px">Priority</th>
          <th>Description</th>
          <th style="width:200px">Equipment / Location</th>
          <th style="width:110px">Status</th>
          <th style="width:240px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="empty">No notifications found.</td></tr>
        <?php else: foreach ($rows as $r):
          $equipLoc = trim(($r['equipment_name'] ?? '').' / '.($r['floc_name'] ?? ''), ' /');
          $equipLoc = $equipLoc !== '' ? $equipLoc : '—';

          $reported_fmt = ($r['reported_at'])
            ? date('M d, Y H:i', strtotime($r['reported_at']))
            : '—';

          $priority = $r['priority'] ?? '';
          $status   = strtoupper($r['status'] ?? '');
        ?>
          <tr>
            <td class="mono" style="font-weight:700;color:var(--primary)"><?= h($r['notif_no']) ?></td>
            <td>
              <?php if ($tab === 'completed'): ?>
                <span class="type-badge type-maintenance">Work Completed</span>
              <?php elseif ($r['row_type'] === 'problem'): ?>
                <span class="type-badge type-problem">Problem Report</span>
              <?php else: ?>
                <span class="type-badge type-note">Tech Note</span>
              <?php endif; ?>
            </td>
            <td><?= h($reported_fmt) ?></td>
            <td><?= $priority ? priorityBadge($priority) : '—' ?></td>
            <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($r['description'] ?? '') ?></td>
            <td><?= h($equipLoc) ?></td>
            <td><?= $status ? statusBadge($status) : '—' ?></td>
            <td>
              <div class="actions">
                <?php if ($tab === 'completed'): ?>
                  <a class="btn sm" href="work_order_details.php?id=<?= (int)$r['row_id'] ?>">View Details</a>
                
                <?php else: ?>
                  
                  <?php if ($status === 'NEW'): ?>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                      <input type="hidden" name="action" value="screen">
                      <input type="hidden" name="notif_id" value="<?= (int)$r['row_id'] ?>">
                      <button type="submit" class="btn sm primary" onclick="return confirm('Mark this notification as SCREENED?')">
                        👁 Screen
                      </button>
                    </form>
                  
                  <?php elseif ($status === 'SCREENED'): ?>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="notif_id" value="<?= (int)$r['row_id'] ?>">
                      <button type="submit" class="btn sm success" onclick="return confirm('Approve this notification?')">
                        ✓ Approve
                      </button>
                    </form>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="notif_id" value="<?= (int)$r['row_id'] ?>">
                      <button type="submit" class="btn sm danger" onclick="return confirm('Reject this notification?')">
                        ✕ Reject
                      </button>
                    </form>
                  
                  <?php elseif ($status === 'APPROVED'): ?>
                    <a class="btn sm primary" href="create_work_order.php?notification_id=<?= (int)$r['row_id'] ?>">
                       Create Work Order
                    </a>
                  
                  <?php elseif ($status === 'REJECTED'): ?>
                    <span class="badge badge-rejected">Rejected - No Action</span>
                  
                  <?php elseif ($status === 'CLOSED'): ?>
                    <span class="badge badge-closed">Closed</span>
                  
                  <?php endif; ?>
                  
                  <a class="btn sm" href="notification_details.php?id=<?= (int)$r['row_id'] ?>">
                     Details
                  </a>
                  
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="info-box">
    <strong>ℹ How It Works:</strong><br>
    <strong>1. NEW</strong> - Fresh notification submitted by users. Click "Screen" to review it.<br>
    <strong>2. SCREENED</strong> - Reviewed notification. Click "Approve" to allow work order creation, or "Reject" if not needed.<br>
    <strong>3. APPROVED</strong> - Ready for work! Click "Create Work Order" to assign it to a technician.<br>
    <strong>4. COMPLETED</strong> - Work finished by technicians. View details to see resolution notes.
  </div>

</div>

</body>
</html>