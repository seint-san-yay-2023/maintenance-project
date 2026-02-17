<?php
// ===================== STAFF LOGOUT =====================
// Location: /cmms/staff/logout.php
// Redirects to: /cmms/login.php (parent folder)

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database config from parent folder
$logActivity = false;
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
    $logActivity = true;
}

// ⭐ Log logout activity (if database connection available)
if ($logActivity && isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    try {
        // Check if activity_log table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'activity_log'")->rowCount();
        
        if ($tableCheck > 0) {
            $logStmt = $pdo->prepare("
                INSERT INTO activity_log 
                    (user_id, username, action, description, ip_address, user_agent, created_at)
                VALUES 
                    (?, ?, 'LOGOUT', ?, ?, ?, NOW())
            ");
            
            $isForced = isset($_GET['timeout']) || isset($_GET['forced']);
            $description = $isForced ? 'Session timeout or forced logout' : 'Staff/Technician logout';
            
            $logStmt->execute([
                $_SESSION['user_id'],
                $_SESSION['username'],
                $description,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
    } catch (Throwable $e) {
        // Log error but don't stop logout process
        error_log("Logout logging failed: " . $e->getMessage());
    }
}

// Store user info for goodbye message (before clearing session)
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? null;
$isTimeout = isset($_GET['timeout']);

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 86400,
            'path' => $params["path"],
            'domain' => $params["domain"],
            'secure' => $params["secure"],
            'httponly' => $params["httponly"],
            'samesite' => 'Lax'
        ]
    );
}

// Destroy the session
session_destroy();

// Start a new temporary session for flash message
session_start();

// Set logout message
if ($isTimeout) {
    $_SESSION['logout_message'] = "⏱️ Your session has expired. Please login again.";
    $_SESSION['logout_type'] = 'warning';
} else {
    $_SESSION['logout_message'] = "✓ You have been successfully logged out. Thank you!";
    $_SESSION['logout_type'] = 'success';
}

$_SESSION['logout_user'] = $username;

// Regenerate session ID for security
session_regenerate_id(true);

// Security headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// ⭐ REDIRECT TO PARENT FOLDER'S LOGIN PAGE
header("Location: ../login.php");
exit();
?>