<?php
// maintenance_plans.php - Preventive Maintenance Planning (Planner)
// UPDATED: Added n8n webhook notification when generating work orders

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

// ---- Auth: planner only ----
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'planner') {
  header('Location: login.php'); exit;
}
$planner_id   = (int)$_SESSION['user_id'];
$planner_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Planner';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- CSRF ----
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok($t){ return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); }

// ---- Helper: Generate WO Number ----
function make_wo_no(PDO $pdo){
  $year = date('Y');
  $stmt = $pdo->prepare("SELECT MAX(wo_no) FROM work_order WHERE wo_no LIKE ?");
  $stmt->execute(["WO{$year}-%"]);
  $max = $stmt->fetchColumn();
  $seq = 0;
  if ($max && preg_match('/WO'.$year.'-(\d{4})$/', $max, $m)) $seq = (int)$m[1];
  return "WO{$year}-".str_pad($seq+1, 4, '0', STR_PAD_LEFT);
}

// ---- Helper: Generate Plan Code ----
function make_plan_code(PDO $pdo){
  $year = date('Y');
  $stmt = $pdo->prepare("SELECT MAX(plan_code) FROM maintenance_plan WHERE plan_code LIKE ?");
  $stmt->execute(["PM{$year}-%"]);
  $max = $stmt->fetchColumn();
  $seq = 0;
  if ($max && preg_match('/PM'.$year.'-(\d{4})$/', $max, $m)) $seq = (int)$m[1];
  return "PM{$year}-".str_pad($seq+1, 4, '0', STR_PAD_LEFT);
}

// ⭐⭐⭐ n8n WEBHOOK FUNCTION ⭐⭐⭐
function sendToN8nWebhook($workOrderData) {
  $webhookUrl = 'http://localhost:5678/webhook-test/93730285-12c6-4bbd-a2d9-562bb5771969';
  
  $payload = json_encode($workOrderData);
  
  $ch = curl_init($webhookUrl);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);
  
  $success = ($httpCode >= 200 && $httpCode < 300);
  
  // Log webhook result
  if (!$success) {
    error_log("n8n Webhook Failed: HTTP $httpCode - Error: $error - Response: $response");
  } else {
    error_log("n8n Webhook Success: Work Order {$workOrderData['wo_no']} notification sent");
  }
  
  return [
    'success' => $success, 
    'http_code' => $httpCode,
    'response' => $response,
    'error' => $error
  ];
}

