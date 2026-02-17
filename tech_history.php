<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Date filter
$date_filter = isset($_GET['period']) ? $_GET['period'] : 'all';
$date_condition = "";

switch ($date_filter) {
    case 'today':
        $date_condition = "AND DATE(w.actual_end) = CURDATE()";
        break;
    case 'week':
        $date_condition = "AND YEARWEEK(w.actual_end, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
        $date_condition = "AND YEAR(w.actual_end) = YEAR(CURDATE()) AND MONTH(w.actual_end) = MONTH(CURDATE())";
        break;
    case 'year':
        $date_condition = "AND YEAR(w.actual_end) = YEAR(CURDATE())";
        break;
}

// Get completed work orders
$query = "
SELECT 
    w.work_order_id,
    w.wo_no,
    w.status,
    w.priority,
    w.actual_start,
    w.actual_end,
    w.resolution_note,
    COALESCE(eq.equipment_name, 'N/A') AS equipment_name,
    COALESCE(eq.equipment_code, '') AS equipment_code,
    COALESCE(fl.floc_name, 'N/A') AS location,
    wl.actual_hours,
    wl.labor_cost
FROM work_order w
LEFT JOIN equipment eq ON w.equipment_id = eq.equipment_id
LEFT JOIN functional_location fl ON w.floc_id = fl.floc_id
LEFT JOIN work_order_labor wl ON w.work_order_id = wl.work_order_id
WHERE w.assigned_user_id = ? 
  AND w.status = 'COMPLETED'
  $date_condition
ORDER BY w.actual_end DESC
";

