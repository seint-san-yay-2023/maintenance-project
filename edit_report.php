<?php
// edit_report.php — schema-aligned to your cmms dump
// - Editable fields: priority, description, floc_id, equipment_id
// - Accepts ?id=<notification_id> OR ?no=<notif_no>
// - Enforces ownership: created_by_user_id = $_SESSION['user_id']
// - Includes a small JSON endpoint for equipment by location (?ajax=equip&floc_id=)

session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$host='localhost'; $dbname='cmms'; $username='root'; $password='';

// Accept both URL styles
$id = (int)($_GET['id'] ?? 0);                 // from dashboard links
$no = trim((string)($_GET['no'] ?? ''));       // alternative by notif_no

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
    $username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
  );

  // --------- Lightweight JSON endpoint for equipment dropdown ---------
  if (isset($_GET['ajax']) && $_GET['ajax'] === 'equip') {
    $flocId = (int)($_GET['floc_id'] ?? 0);
    $out = [];
    if ($flocId > 0) {
      $stmt = $pdo->prepare("
        SELECT equipment_id, equipment_code, equipment_name
        FROM equipment
        WHERE floc_id = ? AND status = 'ACTIVE'
        ORDER BY equipment_name
      ");
      $stmt->execute([$flocId]);
      $out = $stmt->fetchAll();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out);
    exit;
  }

  // --------- Load the notification (must belong to current user) ---------
  $where = ''; $params = [];
  if ($id > 0) {
    $where = "n.notification_id = ? AND n.created_by_user_id = ?";
    $params = [$id, $_SESSION['user_id']];
  } elseif ($no !== '') {
    $where = "n.notif_no = ? AND n.created_by_user_id = ?";
    $params = [$no, $_SESSION['user_id']];
  } else {
    header('Location: user_dashboard.php?err=Missing%20report%20identifier');
    exit();
  }

  $q = $pdo->prepare("
    SELECT 
      n.notification_id, n.notif_no, n.priority, n.description,
      n.floc_id, n.equipment_id
    FROM notification n
    WHERE $where
    LIMIT 1
  ");
  $q->execute($params);
  $n = $q->fetch();

  if (!$n) {
    header('Location: user_dashboard.php?err=Not%20found%20or%20not%20yours.');
    exit();
  }

  // --------- Dropdown data ---------
  $locs = $pdo->query("
    SELECT floc_id, floc_code, floc_name
    FROM functional_location
    WHERE is_active = 1
    ORDER BY floc_name
  ")->fetchAll();

  $equip = [];
  if (!empty($n['floc_id'])) {
    $eq = $pdo->prepare("
      SELECT equipment_id, equipment_code, equipment_name
      FROM equipment
      WHERE floc_id = ? AND status = 'ACTIVE'
      ORDER BY equipment_name
    ");
    $eq->execute([$n['floc_id']]);
    $equip = $eq->fetchAll();
  }

  // --------- Handle POST (update) ---------
  $error = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $PRIORITIES = ['LOW','MEDIUM','HIGH','URGENT'];

    $floc_id      = ($_POST['floc_id'] ?? '') !== '' ? (int)$_POST['floc_id'] : null;
    $equipment_id = ($_POST['equipment_id'] ?? '') !== '' ? (int)$_POST['equipment_id'] : null;
    $priority     = strtoupper(trim((string)($_POST['priority'] ?? 'MEDIUM')));
    if (!in_array($priority, $PRIORITIES, true)) $priority = 'MEDIUM';
    $description  = trim((string)($_POST['description'] ?? ''));

    if ($floc_id === null || $description === '') {
      $error = 'Please fill required fields.';
    } else {
      if ($id > 0) {
        $u = $pdo->prepare("
          UPDATE notification
          SET priority = ?, description = ?, equipment_id = ?, floc_id = ?
          WHERE notification_id = ? AND created_by_user_id = ?
        ");
        $u->execute([$priority, $description, $equipment_id, $floc_id, $id, $_SESSION['user_id']]);
      } else {
        $u = $pdo->prepare("
          UPDATE notification
          SET priority = ?, description = ?, equipment_id = ?, floc_id = ?
          WHERE notif_no = ? AND created_by_user_id = ?
        ");
        $u->execute([$priority, $description, $equipment_id, $floc_id, $no, $_SESSION['user_id']]);
      }
      header('Location: user_dashboard.php?ok=Report%20updated');
      exit();
    }

    // Rehydrate values on validation error
    $n['floc_id']      = $floc_id;
    $n['equipment_id'] = $equipment_id;
    $n['priority']     = $priority;
    $n['description']  = $description;

    // Refresh equipment list if floc changed during this submit
    $equip = [];
    if (!empty($floc_id)) {
      $eq = $pdo->prepare("
        SELECT equipment_id, equipment_code, equipment_name
        FROM equipment
        WHERE floc_id = ? AND status = 'ACTIVE'
        ORDER BY equipment_name
      ");
      $eq->execute([$floc_id]);
      $equip = $eq->fetchAll();
    }
  }

} catch (PDOException $e) {
  die('DB error: '.$e->getMessage());
}

// Helper
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Report <?= e($n['notif_no']) ?> • CMMS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Use your global stylesheet -->
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <div class="container">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="user_dashboard.php">Dashboard</a> &nbsp;/&nbsp;
      <span>Edit Report <?= e($n['notif_no']) ?></span>
    </div>

    <!-- Page header -->
    <div class="page-header">
      <h1>Edit Report #<?= e($n['notif_no']) ?></h1>
      <p class="date-time">Update details and save your changes</p>
    </div>

    <div class="form-card">
      <?php if (!empty($error)): ?>
        <div class="error-message"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-row">
          <div class="form-group">
            <label for="floc_id">Building/Location <span class="required">*</span></label>
            <select id="floc_id" name="floc_id" required onchange="loadEquipment(this.value)">
              <option value="">Select location...</option>
              <?php foreach ($locs as $loc): ?>
                <option value="<?= (int)$loc['floc_id'] ?>" <?= ((int)$n['floc_id'] === (int)$loc['floc_id']) ? 'selected' : '' ?>>
                  <?= e($loc['floc_code'].' - '.$loc['floc_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="help-text">Choose where the issue is located.</span>
          </div>

          <div class="form-group">
            <label for="equipment_id">Equipment (Optional)</label>
            <select id="equipment_id" name="equipment_id">
              <option value="">Select equipment...</option>
              <?php foreach ($equip as $eq): ?>
                <option value="<?= (int)$eq['equipment_id'] ?>" <?= ((int)$n['equipment_id'] === (int)$eq['equipment_id']) ? 'selected' : '' ?>>
                  <?= e(($eq['equipment_code'] ? $eq['equipment_code'].' - ' : '').$eq['equipment_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="help-text">If not sure, you can leave this blank.</span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="priority">Priority <span class="required">*</span></label>
            <select id="priority" name="priority" required>
              <?php foreach (['LOW','MEDIUM','HIGH','URGENT'] as $p): ?>
                <option value="<?= $p ?>" <?= (strtoupper((string)$n['priority']) === $p ? 'selected' : '') ?>><?= $p ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group"></div><!-- spacer -->
        </div>

        <div class="form-group full-width">
          <label for="description">Problem Description <span class="required">*</span></label>
          <textarea id="description" name="description" rows="5" required><?= e($n['description']) ?></textarea>
        </div>

        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Save Changes</button>
          <a class="btn btn-secondary" href="user_dashboard.php">Cancel</a>
        </div>
      </form>
    </div>

  </div>

  <script>
    // Load equipment for a location using this same file as a tiny JSON endpoint
    function loadEquipment(flocId){
      const sel = document.getElementById('equipment_id');
      if(!flocId){
        sel.innerHTML = '<option value="">Select equipment...</option>';
        return;
      }
      fetch('edit_report.php?ajax=equip&floc_id=' + encodeURIComponent(flocId))
        .then(r => r.json())
        .then(data => {
          sel.innerHTML = '<option value="">Select equipment...</option>';
          (data || []).forEach(eq => {
            const opt = document.createElement('option');
            opt.value = eq.equipment_id;
            opt.textContent = (eq.equipment_code ? eq.equipment_code + ' - ' : '') + eq.equipment_name;
            sel.appendChild(opt);
          });
        })
        .catch(() => {
          sel.innerHTML = '<option value="">Select equipment...</option>';
        });
    }
  </script>
</body>
</html>
