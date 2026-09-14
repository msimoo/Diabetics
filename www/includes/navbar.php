<?php
/**
 * Top Navigation Bar
 */
$current_page = basename($_SERVER['PHP_SELF']);

// Get notification count for bell
$notif_count = 0;
if ($mysqli) {
    // Suppress error in case notifications table doesn't exist yet
    $result = @$mysqli->query("SELECT COUNT(*) as cnt FROM notifications WHERE is_read = 0");
    if ($result) {
        $notif_count = (int)$result->fetch_assoc()['cnt'];
    }
}
?>
<nav class="navbar">
    <div class="navbar-inner">
        <div class="navbar-right">
            <button class="sidebar-toggle" onclick="toggleSidebar()" title="القائمة الجانبية">☰</button>
            <a href="<?php echo BASE_URL; ?>/modules/dashboard.php" class="navbar-brand">
                <span class="brand-icon">🏥</span>
                <span class="brand-text">مركز سري</span>
            </a>
        </div>

        <!-- Navbar Search -->
        <div class="navbar-search" id="navbarSearch">
            <span class="search-icon">🔍</span>
            <input type="text" id="navbarSearchInput" placeholder="ابحث عن مريض..." autocomplete="off">
            <div class="search-dropdown" id="searchDropdown"></div>
        </div>

        <div class="navbar-left">
            <!-- Notification Bell -->
            <a href="<?php echo BASE_URL; ?>/modules/analytics/notifications.php" class="notif-bell" title="الإشعارات">
                🔔
                <span class="notif-badge <?php echo $notif_count === 0 ? 'zero' : ''; ?>">
                    <?php echo $notif_count > 99 ? '99+' : $notif_count; ?>
                </span>
            </a>

            <button id="themeToggle" class="theme-toggle" title="الوضع الليلي">🌙</button>
            <span class="user-badge">
                <span class="user-role"><?php echo escape_output($_SESSION['role']); ?></span>
                <span class="user-name"><?php echo escape_output($_SESSION['full_name']); ?></span>
            </span>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="btn-logout">🚪 خروج</a>
        </div>
    </div>
</nav>
