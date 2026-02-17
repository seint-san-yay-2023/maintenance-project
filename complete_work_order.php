<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$work_order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Check for started message
if (isset($_GET['started'])) {
    $success = "Work order started successfully! You can now record materials and complete the work.";
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=cmms;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get work order details
    $wo_query = $pdo->prepare("
        SELECT w.*, 
               eq.equipment_name, eq.equipment_code,
               fl.floc_name,
               wc.wc_name,
               tl.title AS task_list_title,
               tl.estimated_hours,
               n.notification_id, n.created_by_user_id, n.reporter_email,
               planner.email AS planner_email
        FROM work_order w
        LEFT JOIN equipment eq ON w.equipment_id = eq.equipment_id
        LEFT JOIN functional_location fl ON w.floc_id = fl.floc_id
        LEFT JOIN work_center wc ON w.work_center_id = wc.work_center_id
        LEFT JOIN task_list tl ON w.task_list_id = tl.task_list_id
        LEFT JOIN notification n ON w.notification_id = n.notification_id
        LEFT JOIN users planner ON w.planner_user_id = planner.user_id
        WHERE w.work_order_id = ? AND w.assigned_user_id = ?
    ");
    $wo_query->execute([$work_order_id, $_SESSION['user_id']]);
    $wo = $wo_query->fetch(PDO::FETCH_ASSOC);

    if (!$wo) {
        die('Work order not found or not assigned to you.');
    }

    // Get available materials
    $materials_query = $pdo->query("
        SELECT material_id, material_code, material_name, unit_of_measure, on_hand_qty, standard_cost
        FROM material
        WHERE is_active = 1
        ORDER BY material_name
    ");
    $materials = $materials_query->fetchAll(PDO::FETCH_ASSOC);

    // Get already issued materials
    $issued_materials = $pdo->prepare("
        SELECT wom.*, m.material_name, m.material_code, m.unit_of_measure
        FROM work_order_material wom
        JOIN material m ON wom.material_id = m.material_id
        WHERE wom.work_order_id = ?
        ORDER BY wom.issued_at DESC
    ");
    $issued_materials->execute([$work_order_id]);
    $existing_materials = $issued_materials->fetchAll(PDO::FETCH_ASSOC);

    // Get labor record
    $labor_query = $pdo->prepare("SELECT * FROM work_order_labor WHERE work_order_id = ?");
    $labor_query->execute([$work_order_id]);
    $labor = $labor_query->fetch(PDO::FETCH_ASSOC);

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // Add material
        if ($action === 'add_material') {
            $material_id = (int)$_POST['material_id'];
            $quantity = (float)$_POST['quantity'];

            if ($material_id && $quantity > 0) {
                // Check stock
                $stock_check = $pdo->prepare("SELECT on_hand_qty, standard_cost FROM material WHERE material_id = ?");
                $stock_check->execute([$material_id]);
                $stock = $stock_check->fetch(PDO::FETCH_ASSOC);

                if ($stock && $stock['on_hand_qty'] >= $quantity) {
                    // Add to work_order_material
                    $pdo->prepare("
                        INSERT INTO work_order_material 
                        (work_order_id, material_id, quantity, unit_cost, issued_by_user_id, issued_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ")->execute([
                        $work_order_id, 
                        $material_id, 
                        $quantity, 
                        $stock['standard_cost'],
                        $_SESSION['user_id']
                    ]);

                    // Update inventory
                    $pdo->prepare("
                        UPDATE material 
                        SET on_hand_qty = on_hand_qty - ? 
                        WHERE material_id = ?
                    ")->execute([$quantity, $material_id]);

                    $success = "Material issued successfully! Inventory updated.";
                } else {
                    $error = "Insufficient stock! Only " . ($stock['on_hand_qty'] ?? 0) . " available.";
                }
            }
            
            header("Location: complete_work_order.php?id=$work_order_id&material_added=1");
            exit;
        }

        // Complete work order
        if ($action === 'complete') {
            $actual_hours = (float)$_POST['actual_hours'];
            $resolution_note = trim($_POST['resolution_note'] ?? '');

            if ($actual_hours > 0 && $resolution_note !== '') {
                $pdo->beginTransaction();

                try {
                    // Update or insert labor record
                    if ($labor) {
                        $pdo->prepare("
                            UPDATE work_order_labor 
                            SET actual_hours = ?, updated_at = NOW()
                            WHERE work_order_id = ?
                        ")->execute([$actual_hours, $work_order_id]);
                    } else {
                        $pdo->prepare("
                            INSERT INTO work_order_labor 
                            (work_order_id, planned_hours, actual_hours, updated_at)
                            VALUES (?, ?, ?, NOW())
                        ")->execute([
                            $work_order_id,
                            $wo['estimated_hours'] ?? 0,
                            $actual_hours
                        ]);
                    }

                    // Update work order status
                    $pdo->prepare("
                        UPDATE work_order 
                        SET status = 'COMPLETED', 
                            actual_end = NOW(),
                            resolution_note = ?,
                            updated_at = NOW()
                        WHERE work_order_id = ?
                    ")->execute([$resolution_note, $work_order_id]);

                    $pdo->commit();

                    // Redirect to success
                    header("Location: staff_dashboard.php?completed=1");
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Failed to complete work order: " . $e->getMessage();
                }
            } else {
                $error = "Please fill in hours worked and resolution notes.";
            }
        }
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Calculate total material cost
$total_material_cost = 0;
foreach ($existing_materials as $mat) {
    $total_material_cost += $mat['quantity'] * $mat['unit_cost'];
}

// Calculate time elapsed if work started
$time_elapsed = '';
if ($wo['actual_start']) {
    $start_time = strtotime($wo['actual_start']);
    $current_time = time();
    $elapsed_seconds = $current_time - $start_time;
    $hours = floor($elapsed_seconds / 3600);
    $minutes = floor(($elapsed_seconds % 3600) / 60);
    $time_elapsed = sprintf("%d:%02d", $hours, $minutes);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Work Order - Fix Mate CMMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-light: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --brand: #667eea;
            --success:linear-gradient(135deg, #A88A73 0%, #8B6F5B 100%);
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --info: #3b82f6;
            --info-light: #dbeafe;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --radius: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg-light);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Navigation */
        .nav {
            
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px 0;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .nav-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .back-link {
            color: black;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .back-link:hover {
            background: rgba(255,255,255,0.15);
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-success {

            border: 2px solid #A88A73;
            color:linear-gradient(135deg, #A88A73 0%, #8B6F5B 100%);
;
        }

        .alert-error {
            background: var(--danger-light);
            border: 2px solid #fca5a5;
            color: #7f1d1d;
        }

        /* Progress Tabs */
        .progress-tabs {
            background: white;
            border-radius: var(--radius);
            padding: 8px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            display: flex;
            gap: 8px;
        }

        .progress-tab {
            flex: 1;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-muted);
            background: var(--bg-light);
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .progress-tab.active {
            background: linear-gradient(135deg, #A88A73 0%, #8B6F5B 100%);
            color: white;
            box-shadow: var(--shadow);
        }

        .progress-tab.completed {
            background: #a88a733e;
            color: var(--success);
            
        }

        /* WO Summary */
        .wo-summary {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .wo-summary h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .wo-summary p {
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Time Tracker */
        .time-tracker {
            background: linear-gradient(135deg, #A88A73 0%, #8B6F5B 100%);
            color: white;
            padding: 32px;
            border-radius: var(--radius);
            text-align: center;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }

        .time-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .time-value {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 8px;
            font-variant-numeric: tabular-nums;
        }

        .time-started {
            font-size: 13px;
            opacity: 0.8;
        }

        /* Section */
        .section {
            background: white;
            border-radius: var(--radius);
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .section-header {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-icon {
            width: 36px;
            height: 36px;
            background: var(--bg-gradient);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        /* Add Material Form */
        .add-material-form {
            background: var(--bg-light);
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border: 2px solid var(--border);
        }

        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group select,
        .form-group input {
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .stock-indicator {
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
        }

        .stock-ok { color: var(--success); }
        .stock-low { color: var(--warning); }
        .stock-out { color: var(--danger); }

        /* Materials Table */
        .materials-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .materials-table thead {
            background: var(--bg-light);
        }

        .materials-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }

        .materials-table td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
        }

        .materials-table tbody tr:hover {
            background: var(--bg-light);
        }

        .materials-table tfoot {
            background: var(--bg-light);
            font-weight: 700;
        }

        /* Completion Form */
        .completion-alert {
            background: white;
            border: 2px solid #A88A73;
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
        }

        .completion-alert h4 {
            color: #A88A73;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .completion-alert p {
            color: #494847ff;
            margin: 0;
        }

        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            line-height: 1.6;
            resize: vertical;
            transition: all 0.2s;
        }

        textarea:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .help-text {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .required {
            color: var(--danger);
        }

        /* Submit Section */
        .submit-section {
            background: #ffffffff;
            padding: 24px;
            border-radius: var(--radius);
            text-align: center;
            margin-top: 24px;
        }

        .submit-section p {
            color: #A88A73;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary {
             background: linear-gradient(135deg, #A88A73 0%, #8B6F5B 100%);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid #A88A73;
        }

        .btn-secondary:hover {
            border-color: #62554bff;
            color: #A88A73;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .container { padding: 16px; }
            .form-row { grid-template-columns: 1fr; }
            .button-group { flex-direction: column; width: 100%; }
            .btn { width: 100%; justify-content: center; }
            .progress-tabs { flex-direction: column; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <div class="nav">
        <div class="nav-inner">
            <a href="staff_dashboard.php" class="back-link">← Back to Dashboard</a>
            <h1 style="font-size: 18px; font-weight: 600; margin: 0;">Complete Work Order</h1>
        </div>
    </div>

    <div class="container">
        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="alert alert-error">
            <span style="font-size: 20px;">⚠️</span>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <span style="font-size: 20px;">✓</span>
            <div><?= htmlspecialchars($success) ?></div>
        </div>
        <?php endif; ?>

        <!-- Progress Tabs -->
        <div class="progress-tabs">
            <div class="progress-tab completed">1. Start Work</div>
            <div class="progress-tab active"> 2. Record Materials & Time</div>
            <div class="progress-tab">3. Complete</div>
        </div>

        <!-- WO Summary -->
        <div class="wo-summary">
            <h2><?= htmlspecialchars($wo['wo_no']) ?></h2>
            <p>
                ⚙️ <?= htmlspecialchars($wo['equipment_name']) ?>
                <?php if ($wo['equipment_code']): ?>
                    (<?= htmlspecialchars($wo['equipment_code']) ?>)
                <?php endif; ?>
                • 📍 <?= htmlspecialchars($wo['floc_name']) ?>
            </p>
        </div>

        <!-- Time Tracker -->
        <?php if ($wo['actual_start']): ?>
        <div class="time-tracker">
            <div class="time-label">Time Working</div>
            <div class="time-value" id="timer-display">0:00:00</div>
            <div class="time-started">
                Started: <?= date('M d, Y h:i A', strtotime($wo['actual_start'])) ?>
            </div>
        </div>
        <script>
            // Simple timer starting from 0
            let totalSeconds = 0;
            
            function updateTimer() {
                totalSeconds++;
                
                const hours = Math.floor(totalSeconds / 3600);
                const minutes = Math.floor((totalSeconds % 3600) / 60);
                const seconds = totalSeconds % 60;
                
                document.getElementById('timer-display').textContent = 
                    hours + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            }
            
            // Start counting from 0
            setInterval(updateTimer, 1000);
        </script>
        <?php endif; ?>

        <!-- Materials Section -->
        <div class="section">
            <div class="section-header">

                Materials & Parts
            </div>

            <!-- Add Material Form -->
            <div class="add-material-form">
                <h4 style="margin-bottom: 16px; color: var(--text-primary); font-size: 15px;">Issue Material</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="add_material">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="material_id">Material</label>
                            <select id="material_id" name="material_id" required onchange="updateStock(this)">
                                <option value="">Select material...</option>
                                <?php foreach ($materials as $mat): ?>
                                    <option value="<?= $mat['material_id'] ?>" 
                                            data-stock="<?= $mat['on_hand_qty'] ?>"
                                            data-uom="<?= $mat['unit_of_measure'] ?>">
                                        <?= htmlspecialchars($mat['material_code'] . ' - ' . $mat['material_name']) ?>
                                        (Stock: <?= $mat['on_hand_qty'] ?> <?= $mat['unit_of_measure'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="stock-indicator" id="stock-info"></span>
                        </div>

                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" step="0.001" min="0.001" required>
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-bottom: 0;">
                            + Issue
                        </button>
                    </div>
                </form>
            </div>

            <!-- Issued Materials Table -->
            <?php if (!empty($existing_materials)): ?>
            <h4 style="margin: 20px 0 10px; color: var(--text-primary); font-size: 15px;">Issued Materials</h4>
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Material Name</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Total</th>
                        <th>Issued</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existing_materials as $mat): 
                        $item_total = $mat['quantity'] * $mat['unit_cost'];
                    ?>
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
                        <td colspan="4" style="text-align: right;">Total Materials Cost:</td>
                        <td colspan="2">$<?= number_format($total_material_cost, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 12px;">📦</div>
                <p>No materials issued yet for this work order.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Completion Form -->
        <div class="section">
            <div class="section-header">
                
                ✓ Complete Work Order
            </div>

            <div class="completion-alert">
                <h4>Ready to Complete?</h4>
                <p>Please record your hours worked and provide detailed notes about the work completed.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="complete">
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="actual_hours">Hours Worked <span class="required">*</span></label>
                    <input type="number" id="actual_hours" name="actual_hours" 
                           step="0.25" min="0.25" required
                           value="<?= $labor['actual_hours'] ?? ($wo['estimated_hours'] ?? '') ?>"
                           placeholder="e.g., 2.5">
                    <?php if ($wo['estimated_hours']): ?>
                        <span class="help-text">Estimated: <?= $wo['estimated_hours'] ?> hours</span>
                    <?php endif; ?>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="resolution_note">Work Completed & Resolution Notes <span class="required">*</span></label>
                    <textarea id="resolution_note" name="resolution_note" required 
                              placeholder="Describe in detail:&#10;• What work was performed&#10;• Parts replaced or repaired&#10;• Tests conducted&#10;• Current condition of equipment&#10;• Any recommendations"
                              style="min-height: 180px;"><?= htmlspecialchars($wo['resolution_note'] ?? '') ?></textarea>
                    <span class="help-text">Provide comprehensive notes for documentation and future reference</span>
                </div>

                <div class="submit-section">
                    <p>💡 Completing this work order will notify the planner and reporter.</p>
                    <div class="button-group">
                        <a href="view_work_order.php?id=<?= $work_order_id ?>" class="btn btn-secondary">
                            📋 Review Details
                        </a>
                        <button type="submit" class="btn btn-primary">
                            ✓ Complete Work Order
                        </button>
                    </div>
                </div>
            </form>
        </div>