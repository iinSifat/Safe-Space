<?php
/**
 * Update after shovu
 * Safe Space - User Dashboard
 * Main hub for users to access all features
 */

require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get user info
$user_stmt = $conn->prepare("SELECT user_id, username, user_type, profile_picture FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Get user points
$points_stmt = $conn->prepare("SELECT total_points, tier_level, streak_days FROM user_points WHERE user_id = ?");
$points_stmt->bind_param("i", $user_id);
$points_stmt->execute();
$points_result = $points_stmt->get_result();
$user_points = $points_result->fetch_assoc();
$points_stmt->close();

// Get today's mood
$today = date('Y-m-d');
$today_mood_stmt = $conn->prepare("
    SELECT mood_level, mood_emoji, mood_label, logged_at FROM mood_logs
    WHERE user_id = ? AND DATE(logged_at) = ?
    ORDER BY logged_at DESC LIMIT 1
");
$today_mood_stmt->bind_param("is", $user_id, $today);
$today_mood_stmt->execute();
$today_mood_result = $today_mood_stmt->get_result();
$today_mood = $today_mood_result->fetch_assoc();
$today_mood_stmt->close();

// Get recent forum activity
$forum_stmt = $conn->prepare("
    SELECT post_id, title, category, created_at FROM forum_posts
    WHERE user_id = ? 
    ORDER BY created_at DESC LIMIT 5
");
$forum_stmt->bind_param("i", $user_id);
$forum_stmt->execute();
$forum_result = $forum_stmt->get_result();
$recent_posts = [];
while ($row = $forum_result->fetch_assoc()) {
    $recent_posts[] = $row;
}
$forum_stmt->close();

// Get user badges
$badge_stmt = $conn->prepare("
    SELECT badge_name, badge_description FROM user_badges
    WHERE user_id = ?
    ORDER BY earned_at DESC LIMIT 6
");
$badge_stmt->bind_param("i", $user_id);
$badge_stmt->execute();
$badge_result = $badge_stmt->get_result();
$badges = [];
while ($row = $badge_result->fetch_assoc()) {
    $badges[] = $row;
}
$badge_stmt->close();
$notif_stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_res = $notif_stmt->get_result();
$notif = $notif_res->fetch_assoc();
$unread_count = $notif['unread_count'] ?? 0;
$notif_stmt->close();

// Calculate tier progress
$tier_info = [
    'bronze' => ['min' => 0, 'max' => 499],
    'silver' => ['min' => 500, 'max' => 1499],
    'gold' => ['min' => 1500, 'max' => 999999]
];
$current_points = $user_points['total_points'] ?? 0;
$current_tier = $user_points['tier_level'] ?? 'bronze';
$tier_data = $tier_info[$current_tier];
$tier_progress = (($current_points - $tier_data['min']) / ($tier_data['max'] - $tier_data['min'])) * 100;
$tier_progress = min($tier_progress, 100);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: rgba(255, 255, 255, 0.95);
            border-right: 1px solid rgba(12, 27, 51, 0.08);
            padding: 0;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 0;
        }

        .top-bar {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(12, 27, 51, 0.08);
            padding: 0 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            height: 64px;
        }

        .top-bar-right {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .nav-brand {
            padding: 12px 24px;
            height: 64px;
            border-bottom: 1px solid rgba(12, 27, 51, 0.08);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }

        .nav-brand:hover {
            opacity: 0.8;
        }

        .brand-badge {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: grid;
            place-items: center;
            box-shadow: var(--shadow-sm);
            color: white;
            font-weight: 800;
            font-size: 16px;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 0 12px;
        }

        .nav-links a {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-fast);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-links a.active {
            background: var(--primary-color);
            color: white;
        }

        .nav-links a:not(.active) {
            color: var(--text-secondary);
        }

        .nav-links a:not(.active):hover {
            background: var(--light-bg);
        }

        .content-area {
            padding: 2rem;
        }

        .header-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
        }

        .header-left h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header-right {
            text-align: right;
        }

        .mood-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.2);
            padding: 12px 20px;
            border-radius: var(--radius-md);
            font-size: 1.1rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .mood-emoji-large {
            font-size: 2rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-normal);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-display {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background: var(--light-gray);
            border-radius: 5px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
            transition: width 0.5s ease;
            border-radius: 5px;
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .badge-item {
            text-align: center;
            padding: 1rem;
            background: var(--light-bg);
            border-radius: var(--radius-sm);
            transition: all var(--transition-fast);
            cursor: pointer;
        }

        .badge-item:hover {
            background: linear-gradient(135deg, rgba(107, 155, 209, 0.1), rgba(184, 166, 217, 0.1));
            transform: scale(1.05);
        }

        .badge-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .badge-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .activity-list {
            list-style: none;
        }

        .activity-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-list li:last-child {
            border-bottom: none;
        }

        .activity-title {
            color: var(--text-primary);
            font-weight: 500;
        }

        .activity-date {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            padding: 1rem;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-md);
            background: white;
            cursor: pointer;
            transition: all var(--transition-normal);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
            font-weight: 600;
        }

        .action-btn:hover {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(107, 155, 209, 0.05), rgba(184, 166, 217, 0.05));
        }

        .action-icon {
            font-size: 1.5rem;
        }

        .action-text {
            font-size: 0.9rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .header-card {
                flex-direction: column;
                text-align: center;
            }

            .header-right {
                text-align: center;
            }

            .header-left h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        
        <!-- Left Sidebar -->
        <aside class="sidebar">
            <a href="index.php" class="nav-brand">
                <img src="../images/logo.png" alt="Safe Space Logo" style="width: 40px; height: 40px; border-radius: 12px;">
                Safe Space
            </a>
            <nav class="nav-links">
                <a href="index.php" class="active">📊 Dashboard</a>
                <a href="mood_tracker.php">😊 Mood Tracker</a>
                <a href="mental_health_tests.php">🧠 Mental Health Tests</a>
                <a href="forum.php">💬 Forum</a>
                <a href="professionals.php">👨‍⚕️ Professionals</a>
                <a href="volunteer_apply.php">🤝 Apply to Volunteer</a>
                <a href="settings.php">⚙️ Settings</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../dashboard/logout.php" onclick="return confirm('Are you sure you want to logout?');">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Top Bar with Notifications -->
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">Dashboard</h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px; display: flex; align-items: center; gap: 8px;">
                        🔔 Notifications
                        <?php if ($unread_count > 0): ?>
                            <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"><?php echo intval($unread_count); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
        <div class="header-card">
            <div class="header-left">
                <h1>Welcome back, <?php echo htmlspecialchars($user['username']); ?>! 👋</h1>
                <p style="opacity: 0.9;">Keep moving forward on your wellness journey</p>
            </div>
            <div class="header-right">
                <?php if ($today_mood): ?>
                    <div class="mood-badge">
                        <span class="mood-emoji-large"><?php echo htmlspecialchars($today_mood['mood_emoji']); ?></span>
                        <span><?php echo htmlspecialchars($today_mood['mood_label']); ?></span>
                    </div>
                <?php else: ?>
                    <div class="mood-badge">
                        <span class="mood-emoji-large">📊</span>
                        <span>Log your mood</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="dashboard-grid">
            <!-- Points Card -->
            <div class="card">
                <div class="card-title">⭐ Points & Tier</div>
                <div class="stat-display"><?php echo $user_points['total_points'] ?? 0; ?></div>
                <div class="stat-label">Total Points Earned</div>
                <div style="margin-top: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600; color: var(--text-primary);">Tier Progress</span>
                        <span style="color: var(--primary-color); font-weight: 700;">
                            <?php echo ucfirst($current_tier); ?>
                        </span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $tier_progress; ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Streak Card -->
            <div class="card">
                <div class="card-title">🔥 Streak</div>
                <div class="stat-display"><?php echo $user_points['streak_days'] ?? 0; ?></div>
                <div class="stat-label">Day Streak</div>
                <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                    Keep logging your mood daily to build your streak!
                </p>
            </div>

            <!-- Badges Card -->
            <div class="card">
                <div class="card-title">🏆 Badges</div>
                <div class="stat-display"><?php echo count($badges); ?></div>
                <div class="stat-label">Earned</div>
                <?php if (count($badges) > 0): ?>
                    <div class="badges-grid" style="margin-top: 1rem;">
                        <?php foreach (array_slice($badges, 0, 3) as $badge): ?>
                            <div class="badge-item" title="<?php echo htmlspecialchars($badge['badge_description']); ?>">
                                <div class="badge-icon">🎖️</div>
                                <div class="badge-name"><?php echo htmlspecialchars(substr($badge['badge_name'], 0, 10)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                        Start engaging to earn badges!
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <!-- Recent Activity -->
        <?php if (count($recent_posts) > 0): ?>
        <div class="card">
            <div class="card-title">📝 Your Recent Posts</div>
            <ul class="activity-list">
                <?php foreach ($recent_posts as $post): ?>
                    <li>
                        <div>
                            <div class="activity-title"><?php echo htmlspecialchars(substr($post['title'], 0, 50)); ?></div>
                            <div class="activity-date">
                                in <?php echo htmlspecialchars($post['category']); ?> • 
                                <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
</body>
</html>
