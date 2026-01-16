<?php
/**
 * Dashboard Sidebar Navigation
 * Reusable sidebar component for all dashboard pages
 */

// Get current page to highlight active link
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="nav-brand">
        <img src="../images/logo.png" alt="Safe Space Logo" style="width: 40px; height: 40px; border-radius: 12px;">
        Safe Space
    </div>
    <nav class="nav-links">
        <a href="index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">📊 Dashboard</a>
        <a href="mood_tracker.php" class="<?php echo $current_page === 'mood_tracker.php' ? 'active' : ''; ?>">😊 Mood Tracker</a>
        <a href="mental_health_tests.php" class="<?php echo $current_page === 'mental_health_tests.php' || $current_page === 'take_test.php' || $current_page === 'test_results.php' ? 'active' : ''; ?>">🧠 Mental Health Tests</a>
        <a href="forum.php" class="<?php echo $current_page === 'forum.php' ? 'active' : ''; ?>">💬 Forum</a>
        <a href="professionals.php" class="<?php echo $current_page === 'professionals.php' ? 'active' : ''; ?>">👨‍⚕️ Professionals</a>
        <a href="volunteer_apply.php" class="<?php echo $current_page === 'volunteer_apply.php' ? 'active' : ''; ?>">🤝 Apply to Volunteer</a>
        <a href="settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">⚙️ Settings</a>
        <a href="profile.php" class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">👤 Profile</a>
        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?');">🚪 Logout</a>
    </nav>
</aside>
