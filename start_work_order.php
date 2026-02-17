<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$work_order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$error = '';
$reason = '';
$wo = null;

if ($work_order_id <= 0) {
    $error = 'Missing work order id. Open this page like: start_work_order.php?id=123';
}

if (!$error) {
    $stmt = $connect->prepare("
        SELECT work_order_id, wo_no, status
        FROM work_order
        WHERE work_order_id = ? AND assigned_user_id = ?
        LIMIT 1
    ");
    if ($stmt === false) {
        $error = 'Database error: failed to prepare statement.';
    } else {
        $stmt->bind_param("ii", $work_order_id, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $wo = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$wo) {
            $reason = 'Work order not found or not assigned to your account.';
        } else {
            $status = strtoupper(trim((string)$wo['status']));
            $can_start = in_array($status, ['CREATED','RELEASED','ASSIGNED'], true);

            if (!$can_start) {
                $reason = 'This work order cannot be started because its current status is: ' . htmlspecialchars($wo['status']);
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_start) {
                $upd = $connect->prepare("
                    UPDATE work_order
                    SET status = 'IN_PROGRESS',
                        actual_start = NOW(),
                        updated_at = NOW()
                    WHERE work_order_id = ?
                      AND assigned_user_id = ?
                      AND UPPER(TRIM(status)) IN ('CREATED','RELEASED','ASSIGNED')
                ");
                if ($upd === false) {
                    $error = 'Database error: failed to prepare update.';
                } else {
                    $upd->bind_param("ii", $work_order_id, $user_id);
                    $upd->execute();
                    $upd->close();
                    header("Location: complete_work_order.php?id={$work_order_id}&started=1");
                    exit;
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Start Work Order - Fix Mate CMMS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f6f7fb;
  --card:#ffffff;
  --ink:#1f2937;
  --muted:#6b7280;
  --line:#e5e7eb;
  --radius:14px;
  --shadow:0 8px 24px rgba(15,23,42,.06);
  --shadow-lg:0 16px 40px rgba(15,23,42,.12);

  /* brand */
  --grad: linear-gradient(135deg, #A88A73 0%, #8B6F5B 100%);
}

*{box-sizing:border-box;margin:0;padding:0}
body{
  background:var(--bg);
  color:var(--ink);
  font-family:"Inter",-apple-system,BlinkMacSystemFont,system-ui,Segoe UI,Roboto,Arial,sans-serif;
  line-height:1.6;
}

/* Top bar (simple) */
.nav{background:#fff;border-bottom:1px solid var(--line)}
.nav-inner{max-width:880px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;justify-content:space-between}
.nav-title{font-size:16px;font-weight:800;color:#2c3e50}
.back-link{
  color:#6B5644; text-decoration:none; font-weight:700;
  border:2px solid #B99B85; padding:8px 16px; border-radius:999px;
  transition:.15s;
}
.back-link:hover{background:#F5F1E8}

/* Page layout */
.container{max-width:880px;margin:0 auto;padding:26px 20px}
.card{
  background:var(--card); border:1px solid var(--line);
  border-radius:var(--radius); box-shadow:var(--shadow);
  padding:34px; text-align:left;
}

/* Headline / description */
h1{font-size:26px;font-weight:900;margin-bottom:8px}
.p-note{color:var(--muted);margin-bottom:20px}

/* Work order box */
.wo-box{
  background:#fafafb;border:1px solid var(--line);
  border-radius:12px; padding:16px 18px; margin:14px 0 22px;
}
.wo-label{font-size:12px;color:#9aa0a6;font-weight:800;text-transform:uppercase;letter-spacing:.3px}
.wo-value{font-size:22px;font-weight:900;color:#2c3e50;margin-top:4px}

/* Checklist */
.list{
  margin:8px 0 22px; padding-left:18px; color:#374151;
}
.list li{margin:8px 0}

/* Alerts */
.alert{
  border-radius:12px; padding:14px 16px; border:1px solid var(--line);
  background:#fff; color:#374151; box-shadow:var(--shadow);
}
.alert--error{border-color:#fecaca;background:#fef2f2;color:#7f1d1d}
.alert--warn{border-color:#fde68a;background:#fffbeb;color:#7c2d12}
.center{display:flex;gap:10px;justify-content:center;margin-top:10px}

/* Buttons */
.btn{
  display:inline-flex; align-items:center; justify-content:center;
  padding:12px 22px; border-radius:10px; font-weight:800; border:none;
  text-decoration:none; cursor:pointer; transition:.15s;
}
.btn-primary{
  background:var(--grad); color:#fff;
  box-shadow:0 4px 12px rgba(168,138,115,.28);
}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(139,111,91,.38)}
.btn-secondary{background:#fff;color:#374151;border:2px solid var(--line)}
.btn-secondary:hover{border-color:#B99B85;color:#6B5644}

/* Button group */
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
.actions .btn{min-width:160px}

.note{
  margin-top:14px; font-size:13px; color:#6b7280;
  background:#fafafb; border:1px solid var(--line); padding:12px 14px; border-radius:10px;
}

@media (max-width:640px){
  .card{padding:26px 18px}
  .actions{flex-direction:column}
  .actions .btn{width:100%}
}
</style>
</head>
<body>

<div class="nav">
  <div class="nav-inner">
    <a href="staff_dashboard.php" class="back-link">Back to Dashboard</a>
    <div class="nav-title">Start Work Order</div>
  </div>
</div>

<div class="container">
  <?php if ($error): ?>
    <div class="card">
      <div class="alert alert--error" style="margin-bottom:14px;">
        <?= htmlspecialchars($error) ?>
      </div>
      <div class="center">
        <a class="btn btn-secondary" href="staff_dashboard.php">Back to Dashboard</a>
      </div>
    </div>
  <?php elseif ($reason): ?>
    <div class="card">
      <h1>Cannot Start</h1>
      <p class="p-note">The work order cannot be started right now.</p>
      <div class="alert alert--warn" style="margin-bottom:14px;">
        <?= $reason ?>
      </div>
      <div class="actions">
        <?php if ($wo): ?>
          <a class="btn btn-secondary" href="view_work_order.php?id=<?= (int)$work_order_id ?>">View Work Order</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="staff_dashboard.php">Back to Dashboard</a>
      </div>
    </div>
  <?php else: ?>
    <div class="card">
      <h1>Ready to start?</h1>
      <p class="p-note">When you start, the system records your start time and moves the work order to “In Progress”.</p>

      <div class="wo-box">
        <div class="wo-label">Work Order Number</div>
        <div class="wo-value"><?= htmlspecialchars($wo['wo_no']) ?></div>
      </div>

      <div>
        <div class="wo-label" style="margin-bottom:6px;">Before you begin</div>
        <ul class="list">
          <li>Review the work order details and task list.</li>
          <li>Gather the required tools and PPE.</li>
          <li>Check materials availability.</li>
          <li>Confirm safety procedures.</li>
        </ul>
      </div>

      <form method="POST">
        <div class="actions">
          <a href="view_work_order.php?id=<?= (int)$work_order_id ?>" class="btn btn-secondary">Review Details</a>
          <button type="submit" class="btn btn-primary">Start Work Order</button>
        </div>
      </form>

      <div class="note">
        Starting will update the status to <strong>In Progress</strong> and begin time tracking.
      </div>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
