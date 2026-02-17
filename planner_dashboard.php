<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a planner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'planner') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch dashboard statistics
try {
    // Total Work Orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM work_order");
    $total_work_orders = $stmt->fetch()['total'];

    // Work Orders by Status
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM work_order 
        GROUP BY status
    ");
    $wo_by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // FIXED: Add default statuses if no work orders exist
    if (empty($wo_by_status)) {
        $wo_by_status = [
            ['status' => 'CREATED', 'count' => 0],
            ['status' => 'RELEASED', 'count' => 0],
            ['status' => 'IN_PROGRESS', 'count' => 0],
            ['status' => 'WAITING_PARTS', 'count' => 0],
            ['status' => 'COMPLETED', 'count' => 0],
            ['status' => 'CANCELLED', 'count' => 0]
        ];
    }

    // FIXED: Pending Notifications - handle empty status values
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM notification 
        WHERE status IN ('NEW', 'SCREENED', '') OR status IS NULL
    ");
    $pending_notifications = $stmt->fetch()['count'];

    // Active Equipment
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM equipment 
        WHERE status = 'ACTIVE'
    ");
    $active_equipment = $stmt->fetch()['count'];

    // FIXED: High Priority Notifications - handle empty status
    $stmt = $pdo->query("
        SELECT n.*, 
               fl.floc_name,
               e.equipment_name
        FROM notification n
        LEFT JOIN functional_location fl ON n.floc_id = fl.floc_id
        LEFT JOIN equipment e ON n.equipment_id = e.equipment_id
        WHERE n.priority IN ('HIGH', 'URGENT') 
        AND (n.status NOT IN ('CLOSED', 'REJECTED') OR n.status = '' OR n.status IS NULL)
        ORDER BY n.reported_at DESC
        LIMIT 5
    ");
    $urgent_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // === NEW: Performance Indicators ===
    
    // Work Order Completion Rate (Last 30 days)
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed
        FROM work_order
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $completion_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $completion_data = $completion_data ?: ['total' => 0, 'completed' => 0];
    $completion_rate = $completion_data['total'] > 0 
        ? round(($completion_data['completed'] / $completion_data['total']) * 100, 1) 
        : 0;

    // Average Response Time (Time from notification to work order creation)
    $stmt = $pdo->query("
        SELECT AVG(TIMESTAMPDIFF(HOUR, n.reported_at, wo.created_at)) as avg_hours
        FROM work_order wo
        INNER JOIN notification n ON wo.notification_id = n.notification_id
        WHERE wo.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND wo.notification_id IS NOT NULL
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg_response = $result ? $result['avg_hours'] : null;
    $avg_response_hours = $avg_response ? round($avg_response, 1) : 0;

    // On-Time Completion Rate
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN actual_end <= requested_end THEN 1 ELSE 0 END) as on_time
        FROM work_order
        WHERE status = 'COMPLETED' 
        AND requested_end IS NOT NULL
        AND actual_end IS NOT NULL
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $ontime_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $ontime_data = $ontime_data ?: ['total' => 0, 'on_time' => 0];
    $ontime_rate = $ontime_data['total'] > 0 
        ? round(($ontime_data['on_time'] / $ontime_data['total']) * 100, 1) 
        : 0;

    // Equipment Downtime (Average hours)
    $stmt = $pdo->query("
        SELECT AVG(TIMESTAMPDIFF(HOUR, actual_start, actual_end)) as avg_downtime
        FROM work_order
        WHERE status = 'COMPLETED'
        AND actual_start IS NOT NULL
        AND actual_end IS NOT NULL
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg_downtime = $result ? $result['avg_downtime'] : null;
    $avg_downtime_hours = $avg_downtime ? round($avg_downtime, 1) : 0;

    // === NEW: Monthly Trend (Last 6 Months) ===
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled
        FROM work_order
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error fetching dashboard data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planner Dashboard - CMMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            border-left: 3px solid #fff;
        }
        .stat-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        .priority-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        .logo-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
        }
        .logo-icon {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 8px;
            background: white;
            padding: 4px;
        }
        .kpi-card {
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .kpi-card:hover {
            transform: translateY(-3px);
        }
        .kpi-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .kpi-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-3">
                    <h4 class="text-center mb-4 logo-title">
                        <img src="logo.png" alt="FixMate Logo" class="logo-icon">
                        Fix Mate
                    </h4>
                    <hr class="bg-light">
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="planner_dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a class="nav-link" href="work_orders.php">
                            <i class="bi bi-clipboard-check"></i> Work Orders
                        </a>
                        <a class="nav-link" href="notifications.php">
                            <i class="bi bi-bell"></i> Notifications
                        </a>
                        <a class="nav-link" href="maintenance_plans.php">
                            <i class="bi bi-calendar-check"></i> Maintenance Plans
                        </a>
                        <a class="nav-link" href="equipment.php">
                            <i class="bi bi-box"></i> Equipment
                        </a>
                        <a class="nav-link" href="materials.php">
                            <i class="bi bi-boxes"></i> Materials
                        </a>
                        <a class="nav-link" href="task_lists.php">
                            <i class="bi bi-list-task"></i> Task Lists
                        </a>
                        <a class="nav-link" href="vendors.php">
                            <i class="bi bi-shop"></i> Vendors
                        </a>
                        <a class="nav-link" href="work_centers.php">
                            <i class="bi bi-building"></i> Work Centers
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people"></i> Users
                        </a>
                        <hr class="bg-light">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Planner Dashboard</h2>
                    <div>
                        <span class="me-3"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($username); ?></span>
                        <span class="badge bg-primary">Planner</span>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Total Work Orders</h6>
                                        <h2 class="mb-0"><?php echo $total_work_orders; ?></h2>
                                    </div>
                                    <i class="bi bi-clipboard-check" style="font-size: 3rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Pending Notifications</h6>
                                        <h2 class="mb-0"><?php echo $pending_notifications; ?></h2>
                                    </div>
                                    <i class="bi bi-bell" style="font-size: 3rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Active Equipment</h6>
                                        <h2 class="mb-0"><?php echo $active_equipment; ?></h2>
                                    </div>
                                    <i class="bi bi-box" style="font-size: 3rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Work Orders by Status and Urgent Notifications -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Work Orders by Status</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($total_work_orders > 0): ?>
                                    <?php foreach ($wo_by_status as $status): ?>
                                        <?php 
                                        $percentage = $total_work_orders > 0 ? ($status['count'] / $total_work_orders) * 100 : 0;
                                        $color = match($status['status']) {
                                          'CREATED' => 'secondary',
                                            'RELEASED' => 'info',
                                            'IN_PROGRESS' => 'primary',
                                            'WAITING_PARTS' => 'warning',
                                            'COMPLETED' => 'success',
                                            'CANCELLED' => 'danger',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span><?php echo str_replace('_', ' ', $status['status']); ?></span>
                                                <span><?php echo $status['count']; ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                                            </div>
                                            <div class="progress" style="height: 25px;">
                                                <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" 
                                                     style="width: <?php echo $percentage; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">No work orders yet. Start by creating one!</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- High Priority Notifications -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-exclamation-circle"></i> Urgent Notifications</h5>
                                <a href="notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                <?php if (count($urgent_notifications) > 0): ?>
                                    <?php foreach ($urgent_notifications as $notif): ?>
                                        <div class="border-bottom pb-2 mb-2">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($notif['notif_no']); ?></strong>
                                                    <span class="badge bg-<?php echo $notif['priority'] === 'URGENT' ? 'danger' : 'warning'; ?> priority-badge ms-2">
                                                        <?php echo $notif['priority']; ?>
                                                    </span>
                                                    <p class="mb-1 mt-1 small"><?php echo htmlspecialchars($notif['description']); ?></p>
                                                    <small class="text-muted">
                                                        <?php echo $notif['floc_name'] ?? $notif['equipment_name'] ?? 'N/A'; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">No urgent notifications</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Indicators -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Performance Indicators (Last 30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="kpi-card bg-light">
                                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                                    <div class="kpi-value text-success"><?php echo $completion_rate; ?>%</div>
                                    <div class="kpi-label">Completion Rate</div>
                                    <small class="text-muted"><?php echo $completion_data['completed']; ?> of <?php echo $completion_data['total']; ?> completed</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="kpi-card bg-light">
                                    <i class="bi bi-clock-history text-info" style="font-size: 2rem;"></i>
                                    <div class="kpi-value text-info"><?php echo $avg_response_hours; ?>h</div>
                                    <div class="kpi-label">Avg Response Time</div>
                                    <small class="text-muted">Notification to WO</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="kpi-card bg-light">
                                    <i class="bi bi-calendar-check text-primary" style="font-size: 2rem;"></i>
                                    <div class="kpi-value text-primary"><?php echo $ontime_rate; ?>%</div>
                                    <div class="kpi-label">On-Time Completion</div>
                                    <small class="text-muted"><?php echo $ontime_data['on_time']; ?> of <?php echo $ontime_data['total']; ?> on time</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="kpi-card bg-light">
                                    <i class="bi bi-stopwatch text-warning" style="font-size: 2rem;"></i>
                                    <div class="kpi-value text-warning"><?php echo $avg_downtime_hours; ?>h</div>
                                    <div class="kpi-label">Avg Downtime</div>
                                    <small class="text-muted">Per work order</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Trend Chart -->
                <?php if (!empty($monthly_trends)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> Work Order Trend (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (!empty($monthly_trends)): ?>
    <script>
        // Monthly Trend Chart
        const ctx = document.getElementById('monthlyTrendChart').getContext('2d');
        const monthlyTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    foreach ($monthly_trends as $trend) {
                        $date = new DateTime($trend['month'] . '-01');
                        echo "'" . $date->format('M Y') . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Total Work Orders',
                    data: [<?php echo implode(',', array_column($monthly_trends, 'total')); ?>],
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Completed',
                    data: [<?php echo implode(',', array_column($monthly_trends, 'completed')); ?>],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Cancelled',
                    data: [<?php echo implode(',', array_column($monthly_trends, 'cancelled')); ?>],
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>