// ---- Lookups ----
$tasklists = $pdo->query("
  SELECT tl.task_list_id,
         tl.task_list_code,
         tl.title,
         CONCAT(tl.task_list_code,' - ',tl.title) AS label,
         e.equipment_name, wc.wc_name
  FROM task_list tl
  LEFT JOIN equipment e ON e.equipment_id=tl.equipment_id
  LEFT JOIN work_center wc ON wc.work_center_id=tl.work_center_id
  WHERE COALESCE(tl.is_active,1)=1
  ORDER BY tl.task_list_code
")->fetchAll();

$workcenters = $pdo->query("SELECT work_center_id, wc_code, wc_name FROM work_center ORDER BY wc_code")->fetchAll();
$equip = $pdo->query("SELECT equipment_id, equipment_code, equipment_name FROM equipment ORDER BY equipment_code")->fetchAll();
$flocs = $pdo->query("SELECT floc_id, floc_code, floc_name FROM functional_location ORDER BY floc_code")->fetchAll();

// ---- Get Technicians ----
$technicians = [];
try {
  $q = $pdo->query("
    SELECT u.user_id,
           COALESCE(
             NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''),
             NULLIF(u.username,''), u.email
           ) AS tech_name
    FROM users u
    WHERE u.is_active = 1 AND u.role = 'technician'
    ORDER BY tech_name ASC
  ");
  $technicians = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $technicians = [];
}

// ---- Flash messages ----
$flash = ''; $flash_error = '';

// Check for session flash messages
if (!empty($_SESSION['flash_success'])) {
  $flash = $_SESSION['flash_success'];
  unset($_SESSION['flash_success']);
}

// ---- Actions ----
$action = $_POST['action'] ?? '';

if ($action === 'save_plan' && csrf_ok($_POST['csrf'] ?? '')) {
  try {
    $pid  = ($_POST['plan_id'] ?? '') !== '' ? (int)$_POST['plan_id'] : null;
    
    // AUTO-GENERATE plan code for new plans
    if ($pid) {
      // Editing: use existing code
      $code = trim($_POST['plan_code'] ?? '');
      if ($code === '') throw new Exception('Plan Code is required.');
    } else {
      // Creating: auto-generate code
      $code = make_plan_code($pdo);
    }
    
    $title= trim($_POST['title'] ?? '');
    $tlid = (int)($_POST['task_list_id'] ?? 0);
    $plan_type = $_POST['plan_type'] ?? 'TIME';
    $cycle_days = ($_POST['cycle_days'] ?? '') !== '' ? (int)$_POST['cycle_days'] : null;
    $cycle_meter = ($_POST['cycle_meter'] ?? '') !== '' ? (int)$_POST['cycle_meter'] : null;
    $next_due = $_POST['next_due_date'] ?? null;
    $eq   = ($_POST['equipment_id'] ?? '') !== '' ? (int)$_POST['equipment_id'] : null;
    $fl   = ($_POST['floc_id'] ?? '') !== '' ? (int)$_POST['floc_id'] : null;
    $assigned_user = ($_POST['assigned_user_id'] ?? '') !== '' ? (int)$_POST['assigned_user_id'] : null;
    $active = (int)($_POST['is_active'] ?? 1);

    if ($title==='' || $tlid<=0) {
      throw new Exception('Title and Task List are required.');
    }
    
    if ($plan_type === 'TIME' && !$cycle_days) {
      throw new Exception('Cycle Days is required for TIME-based plans.');
    }
    
    if ($plan_type === 'METER' && !$cycle_meter) {
      throw new Exception('Cycle Meter is required for METER-based plans.');
    }

    // For updates only: check uniqueness of code
    if ($pid){
      $q=$pdo->prepare("SELECT COUNT(*) FROM maintenance_plan WHERE plan_code=? AND plan_id<>?");
      $q->execute([$code,$pid]);
      if ((int)$q->fetchColumn()>0) throw new Exception('Plan Code already exists.');
    }

    if ($pid){
      // UPDATE
      $stmt = $pdo->prepare("
        UPDATE maintenance_plan 
        SET plan_code=?, title=?, plan_type=?, cycle_days=?, cycle_meter=?,
            equipment_id=?, floc_id=?, task_list_id=?, next_due_date=?, 
            assigned_user_id=?, is_active=?
        WHERE plan_id=?
      ");
      $stmt->execute([$code, $title, $plan_type, $cycle_days, $cycle_meter, 
                      $eq, $fl, $tlid, $next_due, $assigned_user, $active, $pid]);
      $flash = "Plan <strong>".h($code)."</strong> updated successfully.";
    } else {
      // INSERT
      $stmt = $pdo->prepare("
        INSERT INTO maintenance_plan 
          (plan_code, title, plan_type, cycle_days, cycle_meter, 
           equipment_id, floc_id, task_list_id, next_due_date, assigned_user_id, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([$code, $title, $plan_type, $cycle_days, $cycle_meter,
                      $eq, $fl, $tlid, $next_due, $assigned_user, $active]);
      $flash = "Plan <strong>".h($code)."</strong> created successfully!";
    }
  } catch (Throwable $e) {
    $flash_error = $e->getMessage();
  }
}

// ⭐⭐⭐ Generate Work Orders from Plan with n8n Webhook ⭐⭐⭐
if ($action === 'generate_wos' && csrf_ok($_POST['csrf'] ?? '')) {
  try {
    $pid = (int)$_POST['plan_id'];
    
    // Load plan with all related data
    $p = $pdo->prepare("
      SELECT mp.*,
             tl.title AS task_list_name,
             e.equipment_name, e.work_center_id,
             f.floc_name,
             wc.wc_name
      FROM maintenance_plan mp
      LEFT JOIN task_list tl ON tl.task_list_id = mp.task_list_id
      LEFT JOIN equipment e ON e.equipment_id = mp.equipment_id
      LEFT JOIN functional_location f ON f.floc_id = mp.floc_id
      LEFT JOIN work_center wc ON wc.work_center_id = e.work_center_id
      WHERE mp.plan_id = ?
      LIMIT 1
    ");
    $p->execute([$pid]);
    $plan = $p->fetch();
    
    if (!$plan) throw new Exception('Plan not found.');
    if (!$plan['is_active']) throw new Exception('Plan is inactive.');
    
    // Calculate next due date
    $today = date('Y-m-d');
    $next_due = $plan['next_due_date'] ?: $today;
    
    $pdo->beginTransaction();
    
    try {
      // Create work order
      $wo_no = make_wo_no($pdo);
      
      $ins = $pdo->prepare("
        INSERT INTO work_order
          (wo_no, source, notification_id, plan_id, task_list_id,
           equipment_id, floc_id, work_center_id,
           planner_user_id, assigned_user_id,
           status, priority, requested_end, problem_note,
           created_at, updated_at)
        VALUES
          (?, 'PM', NULL, ?, ?, ?, ?, ?, ?, ?,
           'CREATED', 'MEDIUM', ?, ?, NOW(), NOW())
      ");
      
      $ins->execute([
        $wo_no,
        $pid,
        $plan['task_list_id'],
        $plan['equipment_id'],
        $plan['floc_id'],
        $plan['work_center_id'],
        $planner_id,
        $plan['assigned_user_id'],
        $next_due . ' 23:59:59',
        'PM: ' . $plan['title']
      ]);
      
      $wo_id = (int)$pdo->lastInsertId();
      
      // Copy operations from task list
      $planned_hours = 0;
      try {
        $ops = $pdo->prepare("
          SELECT op_seq, description, std_time_min, safety_notes 
          FROM task_list_operation 
          WHERE task_list_id=? 
          ORDER BY op_seq
        ");
        $ops->execute([$plan['task_list_id']]);
        $operations = $ops->fetchAll();
        
        if (!empty($operations)) {
          $insOp = $pdo->prepare("
            INSERT INTO work_order_operation (work_order_id, op_seq, description, std_time_min, safety_notes)
            VALUES (?, ?, ?, ?, ?)
          ");
          
          $total_minutes = 0;
          foreach ($operations as $op) {
            $insOp->execute([
              $wo_id,
              (int)$op['op_seq'],
              $op['description'],
              (int)($op['std_time_min'] ?? 0),
              $op['safety_notes'] ?? ''
            ]);
            $total_minutes += (int)($op['std_time_min'] ?? 0);
          }
          $planned_hours = round($total_minutes / 60, 2);
        }
      } catch (Throwable $e) {
        error_log("Failed to copy operations: " . $e->getMessage());
      }
      
      // Copy materials from task list
      try {
        $mats = $pdo->prepare("SELECT material_id, quantity FROM task_list_material WHERE task_list_id=?");
        $mats->execute([$plan['task_list_id']]);
        
        $insMat = $pdo->prepare("
          INSERT INTO work_order_material (work_order_id, material_id, quantity, issued_by_user_id)
          VALUES (?, ?, ?, ?)
        ");
        
        foreach ($mats->fetchAll() as $m) {
          if (!empty($m['material_id'])) {
            $insMat->execute([
              $wo_id, 
              (int)$m['material_id'], 
              $m['quantity'],
              $planner_id
            ]);
          }
        }
      } catch (Throwable $e) {
        error_log("Failed to copy materials: " . $e->getMessage());
      }
      
      // Insert planned hours
      if ($planned_hours > 0) {
        try {
          $pdo->prepare("INSERT INTO work_order_labor (work_order_id, planned_hours) VALUES (?, ?)")
              ->execute([$wo_id, $planned_hours]);
        } catch (Throwable $e) {
          error_log("Failed to insert planned hours: " . $e->getMessage());
        }
      }
      
      // Update plan: last_gen_date and calculate next_due_date
      if ($plan['plan_type'] === 'TIME' && $plan['cycle_days']) {
        $new_next_due = date('Y-m-d', strtotime($next_due . ' +' . $plan['cycle_days'] . ' days'));
      } else {
        $new_next_due = $plan['next_due_date'];
      }
      
      $pdo->prepare("UPDATE maintenance_plan SET last_gen_date=?, next_due_date=? WHERE plan_id=?")
          ->execute([date('Y-m-d'), $new_next_due, $pid]);
      
      $pdo->commit();
      
      // ⭐⭐⭐ SEND NOTIFICATION TO n8n WEBHOOK ⭐⭐⭐
      
      // Set timezone
      date_default_timezone_set('Asia/Bangkok'); // Adjust to your timezone
      
      // Get assigned user details
      $assignedEmail = null;
      $assignedName = 'Unassigned';
      $assignedFirstName = null;
      $assignedLastName = null;
      
      if ($plan['assigned_user_id']) {
        try {
          $userQuery = $pdo->prepare("
            SELECT email, first_name, last_name,
                   COALESCE(CONCAT(first_name, ' ', last_name), username, email) as full_name
            FROM users 
            WHERE user_id = ?
          ");
          $userQuery->execute([$plan['assigned_user_id']]);
          $userData = $userQuery->fetch(PDO::FETCH_ASSOC);
          if ($userData) {
            $assignedEmail = $userData['email'];
            $assignedName = $userData['full_name'];
            $assignedFirstName = $userData['first_name'];
            $assignedLastName = $userData['last_name'];
          }
        } catch (Throwable $e) {
          error_log("Failed to get assigned user details: " . $e->getMessage());
        }
      }
      
      // Calculate dates for calendar event
      $now = new DateTime();
      $startDate = clone $now;
      $startDate->modify('+1 hour');
      
      $endDate = new DateTime($next_due . ' 23:59:59');
      
      // Format dates in ISO 8601 format with timezone
      $calendarStartDate = $startDate->format('c');
      $calendarEndDate = $endDate->format('c');
      
      // Prepare comprehensive webhook payload
      $webhookData = [
        // Work Order Details
        'work_order_id' => $wo_id,
        'wo_no' => $wo_no,
        'status' => 'CREATED',
        'priority' => 'MEDIUM',
        'source' => 'PM',
        
        // Equipment & Location
        'equipment_id' => $plan['equipment_id'],
        'equipment_name' => $plan['equipment_name'] ?? 'N/A',
        'location_id' => $plan['floc_id'],
        'location_name' => $plan['floc_name'] ?? 'N/A',
        'work_center_id' => $plan['work_center_id'],
        'work_center_name' => $plan['wc_name'] ?? 'N/A',
        
        // Task & Time
        'task_list_id' => $plan['task_list_id'],
        'task_list_name' => $plan['task_list_name'],
        'planned_hours' => $planned_hours,
        'due_date' => $next_due . ' 23:59:59',
        
        // ⭐ CALENDAR EVENT DATES - Properly Formatted
        'calendar_start_date' => $calendarStartDate,
        'calendar_end_date' => $calendarEndDate,
        'calendar_timezone' => 'Asia/Bangkok',
        
        // People
        'planner_user_id' => $planner_id,
        'assigned_user_id' => $plan['assigned_user_id'],
        'assigned_email' => $assignedEmail,
        'assigned_name' => $assignedName,
        'assigned_first_name' => $assignedFirstName,
        'assigned_last_name' => $assignedLastName,
        
        // Description
        'description' => 'PM: ' . $plan['title'],
        'problem_note' => 'Preventive Maintenance: ' . $plan['title'],
        
        // ⭐ CALENDAR EVENT DETAILS
        'calendar_summary' => "PM Work Order: {$wo_no} - {$plan['equipment_name']}",
        'calendar_description' => "Preventive Maintenance Work Order\n\nPriority: MEDIUM\nEquipment: {$plan['equipment_name']}\nLocation: {$plan['floc_name']}\nPlan: {$plan['plan_code']}\n\nDescription:\n{$plan['title']}\n\nThis is an automatically generated preventive maintenance work order.",
        
        // Maintenance Plan Info
        'maintenance_plan_id' => $pid,
        'maintenance_plan_code' => $plan['plan_code'],
        'maintenance_plan_title' => $plan['title'],
        'plan_type' => $plan['plan_type'],
        'cycle_days' => $plan['cycle_days'],
        
        // Timestamps
        'created_at' => date('Y-m-d H:i:s'),
        'created_date' => date('Y-m-d'),
        'created_time' => date('H:i:s'),
        
        // URLs for linking back
        'work_order_url' => "http://{$_SERVER['HTTP_HOST']}/cmms/admin/work_order_details.php?id={$wo_id}",
        
        // System info
        'system' => 'CMMS',
        'event_type' => 'pm_work_order_created'
      ];
      
      // Send to n8n webhook
      try {
        $webhookResult = sendToN8nWebhook($webhookData);
        
        if (!$webhookResult['success']) {
          error_log("Webhook failed but work order created: WO{$wo_no}");
          $_SESSION['webhook_warning'] = "Work order created but notification failed to send.";
        }
      } catch (Throwable $e) {
        error_log("Webhook exception: " . $e->getMessage());
      }
      
      // Redirect to work order details page
      $_SESSION['flash_success'] = "✓ Work Order <strong>$wo_no</strong> generated from maintenance plan!";
      if ($assignedEmail) {
        $_SESSION['flash_success'] .= "<br>📧 Email notification sent to " . htmlspecialchars($assignedName);
      }
      header("Location: work_order_details.php?id=$wo_id");
      exit;
      
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
    
  } catch (Throwable $e) {
    $flash_error = $e->getMessage();
  }
}

// ---- Data: List all plans ----
$plans = $pdo->query("
  SELECT mp.*,
         tl.task_list_code, tl.title AS tl_title,
         e.equipment_code, e.equipment_name,
         f.floc_code, f.floc_name,
         COALESCE(
           NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''),
           NULLIF(u.username,''), u.email
         ) AS assigned_tech_name
  FROM maintenance_plan mp
  LEFT JOIN task_list tl ON tl.task_list_id = mp.task_list_id
  LEFT JOIN equipment e ON e.equipment_id = mp.equipment_id
  LEFT JOIN functional_location f ON f.floc_id = mp.floc_id
  LEFT JOIN users u ON u.user_id = mp.assigned_user_id
  ORDER BY mp.plan_code DESC
")->fetchAll();

// Edit mode
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit = null;
if ($edit_id > 0) {
  $s = $pdo->prepare("SELECT * FROM maintenance_plan WHERE plan_id=? LIMIT 1");
  $s->execute([$edit_id]);
  $edit = $s->fetch();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Maintenance Plans - CMMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--bg:#f9fafb;--text:#0f172a;--muted:#64748b;--line:#e2e8f0;--primary:#2563eb;--success:#10b981;--danger:#ef4444}
  *{box-sizing:border-box;margin:0;padding:0}
  body{margin:0;background:var(--bg);color:var(--text);font:15px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,sans-serif}
  a{color:var(--primary);text-decoration:none}
  a:hover{text-decoration:underline}
  .wrap{max-width:1400px;margin:0 auto;padding:20px}
  
  /* Header */
  header{display:flex;align-items:center;gap:16px;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid var(--line)}
  header h1{font-size:28px;font-weight:700}
  .back{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#fff;border:1px solid var(--line);border-radius:8px;font-weight:600;color:var(--text)}
  .back:hover{background:#f1f5f9;text-decoration:none}
  .spacer{flex:1}
  .who{color:var(--muted);font-size:14px}
  
  /* Cards */
  .card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
  .card h3{font-size:18px;font-weight:700;margin-bottom:16px;color:var(--text)}
  
  /* Forms */
  .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:16px}
  .form-group{display:flex;flex-direction:column}
  label{font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
  label .req{color:var(--danger)}
  select,input[type=text],input[type=date],input[type=number]{
    width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;
    font-size:14px;font-family:inherit;transition:border-color 0.2s;background:#fff
  }
  select:focus,input:focus{outline:none;border-color:var(--primary)}
  input[readonly]{background:#f3f4f6;color:#6b7280}
  
  /* Buttons */
  .btn-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .btn{
    display:inline-flex;align-items:center;gap:6px;padding:10px 18px;
    background:#fff;border:1px solid var(--line);border-radius:8px;
    font-size:14px;font-weight:600;cursor:pointer;transition:all 0.2s;color:var(--text)
  }
  .btn:hover{background:#f8fafc;text-decoration:none}
  .btn.primary{background:var(--primary);color:#fff;border-color:var(--primary)}
  .btn.primary:hover{background:#1d4ed8}
  .btn.success{background:var(--success);color:#fff;border-color:var(--success)}
  .btn.success:hover{background:#059669}
  .btn.sm{padding:6px 12px;font-size:13px}
  .btn:disabled{opacity:0.5;cursor:not-allowed}
  
  /* Alerts */
  .alert{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:14px}
  .alert.ok{background:#d1fae5;border:1px solid #a7f3d0;color:#065f46}
  .alert.err{background:#fee2e2;border:1px solid #fecaca;color:#991b1b}
  .alert.warning{background:#fef3c7;border:1px solid #fcd34d;color:#92400e}
  .info-box{background:#f0f9ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px;margin-bottom:20px;font-size:14px;color:#1e40af}
  .info-box strong{color:#1e3a8a}
  
  /* Tables */
  table{width:100%;border-collapse:collapse}
  thead th{
    padding:12px;background:#f8fafc;border-bottom:2px solid var(--line);
    text-align:left;font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.5px
  }
  tbody td{padding:14px 12px;border-bottom:1px solid var(--line);vertical-align:middle;font-size:14px}
  tbody tr:hover{background:#f8fafc}
  .mono{font-family:ui-monospace,'Cascadia Code','Source Code Pro',Menlo,Consolas,monospace;font-size:13px}
  .muted{color:var(--muted)}
  .empty{text-align:center;padding:40px;color:var(--muted);font-style:italic}
  
  /* Badges */
  .badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600}
  .badge.active{background:#d1fae5;color:#065f46}
  .badge.inactive{background:#f3f4f6;color:#6b7280}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <a href="planner_dashboard.php" class="back">← Dashboard</a>
    <h1>📅 Maintenance Plans</h1>
    <div class="spacer"></div>
    <div class="who"><?= h($planner_name) ?> · <a href="logout.php">Logout</a></div>
  </header>

  <?php if ($flash): ?><div class="alert ok"><?= $flash ?></div><?php endif; ?>
  <?php if ($flash_error): ?><div class="alert err"><strong>Error:</strong> <?= h($flash_error) ?></div><?php endif; ?>
  
  <?php if (isset($_SESSION['webhook_warning'])): ?>
    <div class="alert warning">
      ⚠️ <?= htmlspecialchars($_SESSION['webhook_warning']) ?>
    </div>
    <?php unset($_SESSION['webhook_warning']); ?>
  <?php endif; ?>

  <div class="info-box">
    <strong>ℹ️ About Maintenance Plans:</strong> Plans automatically generate work orders based on time cycles (e.g., every 30 days) or meter readings. Set the next due date, assign a worker, then click "Generate WO" anytime to create a work order. 📧 Email notifications will be sent automatically to assigned technicians via n8n workflow.
  </div>

  <!-- Create / Edit Plan -->
  <div class="card">
    <h3><?= $edit ? '✏️ Edit Plan' : '➕ Create Plan' ?></h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="save_plan">
      <?php if ($edit): ?>
        <input type="hidden" name="plan_id" value="<?= (int)$edit['plan_id'] ?>">
      <?php endif; ?>

      <?php if (!$edit): ?>
        <!-- Auto-generated info for new plans -->
        <div style="background:#f0f9ff;border:1px solid #bae6fd;padding:12px;border-radius:8px;margin-bottom:16px">
          <strong>ℹ️ Plan Code:</strong> Will be auto-generated (e.g., PM2025-0001)
        </div>
      <?php endif; ?>

      <div class="form-grid">
        <?php if ($edit): ?>
        <div class="form-group">
          <label>Plan Code</label>
          <input type="text" name="plan_code" value="<?= h($edit['plan_code'] ?? '') ?>" readonly>
        </div>
        <?php endif; ?>

        <div class="form-group" style="grid-column:span 2">
          <label>Title <span class="req">*</span></label>
          <input type="text" name="title" required maxlength="150" value="<?= h($edit['title'] ?? '') ?>" placeholder="e.g., Air Conditioner Monthly Check">
        </div>

        <div class="form-group" style="grid-column:span 2">
          <label>Task List <span class="req">*</span></label>
          <select name="task_list_id" required>
            <option value="">Select task list...</option>
            <?php foreach ($tasklists as $tl): ?>
            <option value="<?= (int)$tl['task_list_id'] ?>" <?= isset($edit['task_list_id']) && (int)$edit['task_list_id']===$tl['task_list_id']?'selected':''; ?>>
              <?= h($tl['label']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Plan Type</label>
          <select name="plan_type" id="plan_type" onchange="togglePlanType()">
            <option value="TIME" <?= isset($edit['plan_type']) && $edit['plan_type']==='TIME'?'selected':''; ?>>Time-Based</option>
            <option value="METER" <?= isset($edit['plan_type']) && $edit['plan_type']==='METER'?'selected':''; ?>>Meter-Based</option>
          </select>
        </div>

        <div class="form-group" id="cycle_days_field">
          <label>Cycle (Days) <span class="req" id="days_required">*</span></label>
          <input type="number" name="cycle_days" min="1" value="<?= h($edit['cycle_days'] ?? '') ?>" placeholder="e.g., 30">
        </div>

        <div class="form-group" id="cycle_meter_field" style="display:none">
          <label>Cycle (Meter) <span class="req">*</span></label>
          <input type="number" name="cycle_meter" min="1" value="<?= h($edit['cycle_meter'] ?? '') ?>" placeholder="e.g., 1000">
        </div>

        <div class="form-group">
          <label>Next Due Date</label>
          <input type="date" name="next_due_date" value="<?= h($edit['next_due_date'] ?? '') ?>">
        </div>

        <div class="form-group" style="grid-column:span 2">
          <label>Equipment (Optional)</label>
          <select name="equipment_id">
            <option value="">— None —</option>
            <?php foreach($equip as $e): ?>
            <option value="<?= (int)$e['equipment_id'] ?>" <?= isset($edit['equipment_id']) && (int)$edit['equipment_id']===$e['equipment_id']?'selected':''; ?>>
              <?= h($e['equipment_code'].' - '.$e['equipment_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="grid-column:span 2">
          <label>Location (Optional)</label>
          <select name="floc_id">
            <option value="">— None —</option>
            <?php foreach($flocs as $fl): ?>
            <option value="<?= (int)$fl['floc_id'] ?>" <?= isset($edit['floc_id']) && (int)$edit['floc_id']===$fl['floc_id']?'selected':''; ?>>
              <?= h($fl['floc_code'].' - '.$fl['floc_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="grid-column:span 2">
          <label>Assign Worker (Optional) 
            <span style="color:#10b981;font-weight:normal;font-size:12px">📧 Email notification will be sent when WO generated</span>
          </label>
          <select name="assigned_user_id">
            <option value="">— Unassigned —</option>
            <?php foreach($technicians as $tech): ?>
            <option value="<?= (int)$tech['user_id'] ?>" <?= isset($edit['assigned_user_id']) && (int)$edit['assigned_user_id']===$tech['user_id']?'selected':''; ?>>
              <?= h($tech['tech_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="is_active">
            <option value="1" <?= isset($edit['is_active']) ? ((int)$edit['is_active']===1?'selected':'') : 'selected' ?>>Active</option>
            <option value="0" <?= isset($edit['is_active']) && (int)$edit['is_active']===0 ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>

      <div class="btn-row">
        <button class="btn primary"><?= $edit ? '💾 Update Plan' : '➕ Create Plan' ?></button>
        <?php if ($edit): ?>
          <a class="btn" href="maintenance_plans.php">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Plans Table -->
  <div class="card">
    <h3>📋 All Maintenance Plans (<?= count($plans) ?>)</h3>
    <?php if (!empty($plans)): ?>
    <table>
      <thead>
        <tr>
          <th style="width:120px">Plan Code</th>
          <th>Title</th>
          <th style="width:200px">Task List</th>
          <th style="width:80px">Type</th>
          <th style="width:100px">Cycle</th>
          <th style="width:180px">Equipment/Location</th>
          <th style="width:150px">Assigned Worker</th>
          <th style="width:110px">Next Due</th>
          <th style="width:80px">Status</th>
          <th style="width:200px;text-align:center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($plans as $p): ?>
        <tr>
          <td class="mono" style="font-weight:700;color:var(--primary)"><?= h($p['plan_code']) ?></td>
          <td><?= h($p['title']) ?></td>
          <td>
            <?php if(!empty($p['task_list_code'])): ?>
              <span class="mono"><?= h($p['task_list_code']) ?></span>
              <div class="muted" style="font-size:12px"><?= h($p['tl_title'] ?? '') ?></div>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td><?= h($p['plan_type']) ?></td>
          <td>
            <?php 
              if ($p['plan_type'] === 'TIME' && $p['cycle_days']) {
                echo h($p['cycle_days']) . ' days';
              } elseif ($p['plan_type'] === 'METER' && $p['cycle_meter']) {
                echo h($p['cycle_meter']) . ' units';
              } else {
                echo '<span class="muted">—</span>';
              }
            ?>
          </td>
          <td style="font-size:13px">
            <?php if (!empty($p['equipment_name'])): ?>
              🔧 <?= h($p['equipment_name']) ?><br>
            <?php endif; ?>
            <?php if (!empty($p['floc_name'])): ?>
              📍 <?= h($p['floc_name']) ?>
            <?php endif; ?>
            <?php if (empty($p['equipment_name']) && empty($p['floc_name'])): ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($p['assigned_tech_name'])): ?>
              👤 <?= h($p['assigned_tech_name']) ?>
              <div style="font-size:11px;color:#10b981;margin-top:2px">📧 Will notify</div>
            <?php else: ?>
              <span class="muted">— Unassigned —</span>
            <?php endif; ?>
          </td>
          <td><?= h($p['next_due_date'] ?? '—') ?></td>
          <td>
            <span class="badge <?= (int)$p['is_active']===1?'active':'inactive' ?>">
              <?= (int)$p['is_active']===1?'Active':'Inactive' ?>
            </span>
          </td>
          <td style="text-align:center">
            <div class="btn-row" style="justify-content:center">
              <a class="btn sm" href="maintenance_plans.php?edit=<?= (int)$p['plan_id'] ?>">✏️ Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Generate work order from this plan?\n\n✓ Create new work order\n✓ Copy operations & materials\n✓ Assign to worker\n✓ Send email notification\n✓ Update next due date\n\nProceed?')">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="generate_wos">
                <input type="hidden" name="plan_id" value="<?= (int)$p['plan_id'] ?>">
                <button class="btn sm success" <?= (int)$p['is_active']===0?'disabled title="Plan is inactive"':'' ?>>
                  🔄 Generate WO
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty">
      <strong>No maintenance plans yet.</strong><br>
      Create your first plan above to automate preventive maintenance!
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
function togglePlanType() {
  const type = document.getElementById('plan_type').value;
  const daysField = document.getElementById('cycle_days_field');
  const meterField = document.getElementById('cycle_meter_field');
  const daysInput = daysField.querySelector('input');
  const meterInput = meterField.querySelector('input');
  
  if (type === 'TIME') {
    daysField.style.display = 'block';
    meterField.style.display = 'none';
    daysInput.required = true;
    meterInput.required = false;
  } else {
    daysField.style.display = 'none';
    meterField.style.display = 'block';
    daysInput.required = false;
    meterInput.required = true;
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  togglePlanType();
});
</script>
</body>
</html>