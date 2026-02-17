<?php
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Get user information
$user_query = "SELECT u.*, ed.position, ed.employee_code 
               FROM users u
               LEFT JOIN employee_details ed ON u.user_id = ed.user_id
               WHERE u.user_id = ?";
$user_stmt = $connect->prepare($user_query);
$user_stmt->bind_param("i", $current_user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_info = $user_result->fetch_assoc();

if (!$user_info) {
    die('User not found');
}

// Create full_name variable
$full_name = trim($user_info['first_name'] . ' ' . $user_info['last_name']);

// Filter by status if provided
$status_filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get notifications created by this user with optional filter
$query = "
SELECT 
    n.notification_id,
    n.notif_no,
    n.description,
    n.priority,
    n.status AS report_status,
    n.reported_at,
    COALESCE(fl.floc_name, eq.equipment_name, 'N/A') AS location_equipment,
    COALESCE(eq.equipment_code, '') AS equipment_code,
    w.wo_no,
    w.status AS wo_status,
    w.actual_start,
    w.actual_end
FROM notification n
LEFT JOIN functional_location fl ON n.floc_id = fl.floc_id
LEFT JOIN equipment eq ON n.equipment_id = eq.equipment_id
LEFT JOIN work_order w ON n.notification_id = w.notification_id
WHERE n.created_by_user_id = ?
";

// Add status filter if not 'all'
if ($status_filter != 'all') {
    $query .= " AND n.status = '" . strtoupper($status_filter) . "'";
}

$query .= " ORDER BY n.reported_at DESC";

$stmt = $connect->prepare($query);
if (!$stmt) {
    die('SQL prepare failed: ' . $connect->error);
}
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

// Statistics
$total_reports = 0;
$new_pending = 0;
$in_progress = 0;
$completed = 0;
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
    $total_reports++;
    
    $status = $row['report_status'] ?: 'NEW';
    $wo_status = $row['wo_status'];
    
    if ($wo_status === 'COMPLETED') {
        $completed++;
    } elseif ($wo_status === 'IN_PROGRESS') {
        $in_progress++;
    } elseif ($status === 'NEW' || !$wo_status) {
        $new_pending++;
    }
}

// Get counts for filter tabs
$count_query = "
SELECT 
    SUM(CASE WHEN status = 'NEW' OR status = '' OR status IS NULL THEN 1 ELSE 0 END) as new_count,
    SUM(CASE WHEN status = 'SCREENED' THEN 1 ELSE 0 END) as screened_count,
    SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'CLOSED' THEN 1 ELSE 0 END) as closed_count
FROM notification
WHERE created_by_user_id = ?
";
$count_stmt = $connect->prepare($count_query);
$count_stmt->bind_param("i", $current_user_id);
$count_stmt->execute();
$counts = $count_stmt->get_result()->fetch_assoc();
?>
<style>
.logo img {
height: 70px;
max-width: 120px;
}
</style>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Dashboard - Campus CMMS</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>
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
        <div class="welcome-section">
            <div class="welcome-content">
                <h2>
                    <span></span> Welcome back, <?= htmlspecialchars($full_name) ?>!
                </h2>
                <p>Manage and track all your maintenance reports in one place</p>
            </div>
            <a href="report_issue.php" class="report-new-btn">
               
                Report New Issue
            </a>
        </div>

        <div class="stats">
            <div class="stat-card total">
                <div class="stat-content">
                    <h3>Total Reports</h3>
                    <div class="number"><?= $total_reports ?></div>
                </div>
            
            </div>

            <div class="stat-card new">
                <div class="stat-content">
                    <h3>New / Pending</h3>
                    <div class="number"><?= $new_pending ?></div>
                </div>
                
            </div>

            <div class="stat-card progress">
                <div class="stat-content">
                    <h3>In Progress</h3>
                    <div class="number"><?= $in_progress ?></div>
                </div>
                
            </div>

            <div class="stat-card completed">
                <div class="stat-content">
                    <h3>Completed</h3>
                    <div class="number"><?= $completed ?></div>
                </div>
               
            </div>
        </div>

        <div class="reports-section">
            <div class="reports-header">
                <h3>
                    <span></span> My Reports
                </h3>
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?= $status_filter == 'all' ? 'active' : '' ?>">All</a>
                    <a href="?filter=new" class="filter-tab <?= $status_filter == 'new' ? 'active' : '' ?>">New</a>
                    <a href="?filter=screened" class="filter-tab <?= $status_filter == 'screened' ? 'active' : '' ?>">Screened</a>
                    <a href="?filter=approved" class="filter-tab <?= $status_filter == 'approved' ? 'active' : '' ?>">Approved</a>
                    <a href="?filter=closed" class="filter-tab <?= $status_filter == 'closed' ? 'active' : '' ?>">Closed</a>
                </div>
            </div>

            <?php if (empty($data)): ?>
                <div class="no-data">
                    <div class="no-data-icon">📭</div>
                    <p><strong>No reports found</strong></p>
                    <p>You haven't submitted any maintenance reports yet</p>
                </div>
            <?php else: ?>
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Report #</th>
                            <th>Description</th>
                            <th>Location</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date & Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td>
                                    <div class="report-id"><?= htmlspecialchars($row['notif_no']) ?></div>
                                    <span class="report-type">
                                        <?= $row['equipment_code'] ? 'Equipment: ' . htmlspecialchars($row['equipment_code']) : 'OTHER' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td><?= htmlspecialchars($row['location_equipment']) ?></td>
                                <td>
                                    <?php
                                    $priority = $row['priority'] ?: 'MEDIUM';
                                    $priority_class = 'priority-' . strtolower($priority);
                                    echo "<span class='priority-badge {$priority_class}'>⚠ " . htmlspecialchars($priority) . "</span>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $status = $row['report_status'] ?: 'NEW';
                                    $status_class = 'status-' . strtolower($status);
                                    echo "<span class='status-badge {$status_class}'>" . htmlspecialchars($status) . "</span>";
                                    ?>
                                </td>
                                <td>
                                    <div class="date-time">
                                        <?= date('M d, Y', strtotime($row['reported_at'])) ?><br>
                                        <?= date('h:i A', strtotime($row['reported_at'])) ?>
                                    </div>
                                    <div class="action-buttons" style="margin-top: 8px;">
                                        <a href="view_report.php?id=<?= $row['notification_id'] ?>" class="btn btn-view">View</a>
                                        <a href="edit_report.php?id=<?= $row['notification_id'] ?>" class="btn btn-edit">Edit</a>
                                        <a href="delete_report.php?id=<?= $row['notification_id'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this report?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$stmt->close();
$user_stmt->close();
$count_stmt->close();
$connect->close();
?>