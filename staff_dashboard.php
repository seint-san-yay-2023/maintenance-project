<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}
$current_user_id = (int)$_SESSION['user_id'];

/* --------- load user (must be technician) ---------- */
$sqlUser = "
  SELECT u.*, ed.position, ed.employee_code, wc.wc_name, wc.work_center_id
  FROM users u
  LEFT JOIN employee_details ed ON u.user_id = ed.user_id
  LEFT JOIN work_center wc ON ed.work_center_id = wc.work_center_id
  WHERE u.user_id = ?
";
$st = $connect->prepare($sqlUser);
$st->bind_param("i", $current_user_id);
$st->execute();
$user_info = $st->get_result()->fetch_assoc();
$st->close();

if (!$user_info) { die("User not found"); }
if ($user_info['role'] !== 'technician') { die("Access denied. Technician role required."); }

$full_name = trim(($user_info['first_name'] ?? '').' '.($user_info['last_name'] ?? ''));

/* ---------- flash messages ---------- */
$success = '';
if (isset($_GET['completed'])) $success = "Work order completed successfully!";
if (isset($_GET['started']))   $success = "Work order started. Good luck!";

/* ---------- filter setup ---------- */
$allowed_filters = ['active','new','in-progress','completed','all'];
$raw_filter = strtolower(trim($_GET['filter'] ?? 'active'));
$status_filter = in_array($raw_filter, $allowed_filters, true) ? $raw_filter : 'active';

function build_link($filter){
  return "staff_dashboard.php?" . http_build_query(['filter' => $filter]);
}

/* ---------- base query with TYPE ---------- */
$base = "
SELECT 
  w.work_order_id, w.wo_no, w.status, w.priority,
  w.requested_start, w.requested_end, w.actual_start, w.actual_end,
  w.problem_note, w.created_at,
  n.notif_no, n.description AS notif_description,
  COALESCE(fl.floc_name,'N/A') AS location,
  COALESCE(eq.equipment_code,'') AS equipment_code,
  COALESCE(eq.equipment_name,'N/A') AS equipment_name,
  wc.wc_name AS work_center,
  tl.title AS task_list_title, tl.task_list_id, tl.estimated_hours,
  planner.first_name AS planner_first, planner.last_name AS planner_last,
  CASE
    WHEN w.plan_id IS NOT NULL THEN 'PREVENTIVE'
    WHEN w.notification_id IS NOT NULL THEN 'REACTIVE'
    ELSE 'MANUAL'
  END AS wo_type
FROM work_order w
LEFT JOIN notification n          ON w.notification_id = n.notification_id
LEFT JOIN functional_location fl  ON w.floc_id = fl.floc_id
LEFT JOIN equipment eq            ON w.equipment_id = eq.equipment_id
LEFT JOIN work_center wc          ON w.work_center_id = wc.work_center_id
LEFT JOIN task_list tl            ON w.task_list_id = tl.task_list_id
LEFT JOIN users planner           ON w.planner_user_id = planner.user_id
WHERE w.assigned_user_id = ?
";

/* ---------- apply filter ---------- */
$where = '';
switch($status_filter){
  case 'active':
    $where = " AND UPPER(w.status) IN ('CREATED','RELEASED','ASSIGNED','IN_PROGRESS','WAITING_PARTS') ";
    break;
  case 'new':
    $where = " AND UPPER(w.status) IN ('CREATED','RELEASED','ASSIGNED') ";
    break;
  case 'in-progress':
    $where = " AND UPPER(w.status) = 'IN_PROGRESS' ";
    break;
  case 'completed':
    $where = " AND UPPER(w.status) = 'COMPLETED' ";
    break;
  case 'all':
  default:
    $where = "";
}

$order = "
ORDER BY 
  CASE w.priority
    WHEN 'URGENT' THEN 1
    WHEN 'HIGH'   THEN 2
    WHEN 'MEDIUM' THEN 3
    WHEN 'LOW'    THEN 4
    ELSE 5
  END,
  COALESCE(w.requested_start, w.created_at) ASC,
  w.work_order_id DESC
";

$sql = $base.$where.$order;
$stmt = $connect->prepare($sql);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$rs = $stmt->get_result();

/* ---------- stats ---------- */
$sqlCounts = $connect->prepare($base);
$sqlCounts->bind_param("i", $current_user_id);
$sqlCounts->execute();
$allRows = $sqlCounts->get_result()->fetch_all(MYSQLI_ASSOC);
$sqlCounts->close();

$counts = ['active'=>0,'new'=>0,'in-progress'=>0,'completed'=>0,'all'=>0];
$counts['all'] = count($allRows);

