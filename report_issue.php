<?php
include 'config.php';
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

try {
    // Use PDO for this page (convert from mysqli if needed)
    $pdo = new PDO("mysql:host=localhost;dbname=cmms;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /* =========================
       AJAX: equipment by floc
    ========================= */
    if (isset($_GET['floc_id'])) {
        $eq = $pdo->prepare("
            SELECT equipment_id, equipment_code, equipment_name
            FROM equipment
            WHERE floc_id = ? AND status = 'ACTIVE'
            ORDER BY equipment_name
        ");
        $eq->execute([$_GET['floc_id']]);
        header('Content-Type: application/json');
        echo json_encode($eq->fetchAll(PDO::FETCH_ASSOC));
        exit();
    }

    /* =========================
       Dropdown: locations
    ========================= */
    $locStmt = $pdo->query("
        SELECT floc_id, floc_code, floc_name
        FROM functional_location
        WHERE is_active = 1
        ORDER BY floc_name
    ");
    $locations = $locStmt->fetchAll(PDO::FETCH_ASSOC);

    /* =========================
       Handle submit
    ========================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $floc_id        = !empty($_POST['floc_id']) ? (int)$_POST['floc_id'] : null;
        $equipment_id   = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
        $priority       = $_POST['priority'] ?? 'MEDIUM';
        $description    = trim($_POST['description'] ?? '');
        $reporter_name  = trim($_POST['reporter_name'] ?? '');
        $reporter_email = trim($_POST['reporter_email'] ?? '');

        if ($floc_id === null || $description === '' || $reporter_name === '' || $reporter_email === '') {
            $error = 'Please fill in all required fields';
        } else {
            // Generate notif_no like N001, N002, etc.
            $q = $pdo->query("SELECT notif_no FROM notification ORDER BY notification_id DESC LIMIT 1");
            $last = $q->fetch(PDO::FETCH_ASSOC);
            
            if ($last && preg_match('/^N(\d+)$/', $last['notif_no'], $matches)) {
                $seq = (int)$matches[1] + 1;
            } else {
                $seq = 1;
            }
            $notif_no = sprintf("N%03d", $seq);

            // Insert notification (only fields that exist in the table)
            $ins = $pdo->prepare("
                INSERT INTO notification
                (notif_no, reported_at, status, priority, description,
                 reporter_name, reporter_email, equipment_id, floc_id, created_by_user_id)
                VALUES (?, NOW(), 'NEW', ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $notif_no,
                $priority,
                $description,
                $reporter_name,
                $reporter_email,
                $equipment_id,
                $floc_id,
                $_SESSION['user_id']
            ]);

            // Get the new notification_id for attachments
            $notification_id = (int)$pdo->lastInsertId();

            /* =========================
               File upload handling
               Supports: Images (JPG, JPEG, PNG, GIF, WEBP) and PDF
            ========================= */
            $uploadNote = '';
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    // Allowed file types
                    $allowed = [
                        'image/jpeg',
                        'image/jpg',
                        'image/png',
                        'image/gif',
                        'image/webp',
                        'image/bmp',
                        'application/pdf'
                    ];
                    $maxBytes = 10 * 1024 * 1024; // 10 MB limit

                    $tmpPath  = $_FILES['attachment']['tmp_name'];
                    $origName = $_FILES['attachment']['name'];
                    $fileSize = (int)$_FILES['attachment']['size'];

                    // Get actual MIME type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = finfo_file($finfo, $tmpPath);
                    finfo_close($finfo);

                    if (!in_array($mime, $allowed, true)) {
                        $uploadNote = ' <span style="color:#dc2626;">(⚠️ Attachment skipped: only JPG, PNG, GIF, WEBP, PDF allowed)</span>';
                    } elseif ($fileSize > $maxBytes) {
                        $uploadNote = ' <span style="color:#dc2626;">(⚠️ Attachment skipped: file must be under 10 MB)</span>';
                    } else {
                        // Create uploads directory if it doesn't exist
                        $uploadsDir = __DIR__ . '/uploads';
                        if (!is_dir($uploadsDir)) {
                            @mkdir($uploadsDir, 0755, true);
                        }

                        // Generate safe filename
                        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                        $baseSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
                        $newName = $notif_no . '_' . $baseSafe . '_' . uniqid() . '.' . $ext;
                        $destAbs = $uploadsDir . '/' . $newName;
                        $destRel = 'uploads/' . $newName;

                        if (move_uploaded_file($tmpPath, $destAbs)) {
                            // Insert into notification_attachment table
                            $fa = $pdo->prepare("
                                INSERT INTO notification_attachment
                                (notification_id, file_name, original_name, mime_type, file_size, file_path, uploaded_by_user_id, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $fa->execute([
                                $notification_id,
                                $newName,
                                $origName,
                                $mime,
                                $fileSize,
                                $destRel,
                                $_SESSION['user_id']
                            ]);
                            $uploadNote = ' <span style="color:#059669;">(✅ Attachment uploaded successfully)</span>';
                        } else {
                            $uploadNote = ' <span style="color:#dc2626;">(⚠️ Attachment failed to save)</span>';
                        }
                    }
                } else {
                    $uploadNote = ' <span style="color:#dc2626;">(⚠️ Upload error code: ' . (int)$_FILES['attachment']['error'] . ')</span>';
                }
            }

            $success = "Issue reported successfully! Your reference number is: <strong>$notif_no</strong>$uploadNote";
            
            // Redirect after 3 seconds
            header("Refresh:3; url=user_dashboard.php");
            $_POST = []; // clear the form
        }
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<style>
    .logo img {
height: 70px;
max-width: 120px;}

.header{
    padding:0;
}

.page-header{
    background: linear-gradient(135deg, #A88A73 0%, #8B6F5B 100%);

}
.radio-option input[type="radio"]:checked + .radio-label.priority-urgent {
    color: white;
}
</style>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Report Issue - Campus CMMS</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="header">
    <div class="navbar">
         <div class="logo"><img src="image/logo.png" ></div>
         <div class="user-info">
            <span class="user-name">
                <span>👤</span>
                <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?> 
                <?php echo htmlspecialchars($_SESSION['last_name'] ?? ''); ?>
            </span>
            <a href="user_dashboard.php" class="btn-logout">Dashboard</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

<div class="container">
    <div class="breadcrumb">
        <a href="user_dashboard.php">Dashboard</a> / Report Issue
    </div>

    <div class="page-header">
        <h1>📝 Report Maintenance Issue</h1>
        <p>Help us keep the campus in great condition by reporting any maintenance issues you encounter.</p>
    </div>

    <?php if ($error): ?>
        <div class="error-message">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- IMPORTANT: enctype="multipart/form-data" is required for file uploads -->
    <form method="POST" action="" class="form-card" enctype="multipart/form-data">
        <!-- Location & Equipment Section -->
        <div class="form-section">
            <h3 class="section-title">
                Location & Equipment
            </h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="floc_id">Building/Location <span class="required">*</span></label>
                    <select id="floc_id" name="floc_id" required onchange="loadEquipment(this.value)">
                        <option value="">Select location...</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo $loc['floc_id']; ?>" 
                                    <?php echo (isset($_POST['floc_id']) && $_POST['floc_id'] == $loc['floc_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['floc_code'] . ' - ' . $loc['floc_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="equipment_id">Equipment (Optional)</label>
                    <select id="equipment_id" name="equipment_id">
                        <option value="">Select equipment...</option>
                    </select>
                    <span class="help-text">Leave empty for general building issues</span>
                </div>
            </div>
        </div>

        <!-- Issue Details Section -->
        <div class="form-section">
            <h3 class="section-title">
                
                Issue Details
            </h3>

            <div class="form-group">
                <label>Priority Level <span class="required">*</span></label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="priority_low" name="priority" value="LOW" 
                               <?php echo (isset($_POST['priority']) && $_POST['priority']=='LOW') ? 'checked' : ''; ?>>
                        <label for="priority_low" class="radio-label priority-low"> Low</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="priority_medium" name="priority" value="MEDIUM" 
                               <?php echo (!isset($_POST['priority']) || $_POST['priority']=='MEDIUM') ? 'checked' : ''; ?>>
                        <label for="priority_medium" class="radio-label priority-medium"> Medium</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="priority_high" name="priority" value="HIGH" 
                               <?php echo (isset($_POST['priority']) && $_POST['priority']=='HIGH') ? 'checked' : ''; ?>>
                        <label for="priority_high" class="radio-label priority-high"> High</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="priority_urgent" name="priority" value="URGENT" 
                               <?php echo (isset($_POST['priority']) && $_POST['priority']=='URGENT') ? 'checked' : ''; ?>>
                        <label for="priority_urgent" class="radio-label priority-urgent"> Urgent</label>
                    </div>
                </div>
            </div>

            <div class="form-group full-width">
                <label for="description">Problem Description <span class="required">*</span></label>
                <textarea id="description" name="description" required 
                          placeholder="Please describe the issue in detail..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                <span class="help-text">Be as specific as possible to help us resolve the issue quickly</span>
            </div>

            <!-- File Attachment Field -->
            <div class="form-group full-width">
                <label for="attachment">📎 Attach Photo or Document (Optional)</label>
                <input type="file" id="attachment" name="attachment" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,application/pdf">
                <div class="file-info">
                    <strong>📋 Supported formats:</strong>
                    • Images: JPG, JPEG, PNG, GIF, WEBP, BMP<br>
                    • Documents: PDF<br>
                    • Maximum file size: 10 MB
                </div>
            </div>
        </div>

        <!-- Reporter Information Section -->
        <div class="form-section">
            <h3 class="section-title">
                <span class="section-icon">👤</span>
                Reporter Information
            </h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="reporter_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="reporter_name" name="reporter_name" required
                           value="<?php 
                               if (isset($_POST['reporter_name'])) {
                                   echo htmlspecialchars($_POST['reporter_name']);
                               } else {
                                   $full_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
                                   echo htmlspecialchars($full_name);
                               }
                           ?>">
                </div>
                <div class="form-group">
                    <label for="reporter_email">Email Address <span class="required">*</span></label>
                    <input type="email" id="reporter_email" name="reporter_email" required
                           value="<?php echo isset($_POST['reporter_email']) ? htmlspecialchars($_POST['reporter_email']) : htmlspecialchars($_SESSION['email'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="user_dashboard.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Submit Report</button>
        </div>
    </form>
</div>

<script>
function loadEquipment(flocId) {
    const sel = document.getElementById('equipment_id');
    
    if (!flocId) {
        sel.innerHTML = '<option value="">Select equipment...</option>';
        return;
    }
    
    // Show loading state
    sel.innerHTML = '<option value="">Loading equipment...</option>';
    sel.disabled = true;
    
    fetch('?floc_id=' + encodeURIComponent(flocId))
        .then(r => r.json())
        .then(data => {
            sel.innerHTML = '<option value="">Select equipment...</option>';
            data.forEach(eq => {
                const opt = document.createElement('option');
                opt.value = eq.equipment_id;
                opt.textContent = eq.equipment_code + ' - ' + eq.equipment_name;
                sel.appendChild(opt);
            });
            sel.disabled = false;
        })
        .catch(() => {
            sel.innerHTML = '<option value="">Error loading equipment</option>';
            sel.disabled = false;
        });
}
</script>
</body>
</html>