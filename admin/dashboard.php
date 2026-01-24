<?php
/**
 * Safe Space - Admin Dashboard (Placeholder)
 * Main dashboard for administrators
 */

require_once '../config/config.php';

// Check if admin is logged in
if (!is_admin_logged_in()) {
    redirect('../auth/admin_login.php');
}

// Check session timeout
if (!check_session_timeout()) {
    set_flash_message('warning', 'Your session has expired. Please login again.');
    redirect('../auth/admin_login.php');
}

$admin_id = get_admin_id();
$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];

$db = Database::getInstance();
$conn = $db->getConnection();

// Quick stats
$total_admins = (int)($conn->query('SELECT COUNT(*) AS c FROM admins')?->fetch_assoc()['c'] ?? 0);
$total_users = (int)($conn->query('SELECT COUNT(*) AS c FROM users')?->fetch_assoc()['c'] ?? 0);
$total_professionals = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE user_type = 'professional'")?->fetch_assoc()['c'] ?? 0);
$total_volunteers = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE user_type = 'volunteer'")?->fetch_assoc()['c'] ?? 0);
$total_forum_posts = (int)($conn->query("SELECT COUNT(*) AS c FROM forum_posts WHERE status = 'published'")?->fetch_assoc()['c'] ?? 0);
$total_blog_posts = (int)($conn->query("SELECT COUNT(*) AS c FROM blog_posts WHERE status = 'published'")?->fetch_assoc()['c'] ?? 0);

// Updated by Shuvo - START
// Community quick stats (safe even if tables are missing)
$pending_community_requests = 0;
try {
    $t = $conn->query("SHOW TABLES LIKE 'community_requests'");
    $hasCommunityRequests = ($t && $t->num_rows > 0);
    if ($t) { $t->free(); }
    if ($hasCommunityRequests) {
        $pending_community_requests = (int)($conn->query("SELECT COUNT(*) AS c FROM community_requests WHERE status = 'pending' OR status IS NULL")?->fetch_assoc()['c'] ?? 0);
    }
} catch (Throwable $e) {
    $pending_community_requests = 0;
}
// Updated by Shuvo - END

$page_title = "Admin Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | Safe Space Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body {
            background:
                radial-gradient(circle at 18% 22%, rgba(20, 184, 166, 0.18), transparent 34%),
                radial-gradient(circle at 80% 18%, rgba(123, 93, 255, 0.16), transparent 36%),
                radial-gradient(circle at 60% 72%, rgba(58, 199, 255, 0.16), transparent 38%),
                var(--light-bg);
            min-height: 100vh;
            padding: 2rem;
            margin: 0;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .admin-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }
        
        .admin-title {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .admin-card {
            background: var(--bg-card, #F8F9F7);
            padding: 2rem;
            border-radius: 18px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .admin-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
        }
        
        .card-icon svg {
            width: 22px;
            height: 22px;
            fill: white;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Admin Header with Logo -->
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
            <img src="../images/logo.png" alt="Safe Space Logo" style="width: 50px; height: 50px; border-radius: 12px;">
            <div>
                <h2 style="margin: 0; color: var(--text-primary);">Admin Dashboard</h2>
                <p style="margin: 0.25rem 0 0 0; color: var(--text-secondary); font-size: 0.9rem;">Welcome, <?php echo htmlspecialchars($admin_name); ?></p>
            </div>
        </div>
        <!-- Admin Header -->
        <div class="admin-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 class="admin-title"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display: inline-block; margin-right: 10px; vertical-align: -0.18em;"><path d="M12 1l9 4v6c0 5.55-3.84 10.74-9 12-5.16-1.26-9-6.45-9-12V5l9-4z"/></svg> Admin Dashboard</h1>
                    <p>Welcome, <strong><?php echo htmlspecialchars($admin_name); ?></strong> (<?php echo ucfirst($admin_role); ?>)</p>
                </div>
                <div>
                    <a href="../dashboard/logout.php" class="btn" style="background: var(--bg-card, #F8F9F7); color: #FF6B6B;">Logout</a>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="admin-grid">
            <div class="admin-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo (int)$total_admins; ?></div>
                <div class="stat-label">Total Admins</div>
            </div>

            <div class="admin-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo (int)$total_users; ?></div>
                <div class="stat-label">Total Users</div>
            </div>

            <div class="admin-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo (int)$total_professionals; ?></div>
                <div class="stat-label">Professionals</div>
            </div>

            <div class="admin-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo (int)$total_volunteers; ?></div>
                <div class="stat-label">Volunteers</div>
            </div>

            <div class="admin-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo (int)$total_forum_posts; ?></div>
                <div class="stat-label">Forum Posts</div>
            </div>

            <div class="admin-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 14H7v-2h10v2zm0-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo (int)$total_blog_posts; ?></div>
                <div class="stat-label">Blog Posts</div>
            </div>
        </div>

        <!-- Management Sections -->
        <div class="admin-grid" style="margin-top: 2rem;">
            <div class="admin-card">
                <h3 style="color: var(--text-primary); margin-bottom: 1rem;">User Management</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                    Manage user accounts, verify professionals, approve volunteers.
                </p>
                <a class="btn" href="volunteer_applications.php" style="background: var(--primary-color); color: #fff;">Review Volunteer Applications</a>
            </div>

            <!-- Updated by Shuvo - START -->
            <div class="admin-card">
                <h3 style="color: var(--text-primary); margin-bottom: 1rem;">Community Management</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                    Review community creation requests and community volunteer needs.
                </p>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a class="btn" href="community_requests.php" style="background: var(--primary-color); color: #fff;">
                        Review Community Requests
                        <?php if ($pending_community_requests > 0): ?>
                            <span style="margin-left:8px; display:inline-block; padding:2px 8px; border-radius:999px; background: rgba(255,255,255,0.22); color:#fff; font-weight: 900; font-size: 12px;">
                                <?php echo (int)$pending_community_requests; ?> pending
                            </span>
                        <?php endif; ?>
                    </a>
                    <a class="btn" href="community_volunteer_needs.php" style="background: var(--secondary-color); color: #fff;">Review Volunteer Needs</a>
                </div>
            </div>
            <!-- Updated by Shuvo - END -->

            <div class="admin-card">
                <h3 style="color: var(--text-primary); margin-bottom: 1rem;">Content Moderation</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                    Review and moderate forum posts, comments, and reported content.
                </p>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a class="btn" href="forum_posts.php" style="background: var(--primary-color); color: #fff;">Manage Forum Posts</a>
                    <a class="btn" href="blog_posts.php" style="background: var(--secondary-color); color: #fff;">Manage Blog Posts</a>
                </div>
            </div>

            <div class="admin-card">
                <h3 style="color: var(--text-primary); margin-bottom: 1rem;">Analytics & Reports</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                    View platform statistics, user engagement, and generate reports.
                </p>
                <span class="coming-soon">Coming Soon</span>
            </div>

            <div class="admin-card">
                <h3 style="color: var(--text-primary); margin-bottom: 1rem;">System Settings</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                    Configure platform settings, security, and notifications.
                </p>
                <span class="coming-soon">Coming Soon</span>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="admin-card" style="margin-top: 2rem;">
            <h3 style="color: var(--text-primary); margin-bottom: 1rem;">Recent Activity</h3>
            <p style="color: var(--text-secondary);">
                Activity log will appear here showing user registrations, logins, and system events.
            </p>
            <span class="coming-soon">Coming Soon</span>
        </div>
    </div>
</body>
</html>
