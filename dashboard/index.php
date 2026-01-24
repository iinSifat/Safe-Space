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

$is_professional_user = (($user['user_type'] ?? '') === 'professional');
$professional_profile = $is_professional_user ? get_professional_profile((int)$user_id) : null;

// Get user points (non-professional only)
$user_points = [];
if (!$is_professional_user) {
    $points_stmt = $conn->prepare("SELECT total_points, tier_level, streak_days FROM user_points WHERE user_id = ?");
    $points_stmt->bind_param("i", $user_id);
    $points_stmt->execute();
    $points_result = $points_stmt->get_result();
    $user_points = $points_result->fetch_assoc();
    $points_stmt->close();
}

// Get today's mood (non-professional only)
$today_mood = null;
if (!$is_professional_user) {
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
}

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

// Get user badges (non-professional only)
$badges = [];
if (!$is_professional_user) {
    $badge_stmt = $conn->prepare(" 
        SELECT badge_name, badge_description FROM user_badges
        WHERE user_id = ?
        ORDER BY earned_at DESC LIMIT 6
    ");
    $badge_stmt->bind_param("i", $user_id);
    $badge_stmt->execute();
    $badge_result = $badge_stmt->get_result();
    while ($row = $badge_result->fetch_assoc()) {
        $badges[] = $row;
    }
    $badge_stmt->close();
}
$notif_stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_res = $notif_stmt->get_result();
$notif = $notif_res->fetch_assoc();
$unread_count = $notif['unread_count'] ?? 0;
$notif_stmt->close();

// Professional workspace metrics
$professional_draft_blogs = 0;
$professional_flagged_items = 0;
$professional_forum_posts = 0;
$professional_forum_replies = 0;

// Scheduling/session management metrics (professional only)
$session_requested_count = 0;
$session_accepted_count = 0;
$session_completed_count = 0;
$session_follow_up_count = 0;
$session_high_risk_open_count = 0;
$next_session = null;

// Dashboard client overview (professional only)
$client_overview_rows = [];
$today_schedule_rows = [];

if ($is_professional_user) {
    if (function_exists('ensure_professional_sessions_table')) {
        ensure_professional_sessions_table();
    }

    $draft_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM blog_posts WHERE user_id = ? AND status = 'draft'");
    $draft_stmt->bind_param('i', $user_id);
    $draft_stmt->execute();
    $professional_draft_blogs = (int)($draft_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $draft_stmt->close();

    $flagged_stmt = $conn->prepare("
        SELECT
            (
                (SELECT COUNT(*) FROM blog_posts WHERE user_id = ? AND status = 'flagged') +
                (SELECT COUNT(*) FROM blog_comments WHERE user_id = ? AND status = 'flagged') +
                (SELECT COUNT(*) FROM forum_posts WHERE user_id = ? AND status = 'flagged') +
                (SELECT COUNT(*) FROM forum_replies WHERE user_id = ? AND status = 'flagged')
            ) AS c
    ");
    $flagged_stmt->bind_param('iiii', $user_id, $user_id, $user_id, $user_id);
    $flagged_stmt->execute();
    $professional_flagged_items = (int)($flagged_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $flagged_stmt->close();

    $fp_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM forum_posts WHERE user_id = ?");
    $fp_stmt->bind_param('i', $user_id);
    $fp_stmt->execute();
    $professional_forum_posts = (int)($fp_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $fp_stmt->close();

    $fr_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM forum_replies WHERE user_id = ?");
    $fr_stmt->bind_param('i', $user_id);
    $fr_stmt->execute();
    $professional_forum_replies = (int)($fr_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $fr_stmt->close();

    // Session queue counts (best-effort; table may not exist if DB privileges are limited)
    $sess_counts_stmt = $conn->prepare("SELECT
            SUM(status = 'requested') AS requested_count,
            SUM(status = 'accepted') AS accepted_count,
            SUM(status = 'completed') AS completed_count,
            SUM(follow_up_required = 1 AND status IN ('accepted','completed')) AS follow_up_count,
            SUM(risk_level IN ('high','critical') AND status IN ('requested','accepted')) AS high_risk_open_count
        FROM professional_sessions
        WHERE professional_user_id = ?");
    if ($sess_counts_stmt) {
        $sess_counts_stmt->bind_param('i', $user_id);
        if ($sess_counts_stmt->execute()) {
            $sc = $sess_counts_stmt->get_result()->fetch_assoc();
            $session_requested_count = (int)($sc['requested_count'] ?? 0);
            $session_accepted_count = (int)($sc['accepted_count'] ?? 0);
            $session_completed_count = (int)($sc['completed_count'] ?? 0);
            $session_follow_up_count = (int)($sc['follow_up_count'] ?? 0);
            $session_high_risk_open_count = (int)($sc['high_risk_open_count'] ?? 0);
        }
        $sess_counts_stmt->close();
    }

    $next_stmt = $conn->prepare("SELECT client_alias, scheduled_at, risk_level
        FROM professional_sessions
        WHERE professional_user_id = ? AND status = 'accepted' AND scheduled_at IS NOT NULL AND scheduled_at >= NOW()
        ORDER BY scheduled_at ASC
        LIMIT 1");
    if ($next_stmt) {
        $next_stmt->bind_param('i', $user_id);
        if ($next_stmt->execute()) {
            $next_session = $next_stmt->get_result()->fetch_assoc();
        }
        $next_stmt->close();
    }

    // Client overview: show recent and upcoming interactions (anonymized)
    $overview_stmt = $conn->prepare("SELECT session_id, client_alias, primary_concern, risk_level, preferred_session_type, preferred_duration_minutes, scheduled_at, status, private_notes, follow_up_required, updated_at
        FROM professional_sessions
        WHERE professional_user_id = ? AND status IN ('requested','accepted','completed')
        ORDER BY COALESCE(scheduled_at, updated_at) DESC
        LIMIT 12");
    if ($overview_stmt) {
        $overview_stmt->bind_param('i', $user_id);
        if ($overview_stmt->execute()) {
            $client_overview_rows = $overview_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $overview_stmt->close();
    }

    // Today's schedule
    $today_stmt = $conn->prepare("SELECT session_id, client_alias, primary_concern, risk_level, preferred_session_type, preferred_duration_minutes, scheduled_at, status, private_notes, follow_up_required
        FROM professional_sessions
        WHERE professional_user_id = ? AND status = 'accepted' AND scheduled_at IS NOT NULL AND DATE(scheduled_at) = CURDATE()
        ORDER BY scheduled_at ASC
        LIMIT 12");
    if ($today_stmt) {
        $today_stmt->bind_param('i', $user_id);
        if ($today_stmt->execute()) {
            $today_schedule_rows = $today_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $today_stmt->close();
    }
}

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
            background: var(--bg-surface, #F3F5F2);
            border-right: 1px solid var(--border-soft, #D8E2DD);
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
            background: var(--bg-surface, #F3F5F2);
            border-bottom: 1px solid var(--border-soft, #D8E2DD);
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
            border-bottom: 1px solid var(--border-soft, #D8E2DD);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
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
            background: var(--accent-primary, #7FAFA3);
            color: #FFFFFF;
        }

        .nav-links a:not(.active) {
            color: var(--text-secondary);
        }

        .nav-links a:not(.active):hover {
            background: var(--bg-card, #F8F9F7);
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
            background: var(--bg-card, #F8F9F7);
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
            color: var(--accent-primary, #7FAFA3);
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
            background: rgba(127, 175, 163, 0.15);
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
            border: 2px solid var(--border-soft, #D8E2DD);
            border-radius: var(--radius-md);
            background: var(--bg-card, #F8F9F7);
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
            border-color: var(--accent-primary, #7FAFA3);
            background: rgba(127, 175, 163, 0.08);
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
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Top Bar with Notifications -->
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">Dashboard</h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px; display: flex; align-items: center; gap: 8px;">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Notifications
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
                <h1>Welcome back, <?php echo htmlspecialchars($user['username']); ?>! <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-left: 8px;"><path d="M5.5 8c.7-.3 1.2-.8 1.5-1.7.3.9.8 1.4 1.5 1.7-.7.3-1.2.8-1.5 1.7-.3-.9-.8-1.4-1.5-1.7z M15 13c1.4-.7 2.4-1.6 3-2.9.6 1.3 1.6 2.2 3 2.9-1.4.7-2.4 1.6-3 2.9-.6-1.3-1.6-2.2-3-2.9z M22 10c1-.5 1.8-1.2 2.2-2.2.4 1 1.2 1.7 2.2 2.2-1 .5-1.8 1.2-2.2 2.2-.4-1-1.2-1.7-2.2-2.2z"/></svg></h1>
                <p style="opacity: 0.9;">
                    <?php echo $is_professional_user ? 'Professional workspace — participate in the community with verified guidance.' : 'Keep moving forward on your wellness journey'; ?>
                </p>
            </div>
            <div class="header-right">
                <?php if ($is_professional_user): ?>
                    <div class="mood-badge">
                        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" class="mood-emoji-large"><path d="M12 2l3 7 7 3-7 3-3 7-3-7-7-3 7-3 3-7z"/></svg>
                        <span><?php echo htmlspecialchars(professional_authority_label($professional_profile['specialization'] ?? '', $professional_profile['verification_status'] ?? '')); ?></span>
                    </div>
                <?php else: ?>
                    <?php if ($today_mood): ?>
                        <div class="mood-badge">
                            <span class="mood-emoji-large"><?php echo htmlspecialchars($today_mood['mood_emoji']); ?></span>
                            <span><?php echo htmlspecialchars($today_mood['mood_label']); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="mood-badge">
                            <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" class="mood-emoji-large"><line x1="12" y1="2" x2="12" y2="22"/><polyline points="4 7 12 2 20 7"/><polyline points="4 17 12 22 20 17"/><line x1="2" y1="12" x2="22" y2="12"/></svg>
                            <span>Log your mood</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="dashboard-grid">
            <?php if ($is_professional_user): ?>
                <div class="card">
                    <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M12 2l7 4v6c0 5-3 9-7 10-4-1-7-5-7-10V6l7-4z"/></svg>Professional Status</div>
                    <div class="stat-display"><?php echo htmlspecialchars(($professional_profile['verification_status'] ?? '') === 'verified' ? 'Verified' : 'Not Verified'); ?></div>
                    <div class="stat-label">Account Verification</div>
                    <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                        <?php echo htmlspecialchars($professional_profile['specialization'] ?? ''); ?>
                    </p>
                    <p style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-secondary);">
                        Accepting clients: <?php echo !empty($professional_profile['is_accepting_patients']) ? 'Yes' : 'No'; ?>
                    </p>
                </div>

                <div class="card">
                    <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-9 14H7v-2h3v2zm0-4H7v-2h3v2zm0-4H7V7h3v2zm7 8h-5v-2h5v2zm0-4h-5v-2h5v2zm0-4h-5V7h5v2z"/></svg>Schedule & Requests</div>
                    <div class="stat-display"><?php echo (int)$session_requested_count; ?></div>
                    <div class="stat-label">New Session Requests</div>
                    <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                        Upcoming accepted: <?php echo (int)$session_accepted_count; ?>
                        <?php if ($session_follow_up_count > 0): ?>
                            • Follow-ups: <?php echo (int)$session_follow_up_count; ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($next_session && !empty($next_session['scheduled_at'])): ?>
                        <p style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-secondary);">
                            Next: <?php echo htmlspecialchars($next_session['client_alias'] ?? 'Client'); ?> • <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($next_session['scheduled_at']))); ?>
                        </p>
                    <?php else: ?>
                        <p style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-secondary);">
                            Next: Not scheduled
                        </p>
                    <?php endif; ?>
                    <p style="margin-top: 0.75rem; font-size: 0.9rem;">
                        <a href="professionals.php" style="color: var(--primary-color); font-weight: 700; text-decoration: none;">Open session workspace</a>
                    </p>
                </div>

                <div class="card">
                    <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M21 15a4 4 0 0 1-4 4H7l-4 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>Work Summary</div>
                    <div class="stat-display"><?php echo (int)$session_completed_count; ?></div>
                    <div class="stat-label">Sessions Completed</div>
                    <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                        High-risk open: <?php echo (int)$session_high_risk_open_count; ?>
                        • Draft blogs: <?php echo (int)$professional_draft_blogs; ?>
                    </p>
                    <p style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-secondary);">
                        Forum replies: <?php echo (int)$professional_forum_replies; ?> • Threads: <?php echo (int)$professional_forum_posts; ?>
                    </p>
                    <p style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-secondary);">
                        Held items: <?php echo (int)$professional_flagged_items; ?>
                    </p>
                </div>
            <?php else: ?>
                <!-- Points Card -->
                <div class="card">
                    <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><polygon points="12 2 15.09 10.26 24 10.26 17.55 16.5 19.64 24.76 12 19.52 4.36 24.76 6.45 16.5 0 10.26 8.91 10.26"/></svg>Points & Tier</div>
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
                    <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M13.49 5.48c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm-3.6 13.9l1-4.4 2.1 2v6h2v-7.5l-2.1-2 .6-3c1.3 1.5 3.3 2.5 5.5 2.5v-2c-1.9 0-3.5-1-4.3-2.4l-1-1.6c-.4-.6-1-1-1.7-1-.3 0-.5.1-.8.1l5.2 7.3v3.9h2V9.1h-2l-1.6 5.4L2.7 9h3V7H3c-.5 0-1 .4-1 1v4h3.6L9.6 7.7 9.9 15.1z"/></svg>Streak</div>
                    <div class="stat-display"><?php echo $user_points['streak_days'] ?? 0; ?></div>
                    <div class="stat-label">Day Streak</div>
                    <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                        Keep logging your mood daily to build your streak!
                    </p>
                </div>

                <!-- Badges Card -->
                <div class="card">
                    <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M6 9c0-1 .895-2 2-2h8c1.105 0 2 .895 2 2v8c0 1.105-.895 2-2 2H8c-1.105 0-2-.895-2-2V9z"/><path d="M9 5c0-.552.448-1 1-1h4c.552 0 1 .448 1 1"/></svg>Badges</div>
                    <div class="stat-display"><?php echo count($badges); ?></div>
                    <div class="stat-label">Earned</div>
                    <?php if (count($badges) > 0): ?>
                        <div class="badges-grid" style="margin-top: 1rem;">
                            <?php foreach (array_slice($badges, 0, 3) as $badge): ?>
                                <div class="badge-item" title="<?php echo htmlspecialchars($badge['badge_description']); ?>">
                                    <div class="badge-icon"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M6 9c0-1 .895-2 2-2h8c1.105 0 2 .895 2 2v8c0 1.105-.895 2-2 2H8c-1.105 0-2-.895-2-2V9z"/><path d="M9 5c0-.552.448-1 1-1h4c.552 0 1 .448 1 1"/></svg></div>
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
            <?php endif; ?>
        </div>
        <!-- Recent Activity / Professional Panel -->
        <?php if ($is_professional_user): ?>
            <div class="card">
                <div class="card-title">Client Overview</div>
                <ul class="activity-list">
                    <?php if (count($today_schedule_rows) > 0): ?>
                        <li>
                            <div>
                                <div class="activity-title">Today’s Schedule</div>
                                <div class="activity-date">Accepted sessions scheduled for today</div>
                            </div>
                        </li>
                        <?php foreach ($today_schedule_rows as $s): ?>
                            <?php
                                $needs_notes = empty($s['private_notes']);
                                $duration = (int)($s['preferred_duration_minutes'] ?? 0);
                                $session_type = (string)($s['preferred_session_type'] ?? '');
                            ?>
                            <li>
                                <div>
                                    <div class="activity-title">
                                        <?php echo htmlspecialchars($s['client_alias'] ?? 'Client'); ?>
                                        <?php if (!empty($s['risk_level'])): ?>
                                            • Risk: <?php echo htmlspecialchars($s['risk_level']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($s['primary_concern'])): ?>
                                            • <?php echo htmlspecialchars($s['primary_concern']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-date">
                                        <?php echo htmlspecialchars(date('g:i A', strtotime($s['scheduled_at']))); ?>
                                        <?php if ($duration > 0): ?> • <?php echo (int)$duration; ?> min<?php endif; ?>
                                        <?php if ($session_type !== ''): ?> • <?php echo htmlspecialchars($session_type); ?><?php endif; ?>
                                        <?php if (!empty($s['follow_up_required'])): ?> • Follow-up<?php endif; ?>
                                        <?php if ($needs_notes): ?> • Notes pending<?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (count($client_overview_rows) > 0): ?>
                        <li>
                            <div>
                                <div class="activity-title">Recent Client Sessions</div>
                                <div class="activity-date">Last interaction time, issue tags, and risk level (anonymized)</div>
                            </div>
                        </li>
                        <?php foreach ($client_overview_rows as $s): ?>
                            <?php
                                $needs_notes = (in_array(($s['status'] ?? ''), ['completed'], true) && empty($s['private_notes']));
                                $last_touch = $s['updated_at'] ?? null;
                            ?>
                            <li>
                                <div>
                                    <div class="activity-title">
                                        <?php echo htmlspecialchars($s['client_alias'] ?? 'Client'); ?>
                                        • <?php echo htmlspecialchars($s['status'] ?? ''); ?>
                                        <?php if (!empty($s['risk_level'])): ?> • Risk: <?php echo htmlspecialchars($s['risk_level']); ?><?php endif; ?>
                                        <?php if (!empty($s['primary_concern'])): ?> • <?php echo htmlspecialchars($s['primary_concern']); ?><?php endif; ?>
                                    </div>
                                    <div class="activity-date">
                                        <?php if (!empty($last_touch)): ?>
                                            Last interaction: <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($last_touch))); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($s['scheduled_at'])): ?>
                                            • Scheduled: <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($s['scheduled_at']))); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($s['follow_up_required'])): ?> • Follow-up<?php endif; ?>
                                        <?php if ($needs_notes): ?> • Notes pending<?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>
                            <div>
                                <div class="activity-title">No client sessions yet</div>
                                <div class="activity-date">Client requests will appear once submitted.</div>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php else: ?>
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
        <?php endif; ?>

            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
</body>
</html>