foreach($allRows as $r){
  $st = strtoupper(trim($r['status'] ?? ''));
  if (in_array($st, ['CREATED','RELEASED','ASSIGNED','IN_PROGRESS','WAITING_PARTS'], true)) $counts['active']++;
  if (in_array($st, ['CREATED','RELEASED','ASSIGNED'], true)) $counts['new']++;
  if ($st === 'IN_PROGRESS')  $counts['in-progress']++;
  if ($st === 'COMPLETED')    $counts['completed']++;
}

/* ---------- prepare view data & KPIs ---------- */
$data  = [];
$kpi   = ['new'=>0,'in_progress'=>0,'completed_today'=>0,'overdue'=>0];
$today = date('Y-m-d');

while($row = $rs->fetch_assoc()){
  $data[] = $row;
  $stUpper = strtoupper(trim($row['status'] ?? ''));
  if (in_array($stUpper, ['CREATED','RELEASED','ASSIGNED'], true)) $kpi['new']++;
  if ($stUpper === 'IN_PROGRESS') $kpi['in_progress']++;
  if ($stUpper === 'COMPLETED' && !empty($row['actual_end']) &&
      date('Y-m-d', strtotime($row['actual_end'])) === $today) {
    $kpi['completed_today']++;
  }
  if (!empty($row['requested_end']) && $stUpper !== 'COMPLETED' &&
      strtotime($row['requested_end']) < time()) {
    $kpi['overdue']++;
  }
}
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Dashboard - Fix Mate CMMS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, sans-serif;
  background: #f5f7fa;
  color: #1a202c;
  line-height: 1.6;
}

/* Top Bar */
.topbar {
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  padding: 16px 0;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.topbar-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  font-size: 20px;
  font-weight: 700;
  color: #2563eb;
}

.logo img {
  height: 70px;
  max-width: 120px;
}

.nav-menu {
  display: flex;
  gap: 8px;
}

.nav-link {
  padding: 8px 16px;
  border-radius: 6px;
  color: #64748b;
  text-decoration: none;
  font-weight: 500;
  font-size: 14px;
  transition: all 0.2s;
}

.nav-link:hover {
  background: #f1f5f9;
  color: #1e293b;
}

/* Container */
.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px;
}

/* Success Message */
.success-msg {
  background: #d1fae5;
  border-left: 4px solid #10b981;
  padding: 12px 16px;
  border-radius: 6px;
  margin-bottom: 20px;
  color: #065f46;
  font-weight: 500;
  animation: slideDown 0.3s;
}

