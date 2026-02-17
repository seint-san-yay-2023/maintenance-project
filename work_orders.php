<?php
// work_orders.php - Planner Work Order Management with Stock Deduction & Type Display
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

// ---- Auth ----
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'planner') {
  header('Location: login.php');
  exit;
}
$planner_id = (int)$_SESSION['user_id'];
$planner_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Planner';

// ---- CSRF ----
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok($t){ return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); }

// ---- Helper ----
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function badge($text,$color){ return "<span style='background:$color;color:#fff;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600'>$text</span>"; }
function status_badge($s){
  $map = ['CREATED'=>'#6c757d','RELEASED'=>'#0dcaf0','IN_PROGRESS'=>'#0d6efd','WAITING_PARTS'=>'#ffc107','COMPLETED'=>'#198754','CANCELLED'=>'#dc3545'];
  return badge(str_replace('_',' ',$s), $map[$s] ?? '#6c757d');
}
function priority_badge($p){
  $map = ['LOW'=>'#6c757d','MEDIUM'=>'#0dcaf0','HIGH'=>'#fd7e14','URGENT'=>'#dc3545'];
  return badge($p, $map[$p] ?? '#6c757d');
}
function make_wo_no(PDO $pdo){
  $year = date('Y');
  $stmt = $pdo->prepare("SELECT MAX(wo_no) FROM work_order WHERE wo_no LIKE ?");
  $stmt->execute(["WO{$year}-%"]);
  $max = $stmt->fetchColumn();
  $seq = 0;
  if ($max && preg_match("/WO{$year}-(\\d{4})$/", $max, $m)) $seq = (int)$m[1];
  return "WO{$year}-".str_pad($seq+1, 4, '0', STR_PAD_LEFT);
}

// ---- POST Actions ----
$flash = $err = '';

