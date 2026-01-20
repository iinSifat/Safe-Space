<?php
/**
 * Dashboard Sidebar Navigation
 * Reusable sidebar component for all dashboard pages
 */

// Get current page to highlight active link
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <a href="index.php" class="nav-brand">
        <img src="../images/logo.png" alt="Safe Space Logo" style="width: 40px; height: 40px; border-radius: 12px;">
        Safe Space
    </a>
    <nav class="nav-links">
        <a href="index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
            Dashboard
        </a>
        <a href="mood_tracker.php" class="<?php echo $current_page === 'mood_tracker.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/>
            </svg>
            Mood Tracker
        </a>
        <a href="mental_health_tests.php" class="<?php echo $current_page === 'mental_health_tests.php' || $current_page === 'take_test.php' || $current_page === 'test_results.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Mental Health Tests
        </a>
        <a href="forum.php" class="<?php echo $current_page === 'forum.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
            Forum
        </a>
        <a href="blogs.php" class="<?php echo $current_page === 'blogs.php' || $current_page === 'blog_view.php' || $current_page === 'blog_edit.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <path d="M7 8h10"/>
                <path d="M7 12h10"/>
                <path d="M7 16h6"/>
            </svg>
            Blog
        </a>
        <a href="professionals.php" class="<?php echo $current_page === 'professionals.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/>
            </svg>
            Professionals
        </a>
        <a href="volunteer_apply.php" class="<?php echo $current_page === 'volunteer_apply.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M16 11a2 2 0 100-4 2 2 0 000 4zM9 7a4 4 0 100 8 4 4 0 000-8z"/>
            </svg>
            Apply to Volunteer
        </a>
        <a href="settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6M4.22 4.22l4.24 4.24m5.08 0l4.24-4.24M1 12h6m6 0h6M4.22 19.78l4.24-4.24m5.08 0l4.24 4.24M19 12a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            Settings
        </a>
        <a href="profile.php" class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/>
            </svg>
            Profile
        </a>
        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?');">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5m7-7l-7 7 7 7"/>
            </svg>
            Logout
        </a>
    </nav>
</aside>

<script>
    // If the dashboard is rendered inside the forum overlay iframe,
    // force sidebar navigation to open as a full page (top window).
    (function () {
        if (window.self === window.top) return;

        document.querySelectorAll('.sidebar .nav-links a[href]').forEach(function (a) {
            a.setAttribute('target', '_top');
        });
    })();
</script>