@keyframes slideDown {
  from { transform: translateY(-10px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

/* Header */
.welcome {
  background: white;
  padding: 24px;
  border-radius: 12px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.welcome h1 {
  font-size: 24px;
  margin-bottom: 4px;
  color: #1e293b;
}

.welcome p {
  color: #64748b;
  font-size: 14px;
}

/* Stats */
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-box {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  border-left: 4px solid;
}

.stat-box:nth-child(1) { border-left-color: #3b82f6; }
.stat-box:nth-child(2) { border-left-color: #f59e0b; }
.stat-box:nth-child(3) { border-left-color: #10b981; }
.stat-box:nth-child(4) { border-left-color: #ef4444; }

.stat-number {
  font-size: 32px;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 4px;
}

.stat-box:nth-child(4) .stat-number {
  color: #ef4444;
}

.stat-label {
  color: #64748b;
  font-size: 14px;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.tab {
  padding: 10px 16px;
  border-radius: 8px;
  background: white;
  border: 2px solid #e2e8f0;
  color: #64748b;
  text-decoration: none;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.2s;
  display: inline-flex;
  gap: 6px;
  align-items: center;
}

.tab:hover {
  border-color: #cbd5e1;
  background: #f8fafc;
}

.tab.active {
  background: #2563eb;
  color: white;
  border-color: #2563eb;
}

.count {
  background: rgba(0,0,0,0.1);
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
}

.tab:not(.active) .count {
  background: #e2e8f0;
  color: #64748b;
}

/* Work Orders Grid */
.work-orders {
  display: grid;
  gap: 16px;
}

.wo-card {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  border-left: 4px solid #e2e8f0;
  transition: all 0.2s;
}

.wo-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  transform: translateY(-2px);
}

.wo-card.urgent { border-left-color: #ef4444; }
.wo-card.high { border-left-color: #f59e0b; }
.wo-card.medium { border-left-color: #3b82f6; }
.wo-card.low { border-left-color: #94a3b8; }

.wo-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 12px;
  gap: 12px;
}

.wo-number {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 4px;
}

.wo-equipment {
  color: #64748b;
  font-size: 14px;
}

.badges {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.badge {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  white-space: nowrap;
}

.badge-urgent { background: #fee2e2; color: #991b1b; }
.badge-high { background: #fef3c7; color: #92400e; }
.badge-medium { background: #dbeafe; color: #1e40af; }
.badge-low { background: #f1f5f9; color: #475569; }
.badge-overdue { background: #fee2e2; color: #991b1b; }
.badge-due-today { background: #fef3c7; color: #92400e; }
.badge-new { background: #dbeafe; color: #1e40af; }
.badge-progress { background: #fef3c7; color: #92400e; }
.badge-done { background: #d1fae5; color: #065f46; }

/* Type Badges */
.badge-pm { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
.badge-reactive { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.badge-manual { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }

.wo-info {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin: 12px 0;
  padding: 12px;
  background: #f8fafc;
  border-radius: 8px;
}

.info-item {
  font-size: 13px;
}

.info-label {
  color: #94a3b8;
  display: block;
  margin-bottom: 2px;
}

.info-value {
  color: #1e293b;
  font-weight: 600;
}

.wo-description {
  color: #475569;
  font-size: 14px;
  margin: 12px 0;
  line-height: 1.5;
}

.wo-actions {
  display: flex;
  gap: 8px;
  margin-top: 16px;
}

.btn {
  padding: 10px 20px;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 600;
  font-size: 14px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-block;
  text-align: center;
}

.btn-primary {
  background: #2563eb;
  color: white;
}

.btn-primary:hover {
  background: #1d4ed8;
}

.btn-secondary {
  background: white;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.btn-secondary:hover {
  background: #f8fafc;
  color: #1e293b;
}

/* Empty State */
.empty {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 12px;
  color: #94a3b8;
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
}

.empty-text {
  font-size: 16px;
  color: #64748b;
}

/* Responsive */
@media (max-width: 768px) {
  .topbar-inner {
    flex-direction: column;
    gap: 12px;
  }

  .nav-menu {
    width: 100%;
    justify-content: center;
  }

  .stats {
    grid-template-columns: 1fr;
  }

  .wo-header {
    flex-direction: column;
  }

  .wo-info {
    grid-template-columns: 1fr;
  }

  .wo-actions {
    flex-direction: column;
  }

  .btn {
    width: 100%;
  }
}
</style>
</head>
<body>

<!-- Top Bar -->
<div class="topbar">
  <div class="topbar-inner">
    <div class="logo"><img src="../image/logo.png" alt="Fix Mate CMMS Logo"></div>
    <nav class="nav-menu">
      <a href="staff_dashboard.php" class="nav-link">Dashboard</a>
      <a href="tech_history.php" class="nav-link">History</a>
      <a href="./logout.php" class="nav-link">Logout</a>
    </nav>
  </div>
</div>

<div class="container">
  
  <!-- Success Message -->
  <?php if ($success): ?>
  <div class="success-msg">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- Welcome -->
  <div class="welcome">
    <h1>Welcome back, <?= htmlspecialchars($full_name) ?></h1>
    <p>
      <?= htmlspecialchars($user_info['position'] ?: 'Technician') ?>
      <?php if (!empty($user_info['employee_code'])): ?>
        • ID: <?= htmlspecialchars($user_info['employee_code']) ?>
      <?php endif; ?>
      <?php if (!empty($user_info['wc_name'])): ?>
        • <?= htmlspecialchars($user_info['wc_name']) ?>
      <?php endif; ?>
    </p>
  </div>

  

  <!-- Filter Tabs -->
  <div class="tabs">
    <a href="<?= build_link('active') ?>" class="tab <?= $status_filter==='active'?'active':'' ?>">
      Active <span class="count"><?= $counts['active'] ?></span>
    </a>
    <a href="<?= build_link('new') ?>" class="tab <?= $status_filter==='new'?'active':'' ?>">
      New <span class="count"><?= $counts['new'] ?></span>
    </a>
    <a href="<?= build_link('in-progress') ?>" class="tab <?= $status_filter==='in-progress'?'active':'' ?>">
      In Progress <span class="count"><?= $counts['in-progress'] ?></span>
    </a>
    <a href="<?= build_link('completed') ?>" class="tab <?= $status_filter==='completed'?'active':'' ?>">
      Completed <span class="count"><?= $counts['completed'] ?></span>
    </a>
    <a href="<?= build_link('all') ?>" class="tab <?= $status_filter==='all'?'active':'' ?>">
      All <span class="count"><?= $counts['all'] ?></span>
    </a>
  </div>

  <!-- Work Orders -->
  <?php if (empty($data)): ?>
    <div class="empty">
      <div class="empty-icon">📋</div>
      <div class="empty-text">No work orders found</div>
    </div>
  <?php else: ?>
    <div class="work-orders">
      <?php foreach($data as $wo): 
        $priority = strtolower($wo['priority'] ?: 'medium');
        $status = strtoupper(trim($wo['status'] ?? ''));
        $desc = $wo['problem_note'] ?: ($wo['notif_description'] ?: '');
        if (mb_strlen($desc) > 150) $desc = mb_substr($desc, 0, 150) . '...';
        
        $dueLabel = '';
        if (!empty($wo['requested_end']) && $status !== 'COMPLETED') {
          $days = (strtotime($wo['requested_end']) - time()) / 86400;
          if ($days < 0) $dueLabel = 'overdue';
          elseif ($days < 1) $dueLabel = 'due-today';
        }
      ?>
      <div class="wo-card <?= $priority ?>">
        <div class="wo-header">
          <div>
            <div class="wo-number"><?= htmlspecialchars($wo['wo_no']) ?></div>
            <div class="wo-equipment">
              <?= htmlspecialchars($wo['equipment_name']) ?>
              <?= $wo['equipment_code'] ? ' ('.htmlspecialchars($wo['equipment_code']).')' : '' ?>
            </div>
          </div>
          <div class="badges">
            <!-- TYPE BADGE -->
            <?php if ($wo['wo_type'] === 'PREVENTIVE'): ?>
              <span class="badge badge-pm">📅 PM</span>
            <?php elseif ($wo['wo_type'] === 'REACTIVE'): ?>
              <span class="badge badge-reactive">🔔 Reactive</span>
            <?php else: ?>
              <span class="badge badge-manual">📝 Manual</span>
            <?php endif; ?>
            
            <span class="badge badge-<?= $priority ?>">
              <?= strtoupper($wo['priority'] ?: 'MEDIUM') ?>
            </span>
            <?php if ($dueLabel): ?>
            <span class="badge badge-<?= $dueLabel ?>">
              <?= $dueLabel === 'overdue' ? '⚠ OVERDUE' : '⏰ DUE TODAY' ?>
            </span>
            <?php endif; ?>
            <?php if (in_array($status, ['CREATED','RELEASED','ASSIGNED'])): ?>
<span class="badge badge-new">NEW</span>
<?php elseif ($status === 'IN_PROGRESS'): ?>
<span class="badge badge-progress">IN PROGRESS</span>
<?php elseif ($status === 'WAITING_PARTS'): ?>
<span class="badge badge-waiting">WAITING PARTS</span>
<?php elseif ($status === 'COMPLETED'): ?>
<span class="badge badge-done">COMPLETED</span>
<?php endif; ?>
          </div>
        </div>

        <?php if ($desc): ?>
        <div class="wo-description"><?= htmlspecialchars($desc) ?></div>
        <?php endif; ?>

        <div class="wo-info">
          <div class="info-item">
            <span class="info-label">Location</span>
            <span class="info-value"><?= htmlspecialchars($wo['location']) ?></span>
          </div>
          <?php if (!empty($wo['requested_end'])): ?>
          <div class="info-item">
            <span class="info-label">Due Date</span>
            <span class="info-value"><?= date('M d, Y', strtotime($wo['requested_end'])) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($wo['work_center'])): ?>
          <div class="info-item">
            <span class="info-label">Work Center</span>
            <span class="info-value"><?= htmlspecialchars($wo['work_center']) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($wo['estimated_hours'])): ?>
          <div class="info-item">
            <span class="info-label">Est. Time</span>
            <span class="info-value"><?= $wo['estimated_hours'] ?> hrs</span>
          </div>
          <?php endif; ?>
        </div>

        <div class="wo-actions">
          <?php if (in_array($status, ['CREATED','RELEASED','ASSIGNED'])): ?>
            <a href="start_work_order.php?id=<?= $wo['work_order_id'] ?>" class="btn btn-primary">
              ▶ Start Work
            </a>
          <?php elseif (in_array($status, ['IN_PROGRESS','WAITING_PARTS'])): ?>
            <a href="complete_work_order.php?id=<?= $wo['work_order_id'] ?>" class="btn btn-primary">
              ✓ Complete
            </a>
          <?php endif; ?>
          <a href="view_work_order.php?id=<?= $wo['work_order_id'] ?>" class="btn btn-secondary">
            Details
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

</body>
</html>