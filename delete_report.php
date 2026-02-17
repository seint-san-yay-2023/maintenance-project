<?php
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$notification_data = null;

// Use PDO for this operation
try {
    $pdo = new PDO("mysql:host=localhost;dbname=cmms;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get notification ID from query string
    $notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($notification_id > 0) {
        // Fetch notification details to show confirmation
        $stmt = $pdo->prepare("
            SELECT n.*, 
                   fl.floc_name, 
                   eq.equipment_name,
                   eq.equipment_code
            FROM notification n
            LEFT JOIN functional_location fl ON n.floc_id = fl.floc_id
            LEFT JOIN equipment eq ON n.equipment_id = eq.equipment_id
            WHERE n.notification_id = ? AND n.created_by_user_id = ?
        ");
        $stmt->execute([$notification_id, $_SESSION['user_id']]);
        $notification_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notification_data) {
            $error = "Report not found or you don't have permission to delete it.";
        }
    }

    // Handle DELETE confirmation (POST request)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $notif_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
        $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';

        if ($confirm === 'yes' && $notif_id > 0) {
            // Verify ownership again
            $check = $pdo->prepare("
                SELECT notification_id, notif_no 
                FROM notification 
                WHERE notification_id = ? AND created_by_user_id = ?
            ");
            $check->execute([$notif_id, $_SESSION['user_id']]);
            $verify = $check->fetch(PDO::FETCH_ASSOC);

            if (!$verify) {
                $error = "Report not found or you don't have permission to delete it.";
            } else {
                // Start transaction
                $pdo->beginTransaction();

                try {
                    // Get attachment files to delete from disk
                    $files_stmt = $pdo->prepare("
                        SELECT file_path 
                        FROM notification_attachment 
                        WHERE notification_id = ?
                    ");
                    $files_stmt->execute([$notif_id]);
                    $files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Delete attachments from database
                    $pdo->prepare("DELETE FROM notification_attachment WHERE notification_id = ?")
                        ->execute([$notif_id]);

                    // Delete notification comments if any
                    $pdo->prepare("DELETE FROM notification_comment WHERE notification_id = ?")
                        ->execute([$notif_id]);

                    // Delete the notification itself
                    // Note: work_order will be set to NULL due to FK constraint ON DELETE SET NULL
                    $pdo->prepare("DELETE FROM notification WHERE notification_id = ?")
                        ->execute([$notif_id]);

                    // Commit transaction
                    $pdo->commit();

                    // Delete physical files from disk (after successful DB commit)
                    foreach ($files as $file) {
                        $file_path = __DIR__ . '/' . ltrim($file['file_path'], '/');
                        if (is_file($file_path)) {
                            @unlink($file_path);
                        }
                    }

                    // Redirect to dashboard with success message
                    header('Location: user_dashboard.php?deleted=1');
                    exit();

                } catch (Exception $e) {
                    // Rollback on error
                    $pdo->rollBack();
                    $error = "Failed to delete report. Please try again.";
                }
            }
        } else {
            // User cancelled
            header('Location: user_dashboard.php');
            exit();
        }
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Report - Fix Mate CMMS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .delete-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .delete-card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-top: 4px solid #ef4444;
        }

        .delete-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        .delete-title {
            text-align: center;
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .delete-description {
            text-align: center;
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .report-details {
            background: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #374151;
        }

        .detail-value {
            color: #6b7280;
            text-align: right;
        }

        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .warning-box p {
            margin: 0;
            color: #92400e;
            font-size: 14px;
        }

        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 14px 30px;
            border-radius: 10px;
            border: none;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-cancel {
            background: #f3f4f6;
            color: #6b7280;
            padding: 14px 30px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #e5e7eb;
            color: #374151;
        }

        .error-alert {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc2626;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-left">
            <span style="font-size: 24px;">🔧</span>
            <h1>Fix Mate CMMS</h1>
        </div>
        <a href="user_dashboard.php" class="logout-btn">Back to Dashboard</a>
    </div>

    <div class="delete-container">
        <?php if ($error): ?>
            <div class="error-alert">
                ❌ <?php echo htmlspecialchars($error); ?>
                <br><br>
                <a href="user_dashboard.php" class="btn-cancel" style="display: inline-block; margin-top: 10px;">
                    Return to Dashboard
                </a>
            </div>
        <?php elseif ($notification_data): ?>
            <div class="delete-card">
                <div class="delete-icon">
                    🗑️
                </div>
                
                <h1 class="delete-title">Delete Report?</h1>
                <p class="delete-description">
                    Are you sure you want to delete this maintenance report? This action cannot be undone.
                </p>

                <div class="report-details">
                    <div class="detail-row">
                        <span class="detail-label">Report Number:</span>
                        <span class="detail-value"><strong><?php echo htmlspecialchars($notification_data['notif_no']); ?></strong></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Description:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($notification_data['description']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Location:</span>
                        <span class="detail-value">
                            <?php echo htmlspecialchars($notification_data['floc_name'] ?: 'N/A'); ?>
                        </span>
                    </div>
                    <?php if ($notification_data['equipment_name']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Equipment:</span>
                        <span class="detail-value">
                            <?php echo htmlspecialchars($notification_data['equipment_code'] . ' - ' . $notification_data['equipment_name']); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="detail-label">Priority:</span>
                        <span class="detail-value">
                            <span class="priority-badge priority-<?php echo strtolower($notification_data['priority']); ?>">
                                <?php echo htmlspecialchars($notification_data['priority']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Reported Date:</span>
                        <span class="detail-value">
                            <?php echo date('M d, Y h:i A', strtotime($notification_data['reported_at'])); ?>
                        </span>
                    </div>
                </div>

                <div class="warning-box">
                    <p><strong>⚠️ Warning:</strong> Deleting this report will also remove any associated attachments and comments. This action is permanent and cannot be reversed.</p>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="notification_id" value="<?php echo $notification_data['notification_id']; ?>">
                    
                    <div class="button-group">
                        <a href="user_dashboard.php" class="btn-cancel">
                            Cancel
                        </a>
                        <button type="submit" name="confirm" value="yes" class="btn-danger">
                            🗑️ Yes, Delete Report
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="error-alert">
                ❌ No report specified for deletion.
                <br><br>
                <a href="user_dashboard.php" class="btn-cancel" style="display: inline-block; margin-top: 10px;">
                    Return to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>