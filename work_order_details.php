<?php
// work_order_details.php — read-only WO details (white theme)
// Shows header, equipment/WC, planned hours, Task List operations, and WO materials.
// This version is defensive: does NOT assume a 'uom' column exists in material.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$display_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';

// ---- Input ----
$wo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($wo_id <= 0 && !empty($_GET['wo_no'])) {
  $stmt = $pdo->prepare("SELECT work_order_id FROM work_order WHERE wo_no = ? LIMIT 1");
  $stmt->execute([trim($_GET['wo_no'])]);
  $wo_id = (int)($stmt->fetchColumn() ?: 0);
}
if ($wo_id <= 0) {
  echo '<div style="font:16px system-ui;margin:24px">Invalid Work Order ID. <a href="work_orders.php" style="color:#0d6efd">Back</a></div>';
  exit;
}

// ---- Work Order header ----
$sql = "
SELECT
  wo.*,
  tl.task_list_code, tl.title AS task_list_title,
  e.equipment_name, e.equipment_code,
  fl.floc_name,
  wc.wc_name,
  u.username AS assigned_name
FROM work_order wo
LEFT JOIN task_list tl           ON tl.task_list_id = wo.task_list_id
LEFT JOIN equipment e            ON e.equipment_id  = wo.equipment_id
LEFT JOIN functional_location fl ON fl.floc_id      = wo.floc_id
LEFT JOIN work_center wc         ON wc.work_center_id = wo.work_center_id
LEFT JOIN users u                ON u.user_id       = wo.assigned_user_id
WHERE wo.work_order_id = ?
LIMIT 1";
$st = $pdo->prepare($sql);
$st->execute([$wo_id]);
$wo = $st->fetch();
if (!$wo) { echo '<div style="font:16px system-ui;margin:24px">Work Order not found. <a href="work_orders.php" style="color:#0d6efd">Back</a></div>'; exit; }

// ---- Planned hours (optional table) ----
$planned_hours = null;
try {
  $qh = $pdo->prepare("SELECT planned_hours FROM work_order_labor WHERE work_order_id = ? LIMIT 1");
  $qh->execute([$wo_id]);
  $rowH = $qh->fetch();
  if ($rowH && $rowH['planned_hours'] !== null) $planned_hours = (float)$rowH['planned_hours'];
} catch (Throwable $e) { /* table might not exist; ignore */ }

