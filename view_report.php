<?php
// view_report.php — accepts ?id=<notification_id> OR ?no=<notif_no>

session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$host = 'localhost';
$dbname = 'cmms';
$username = 'root';
$password = '';

$id = (int)($_GET['id'] ?? 0);           // from user_dashboard.php links
$no = trim((string)($_GET['no'] ?? '')); // alternative route

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function status_badge_class($s){
  switch (strtoupper((string)$s)) {
    case 'NEW': return 'status-badge status-new';
    case 'SCREENED': return 'status-badge status-screened';
    case 'APPROVED': return 'status-badge status-approved';
    case 'CLOSED': return 'status-badge status-closed';
    default: return 'status-badge';
  }
}
function priority_badge_class($p){
  switch (strtoupper((string)$p)) {
    case 'LOW': return 'priority-badge priority-low';
    case 'MEDIUM': return 'priority-badge priority-medium';
    case 'HIGH': return 'priority-badge priority-high';
    case 'URGENT': return 'priority-badge priority-urgent';
    default: return 'priority-badge';
  }
}

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
    $username, $password,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
  );

  // --- Fetch current user for navbar display (first/last name) ---
  $uStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ? LIMIT 1");
  $uStmt->execute([$_SESSION['user_id']]);
  $u = $uStmt->fetch() ?: ['first_name' => ($_SESSION['first_name'] ?? 'User'), 'last_name' => ($_SESSION['last_name'] ?? '')];

  // Build WHERE by id or no (with ownership)
  $where = '';
  $params = [];
  if ($id > 0) {
    $where = "n.notification_id = ? AND n.created_by_user_id = ?";
    $params = [$id, $_SESSION['user_id']];
  } elseif ($no !== '') {
    $where = "n.notif_no = ? AND n.created_by_user_id = ?";
    $params = [$no, $_SESSION['user_id']];
  } else {
    http_response_code(400);
    echo "Missing report identifier.";
    exit;
  }

  $stmt = $pdo->prepare("
    SELECT
      n.notification_id, n.notif_no, n.reported_at, n.status, n.priority, n.description,
      n.reporter_name, n.reporter_email, n.equipment_id, n.floc_id,
      fl.floc_code, fl.floc_name,
      e.equipment_code, e.equipment_name
    FROM notification n
    LEFT JOIN functional_location fl ON n.floc_id = fl.floc_id
    LEFT JOIN equipment e ON n.equipment_id = e.equipment_id
    WHERE $where
    LIMIT 1
  ");
  $stmt->execute($params);
  $notif = $stmt->fetch();

  if (!$notif) {
    http_response_code(404);
    echo "Report not found or not authorized.";
    exit;
  }

  // Handle new comment
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_comment') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
      http_response_code(403);
      echo "Invalid CSRF token.";
      exit;
    }
    $comment_text = trim((string)($_POST['comment_text'] ?? ''));
    if ($comment_text !== '') {
      $ins = $pdo->prepare("
        INSERT INTO notification_comment (notification_id, author_user_id, comment_text)
        VALUES (?, ?, ?)
      ");
      $ins->execute([$notif['notification_id'], $_SESSION['user_id'], $comment_text]);
      $redir = 'view_report.php?' . ($id ? 'id=' . urlencode($id) : 'no=' . urlencode($notif['notif_no']));
      header("Location: $redir");
      exit;
    }
  }

  // Load comments
  $cstmt = $pdo->prepare("
    SELECT nc.comment_id, nc.comment_text, nc.created_at,
           u.username, u.first_name, u.last_name
    FROM notification_comment nc
    LEFT JOIN users u ON u.user_id = nc.author_user_id
    WHERE nc.notification_id = ?
    ORDER BY nc.created_at ASC, nc.comment_id ASC
  ");
  $cstmt->execute([$notif['notification_id']]);
  $comments = $cstmt->fetchAll();

  $status = $notif['status'] ?: 'NEW';
  $priority = $notif['priority'] ?: 'MEDIUM';

} catch (PDOException $e) {
  http_response_code(500);
  echo "Database error: " . e($e->getMessage());
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Report <?= e($notif['notif_no']) ?> • CMMS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- Top Navigation (matches your dashboard) -->
  <div class="navbar">
    <div class="navbar-left">
      <span style="font-size: 24px;">🔧</span>
      <h1>Campus CMMS</h1>
    </div>
    <div class="user-info">
      <span class="user-name">
        <span>👤</span>
        <?= e($u['first_name'] ?? ($_SESSION['first_name'] ?? 'User')) ?>
        <?= e($u['last_name']  ?? ($_SESSION['last_name']  ?? '')) ?>
      </span>
      <a href="user_dashboard.php" class="btn-logout">Dashboard</a>
      <a href="logout.php" class="btn-logout">Logout</a>
    </div>
  </div>

  <div class="container">

    <div class="breadcrumb">
      <a href="user_dashboard.php">Dashboard</a> &nbsp;/&nbsp;
      <span>Report <?= e($notif['notif_no']) ?></span>
    </div>

    <div class="page-header">
      <h1>Report #<?= e($notif['notif_no']) ?></h1>
      <p class="date-time">
        Reported at: <?= date('M d, Y h:i A', strtotime($notif['reported_at'])) ?>
      </p>
    </div>

    <div class="form-card">
      <div class="form-row">
        <div class="form-group">
          <label>Status</label>
          <div><span class="<?= status_badge_class($status) ?>"><?= e($status) ?></span></div>
        </div>
        <div class="form-group">
          <label>Priority</label>
          <div><span class="<?= priority_badge_class($priority) ?>"><?= e($priority) ?></span></div>
        </div>
        <div class="form-group">
          <label>Functional Location</label>
          <div class="report-id">
            <?= e(trim(($notif['floc_code'] ?? '') . ' ' . ($notif['floc_name'] ?? ''))) ?: '—' ?>
          </div>
        </div>
        <div class="form-group">
          <label>Equipment</label>
          <div class="report-id">
            <?= e(trim(($notif['equipment_code'] ?? '') . ' ' . ($notif['equipment_name'] ?? ''))) ?: '—' ?>
          </div>
        </div>
        <div class="form-group">
          <label>Reporter Name</label>
          <div><?= e($notif['reporter_name'] ?? '') ?: '—' ?></div>
        </div>
        <div class="form-group">
          <label>Reporter Email</label>
          <div><?= e($notif['reporter_email'] ?? '') ?: '—' ?></div>
        </div>
        <div class="form-group full-width">
          <label>Description</label>
          <div><?= nl2br(e($notif['description'] ?? '')) ?></div>
        </div>
      </div>

      <div class="form-actions">
        <a class="btn btn-view" href="user_dashboard.php">Back to Dashboard</a>
        <a class="btn btn-edit" href="edit_report.php?id=<?= urlencode($notif['notification_id']) ?>">Edit</a>
        <form method="POST" action="delete_report.php" onsubmit="return confirm('Delete this report? This cannot be undone.');" style="display:inline">
          <input type="hidden" name="id" value="<?= e($notif['notification_id']) ?>">
          <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
          <button type="submit" class="btn btn-delete">Delete</button>
        </form>
      </div>
    </div>

    <div class="form-card" style="margin-top:20px;">
      <div class="section-title">Comments</div>

      <?php if (!$comments): ?>
        <div class="no-data">
          <div class="no-data-icon">💬</div>
          No comments yet.
        </div>
      <?php else: ?>
        <?php foreach ($comments as $c): ?>
          <div class="form-group" style="border-bottom:2px solid #f3f4f6; padding-bottom:12px; margin-bottom:12px;">
            <div style="font-weight:600; color:#374151;">
              <?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['username'] ?? 'User')) ?>
              <span class="date-time" style="margin-left:8px;"><?= date('M d, Y h:i A', strtotime($c['created_at'])) ?></span>
            </div>
            <div style="margin-top:6px;"><?= nl2br(e($c['comment_text'])) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <form method="POST" class="form-group" style="margin-top:10px;">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="add_comment">
        <label for="comment_text">Add Comment</label>
        <textarea id="comment_text" name="comment_text" rows="3" placeholder="Write an update or note..." required></textarea>
        <div class="form-actions" style="justify-content:flex-start;">
          <button class="btn btn-primary" type="submit">Post Comment</button>
          <a class="btn btn-secondary" href="user_dashboard.php">Cancel</a>
        </div>
      </form>
    </div>

  </div>
</body>
</html>
