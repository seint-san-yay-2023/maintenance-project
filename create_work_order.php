<?php
// ===================== Create Work Order (from Notification) =====================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

// Auth: planner only
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'planner')) {
  header('Location: login.php'); exit;
}
$planner_id = (int)$_SESSION['user_id'];

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok($t){ return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); }

function next_wo_no(PDO $pdo): string {
  $year = date('Y');
  $q = $pdo->prepare("SELECT MAX(wo_no) AS max_no FROM work_order WHERE wo_no LIKE ?");
  $q->execute(["WO{$year}-%"]);
  $max = $q->fetch()['max_no'] ?? null;
  $seq = 0;
  if ($max && preg_match('/WO'.$year.'-(\d{4})$/', $max, $m)) $seq = (int)$m[1];
  return sprintf("WO%s-%04d", $year, $seq + 1);
}

// ⭐ IMPROVED n8n webhook function with better error handling
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

$nid = (int)($_GET['notification_id'] ?? $_POST['notification_id'] ?? 0);
if (!$nid) { header('Location: notifications.php'); exit; }

// Load notification (+joins for context)
$notif = null;
try {
  $st = $pdo->prepare("
    SELECT n.*, e.equipment_name, e.equipment_id, e.work_center_id,
           fl.floc_name, wc.wc_name
    FROM notification n
    LEFT JOIN equipment e ON e.equipment_id = n.equipment_id
    LEFT JOIN functional_location fl ON fl.floc_id = n.floc_id
    LEFT JOIN work_center wc ON wc.work_center_id = e.work_center_id
    WHERE n.notification_id = ? LIMIT 1
  ");
  $st->execute([$nid]);
  $notif = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

if (!$notif) { header('Location: notifications.php'); exit; }

// ================= Technicians = Maintenance Staff =================
$technicians = [];
try {
  $q = $pdo->prepare("
    SELECT
      u.user_id,
      COALESCE(
        NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''),
        NULLIF(u.username,''), u.email
      ) AS tech_name,
      u.email,
      u.first_name,
      u.last_name,
      ed.work_center_id
    FROM users u
    LEFT JOIN employee_details ed ON ed.user_id = u.user_id
    WHERE u.is_active = 1
      AND u.role = 'technician'
    ORDER BY 
      CASE 
        WHEN ed.work_center_id = :wc_id THEN 1
        WHEN ed.work_center_id IS NULL THEN 2
        ELSE 3
      END,
      tech_name ASC
  ");
  $q->execute([':wc_id' => $notif['work_center_id'] ?? null]);
  $technicians = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $technicians = [];
}

// ================= FIXED: Show ALL Task Lists with Smart Sorting =================
$taskLists = [];
try {
  $q = $pdo->prepare("
    SELECT tl.task_list_id,
           tl.task_list_code,
           tl.title,
           tl.equipment_id,
           tl.work_center_id,
           e.equipment_name,
           wc.wc_name,
           CASE
             WHEN tl.equipment_id = :eq_id THEN 4
             WHEN tl.work_center_id = :wc_id THEN 3
             WHEN tl.equipment_id IS NULL AND tl.work_center_id IS NULL THEN 2
             ELSE 1
           END as match_priority
    FROM task_list tl
    LEFT JOIN equipment   e  ON e.equipment_id    = tl.equipment_id
    LEFT JOIN work_center wc ON wc.work_center_id = tl.work_center_id
    WHERE COALESCE(tl.is_active,1) = 1
    ORDER BY match_priority DESC, tl.task_list_code ASC
  ");
  $q->execute([
    ':eq_id' => (int)($notif['equipment_id'] ?? 0),
    ':wc_id' => (int)($notif['work_center_id'] ?? 0),
  ]);
  $taskLists = $q->fetchAll(PDO::FETCH_ASSOC);
  
} catch (Throwable $e) {
  error_log("Task list query error: " . $e->getMessage());
  $taskLists = [];
}

// Build task list data for JavaScript
$taskListData = [];
foreach ($taskLists as $tl) {
  try {
    // Get operations
    $ops = $pdo->prepare("SELECT op_seq, description, std_time_min, safety_notes FROM task_list_operation WHERE task_list_id = ? ORDER BY op_seq");
    $ops->execute([$tl['task_list_id']]);
    $operations = $ops->fetchAll(PDO::FETCH_ASSOC);
    
    // Get materials
    $mats = $pdo->prepare("
      SELECT tlm.quantity, m.material_code, m.material_name, m.unit_of_measure
      FROM task_list_material tlm
      JOIN material m ON m.material_id = tlm.material_id
      WHERE tlm.task_list_id = ?
      ORDER BY m.material_code
    ");
    $mats->execute([$tl['task_list_id']]);
    $materials = $mats->fetchAll(PDO::FETCH_ASSOC);
    
    $total_time = 0;
    foreach ($operations as $op) {
      $total_time += (int)($op['std_time_min'] ?? 0);
    }
    
    $taskListData[$tl['task_list_id']] = [
      'operations' => $operations,
      'materials' => $materials,
      'total_time_min' => $total_time
    ];
  } catch (Throwable $e) {
    $taskListData[$tl['task_list_id']] = ['operations' => [], 'materials' => [], 'total_time_min' => 0];
  }
}

// Handle submit
$flash = '';
$flashType = 'error';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create' && csrf_ok($_POST['csrf'] ?? '')) {
  $task_list_id     = (int)($_POST['task_list_id'] ?? 0);
  $planned_hours    = (float)($_POST['planned_hours'] ?? 0);
  $assigned_user_id = (int)($_POST['assigned_user_id'] ?? 0);
  $priority         = in_array(($_POST['priority'] ?? ''), ['LOW','MEDIUM','HIGH','URGENT']) 
                      ? $_POST['priority'] 
                      : ($notif['priority'] ?: 'MEDIUM');
  $requested_end = !empty($_POST['due_date']) ? ($_POST['due_date'].' 23:59:59') : null;
  $summary          = trim($_POST['problem_note'] ?? ($notif['description'] ?: 'WO from notification '.$notif['notif_no']));

  try {
    $pdo->beginTransaction();

    // Ensure notification is APPROVED
    if ($notif['status'] !== 'APPROVED') {
      $pdo->prepare("UPDATE notification SET status='APPROVED' WHERE notification_id=?")->execute([$nid]);
      $notif['status'] = 'APPROVED';
    }

    // Create WO
    $wo_no = next_wo_no($pdo);
    $ins = $pdo->prepare("
      INSERT INTO work_order
        (wo_no, source, notification_id, plan_id, task_list_id,
         equipment_id, floc_id, work_center_id,
         planner_user_id, assigned_user_id,
         status, priority, requested_start, requested_end,
         actual_start, actual_end,
         problem_note, resolution_note,
         created_at, updated_at)
      VALUES
        (?, 'NOTIFICATION', ?, NULL, ?,
         ?, ?, ?,
         ?, ?,
         'CREATED', ?, NULL, ?,
         NULL, NULL,
         ?, NULL,
         NOW(), NOW())
    ");

    $ins->execute([
      $wo_no, 
      $nid, 
      $task_list_id > 0 ? $task_list_id : null,
      $notif['equipment_id'], 
      $notif['floc_id'], 
      $notif['work_center_id'],
      $planner_id, 
      $assigned_user_id > 0 ? $assigned_user_id : null,
      $priority,
      $requested_end,
      $summary
    ]);

    $work_order_id = (int)$pdo->lastInsertId();

    // Calculate planned hours from task list if not manually entered
    if ($task_list_id > 0 && $planned_hours <= 0) {
      $sum = $pdo->prepare("SELECT COALESCE(SUM(std_time_min),0) FROM task_list_operation WHERE task_list_id = ?");
      $sum->execute([$task_list_id]);
      $tot_min = (int)$sum->fetchColumn();
      $planned_hours = round($tot_min / 60, 2);
    }

    // Insert planned hours into work_order_labor
    if ($planned_hours > 0) {
      try {
        $pdo->prepare("INSERT INTO work_order_labor (work_order_id, planned_hours) VALUES (?, ?)")
            ->execute([$work_order_id, $planned_hours]);
      } catch (Throwable $e) { /* table might not exist */ }
    }

    // Copy operations and materials from task list if selected
    if ($task_list_id > 0) {
      // Copy operations
      try {
        $stOps = $pdo->prepare("
          SELECT op_seq, description, std_time_min, safety_notes
          FROM task_list_operation
          WHERE task_list_id = ?
          ORDER BY op_seq ASC
        ");
        $stOps->execute([$task_list_id]);
        $ops = $stOps->fetchAll(PDO::FETCH_ASSOC);

        if ($ops) {
          $insOp = $pdo->prepare("
            INSERT INTO work_order_operation
              (work_order_id, op_seq, description, std_time_min, safety_notes)
            VALUES (?, ?, ?, ?, ?)
          ");
          foreach ($ops as $op) {
            $insOp->execute([
              $work_order_id,
              (int)$op['op_seq'],
              (string)($op['description'] ?? ''),
              (int)($op['std_time_min'] ?? 0),
              (string)($op['safety_notes'] ?? '')
            ]);
          }
        }
      } catch (Throwable $e) { /* ignore if table doesn't exist */ }

      // Copy materials
      try {
        $stMat = $pdo->prepare("
          SELECT material_id, quantity
          FROM task_list_material
          WHERE task_list_id = ?
        ");
        $stMat->execute([$task_list_id]);
        $mats = $stMat->fetchAll(PDO::FETCH_ASSOC);

        if ($mats) {
          $insMat = $pdo->prepare("
            INSERT INTO work_order_material
              (work_order_id, material_id, quantity, issued_by_user_id)
            VALUES (?, ?, ?, ?)
          ");
          foreach ($mats as $m) {
            $insMat->execute([
              $work_order_id,
              (int)$m['material_id'],
              (float)($m['quantity'] ?? 1),
              $planner_id
            ]);
          }
        }
      } catch (Throwable $e) { /* ignore if table doesn't exist */ }
    }

    $pdo->commit();
    
    // ⭐⭐⭐ SEND NOTIFICATION TO n8n WEBHOOK ⭐⭐⭐
    // Get assigned user details for notification
    $assignedEmail = null;
    $assignedName = 'Unassigned';
    $assignedFirstName = null;
    $assignedLastName = null;
    
    if ($assigned_user_id > 0) {
      try {
        $userQuery = $pdo->prepare("
          SELECT email, first_name, last_name, 
                 COALESCE(CONCAT(first_name, ' ', last_name), username, email) as full_name
          FROM users 
          WHERE user_id = ?
        ");
        $userQuery->execute([$assigned_user_id]);
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
    
    // Get task list name if applicable
    $taskListName = null;
    if ($task_list_id > 0) {
      try {
        $tlQuery = $pdo->prepare("SELECT title FROM task_list WHERE task_list_id = ?");
        $tlQuery->execute([$task_list_id]);
        $tlData = $tlQuery->fetch(PDO::FETCH_ASSOC);
        $taskListName = $tlData['title'] ?? null;
      } catch (Throwable $e) {}
    }
    
    // Prepare comprehensive webhook payload
   // ⭐⭐⭐ SEND NOTIFICATION TO n8n WEBHOOK ⭐⭐⭐
// Set timezone (adjust to your timezone)
date_default_timezone_set('Asia/Bangkok'); // or 'Asia/Yangon' for Myanmar

// Calculate dates for calendar event
$now = new DateTime();
$startDate = clone $now;
$startDate->modify('+1 hour'); // Start 1 hour from now

// If there's a due date, use it as end date
if ($requested_end) {
    $endDate = new DateTime($requested_end);
} else {
    // Default to 2 hours after start if no due date
    $endDate = clone $startDate;
    $endDate->modify('+2 hours');
}

// Format dates in ISO 8601 format with timezone
$calendarStartDate = $startDate->format('c'); // e.g., 2025-10-19T14:30:00+07:00
$calendarEndDate = $endDate->format('c');     // e.g., 2025-10-20T23:59:59+07:00

// Prepare comprehensive webhook payload
$webhookData = [
    // Work Order Details
    'work_order_id' => $work_order_id,
    'wo_no' => $wo_no,
    'status' => 'CREATED',
    'priority' => $priority,
    'source' => 'NOTIFICATION',
    
    // Equipment & Location
    'equipment_id' => $notif['equipment_id'],
    'equipment_name' => $notif['equipment_name'] ?? 'N/A',
    'location_id' => $notif['floc_id'],
    'location_name' => $notif['floc_name'] ?? 'N/A',
    'work_center_id' => $notif['work_center_id'],
    'work_center_name' => $notif['wc_name'] ?? 'N/A',
    
    // Task & Time
    'task_list_id' => $task_list_id > 0 ? $task_list_id : null,
    'task_list_name' => $taskListName,
    'planned_hours' => $planned_hours,
    'due_date' => $requested_end,
    
    // ⭐ CALENDAR EVENT DATES - Properly Formatted
    'calendar_start_date' => $calendarStartDate,  // ISO 8601 with timezone
    'calendar_end_date' => $calendarEndDate,      // ISO 8601 with timezone
    'calendar_timezone' => 'Asia/Bangkok',         // Explicit timezone
    
    // People
    'planner_user_id' => $planner_id,
    'assigned_user_id' => $assigned_user_id > 0 ? $assigned_user_id : null,
    'assigned_email' => $assignedEmail,
    'assigned_name' => $assignedName,
    'assigned_first_name' => $assignedFirstName,
    'assigned_last_name' => $assignedLastName,
    
    // Description
    'description' => $summary,
    'problem_note' => $summary,
    
    // ⭐ CALENDAR EVENT DETAILS
    'calendar_summary' => "Work Order: {$wo_no} - {$notif['equipment_name']}",
    'calendar_description' => "Priority: {$priority}\nEquipment: {$notif['equipment_name']}\nLocation: {$notif['floc_name']}\n\nDescription:\n{$summary}",
    
    // Original Notification
    'notification_id' => $nid,
    'notification_no' => $notif['notif_no'],
    'notification_reporter' => $notif['reporter_name'] ?? 'Unknown',
    'notification_reporter_email' => $notif['reporter_email'] ?? null,
    
    // Timestamps
    'created_at' => date('Y-m-d H:i:s'),
    'created_date' => date('Y-m-d'),
    'created_time' => date('H:i:s'),
    
    // URLs for linking back
    'work_order_url' => "http://{$_SERVER['HTTP_HOST']}/cmms/admin/work_order_details.php?id={$work_order_id}",
    
    // System info
    'system' => 'CMMS',
    'event_type' => 'work_order_created'
];

// Send to n8n webhook
try {
    $webhookResult = sendToN8nWebhook($webhookData);
    
    if (!$webhookResult['success']) {
        $_SESSION['webhook_warning'] = "Work order created but notification failed to send.";
    }
} catch (Throwable $e) {
    error_log("Webhook exception: " . $e->getMessage());
}
    
    // Redirect to work order details
    header('Location: work_order_details.php?id='.(int)$work_order_id);
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $flash = "Create failed: " . htmlspecialchars($e->getMessage());
    $flashType = 'error';
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Work Order</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--line:#e5e7eb;--muted:#6b7280;--danger:#ef4444;--success:#10b981;--primary:#2563eb}
  body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial;color:#0f172a;background:#f9fafb}
  header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--line);background:#fff}
  .wrap{max-width:1100px;margin:18px auto;padding:0 16px}
  .card{border:1px solid var(--line);border-radius:12px;background:#fff;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  label{font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px}
  select,input,textarea{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;font-size:14px;transition:border-color 0.2s}
  select:focus,input:focus,textarea:focus{outline:none;border-color:#2563eb}
  .row{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
  .btn{padding:10px 20px;border:1px solid var(--line);border-radius:8px;background:#fff;font-weight:600;cursor:pointer;font-size:14px;transition:all 0.2s}
  .btn:hover{background:#f9fafb}
  .btn.primary{background:#2563eb;color:#fff;border-color:#2563eb}
  .btn.primary:hover{background:#1d4ed8}
  .flash{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px}
  .flash.error{background:#fee;border:1px solid #fca5a5;color:#991b1b}
  .flash.success{background:#d1fae5;border:1px solid #a7f3d0;color:#065f46}
  .flash.warning{background:#fef3c7;border:1px solid #fcd34d;color:#92400e}
  .muted{color:var(--muted);font-size:13px}
  .notif-box{background:#f8fafc;border:1px solid var(--line);border-radius:8px;padding:14px;margin-bottom:16px}
  .notif-box .title{font-weight:700;font-size:15px;color:#0f172a;margin-bottom:6px}
  .notif-box .desc{color:#64748b;font-size:13px;margin-top:6px}
  .info-row{display:flex;gap:20px;margin-top:8px;flex-wrap:wrap}
  .info-item{font-size:13px;color:#64748b}
  .info-item strong{color:#374151;font-weight:600}
  .preview-box{display:none;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px;margin-top:12px;grid-column:1/-1}
  .preview-box.show{display:block}
  .preview-title{font-weight:700;font-size:14px;color:#0369a1;margin-bottom:12px;display:flex;align-items:center;gap:6px}
  .preview-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
  .preview-section h4{font-size:13px;font-weight:700;color:#1e3a8a;margin-bottom:8px}
  .preview-section ul{margin:0;padding-left:20px;font-size:13px;line-height:1.8}
  .preview-section li{color:#334155}
  .preview-section .empty{color:#64748b;font-style:italic;padding-left:0}
  .time-badge{background:#eff6ff;color:#1e40af;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600}
</style>
</head>
<body>
<header>
  <a href="notifications.php" class="btn">← Back</a>
  <div style="font-weight:800;font-size:18px">Create Work Order</div>
  <div></div>
</header>

<div class="wrap">
  <?php if ($flash): ?>
    <div class="flash <?= $flashType ?>"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['webhook_warning'])): ?>
    <div class="flash warning">
      ⚠️ <?= htmlspecialchars($_SESSION['webhook_warning']) ?>
    </div>
    <?php unset($_SESSION['webhook_warning']); ?>
  <?php endif; ?>

  <div class="notif-box">
    <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">From Notification</div>
    <div class="title"><?= htmlspecialchars($notif['notif_no']) ?></div>
    <div class="desc"><?= htmlspecialchars($notif['description'] ?? 'No description') ?></div>
    <div class="info-row">
      <div class="info-item">
        <strong>Equipment:</strong> <?= htmlspecialchars($notif['equipment_name'] ?? '—') ?>
      </div>
      <?php if (!empty($notif['floc_name'])): ?>
        <div class="info-item">
          <strong>Location:</strong> <?= htmlspecialchars($notif['floc_name']) ?>
        </div>
      <?php endif; ?>
      <div class="info-item">
        <strong>Priority:</strong> <?= htmlspecialchars($notif['priority'] ?: 'MEDIUM') ?>
      </div>
    </div>
  </div>

  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <input type="hidden" name="notification_id" value="<?= (int)$nid ?>">
    <input type="hidden" name="action" value="create">

    <div class="grid">
      <div style="grid-column:1/-1">
        <label>Task List (optional) 
          <?php if (!empty($taskLists)): ?>
            <span style="color:var(--success);font-weight:normal;font-size:12px">✓ <?= count($taskLists) ?> task list(s) available</span>
          <?php endif; ?>
        </label>
        <select name="task_list_id" id="taskListSelect" onchange="showTaskListPreview(this.value)">
          <option value="0">— None — (No task list)</option>
          
          <?php 
          // Separate task lists by relevance
          $exact_match = [];      // Same equipment
          $wc_match = [];         // Same work center  
          $generic = [];          // Generic (no equipment/wc)
          $other = [];            // Other equipment/wc
          
          foreach ($taskLists as $tl) {
            // Exact equipment match
            if ($tl['equipment_id'] == $notif['equipment_id'] && $tl['equipment_id'] !== null) {
              $exact_match[] = $tl;
            }
            // Work center match (but not equipment match)
            elseif ($tl['work_center_id'] == $notif['work_center_id'] && $tl['work_center_id'] !== null) {
              $wc_match[] = $tl;
            }
            // Generic (can be used for any equipment)
            elseif ($tl['equipment_id'] === null && $tl['work_center_id'] === null) {
              $generic[] = $tl;
            }
            // Other specific equipment/work center
            else {
              $other[] = $tl;
            }
          }
          ?>
          
          <?php if (!empty($exact_match)): ?>
            <optgroup label="⭐ Perfect Match - For This Equipment (<?= count($exact_match) ?>)">
              <?php foreach ($exact_match as $tl): ?>
                <option value="<?= (int)$tl['task_list_id'] ?>" style="font-weight:700;color:#059669">
                  <?= htmlspecialchars($tl['task_list_code'] . ' · ' . $tl['title']) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
          
          <?php if (!empty($wc_match)): ?>
            <optgroup label="🔧 Work Center Match (<?= count($wc_match) ?>)">
              <?php foreach ($wc_match as $tl): ?>
                <option value="<?= (int)$tl['task_list_id'] ?>">
                  <?= htmlspecialchars($tl['task_list_code'] . ' · ' . $tl['title']) ?>
                  <?php if ($tl['equipment_name']): ?>
                    (for <?= htmlspecialchars($tl['equipment_name']) ?>)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
          
          <?php if (!empty($generic)): ?>
            <optgroup label="📋 Generic - Any Equipment (<?= count($generic) ?>)">
              <?php foreach ($generic as $tl): ?>
                <option value="<?= (int)$tl['task_list_id'] ?>">
                  <?= htmlspecialchars($tl['task_list_code'] . ' · ' . $tl['title']) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
          
          <?php if (!empty($other)): ?>
            <optgroup label="📦 Other Task Lists (<?= count($other) ?>)">
              <?php foreach ($other as $tl): ?>
                <option value="<?= (int)$tl['task_list_id'] ?>" style="color:#6b7280">
                  <?= htmlspecialchars($tl['task_list_code'] . ' · ' . $tl['title']) ?>
                  <?php if ($tl['equipment_name']): ?>
                    (for <?= htmlspecialchars($tl['equipment_name']) ?>)
                  <?php elseif ($tl['wc_name']): ?>
                    (<?= htmlspecialchars($tl['wc_name']) ?>)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
        </select>
        <small class="muted" style="display:block;margin-top:4px">
          <?php if (!empty($exact_match)): ?>
            <span style="color:#10b981;font-weight:600">✓ <?= count($exact_match) ?> task list(s) specifically for <?= htmlspecialchars($notif['equipment_name'] ?? 'this equipment') ?></span>
          <?php elseif (!empty($wc_match)): ?>
            <span style="color:#2563eb;font-weight:600">✓ <?= count($wc_match) ?> task list(s) for this work center</span>
          <?php elseif (!empty($generic)): ?>
            <span style="color:#6b7280"><?= count($generic) ?> generic task list(s) available</span>
          <?php elseif (!empty($other)): ?>
            <span style="color:#f59e0b">⚠ <?= count($other) ?> task list(s) from other equipment available</span>
          <?php else: ?>
            <span style="color:#dc2626">⚠ No task lists found. <a href="task_lists.php" style="color:#dc2626;text-decoration:underline">Create one</a></span>
          <?php endif; ?>
          <?php if (count($taskLists) > 0): ?>
            · <span style="color:#64748b"><?= count($taskLists) ?> total available</span>
          <?php endif; ?>
        </small>
      </div>

      <!-- Task List Preview -->
      <div id="taskListPreview" class="preview-box">
        <div class="preview-title">
          📋 Task List Preview: <span id="previewTaskListName"></span>
        </div>
        <div class="preview-grid">
          <div class="preview-section">
            <h4>Operations (<span id="opsCount">0</span>)</h4>
            <ul id="opsList"></ul>
          </div>
          <div class="preview-section">
            <h4>🔧 Materials (<span id="matsCount">0</span>)</h4>
            <ul id="matsList"></ul>
          </div>
        </div>
        <div style="margin-top:12px;font-size:13px;color:#0369a1">
          ⏱ Total estimated time: <span id="totalTime" class="time-badge">0 min</span>
        </div>
      </div>

      <div>
        <label>Assign Technician (optional) 
          <span style="color:#10b981;font-weight:normal;font-size:12px">
            <?php if ($assigned_user_id ?? 0): ?>
              ✉️ Email notification will be sent
            <?php else: ?>
              ℹ️ Select to send email notification
            <?php endif; ?>
          </span>
        </label>
        <select name="assigned_user_id">
          <option value="0">— Unassigned —</option>
          <?php foreach ($technicians as $t): ?>
            <option value="<?= (int)$t['user_id'] ?>">
              <?= htmlspecialchars($t['tech_name'] ?? ('User #'.$t['user_id'])) ?>
              <?php if ($t['work_center_id'] == $notif['work_center_id']): ?>
                ✓ (Same WC)
              <?php endif; ?>
              <?php if (!empty($t['email'])): ?>
                - <?= htmlspecialchars($t['email']) ?>
              <?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="muted" style="display:block;margin-top:4px">Technicians from the same work center shown first. Email notification will be sent automatically via n8n.</small>
      </div>

      <div>
        <label>Planned Hours (optional)</label>
        <input type="number" name="planned_hours" step="0.25" min="0" placeholder="Auto-calculated from task list">
        <small class="muted" style="display:block;margin-top:4px">Leave blank to auto-calculate from operations</small>
      </div>

      <div>
        <label>Priority</label>
        <select name="priority">
          <?php foreach (['LOW','MEDIUM','HIGH','URGENT'] as $p): ?>
            <option value="<?= $p ?>" <?= ($p===($notif['priority'] ?: 'MEDIUM'))?'selected':'' ?>><?= $p ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Due Date (optional)</label>
        <input type="date" name="due_date">
      </div>

      <div style="grid-column:1/-1">
        <label>Work Summary</label>
        <textarea name="problem_note" rows="4" placeholder="Describe the work to be performed"><?= htmlspecialchars($notif['description'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="row">
      <button class="btn" type="button" onclick="location.href='notifications.php'">Cancel</button>
      <button class="btn primary" type="submit">🚀 Create Work Order & Send Notification</button>
    </div>
  </form>
</div>

<script>
// Task list data embedded from PHP
const taskListData = <?= json_encode($taskListData) ?>;

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function showTaskListPreview(taskListId) {
  const preview = document.getElementById('taskListPreview');
  
  if (!taskListId || taskListId === '0') {
    preview.classList.remove('show');
    return;
  }
  
  const data = taskListData[taskListId];
  if (!data) {
    preview.classList.remove('show');
    return;
  }
  
  preview.classList.add('show');
  
  // Get task list name
  const select = document.getElementById('taskListSelect');
  const selectedOption = select.options[select.selectedIndex];
  document.getElementById('previewTaskListName').textContent = selectedOption.text.split(' · ')[1] || selectedOption.text;
  
  // Operations
  const opsList = document.getElementById('opsList');
  const opsCount = document.getElementById('opsCount');
  opsCount.textContent = data.operations.length;
  
  if (data.operations.length > 0) {
    opsList.innerHTML = data.operations.map(op => {
      let html = `<li><strong>${escapeHtml(op.op_seq)}.</strong> ${escapeHtml(op.description)}`;
      if (op.std_time_min > 0) {
        html += ` <span style="color:#64748b">(${op.std_time_min} min)</span>`;
      }
      if (op.safety_notes) {
        html += `<br><span style="color:#f59e0b;font-size:12px">⚠️ ${escapeHtml(op.safety_notes)}</span>`;
      }
      html += '</li>';
      return html;
    }).join('');
  } else {
    opsList.innerHTML = '<li class="empty">No operations defined</li>';
  }
  
  // Materials
  const matsList = document.getElementById('matsList');
  const matsCount = document.getElementById('matsCount');
  matsCount.textContent = data.materials.length;
  
  if (data.materials.length > 0) {
    matsList.innerHTML = data.materials.map(mat => 
      `<li><strong>${escapeHtml(mat.material_code)}</strong> - ${escapeHtml(mat.material_name)} 
       <span style="color:#64748b">(Qty: ${mat.quantity} ${mat.unit_of_measure || ''})</span></li>`
    ).join('');
  } else {
    matsList.innerHTML = '<li class="empty">No materials required</li>';
  }
  
  // Total time
  const totalTime = document.getElementById('totalTime');
  totalTime.textContent = `${data.total_time_min} min (≈ ${(data.total_time_min/60).toFixed(2)} hrs)`;
}
</script>
</body>
</html>