// ---- Task List operations ----
$ops = []; $total_min = 0;
if (!empty($wo['task_list_id'])) {
  $qo = $pdo->prepare("SELECT op_seq, description, std_time_min, safety_notes
                       FROM task_list_operation
                       WHERE task_list_id = ?
                       ORDER BY op_seq");
  $qo->execute([(int)$wo['task_list_id']]);
  $ops = $qo->fetchAll();
  foreach ($ops as $op) $total_min += (int)$op['std_time_min'];
}

// ---- WO materials (NO 'uom' dependency) ----
$mats = [];
$qm = $pdo->prepare("SELECT wom.quantity, m.material_code, m.material_name
                     FROM work_order_material wom
                     JOIN material m ON m.material_id = wom.material_id
                     WHERE wom.work_order_id = ?
                     ORDER BY m.material_code");
$qm->execute([$wo_id]);
$mats = $qm->fetchAll();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function badge($text,$color){
  return "<span style='display:inline-block;background:$color;color:#fff;padding:4px 8px;border-radius:999px;font-size:12px'>$text</span>";
}
function status_badge($s){
  $map = ['CREATED'=>'#6c757d','RELEASED'=>'#0dcaf0','IN_PROGRESS'=>'#0d6efd','WAITING_PARTS'=>'#ffc107','COMPLETED'=>'#198754','CANCELLED'=>'#dc3545'];
  return badge(str_replace('_',' ',$s), $map[$s] ?? '#6c757d');
}
function prio_badge($p){
  $map = ['LOW'=>'#6c757d','MEDIUM'=>'#0dcaf0','HIGH'=>'#fd7e14','URGENT'=>'#dc3545'];
  return badge($p, $map[$p] ?? '#6c757d');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>WO <?= h($wo['wo_no']) ?> · Details</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--bg:#fff;--text:#111;--muted:#5b6b7b;--line:#e9edf2;}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto}
  a{color:#0d6efd;text-decoration:none}
  .wrap{max-width:1100px;margin:28px auto;padding:0 16px}
  header{display:flex;align-items:center;gap:12px;margin-bottom:12px}
  .back{border:1px solid var(--line);padding:6px 10px;border-radius:8px}
  .spacer{flex:1}
  .who{color:var(--muted);font-size:14px}
  .card{border:1px solid var(--line);border-radius:14px;background:#fff;margin-bottom:16px}
  .pad{padding:16px}
  h2,h3{margin:0}
  h2{font-size:22px}
  h3{font-size:18px}
  .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
  .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
  .item{padding:8px 0}
  .label{color:var(--muted);font-size:13px}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border-bottom:1px solid var(--line);vertical-align:top}
  thead th{font-size:14px;color:#1b2a3a;text-align:left}
  .muted{color:#6b7280}
  .pill{display:inline-block;background:#eef2ff;color:#4338ca;border-radius:999px;padding:2px 8px;font-size:12px}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
  .note{white-space:pre-wrap}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <a class="back" href="work_orders.php">← Back</a>
    <h2>Work Order · <?= h($wo['wo_no']) ?></h2>
    <div class="spacer"></div>
    <div class="who"><?= h($display_name) ?></div>
  </header>

  <!-- Summary -->
  <div class="card pad">
    <div class="grid3">
      <div class="item"><div class="label">Status</div><div><?= status_badge($wo['status']) ?></div></div>
      <div class="item"><div class="label">Priority</div><div><?= prio_badge($wo['priority']) ?></div></div>
      <div class="item"><div class="label">Assigned</div><div><?= h($wo['assigned_name'] ?? 'Unassigned') ?></div></div>
      <div class="item"><div class="label">Due Date</div><div><?= $wo['requested_end'] ? date('Y-m-d', strtotime($wo['requested_end'])) : '—' ?></div></div>
      <div class="item"><div class="label">Created</div><div><?= $wo['created_at'] ? date('Y-m-d H:i', strtotime($wo['created_at'])) : '—' ?></div></div>
      <div class="item"><div class="label">Source</div><div><?= h($wo['source'] ?? '—') ?></div></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--line);margin:12px 0">

    <div class="grid">
      <div class="item">
        <div class="label">Equipment</div>
        <div>
          <?= h($wo['equipment_name'] ?? '') ?>
          <?php if(!empty($wo['equipment_code'])): ?>
            <span class="pill mono"><?= h($wo['equipment_code']) ?></span>
          <?php endif; ?>
          <?php if(!empty($wo['floc_name'])): ?>
            · <span class="pill"><?= h($wo['floc_name']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="item">
        <div class="label">Work Center</div>
        <div><?= h($wo['wc_name'] ?? '—') ?></div>
      </div>
    </div>

    <div class="grid" style="margin-top:10px">
      <div class="item">
        <div class="label">Task List</div>
        <div>
          <?php if(!empty($wo['task_list_code']) || !empty($wo['task_list_title'])): ?>
            <span class="mono pill"><?= h($wo['task_list_code'] ?? '') ?></span>
            <?= h($wo['task_list_title'] ?? '') ?>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="item">
        <div class="label">Planned Hours</div>
        <div>
          <?php if ($planned_hours !== null): ?>
            <?= number_format($planned_hours, 2) ?> h
          <?php elseif (!empty($ops)): ?>
            <?= number_format($total_min/60, 2) ?> h <span class="muted">(from operations)</span>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if(!empty($wo['problem_note'])): ?>
      <div style="margin-top:10px">
        <div class="label">Work Summary</div>
        <div class="note"><?= h($wo['problem_note']) ?></div>
      </div>
    <?php endif; ?>

    <?php if(!empty($wo['resolution_note'])): ?>
      <div style="margin-top:10px">
        <div class="label">Resolution Note</div>
        <div class="note"><?= h($wo['resolution_note']) ?></div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Operations -->
  <div class="card pad">
    <h3>Operations</h3>
    <div class="muted" style="margin:6px 0">
      <?php if(!empty($ops)): ?>
        Total Standard Time: <strong><?= (int)$total_min ?></strong> min (≈ <?= number_format($total_min/60, 2) ?> h)
      <?php else: ?>
        This work order’s task list has no operations.
      <?php endif; ?>
    </div>
    <table>
      <thead><tr><th style="width:100px">Seq</th><th>Description</th><th style="width:120px">Std (min)</th><th style="width:35%">Safety Notes</th></tr></thead>
      <tbody>
        <?php if(!empty($ops)): foreach($ops as $op): ?>
        <tr>
          <td class="mono"><?= (int)$op['op_seq'] ?></td>
          <td><?= h($op['description']) ?></td>
          <td class="mono"><?= (int)$op['std_time_min'] ?></td>
          <td><?= h($op['safety_notes'] ?? '') ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="4" class="muted">No operations defined.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Materials -->
  <div class="card pad">
    <h3>Materials</h3>
    <table>
      <thead><tr><th>Material</th><th style="width:120px">Qty</th></tr></thead>
      <tbody>
        <?php if(!empty($mats)): foreach($mats as $m): ?>
        <tr>
          <td class="mono"><?= h(($m['material_code'] ?? '').' - '.($m['material_name'] ?? '')) ?></td>
          <td class="mono"><?= h($m['quantity']) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="2" class="muted">No materials reserved for this work order.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div style="text-align:right;margin-top:8px">
    <a class="back" href="work_orders.php">← Back to Work Orders</a>
  </div>
</div>
</body>
</html>