// Create from Task List
if (($_POST['action'] ?? '') === 'create_from_tasklist') {
  if (!csrf_ok($_POST['csrf'] ?? '')) {
    $err = 'Invalid CSRF token.';
  } else {
    try {
      $tl_id    = (int)($_POST['task_list_id'] ?? 0);
      $priority = $_POST['priority'] ?? 'MEDIUM';
      $assigned = ($_POST['assigned_user_id'] ?? '') !== '' ? (int)$_POST['assigned_user_id'] : null;
      $req_end  = !empty($_POST['req_end']) ? $_POST['req_end'].' 23:59:59' : null;

      $q = $pdo->prepare("SELECT * FROM task_list WHERE task_list_id=? AND COALESCE(is_active,1)=1");
      $q->execute([$tl_id]);
      $tl = $q->fetch();
      if (!$tl) throw new Exception("Task List not found or inactive.");

      // Create WO
      $wo_no = make_wo_no($pdo);
      $ins = $pdo->prepare("
        INSERT INTO work_order
          (wo_no, source, notification_id, plan_id, task_list_id,
           equipment_id, floc_id, work_center_id,
           planner_user_id, assigned_user_id,
           status, priority, requested_end, problem_note,
           created_at, updated_at)
        VALUES
          (?, 'MANUAL', NULL, NULL, ?, ?, NULL, ?, ?, ?, 'CREATED', ?, ?, ?, NOW(), NOW())
      ");
      $ins->execute([
        $wo_no,
        $tl['task_list_id'],
        $tl['equipment_id'],
        $tl['work_center_id'],
        $planner_id,
        $assigned,
        $priority,
        $req_end,
        $tl['title']
      ]);
      $wo_id = (int)$pdo->lastInsertId();

      // Store planned hours
      $sum = $pdo->prepare("SELECT COALESCE(SUM(std_time_min),0) FROM task_list_operation WHERE task_list_id = ?");
      $sum->execute([$tl_id]);
      $tot_min = (int)$sum->fetchColumn();
      $planned_hours = round($tot_min / 60, 2);

      try {
        $pdo->prepare("
          INSERT INTO work_order_labor (work_order_id, planned_hours)
          VALUES (?, ?)
          ON DUPLICATE KEY UPDATE planned_hours = VALUES(planned_hours)
        ")->execute([$wo_id, $planned_hours]);
      } catch (Throwable $e) { /* table might not exist */ }

      // Preload Materials from Task List
      $mats = $pdo->prepare("SELECT material_id, quantity FROM task_list_material WHERE task_list_id=?");
      $mats->execute([$tl_id]);
      foreach ($mats->fetchAll() as $m) {
        if (!empty($m['material_id'])) {
          $pdo->prepare("INSERT INTO work_order_material (work_order_id, material_id, quantity) VALUES (?, ?, ?)")
              ->execute([$wo_id, (int)$m['material_id'], $m['quantity']]);
        }
      }
      
      // Also add materials from Equipment BOM if equipment is specified
      if (!empty($tl['equipment_id'])) {
        $eqBom = $pdo->prepare("SELECT material_id, quantity FROM equipment_bom WHERE equipment_id=?");
        $eqBom->execute([$tl['equipment_id']]);
        foreach ($eqBom->fetchAll() as $bm) {
          if (!empty($bm['material_id'])) {
            // Check if already exists from task list
            $check = $pdo->prepare("SELECT COUNT(*) FROM work_order_material WHERE work_order_id=? AND material_id=?");
            $check->execute([$wo_id, $bm['material_id']]);
            if ((int)$check->fetchColumn() == 0) {
              $pdo->prepare("INSERT INTO work_order_material (work_order_id, material_id, quantity) VALUES (?, ?, ?)")
                  ->execute([$wo_id, (int)$bm['material_id'], $bm['quantity']]);
            }
          }
        }
      }

      $flash = "Work Order <strong>".h($wo_no)."</strong> created successfully.";
    } catch (Throwable $e) {
      $err = $e->getMessage();
    }
  }
}

// Quick Actions with Stock Deduction on Release
if (($_POST['action'] ?? '') === 'quick_action') {
  if (!csrf_ok($_POST['csrf'] ?? '')) {
    $err = 'Invalid CSRF token.';
  } else {
    try {
      $wo_id = (int)($_POST['work_order_id'] ?? 0);
      $act_type = $_POST['action_type'] ?? '';
      
      if ($wo_id <= 0) throw new Exception('Invalid Work Order ID.');
      
      // Load current WO
      $wo = $pdo->prepare("SELECT * FROM work_order WHERE work_order_id = ? LIMIT 1");
      $wo->execute([$wo_id]);
      $wo_data = $wo->fetch();
      if (!$wo_data) throw new Exception('Work Order not found.');
      
      switch ($act_type) {
        case 'release':
          if ($wo_data['status'] !== 'CREATED') throw new Exception('Can only release CREATED work orders.');
          
          // Begin transaction for stock deduction
          $pdo->beginTransaction();
          
          try {
            // Get materials for this work order
            $mats = $pdo->prepare("
              SELECT wom.material_id, wom.quantity, m.material_code, m.material_name, m.on_hand_qty
              FROM work_order_material wom
              JOIN material m ON m.material_id = wom.material_id
              WHERE wom.work_order_id = ?
            ");
            $mats->execute([$wo_id]);
            $materials = $mats->fetchAll();
            
            $insufficient = [];
            $deductions = [];
            
            // Check stock availability
            foreach ($materials as $mat) {
              if ($mat['on_hand_qty'] < $mat['quantity']) {
                $insufficient[] = $mat['material_code'] . ' (Need: ' . $mat['quantity'] . ', Available: ' . $mat['on_hand_qty'] . ')';
              } else {
                $deductions[] = [
                  'id' => $mat['material_id'],
                  'qty' => $mat['quantity'],
                  'name' => $mat['material_name']
                ];
              }
            }
            
            // If any material is insufficient, rollback and warn
            if (!empty($insufficient)) {
              $pdo->rollBack();
              throw new Exception('Insufficient stock for materials: ' . implode(', ', $insufficient));
            }
            
            // Deduct materials from stock
            foreach ($deductions as $ded) {
              $pdo->prepare("
                UPDATE material 
                SET on_hand_qty = on_hand_qty - ? 
                WHERE material_id = ?
              ")->execute([$ded['qty'], $ded['id']]);
            }
            
            // Update work order status
            $pdo->prepare("UPDATE work_order SET status='RELEASED' WHERE work_order_id=?")->execute([$wo_id]);
            
            $pdo->commit();
            
            $deduction_msg = '';
            if (!empty($deductions)) {
              $deduction_msg = ' Materials deducted: ' . count($deductions) . ' items.';
            }
            
            $flash = "Work Order released successfully." . $deduction_msg;
            
          } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
          }
          break;
          
        case 'start':
          if (!in_array($wo_data['status'], ['RELEASED'])) throw new Exception('Can only start RELEASED work orders.');
          $pdo->prepare("UPDATE work_order SET status='IN_PROGRESS', actual_start=NOW() WHERE work_order_id=?")->execute([$wo_id]);
          $flash = "Work Order started.";
          break;
          
        case 'complete':
          if (!in_array($wo_data['status'], ['IN_PROGRESS'])) throw new Exception('Can only complete IN_PROGRESS work orders.');
          $pdo->prepare("UPDATE work_order SET status='COMPLETED', actual_end=NOW() WHERE work_order_id=?")->execute([$wo_id]);
          $flash = "Work Order completed.";
          break;
          
        case 'cancel':
          if ($wo_data['status'] === 'COMPLETED') throw new Exception('Cannot cancel completed work orders.');
          
          // If work order was RELEASED, we should return materials to stock
          if ($wo_data['status'] === 'RELEASED') {
            $pdo->beginTransaction();
            try {
              // Return materials to stock
              $mats = $pdo->prepare("
                SELECT wom.material_id, wom.quantity
                FROM work_order_material wom
                WHERE wom.work_order_id = ?
              ");
              $mats->execute([$wo_id]);
              
              foreach ($mats->fetchAll() as $mat) {
                $pdo->prepare("
                  UPDATE material 
                  SET on_hand_qty = on_hand_qty + ? 
                  WHERE material_id = ?
                ")->execute([$mat['quantity'], $mat['material_id']]);
              }
              
              $pdo->prepare("UPDATE work_order SET status='CANCELLED' WHERE work_order_id=?")->execute([$wo_id]);
              $pdo->commit();
              
              $flash = "Work Order cancelled. Materials returned to stock.";
            } catch (Throwable $e) {
              $pdo->rollBack();
              throw $e;
            }
          } else {
            $pdo->prepare("UPDATE work_order SET status='CANCELLED' WHERE work_order_id=?")->execute([$wo_id]);
            $flash = "Work Order cancelled.";
          }
          break;
          
        case 'assign':
          $tech_id = (int)($_POST['technician_id'] ?? 0);
          if ($tech_id <= 0) throw new Exception('Select a technician.');
          $pdo->prepare("UPDATE work_order SET assigned_user_id=? WHERE work_order_id=?")->execute([$tech_id, $wo_id]);
          $flash = "Technician assigned.";
          break;
          
        default:
          throw new Exception('Invalid action type.');
      }
      
    } catch (Throwable $e) {
      $err = $e->getMessage();
    }
  }
}

// ---- Lookups ----
$techs = $pdo->query("SELECT user_id, username, CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) AS full_name FROM users WHERE role='technician' AND is_active=1")->fetchAll();
$tasklists = $pdo->query("
  SELECT tl.task_list_id, CONCAT(tl.task_list_code,' - ',tl.title) AS label,
         e.equipment_name, wc.wc_name
  FROM task_list tl
  LEFT JOIN equipment e ON e.equipment_id=tl.equipment_id
  LEFT JOIN work_center wc ON wc.work_center_id=tl.work_center_id
  WHERE COALESCE(tl.is_active,1)=1
  ORDER BY tl.task_list_code
")->fetchAll();

$priorities = ['LOW','MEDIUM','HIGH','URGENT'];
$statuses   = ['ALL','CREATED','RELEASED','IN_PROGRESS','WAITING_PARTS','COMPLETED','CANCELLED'];

// ---- Query WOs ----
$f_status = $_GET['status'] ?? 'ALL';
$f_priority = $_GET['priority'] ?? 'ALL';
$f_type = $_GET['type'] ?? 'ALL';
$q = trim($_GET['q'] ?? '');

$where=[];$params=[];
if ($f_status!=='ALL'){ $where[]="wo.status=?"; $params[]=$f_status; }
if ($f_priority!=='ALL'){ $where[]="wo.priority=?"; $params[]=$f_priority; }

// Type filter
if ($f_type === 'PREVENTIVE') {
  $where[] = "wo.plan_id IS NOT NULL";
} elseif ($f_type === 'REACTIVE') {
  $where[] = "wo.notification_id IS NOT NULL";
} elseif ($f_type === 'MANUAL') {
  $where[] = "wo.plan_id IS NULL AND wo.notification_id IS NULL";
}

if ($q !== '') {
  $where[] = "(wo.wo_no LIKE ? OR e.equipment_name LIKE ? OR wc.wc_name LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}

$sql = "
  SELECT wo.*,
         e.equipment_name,
         wc.wc_name,
         fl.floc_name,
         u.username AS assigned_name,
         CASE
           WHEN wo.plan_id IS NOT NULL THEN 'PREVENTIVE'
           WHEN wo.notification_id IS NOT NULL THEN 'REACTIVE'
           ELSE 'MANUAL'
         END AS wo_type
  FROM work_order wo
  LEFT JOIN equipment e ON e.equipment_id = wo.equipment_id
  LEFT JOIN work_center wc ON wc.work_center_id = wo.work_center_id
  LEFT JOIN functional_location fl ON fl.floc_id = wo.floc_id
  LEFT JOIN users u ON u.user_id = wo.assigned_user_id
  " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
  ORDER BY wo.created_at DESC
  LIMIT 500
";

$stmt=$pdo->prepare($sql);
$stmt->execute($params);
$rows=$stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Work Orders - CMMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--bg:#f9fafb;--text:#0f172a;--muted:#64748b;--line:#e2e8f0;--primary:#2563eb;--success:#10b981;--danger:#ef4444}
  *{box-sizing:border-box;margin:0;padding:0}
  body{margin:0;background:var(--bg);color:var(--text);font:15px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,sans-serif}
  a{color:var(--primary);text-decoration:none}
  a:hover{text-decoration:underline}
  .wrap{max-width:1600px;margin:0 auto;padding:20px}
  
  /* Header */
  header{display:flex;align-items:center;gap:16px;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid var(--line)}
  header h1{font-size:28px;font-weight:700}
  .back{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#fff;border:1px solid var(--line);border-radius:8px;font-weight:600;color:var(--text)}
  .back:hover{background:#f1f5f9;text-decoration:none}
  .spacer{flex:1}
  .who{color:var(--muted);font-size:14px}
  
  /* Cards */
  .card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
  
  /* Forms */
  .row{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
  label{font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px}
  select,input[type=text],input[type=date]{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;font-size:14px;font-family:inherit;background:#fff}
  select:focus,input:focus{outline:none;border-color:var(--primary)}
  
  /* Buttons */
  .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;background:#fff;border:1px solid var(--line);border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all 0.2s;color:var(--text)}
  .btn:hover{background:#f8fafc;text-decoration:none}
  .btn.primary{background:var(--primary);color:#fff;border-color:var(--primary)}
  .btn.primary:hover{background:#1d4ed8}
  .btn.success{background:var(--success);color:#fff;border-color:var(--success)}
  .btn.success:hover{background:#059669}
  .btn.danger{background:var(--danger);color:#fff;border-color:var(--danger)}
  .btn.danger:hover{background:#b91c1c}
  .btn.sm{padding:6px 12px;font-size:13px}
  .btn:disabled{opacity:0.5;cursor:not-allowed}
  
  /* Alerts */
  .alert{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:14px}
  .alert.ok{background:#d1fae5;border:1px solid #a7f3d0;color:#065f46}
  .alert.err{background:#fee2e2;border:1px solid #fecaca;color:#991b1b}
  .info-box{background:#f0f9ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px;margin-bottom:20px;font-size:14px;color:#1e40af}
  .info-box strong{color:#1e3a8a}
  
  /* Tables */
  table{width:100%;border-collapse:collapse}
  thead th{padding:12px;background:#f8fafc;border-bottom:2px solid var(--line);text-align:left;font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.5px}
  tbody td{padding:14px 12px;border-bottom:1px solid var(--line);vertical-align:middle;font-size:14px}
  tbody tr:hover{background:#f8fafc}
  .mono{font-family:ui-monospace,'Cascadia Code','Source Code Pro',Menlo,Consolas,monospace;font-size:13px}
  .muted{color:var(--muted)}
  .empty{text-align:center;padding:40px;color:var(--muted);font-style:italic}
  .right{text-align:right}
  .actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
  
  /* Badges */
  .badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600}
  .badge.pm{background:#dbeafe;color:#1e40af;border:1px solid #93c5fd}
  .badge.reactive{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
  .badge.manual{background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <a href="planner_dashboard.php" class="back">← Dashboard</a>
    <h1>🔧 Work Orders</h1>
    <div class="spacer"></div>
    <div class="who"><?=h($planner_name)?> · <a href="logout.php">Logout</a></div>
  </header>

  <?php if($flash): ?><div class="alert ok">✓ <?=$flash?></div><?php endif; ?>
  <?php if($err): ?><div class="alert err">✗ <?=h($err)?></div><?php endif; ?>

  <div class="info-box">
    <strong>📦 Stock Deduction:</strong> When you release a work order, materials will be automatically deducted from stock. If materials are insufficient, the release will be blocked. Cancelling a RELEASED work order returns materials to stock.
  </div>

  <!-- Filters -->
  <div class="card">
    <form method="get" class="row">
      <div style="min-width:280px;flex:1">
        <label>Search</label>
        <input type="text" name="q" placeholder="WO No, Equipment, Work Center..." value="<?= h($q) ?>">
      </div>
      
      <div style="min-width:140px">
        <label>Type</label>
        <select name="type">
          <option value="ALL">All Types</option>
          <option value="PREVENTIVE" <?= $f_type === 'PREVENTIVE' ? 'selected' : '' ?>>📅 Preventive</option>
          <option value="REACTIVE" <?= $f_type === 'REACTIVE' ? 'selected' : '' ?>>🔔 Reactive</option>
          <option value="MANUAL" <?= $f_type === 'MANUAL' ? 'selected' : '' ?>>📝 Manual</option>
        </select>
      </div>
      
      <div style="min-width:160px">
        <label>Status</label>
        <select name="status">
          <?php foreach($statuses as $s): ?>
          <option <?=$s===$f_status?'selected':''?>><?=$s?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div style="min-width:140px">
        <label>Priority</label>
        <select name="priority">
          <option value="ALL">All</option>
          <?php foreach($priorities as $p): ?>
          <option <?=$p===$f_priority?'selected':''?>><?=$p?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div style="flex:1 0 auto">
        <button class="btn primary">Apply</button>
        <a class="btn" href="work_orders.php">Reset</a>
      </div>
    </form>
  </div>

  <!-- Work Orders Table -->
  <div class="card">
    <table>
      <thead>
        <tr>
          <th style="width:130px">WO No</th>
          <th style="width:110px">Type</th>
          <th style="width:180px">Equipment</th>
          <th style="width:140px">Work Center</th>
          <th style="width:100px">Priority</th>
          <th style="width:120px">Status</th>
          <th style="width:130px">Assigned</th>
          <th style="width:100px">Due Date</th>
          <th class="right" style="width:140px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="empty">No work orders found.</td></tr>
        <?php else: ?>
        <?php foreach($rows as $r): ?>
        <tr>
          <td class="mono" style="font-weight:700;color:var(--primary)"><?=h($r['wo_no'])?></td>
          
          <td>
            <?php if ($r['wo_type'] === 'PREVENTIVE'): ?>
              <span class="badge pm">📅 PM</span>
            <?php elseif ($r['wo_type'] === 'REACTIVE'): ?>
              <span class="badge reactive">🔔 Reactive</span>
            <?php else: ?>
              <span class="badge manual">📝 Manual</span>
            <?php endif; ?>
          </td>
          
          <td><?=h($r['equipment_name']??'—')?></td>
          <td><?=h($r['wc_name']??'—')?></td>
          <td><?=priority_badge($r['priority'])?></td>
          <td><?=status_badge($r['status'])?></td>
          <td><?=h($r['assigned_name']??'Unassigned')?></td>
          <td><?=!empty($r['requested_end'])?date('Y-m-d',strtotime($r['requested_end'])):'—'?></td>
          <td class="right">
            <div class="actions">
              <a class="btn sm" href="work_order_details.php?id=<?=(int)$r['work_order_id']?>">View</a>
              <?php if (!in_array($r['status'], ['COMPLETED', 'CANCELLED'])): ?>                  
                <form method="post" style="display:inline" onsubmit="return confirm('Cancel this work order?<?= $r['status']==='RELEASED'?' Materials will be returned to stock.':''?>')">
                  <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
                  <input type="hidden" name="action" value="quick_action">
                  <input type="hidden" name="action_type" value="cancel">
                  <input type="hidden" name="work_order_id" value="<?=(int)$r['work_order_id']?>">
                  <button class="btn sm danger">Cancel</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>