$stmt = $connect->prepare($query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
$stats = [
    'total_completed' => 0,
    'total_hours' => 0,
    'avg_hours' => 0,
    'total_cost' => 0
];

while ($row = $result->fetch_assoc()) {
    $history[] = $row;
    $stats['total_completed']++;
    $stats['total_hours'] += $row['actual_hours'] ?? 0;
    $stats['total_cost'] += $row['labor_cost'] ?? 0;
}

if ($stats['total_completed'] > 0) {
    $stats['avg_hours'] = round($stats['total_hours'] / $stats['total_completed'], 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work History - Fix Mate CMMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f5f7fa;
            color: #1a202c;
            line-height: 1.6;
        }

        /* Top Bar - Match Dashboard */
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .topbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo img {
            height: 70px;

        }

        .nav-menu {
            display: flex;
            gap: 8px;
        }

        .nav-link {
            padding: 8px 16px;
            border-radius: 6px;
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

.logo img {
  height: 70px;
max-width: 120px;
}

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        /* Page Title */
        .page-title {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .page-title h1 {
            font-size: 24px;
            color: #1e293b;
        }

        /* Period Tabs */
        .period-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .period-tab {
            padding: 10px 16px;
            border-radius: 8px;
            background: white;
            border: 2px solid #e2e8f0;
            color: #64748b;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }

        .period-tab:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .period-tab.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        /* Statistics */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }

        .stat-box:nth-child(1) { border-left-color: #10b981; }
        .stat-box:nth-child(2) { border-left-color: #3b82f6; }
        .stat-box:nth-child(3) { border-left-color: #f59e0b; }
        .stat-box:nth-child(4) { border-left-color: #8b5cf6; }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
            color: #1e293b;
        }

        .stat-label {
            color: #64748b;
            font-size: 14px;
        }

        /* History Section */
        .history-section {
            background: white;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .section-header {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }

        /* History Items */
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .history-item {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #10b981;
            transition: all 0.2s;
        }

        .history-item:hover {
            background: #f1f5f9;
            transform: translateX(2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 12px;
        }

        .wo-number {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .equipment {
            color: #64748b;
            font-size: 14px;
        }

        .status-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin: 12px 0;
            padding: 16px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .meta-label {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
        }

        .meta-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 600;
        }

        .resolution {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin: 12px 0;
        }

        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            background: white;
            color: #2563eb;
            border: 2px solid #2563eb;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #2563eb;
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-text {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .empty-subtext {
            font-size: 14px;
            color: #94a3b8;
        }

        @media (max-width: 768px) {
            .topbar-inner {
                flex-direction: row;
            }

            .logo img {
                height: 28px;
            }

            .nav-menu {
                gap: 4px;
            }

            .nav-link {
                padding: 6px 12px;
                font-size: 13px;
            }

            .container {
                padding: 16px;
            }

            .period-tabs {
                flex-direction: column;
            }

            .period-tab {
                text-align: center;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .history-header {
                flex-direction: column;
            }

            .meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="topbar">
        <div class="topbar-inner">
            <a href="staff_dashboard.php" class="logo">
                <img src="../image/logo.png" alt="Fix Mate CMMS">
            </a>
            <nav class="nav-menu">
                <a href="staff_dashboard.php" class="nav-link">Dashboard</a>
                <a href="tech_history.php" class="nav-link">History</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <!-- Page Title -->
        <div class="page-title">
            <h1>Work History</h1>
        </div>

        <!-- Period Filter -->
        <div class="period-tabs">
            <a href="?period=today" class="period-tab <?= $date_filter == 'today' ? 'active' : '' ?>">
                Today
            </a>
            <a href="?period=week" class="period-tab <?= $date_filter == 'week' ? 'active' : '' ?>">
                This Week
            </a>
            <a href="?period=month" class="period-tab <?= $date_filter == 'month' ? 'active' : '' ?>">
                This Month
            </a>
            <a href="?period=year" class="period-tab <?= $date_filter == 'year' ? 'active' : '' ?>">
                This Year
            </a>
            <a href="?period=all" class="period-tab <?= $date_filter == 'all' ? 'active' : '' ?>">
                All Time
            </a>
        </div>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_completed'] ?></div>
                <div class="stat-label">Completed Orders</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?= number_format($stats['total_hours'], 1) ?></div>
                <div class="stat-label">Total Hours</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?= number_format($stats['avg_hours'], 1) ?></div>
                <div class="stat-label">Avg Hours/Order</div>
            </div>

            <div class="stat-box">
                <div class="stat-number">$<?= number_format($stats['total_cost'], 0) ?></div>
                <div class="stat-label">Total Labor Cost</div>
            </div>
        </div>

        <!-- History List -->
        <div class="history-section">
            <div class="section-header">Completed Work Orders</div>

            <?php if (empty($history)): ?>
                <div class="empty-state">
                    <div class="empty-text">No History Found</div>
                    <div class="empty-subtext">You haven't completed any work orders in this period.</div>
                </div>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($history as $item): ?>
                        <div class="history-item">
                            <div class="history-header">
                                <div>
                                    <div class="wo-number"><?= htmlspecialchars($item['wo_no']) ?></div>
                                    <div class="equipment">
                                        <?= htmlspecialchars($item['equipment_name']) ?>
                                        <?php if ($item['equipment_code']): ?>
                                            (<?= htmlspecialchars($item['equipment_code']) ?>)
                                        <?php endif; ?>
                                        • <?= htmlspecialchars($item['location']) ?>
                                    </div>
                                </div>
                                <span class="status-badge">COMPLETED</span>
                            </div>

                            <div class="meta-grid">
                                <div class="meta-item">
                                    <div class="meta-label">Completed On</div>
                                    <div class="meta-value">
                                        <?= date('M d, Y', strtotime($item['actual_end'])) ?>
                                        <br>
                                        <small style="color: #94a3b8; font-weight: 400;">
                                            <?= date('h:i A', strtotime($item['actual_end'])) ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="meta-item">
                                    <div class="meta-label">Duration</div>
                                    <div class="meta-value">
                                        <?php
                                        $start = strtotime($item['actual_start']);
                                        $end = strtotime($item['actual_end']);
                                        $duration = ($end - $start) / 3600;
                                        echo number_format($duration, 1) . ' hrs';
                                        ?>
                                    </div>
                                </div>

                                <?php if ($item['actual_hours']): ?>
                                <div class="meta-item">
                                    <div class="meta-label">Hours Logged</div>
                                    <div class="meta-value"><?= $item['actual_hours'] ?> hrs</div>
                                </div>
                                <?php endif; ?>

                                <?php if ($item['labor_cost']): ?>
                                <div class="meta-item">
                                    <div class="meta-label">Labor Cost</div>
                                    <div class="meta-value">$<?= number_format($item['labor_cost'], 2) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($item['resolution_note']): ?>
                            <div class="resolution">
                                <strong style="color: #1e293b;">Resolution:</strong>
                                <?= htmlspecialchars(substr($item['resolution_note'], 0, 200)) ?>
                                <?= strlen($item['resolution_note']) > 200 ? '...' : '' ?>
                            </div>
                            <?php endif; ?>

                            <div style="margin-top: 16px;">
                                <a href="view_work_order.php?id=<?= $item['work_order_id'] ?>" class="btn-view">
                                    View Full Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$stmt->close();
$connect->close();
?>