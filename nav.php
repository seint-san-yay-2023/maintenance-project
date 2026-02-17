<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Define active class function
function isActive($page) {
    global $current_page;
    return ($current_page == $page) ? 'active' : '';
}
?>
<!DOCTYPE html>
<style>
/* Navigation Styles */
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

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: #1e293b;
}

.logo img {
    height: 32px;
    width: auto;
    max-width: 150px;
    display: block;
    object-fit: contain;
}

.logo-text {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    display: none; /* Hide text, logo speaks for itself */
}

.nav-menu {
    display: flex;
    gap: 8px;
    align-items: center;
}

.nav-link {
    padding: 8px 16px;
    border-radius: 6px;
    color: #64748b;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s;
    position: relative;
}

.nav-link:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.nav-link.active {
    background: #2563eb;
    color: white;
    font-weight: 600;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .topbar-inner {
        flex-direction: row;
        gap: 12px;
    }

    .logo img {
        height: 28px;
        max-width: 120px;
    }

    .logo-text {
        display: none;
    }

    .nav-menu {
        gap: 4px;
    }

    .nav-link {
        padding: 6px 12px;
        font-size: 13px;
    }
}

@media (max-width: 480px) {
    .logo img {
        height: 24px;
        max-width: 100px;
    }

    .nav-link {
        padding: 6px 10px;
        font-size: 12px;
    }
}
</style>

<!-- Top Navigation Bar -->
<div class="topbar">
    <div class="topbar-inner">
        <!-- Logo -->
        <a href="staff_dashboard.php" class="logo">
            <img src="../image/logo.png" alt="Fix Mate CMMS">
            <!-- Optional: Add text next to logo -->
            <!-- <span class="logo-text">Fix Mate</span> -->
        </a>

        <!-- Navigation Menu -->
        <nav class="nav-menu">
            <a href="staff_dashboard.php" class="nav-link <?= isActive('staff_dashboard.php') ?>">
                Dashboard
            </a>
            <a href="tech_history.php" class="nav-link <?= isActive('tech_history.php') ?>">
                History
            </a>
            <a href="logout.php" class="nav-link <?= isActive('logout.php') ?>">
                Logout
            </a>
        </nav>
    </div>
</div>