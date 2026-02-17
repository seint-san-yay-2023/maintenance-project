<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$work_order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $pdo = new PDO("mysql:host=localhost;dbname=cmms;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Work order details
    $wo_query = $pdo->prepare("
        SELECT w.*, 
               eq.equipment_name, eq.equipment_code, eq.serial_no, eq.model_no,
               fl.floc_name, fl.floc_code,
               wc.wc_name,
               tl.title AS task_list_title, tl.task_list_code, tl.estimated_hours,
               n.notification_id, n.notif_no, n.description AS notif_description,
               n.reporter_name, n.reporter_email,
               planner.first_name AS planner_first, planner.last_name AS planner_last,
               planner.email AS planner_email,
               tech.first_name AS tech_first, tech.last_name AS tech_last
        FROM work_order w
        LEFT JOIN equipment eq ON w.equipment_id = eq.equipment_id
        LEFT JOIN functional_location fl ON w.floc_id = fl.floc_id
        LEFT JOIN work_center wc ON w.work_center_id = wc.work_center_id
        LEFT JOIN task_list tl ON w.task_list_id = tl.task_list_id
        LEFT JOIN notification n ON w.notification_id = n.notification_id
        LEFT JOIN users planner ON w.planner_user_id = planner.user_id
        LEFT JOIN users tech ON w.assigned_user_id = tech.user_id
        WHERE w.work_order_id = ? AND w.assigned_user_id = ?
    ");
    $wo_query->execute([$work_order_id, $_SESSION['user_id']]);
    $wo = $wo_query->fetch(PDO::FETCH_ASSOC);

    if (!$wo) {
        die('Work order not found or not assigned to you.');
    }

    // Task list operations
    $operations = [];
    if ($wo['task_list_id']) {
        $ops_query = $pdo->prepare("
            SELECT * FROM task_list_operation 
            WHERE task_list_id = ? 
            ORDER BY op_seq
        ");
        $ops_query->execute([$wo['task_list_id']]);
        $operations = $ops_query->fetchAll(PDO::FETCH_ASSOC);
    }

    // Materials
    $materials_query = $pdo->prepare("
        SELECT wom.*, m.material_name, m.material_code, m.unit_of_measure
        FROM work_order_material wom
        JOIN material m ON wom.material_id = m.material_id
        WHERE wom.work_order_id = ?
        ORDER BY wom.issued_at DESC
    ");
    $materials_query->execute([$work_order_id]);
    $materials = $materials_query->fetchAll(PDO::FETCH_ASSOC);

    // Labor
    $labor_query = $pdo->prepare("SELECT * FROM work_order_labor WHERE work_order_id = ?");
    $labor_query->execute([$work_order_id]);
    $labor = $labor_query->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

$total_material_cost = 0;
foreach ($materials as $mat) {
    $total_material_cost += $mat['quantity'] * $mat['unit_cost'];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Work Order <?= htmlspecialchars($wo['wo_no']) ?> - Fix Mate CMMS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f7f7f9;
  --ink:#1f2937;
  --muted:#6b7280;
  --line:#e5e7eb;
  --brand-1:#A88A73;
  --brand-2:#8B6F5B;
  --accent:#3b82f6;
  --success:#10b981;
  --warning:#f59e0b;
  --danger:#ef4444;

  --radius:12px;
  --shadow:0 4px 14px rgba(15,23,42,.06);
  --shadow-lg:0 14px 32px rgba(15,23,42,.12);
}

*{box-sizing:border-box;margin:0;padding:0}
body{
  background:var(--bg);
  color:var(--ink);
  font-family:"Inter",-apple-system,BlinkMacSystemFont,system-ui,Segoe UI,Roboto,Arial,sans-serif;
  line-height:1.6;
}

/* Top bar */
.nav{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:10}
.nav-inner{max-width:1280px;margin:0 auto;padding:14px 24px;display:flex;align-items:center;justify-content:space-between}
.nav-title{font-size:16px;font-weight:800;color:#2c3e50}
.back-link{color:#6B5644;text-decoration:none;border:2px solid #B99B85;padding:8px 16px;border-radius:999px;font-weight:600;transition:.15s}
.back-link:hover{background:#f5f1e8}

/* Container */
.container{max-width:1280px;margin:0 auto;padding:24px}

/* Cards / sections */
.section{background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:24px;margin-bottom:18px}
.section-header{display:flex;align-items:center;gap:10px;font-size:18px;font-weight:800;color:#2c3e50;margin-bottom:16px}
.section-header small{color:var(--muted);font-weight:600}

/* Header block */
.wo-header{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:24px;margin-bottom:18px;box-shadow:var(--shadow);border-left:6px solid #e5e7eb}
.wo-header.priority-urgent{border-left-color:var(--danger)}
.wo-header.priority-high{border-left-color:#ea580c}
.wo-header.priority-medium{border-left-color:var(--warning)}
.wo-header.priority-low{border-left-color:#9ca3af}
.wo-title{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
.wo-no{font-size:28px;font-weight:900}
.wo-sub{color:var(--muted);font-weight:600}

/* Badges */
.badges{display:flex;gap:8px;flex-wrap:wrap}
.badge{padding:6px 12px;border-radius:8px;font-size:12px;font-weight:800;text-transform:uppercase}
.priority-urgent{background:#fee2e2;color:#b91c1c}
.priority-high{background:#fff7ed;color:#c2410c}
.priority-medium{background:#fef3c7;color:#b45309}
.priority-low{background:#f3f4f6;color:#6b7280}

.status-created,.status-released,.status-assigned{background:#e3f0ff;color:#1e40af}
.status-in-progress{color:#b45309}
.status-waiting-parts{background:#fde68a;color:#7c2d12}
.status-completed{background:#dcfce7;color:#166534}
.status-cancelled{background:#fee2e2;color:#b91c1c}

/* Info grid */
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
.info-card{background:#fafafc;border:1px solid var(--line);padding:14px;border-radius:10px}
.info-label{font-size:11px;color:#9aa0a6;font-weight:800;letter-spacing:.4px;text-transform:uppercase;margin-bottom:4px}
.info-value{font-weight:800}

/* Timeline (simple) */
.timeline{display:grid;gap:12px}
.tl{display:grid;grid-template-columns:180px 1fr;gap:10px;align-items:center}
.tl .label{font-size:12px;color:#9aa0a6;font-weight:800;text-transform:uppercase}
.tl .value{font-weight:700}

/* Operations */
.operations{display:flex;flex-direction:column;gap:10px}
.operation{background:#fafafc;border:1px solid var(--line);padding:12px;border-radius:10px}
.operation .head{display:flex;align-items:center;gap:10px}
.op-num{min-width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:#B99B85;color:#fff;font-weight:900}
.op-desc{font-weight:600}
.op-time{margin-left:auto;font-size:12px;font-weight:800;color:#6b7280}

/* Tables */
.table{width:100%;border-collapse:collapse}
.table thead{background:#f9fafb}
.table th{font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:#6b7280;border-bottom:2px solid var(--line);padding:12px;text-align:left}
.table td{border-bottom:1px solid var(--line);padding:12px}

/* Buttons */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;border-radius:10px;font-weight:800;border:none;cursor:pointer;transition:.15s;text-decoration:none}
.btn-primary{
  background:linear-gradient(135deg, #A88A73 0%, #8B6F5B 100%);
  color:#fff; box-shadow:0 4px 12px rgba(168,138,115,.3);
}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(139,111,91,.4)}
.btn-secondary{background:#fff;color:#374151;border:2px solid var(--line)}
.btn-secondary:hover{border-color:#B99B85;color:#6B5644}

/* Sticky action bar */
.action-bar{position:sticky;bottom:20px;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow-lg);padding:14px;display:flex;gap:10px;justify-content:center}

/* Responsive */
@media (max-width:768px){
  .container{padding:16px}
  .tl{grid-template-columns:1fr}
  .action-bar{bottom:0;border-radius:0}
  .btn{width:100%}
}
</style>
</head>
<body>

<!-- Top -->
<div class="nav">
  <div class="nav-inner">
    <a href="staff_dashboard.php" class="back-link">Back to Dashboard</a>
    <div class="nav-title">Work Order Details</div>
  </div>
</div>

<div class="container">

  <!-- Header -->
  <div class="wo-header priority-<?= strtolower($wo['priority']) ?>">
    <div class="wo-title">
      <div>
        <div class="wo-no"><?= htmlspecialchars($wo['wo_no']) ?></div>
        <div class="wo-sub">
          <?= htmlspecialchars($wo['equipment_name']) ?>
          <?php if ($wo['equipment_code']): ?>(<?= htmlspecialchars($wo['equipment_code']) ?>)<?php endif; ?>
        </div>
      </div>
      <div class="badges">
        <span class="badge priority-<?= strtolower($wo['priority']) ?>"><?= htmlspecialchars($wo['priority']) ?> Priority</span>
        <span class="badge status-<?= strtolower(str_replace('_','-',$wo['status'])) ?>"><?= htmlspecialchars(str_replace('_',' ',$wo['status'])) ?></span>
      </div>
    </div>

    <?php if ($wo['problem_note']): ?>
      <div class="section" style="margin:16px 0 0">
        <div class="section-header">Problem Description</div>
        <div><?= nl2br(htmlspecialchars($wo['problem_note'])) ?></div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Equipment & Location -->
  <div class="section">
    <div class="section-header">Equipment & Location</div>
    <div class="info-grid">
      <div class="info-card">
        <div class="info-label">Location</div>
        <div class="info-value">
          <?= htmlspecialchars($wo['floc_name']) ?>
          <?php if ($wo['floc_code']): ?><div style="color:var(--muted);font-weight:600;">(<?= htmlspecialchars($wo['floc_code']) ?>)</div><?php endif; ?>
        </div>
      </div>
      <div class="info-card">
        <div class="info-label">Work Center</div>
        <div class="info-value"><?= htmlspecialchars($wo['wc_name'] ?: 'Not Assigned') ?></div>
      </div>
      <?php if ($wo['serial_no']): ?>
      <div class="info-card">
        <div class="info-label">Serial Number</div>
        <div class="info-value"><?= htmlspecialchars($wo['serial_no']) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($wo['model_no']): ?>
      <div class="info-card">
        <div class="info-label">Model Number</div>
        <div class="info-value"><?= htmlspecialchars($wo['model_no']) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Schedule -->
  <div class="section">
    <div class="section-header">Schedule & Timeline</div>
    <div class="timeline">
      <div class="tl">
        <div class="label">Requested Start</div>
        <div class="value"><?= $wo['requested_start'] ? date('M d, Y H:i', strtotime($wo['requested_start'])) : 'Not specified' ?></div>
      </div>
      <div class="tl">
        <div class="label">Requested End</div>
        <div class="value"><?= $wo['requested_end'] ? date('M d, Y H:i', strtotime($wo['requested_end'])) : 'Not specified' ?></div>
      </div>
      <?php if ($wo['actual_start']): ?>
      <div class="tl">
        <div class="label">Actual Start</div>
        <div class="value"><?= date('M d, Y H:i', strtotime($wo['actual_start'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($wo['actual_end']): ?>
      <div class="tl">
        <div class="label">Actual End</div>
        <div class="value"><?= date('M d, Y H:i', strtotime($wo['actual_end'])) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Operations -->
  <?php if (!empty($operations)): ?>
  <div class="section">
    <div class="section-header">
      Task List: <?= htmlspecialchars($wo['task_list_title']) ?>
      <?php if ($wo['estimated_hours']): ?>
        <small>(Est. <?= $wo['estimated_hours'] ?> hours)</small>
      <?php endif; ?>
    </div>
    <div class="operations">
      <?php foreach ($operations as $op): ?>
      <div class="operation">
        <div class="head">
          <div class="op-num"><?= (int)$op['op_seq'] ?></div>
          <div class="op-desc"><?= htmlspecialchars($op['description']) ?></div>
          <?php if ($op['std_time_min']): ?><div class="op-time"><?= (int)$op['std_time_min'] ?> min</div><?php endif; ?>
        </div>
        <?php if ($op['safety_notes']): ?>
          <div style="margin-top:8px;color:#7c2d12;font-size:13px;background:#fff7ed;border-left:3px solid #f59e0b;padding:8px 10px;border-radius:8px;">
            <strong>Safety:</strong> <?= htmlspecialchars($op['safety_notes']) ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Materials -->
  <?php if (!empty($materials)): ?>
  <div class="section">
    <div class="section-header">Materials Used</div>
    <table class="table">
      <thead>
        <tr>
          <th>Material Code</th>
          <th>Material Name</th>
          <th>Quantity</th>
          <th>Unit Cost</th>
          <th>Total</th>
          <th>Issued</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($materials as $mat): $item_total = $mat['quantity'] * $mat['unit_cost']; ?>
        <tr>
          <td><?= htmlspecialchars($mat['material_code']) ?></td>
          <td><?= htmlspecialchars($mat['material_name']) ?></td>
          <td><?= $mat['quantity'] ?> <?= $mat['unit_of_measure'] ?></td>
          <td>$<?= number_format($mat['unit_cost'], 2) ?></td>
          <td><strong>$<?= number_format($item_total, 2) ?></strong></td>
          <td><?= date('M d, H:i', strtotime($mat['issued_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" class="text-right" style="text-align:right;">Total Material Cost:</td>
          <td colspan="2"><strong>$<?= number_format($total_material_cost, 2) ?></strong></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>

  <!-- Labor -->
  <?php if ($labor): ?>
  <div class="section">
    <div class="section-header">Labor Information</div>
    <div class="info-grid">
      <?php if ($labor['planned_hours']): ?>
      <div class="info-card">
        <div class="info-label">Planned Hours</div>
        <div class="info-value"><?= $labor['planned_hours'] ?> hours</div>
      </div>
      <?php endif; ?>
      <?php if ($labor['actual_hours']): ?>
      <div class="info-card">
        <div class="info-label">Actual Hours</div>
        <div class="info-value"><?= $labor['actual_hours'] ?> hours</div>
      </div>
      <?php endif; ?>
      <?php if ($labor['labor_cost']): ?>
      <div class="info-card">
        <div class="info-label">Labor Cost</div>
        <div class="info-value">$<?= number_format($labor['labor_cost'], 2) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Resolution -->
  <?php if ($wo['resolution_note']): ?>
  <div class="section">
    <div class="section-header">Resolution Notes</div>
    <div style="border:1px solid #A88A73;padding:16px;border-radius:10px;color:#A88A73;">
      <?= nl2br(htmlspecialchars($wo['resolution_note'])) ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Actions -->
  <?php if ($wo['status'] != 'COMPLETED' && $wo['status'] != 'CANCELLED'): ?>
  <div class="action-bar">
    <?php if (in_array($wo['status'], ['CREATED', 'RELEASED'])): ?>
      <a href="start_work_order.php?id=<?= $work_order_id ?>" class="btn btn-primary">Start Work Order</a>
    <?php elseif (in_array($wo['status'], ['IN_PROGRESS', 'WAITING_PARTS'])): ?>
      <a href="complete_work_order.php?id=<?= $work_order_id ?>" class="btn btn-primary">Continue & Complete</a>
    <?php endif; ?>
    <a href="staff_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
