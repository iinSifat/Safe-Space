<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

// Updated by Shuvo - START
// Support viewing another user's profile (professionals/volunteers can view; only owner can edit).
$user_id = get_user_id();
$viewer_user_type = get_user_type();
$profile_user_id = $user_id;

$requested_profile_user_id = (int)($_GET['user_id'] ?? 0);
if ($requested_profile_user_id > 0 && $requested_profile_user_id !== (int)$user_id) {
    if (in_array($viewer_user_type, ['professional', 'volunteer'], true)) {
        $profile_user_id = $requested_profile_user_id;
    }
}

$is_profile_owner = ((int)$profile_user_id === (int)$user_id);

// Local helper: table existence check for graceful degradation.
$__ss_table_exists = function(mysqli $conn, string $table): bool {
    $res = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    if (!$res) { return false; }
    $ok = $res->num_rows > 0;
    $res->free();
    return $ok;
};
// Updated by Shuvo - END
$db = Database::getInstance();
$conn = $db->getConnection();

// Get user profile
// Updated by Shuvo - START
$user_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $profile_user_id);
// Updated by Shuvo - END
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// Updated by Shuvo - START
if (!$user) {
    // Avoid exposing internal details; just redirect safely.
    redirect('index.php');
}
// Updated by Shuvo - END

$is_professional_user = (($user['user_type'] ?? '') === 'professional');
// Updated by Shuvo - START
$professional_profile = $is_professional_user ? get_professional_profile((int)$profile_user_id) : null;
// Updated by Shuvo - END

// Updated by Shuvo - START
// Role display policy: never show "Patient"/role labels on profile.
// Only show a premium verified-style indicator if the user is an approved volunteer.
$is_verified_volunteer = (($user['user_type'] ?? '') === 'volunteer');
if (!$is_verified_volunteer && function_exists('user_has_volunteer_permission')) {
    $is_verified_volunteer = user_has_volunteer_permission((int)$user_id);
}
// Updated by Shuvo - END

// Get points (non-professional only)
$points_data = [];
if ($is_profile_owner && !$is_professional_user) {
    $points_stmt = $conn->prepare("SELECT * FROM user_points WHERE user_id = ?");
    $points_stmt->bind_param("i", $user_id);
    $points_stmt->execute();
    $points_data = $points_stmt->get_result()->fetch_assoc();
    $points_stmt->close();
}

// Get badges (non-professional only)
$badges = [];
if ($is_profile_owner && !$is_professional_user) {
    $badge_stmt = $conn->prepare("SELECT * FROM user_badges WHERE user_id = ? ORDER BY earned_at DESC");
    $badge_stmt->bind_param("i", $user_id);
    $badge_stmt->execute();
    while ($row = $badge_stmt->get_result()->fetch_assoc()) {
        $badges[] = $row;
    }
    $badge_stmt->close();
}

// Professional contribution counts
$professional_forum_posts = 0;
$professional_forum_replies = 0;
$professional_blog_published = 0;
$professional_blog_drafts = 0;

if ($is_professional_user) {
    $fp_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM forum_posts WHERE user_id = ? AND status <> 'deleted'");
    // Updated by Shuvo - START
    $fp_stmt->bind_param('i', $profile_user_id);
    // Updated by Shuvo - END
    $fp_stmt->execute();
    $professional_forum_posts = (int)($fp_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $fp_stmt->close();

    $fr_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM forum_replies WHERE user_id = ? AND status <> 'deleted'");
    // Updated by Shuvo - START
    $fr_stmt->bind_param('i', $profile_user_id);
    // Updated by Shuvo - END
    $fr_stmt->execute();
    $professional_forum_replies = (int)($fr_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $fr_stmt->close();

    $bp_stmt = $conn->prepare("SELECT SUM(status='published') AS published_count, SUM(status='draft') AS draft_count FROM blog_posts WHERE user_id = ? AND status IN ('published','draft')");
    // Updated by Shuvo - START
    $bp_stmt->bind_param('i', $profile_user_id);
    // Updated by Shuvo - END
    $bp_stmt->execute();
    $row = $bp_stmt->get_result()->fetch_assoc() ?? [];
    $professional_blog_published = (int)($row['published_count'] ?? 0);
    $professional_blog_drafts = (int)($row['draft_count'] ?? 0);
    $bp_stmt->close();
}

// Updated by Shuvo - START
// Health Metrics (optional) - fetch + owner-only update
$has_health_metrics_table = $__ss_table_exists($conn, 'user_health_metrics');
$health_metrics = null;
$bmi_value = null;
$bmi_status = null;

function __ss_bmi_status_who($bmi_value, $age_years) {
    $bmi = (float)$bmi_value;
    $age = ($age_years === null || $age_years === '') ? null : (int)$age_years;

    // WHO adult BMI classification is defined for adults; for under-18, BMI-for-age is required.
    // To keep results accurate, we only classify when age is >= 18 (or missing).
    if ($age !== null && $age < 18) {
        return null;
    }

    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25) return 'Normal';
    if ($bmi < 30) return 'Overweight';
    return 'Obese';
}

if ($has_health_metrics_table) {
    $hm_stmt = $conn->prepare("SELECT age_years, height_cm, weight_kg, updated_at FROM user_health_metrics WHERE user_id = ? LIMIT 1");
    $hm_stmt->bind_param('i', $profile_user_id);
    $hm_stmt->execute();
    $health_metrics = $hm_stmt->get_result()->fetch_assoc() ?: null;
    $hm_stmt->close();

    $height_cm = isset($health_metrics['height_cm']) ? (float)$health_metrics['height_cm'] : 0.0;
    $weight_kg = isset($health_metrics['weight_kg']) ? (float)$health_metrics['weight_kg'] : 0.0;
    if ($height_cm > 0 && $weight_kg > 0) {
        $m = $height_cm / 100.0;
        $bmi_value = round($weight_kg / ($m * $m), 1);

        $bmi_status = __ss_bmi_status_who($bmi_value, $health_metrics['age_years'] ?? null);
    }
}

$health_message = '';
$health_message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_health_metrics'])) {
    if (!$is_profile_owner) {
        $health_message_type = 'error';
        $health_message = 'You can only edit your own health information.';
    } elseif (!$has_health_metrics_table) {
        $health_message_type = 'error';
        $health_message = 'Health metrics are not available right now.';
    } else {
        $age_raw = trim((string)($_POST['health_age'] ?? ''));
        $height_raw = trim((string)($_POST['health_height_cm'] ?? ''));
        $weight_raw = trim((string)($_POST['health_weight_kg'] ?? ''));

        $age_years = ($age_raw === '') ? null : (int)$age_raw;
        $height_cm = ($height_raw === '') ? null : (float)$height_raw;
        $weight_kg = ($weight_raw === '') ? null : (float)$weight_raw;

        if ($age_years !== null && ($age_years < 1 || $age_years > 120)) {
            $health_message_type = 'error';
            $health_message = 'Please enter a valid age.';
        } elseif ($height_cm !== null && ($height_cm < 30 || $height_cm > 250)) {
            $health_message_type = 'error';
            $health_message = 'Please enter a valid height in cm.';
        } elseif ($weight_kg !== null && ($weight_kg < 2 || $weight_kg > 500)) {
            $health_message_type = 'error';
            $health_message = 'Please enter a valid weight in kg.';
        } else {
            // Store optional fields as NULL when empty (avoid empty inputs becoming 0)
            $up_stmt = $conn->prepare(
                "INSERT INTO user_health_metrics (user_id, age_years, height_cm, weight_kg)
                 VALUES (?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''))
                 ON DUPLICATE KEY UPDATE
                    age_years = VALUES(age_years),
                    height_cm = VALUES(height_cm),
                    weight_kg = VALUES(weight_kg),
                    updated_at = CURRENT_TIMESTAMP"
            );
            $age_bind = ($age_years === null) ? '' : (string)$age_years;
            $height_bind = ($height_cm === null) ? '' : (string)$height_cm;
            $weight_bind = ($weight_kg === null) ? '' : (string)$weight_kg;
            $up_stmt->bind_param('isss', $user_id, $age_bind, $height_bind, $weight_bind);
            if ($up_stmt->execute()) {
                $health_message = 'Health metrics updated.';
            } else {
                $health_message_type = 'error';
                $health_message = 'Unable to update health metrics.';
            }
            $up_stmt->close();

            // Refresh in-memory view
            $hm_stmt = $conn->prepare("SELECT age_years, height_cm, weight_kg, updated_at FROM user_health_metrics WHERE user_id = ? LIMIT 1");
            $hm_stmt->bind_param('i', $profile_user_id);
            $hm_stmt->execute();
            $health_metrics = $hm_stmt->get_result()->fetch_assoc() ?: null;
            $hm_stmt->close();

            $height_cm_now = isset($health_metrics['height_cm']) ? (float)$health_metrics['height_cm'] : 0.0;
            $weight_kg_now = isset($health_metrics['weight_kg']) ? (float)$health_metrics['weight_kg'] : 0.0;
            $bmi_value = null;
            if ($height_cm_now > 0 && $weight_kg_now > 0) {
                $m = $height_cm_now / 100.0;
                $bmi_value = round($weight_kg_now / ($m * $m), 1);

                $bmi_status = __ss_bmi_status_who($bmi_value, $health_metrics['age_years'] ?? null);
            }
        }
    }
}
// Updated by Shuvo - END

// Handle profile update
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Updated by Shuvo - START
    if (!$is_profile_owner) {
        $update_message = 'You can only edit your own profile.';
    } else {
    // Updated by Shuvo - END
    $bio = sanitize_input($_POST['bio'] ?? '');
    $country = sanitize_input($_POST['country'] ?? '');
    
    $update_stmt = $conn->prepare("UPDATE users SET bio = ?, country = ? WHERE user_id = ?");
    $update_stmt->bind_param("ssi", $bio, $country, $user_id);
    if ($update_stmt->execute()) {
        $update_message = "Profile updated successfully!";
        $user['bio'] = $bio;
        $user['country'] = $country;
    }
    $update_stmt->close();

    // Updated by Shuvo - START
    }
    // Updated by Shuvo - END
}

// Handle forum post delete (soft delete)
$forum_message = '';
$forum_message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_forum_post'])) {
    // Updated by Shuvo - START
    if (!$is_profile_owner) {
        $forum_message_type = 'error';
        $forum_message = 'You can only manage your own posts.';
    } else {
    // Updated by Shuvo - END
    $post_id = (int)($_POST['post_id'] ?? 0);
    if ($post_id > 0) {
        $delete_stmt = $conn->prepare("UPDATE forum_posts SET status = 'deleted' WHERE post_id = ? AND user_id = ?");
        $delete_stmt->bind_param('ii', $post_id, $user_id);
        $delete_stmt->execute();
        $affected = $delete_stmt->affected_rows;
        $delete_stmt->close();

        if ($affected > 0) {
            $forum_message = 'Post deleted successfully.';
        } else {
            $forum_message_type = 'error';
            $forum_message = 'Unable to delete that post.';
        }
    } else {
        $forum_message_type = 'error';
        $forum_message = 'Invalid post.';
    }

    // Updated by Shuvo - START
    }
    // Updated by Shuvo - END
}

// Handle blog post delete (soft delete)
$blog_message = '';
$blog_message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_blog_post'])) {
    // Updated by Shuvo - START
    if (!$is_profile_owner) {
        $blog_message_type = 'error';
        $blog_message = 'You can only manage your own blog posts.';
    } else {
    // Updated by Shuvo - END
    $blog_id = (int)($_POST['blog_id'] ?? 0);
    if ($blog_id > 0) {
        $delete_stmt = $conn->prepare("UPDATE blog_posts SET status = 'deleted' WHERE blog_id = ? AND user_id = ?");
        $delete_stmt->bind_param('ii', $blog_id, $user_id);
        $delete_stmt->execute();
        $affected = $delete_stmt->affected_rows;
        $delete_stmt->close();

        if ($affected > 0) {
            $blog_message = 'Blog post deleted successfully.';
        } else {
            $blog_message_type = 'error';
            $blog_message = 'Unable to delete that blog post.';
        }
    } else {
        $blog_message_type = 'error';
        $blog_message = 'Invalid blog post.';
    }

    // Updated by Shuvo - START
    }
    // Updated by Shuvo - END
}

// Fetch user's forum posts
$my_posts = [];
if ($is_profile_owner) {
    $my_posts_stmt = $conn->prepare("
    SELECT post_id, title, category, view_count, reply_count, created_at, status
    FROM forum_posts
    WHERE user_id = ? AND status <> 'deleted'
    ORDER BY created_at DESC
    LIMIT 50
");
$my_posts_stmt->bind_param('i', $user_id);
$my_posts_stmt->execute();
$my_posts_result = $my_posts_stmt->get_result();
$my_posts = $my_posts_result->fetch_all(MYSQLI_ASSOC);
$my_posts_stmt->close();
}

// Fetch user's blog posts
$my_blogs = [];
if ($is_profile_owner) {
    $my_blogs_stmt = $conn->prepare("
    SELECT blog_id, title, category, view_count, comment_count, created_at, status
    FROM blog_posts
    WHERE user_id = ? AND status <> 'deleted'
    ORDER BY created_at DESC
    LIMIT 50
");
$my_blogs_stmt->bind_param('i', $user_id);
$my_blogs_stmt->execute();
$my_blogs_result = $my_blogs_stmt->get_result();
$my_blogs = $my_blogs_result->fetch_all(MYSQLI_ASSOC);
$my_blogs_stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
        <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
        }

        .profile-info h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        /* Updated by Shuvo - START */
        .profile-name-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .volunteer-verified {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            box-shadow:
                0 0 0 3px rgba(255, 255, 255, 0.12),
                0 0 18px rgba(255, 255, 255, 0.28);
            backdrop-filter: blur(6px);
        }

        .volunteer-verified svg {
            width: 18px;
            height: 18px;
            stroke: #ffffff;
        }
        /* Updated by Shuvo - END */

        .profile-card {
            background: var(--bg-card, #F8F9F7);
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        /* Updated by Shuvo - START */
        .health-metrics-note {
            margin-top: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .health-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 1rem;
        }

        @media (max-width: 900px) {
            .health-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        .health-item {
            background: rgba(127, 175, 163, 0.08);
            border: 1px solid var(--border-soft, #D8E2DD);
            border-radius: 14px;
            padding: 14px;
        }

        .health-label {
            color: var(--text-secondary);
            font-weight: 800;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }

        .health-value {
            color: var(--text-primary);
            font-weight: 900;
            font-size: 1.1rem;
        }

        .health-form {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        @media (max-width: 900px) {
            .health-form { grid-template-columns: 1fr; }
        }

        .health-inline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        /* Updated by Shuvo - END */

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-sm);
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            background: var(--light-bg);
            padding: 1rem;
            border-radius: var(--radius-sm);
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 1rem;
        }

        .badge-item {
            text-align: center;
            padding: 1rem;
            background: var(--light-bg);
            border-radius: var(--radius-sm);
        }

        .badge-emoji {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .badge-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .success-alert {
            background: rgba(111, 207, 151, 0.15);
            color: #2d7a4d;
            padding: 1rem;
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--success);
            margin-bottom: 1rem;
            display: none;
        }

        .success-alert.show {
            display: block;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
            border-left: 4px solid var(--error);
        }

        .my-posts-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .my-post-item {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            padding: 14px;
            border: 1px solid var(--border-soft, #D8E2DD);
            border-radius: var(--radius-md);
            background: var(--bg-card, #F8F9F7);
        }

        .my-post-title {
            font-weight: 800;
            color: var(--text-primary);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 6px;
        }

        .my-post-title:hover {
            text-decoration: underline;
        }

        .my-post-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .my-post-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .btn-sm {
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.18);
        }

        @media (max-width: 768px) {
            .my-post-item {
                flex-direction: column;
            }
            .my-post-actions {
                width: 100%;
            }
            .my-post-actions a,
            .my-post-actions button {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg>My Profile</h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                        <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Notifications
                    </a>
                </div>
            </div>
            
            <div class="content-area">
    <div class="profile-container" style="max-width: 1000px; margin: 0 auto;">
        <!-- Success Alert -->
        <div class="success-alert <?php echo !empty($update_message) ? 'show' : ''; ?>">
            <?php echo htmlspecialchars($update_message); ?>
        </div>

        <?php if (!empty($forum_message)): ?>
            <div class="success-alert show <?php echo $forum_message_type === 'error' ? 'alert-error' : ''; ?>">
                <?php echo htmlspecialchars($forum_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($blog_message)): ?>
            <div class="success-alert show <?php echo $blog_message_type === 'error' ? 'alert-error' : ''; ?>">
                <?php echo htmlspecialchars($blog_message); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="2" class="profile-avatar"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg>
            <div class="profile-info">
                <!-- Updated by Shuvo - START -->
                <div class="profile-name-row">
                    <h1 style="margin: 0;"><?php echo htmlspecialchars($user['username']); ?></h1>
                    <?php if ($is_verified_volunteer): ?>
                        <span class="volunteer-verified" title="Verified Volunteer" aria-label="Verified Volunteer">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 6L9 17l-5-5" />
                            </svg>
                        </span>
                    <?php endif; ?>
                </div>
                <!-- Updated by Shuvo - END -->
                <?php if ($is_professional_user): ?>
                    <p style="opacity: 0.9; margin-bottom: 0.5rem; font-weight: 800;">
                        <?php echo htmlspecialchars(professional_authority_label($professional_profile['specialization'] ?? '', $professional_profile['verification_status'] ?? '')); ?>
                    </p>
                <?php endif; ?>
                <p style="opacity: 0.8; font-size: 0.95rem;">
                    Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                </p>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="profile-card">
            <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><line x1="12" y1="2" x2="12" y2="22"/><polyline points="4 7 12 2 20 7"/><polyline points="4 17 12 22 20 17"/><line x1="2" y1="12" x2="22" y2="12"/></svg><?php echo $is_professional_user ? 'Professional Summary' : 'Your Statistics'; ?></div>
            <div class="stat-row">
                <?php if ($is_professional_user): ?>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo htmlspecialchars(($professional_profile['verification_status'] ?? '') === 'verified' ? 'Verified' : 'Not Verified'); ?></div>
                        <div class="stat-label">Verification</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo htmlspecialchars($professional_profile['specialization'] ?? ''); ?></div>
                        <div class="stat-label">Specialization</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo (int)$professional_forum_replies; ?></div>
                        <div class="stat-label">Forum Replies</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo (int)$professional_blog_drafts; ?></div>
                        <div class="stat-label">Draft Blogs</div>
                    </div>
                <?php else: ?>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $points_data['total_points'] ?? 0; ?></div>
                        <div class="stat-label">Total Points</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo ucfirst($points_data['tier_level'] ?? 'bronze'); ?></div>
                        <div class="stat-label">Tier</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $points_data['streak_days'] ?? 0; ?></div>
                        <div class="stat-label">Day Streak</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo count($badges); ?></div>
                        <div class="stat-label">Badges</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Updated by Shuvo - START -->
        <!-- Health Metrics Section (Informational / Calculated Data) -->
        <?php if ($has_health_metrics_table): ?>
        <div class="profile-card" id="healthMetricsCard">
            <div class="health-inline">
                <div class="card-title" style="margin-bottom: 0;"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M12 21s-8-4.5-8-11a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 6.5-8 11-8 11z"/></svg>Health Metrics</div>
                <div style="color: var(--text-secondary); font-weight: 800;">Informational / Calculated Data</div>
            </div>

            <?php if (!empty($health_message)): ?>
                <div class="alert <?php echo $health_message_type === 'error' ? 'error' : 'success'; ?>" style="margin-top: 1rem;">
                    <?php echo htmlspecialchars($health_message); ?>
                </div>
            <?php endif; ?>

            <?php
                $age_disp = $health_metrics['age_years'] ?? null;
                $height_disp = $health_metrics['height_cm'] ?? null;
                $weight_disp = $health_metrics['weight_kg'] ?? null;
                $gender_disp = $user['gender'] ?? null;
            ?>
            <div class="health-grid">
                <div class="health-item">
                    <div class="health-label">Age</div>
                    <div class="health-value"><?php echo $age_disp ? (int)$age_disp . ' yrs' : '—'; ?></div>
                </div>
                <div class="health-item">
                    <div class="health-label">Height</div>
                    <div class="health-value"><?php echo $height_disp ? htmlspecialchars((string)$height_disp) . ' cm' : '—'; ?></div>
                </div>
                <div class="health-item">
                    <div class="health-label">Weight</div>
                    <div class="health-value"><?php echo $weight_disp ? htmlspecialchars((string)$weight_disp) . ' kg' : '—'; ?></div>
                </div>
                <div class="health-item">
                    <div class="health-label">BMI</div>
                    <div class="health-value" style="display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap;">
                        <span id="bmiValueDisplay"><?php echo $bmi_value !== null ? 'Calculated BMI: ' . htmlspecialchars((string)$bmi_value) : 'Calculated BMI: —'; ?></span>
                        <span style="color: var(--text-secondary);">•</span>
                        <span id="bmiStatusDisplay"><?php echo $bmi_status !== null ? htmlspecialchars((string)$bmi_status) : '—'; ?></span>
                    </div>
                </div>
            </div>

            <div class="health-metrics-note" style="margin-top: 0.75rem;">
                Gender (read-only): <strong><?php echo $gender_disp ? htmlspecialchars(str_replace('_', ' ', (string)$gender_disp)) : '—'; ?></strong>
            </div>

            <?php if ($is_profile_owner): ?>
                <form method="POST" action="" style="margin-top: 1rem;">
                    <div class="health-form">
                        <div class="form-group" style="margin: 0;">
                            <label>Age (optional)</label>
                            <input type="number" name="health_age" id="healthAge" min="1" max="120" value="<?php echo htmlspecialchars((string)($health_metrics['age_years'] ?? '')); ?>" placeholder="e.g., 24">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label>Height (cm)</label>
                            <input type="number" name="health_height_cm" id="healthHeight" step="0.1" min="30" max="250" value="<?php echo htmlspecialchars((string)($health_metrics['height_cm'] ?? '')); ?>" placeholder="e.g., 170">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label>Weight (kg)</label>
                            <input type="number" name="health_weight_kg" id="healthWeight" step="0.1" min="2" max="500" value="<?php echo htmlspecialchars((string)($health_metrics['weight_kg'] ?? '')); ?>" placeholder="e.g., 65">
                        </div>
                    </div>
                    <button type="submit" name="update_health_metrics" class="btn btn-primary" style="width: 100%; margin-top: 12px;">
                        Save Health Metrics
                    </button>
                </form>
            <?php else: ?>
                <div class="health-metrics-note" style="margin-top: 1rem;">
                    Only the profile owner can add or edit health information.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <!-- Updated by Shuvo - END -->

        <!-- Edit Profile -->
        <?php if ($is_profile_owner): ?>
        <div class="profile-card">
            <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>Edit Profile</div>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                </div>

                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" placeholder="Tell us about yourself...">
<?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>" 
                           placeholder="Where are you from?">
                </div>

                <button type="submit" name="update_profile" class="btn btn-primary" style="width: 100%;">
                    Save Changes
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Badges Section -->
        <?php if ($is_profile_owner && !$is_professional_user && count($badges) > 0): ?>
        <div class="profile-card">
            <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M6 9c0-1 .895-2 2-2h8c1.105 0 2 .895 2 2v8c0 1.105-.895 2-2 2H8c-1.105 0-2-.895-2-2V9z"/><path d="M9 5c0-.552.448-1 1-1h4c.552 0 1 .448 1 1"/><path d="M12 14v-3M10 11h4"/></svg>Your Badges</div>
            <div class="badges-grid">
                <?php foreach ($badges as $badge): ?>
                    <div class="badge-item" title="<?php echo htmlspecialchars($badge['badge_description']); ?>">
                        <div class="badge-emoji">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width: 32px; height: 32px;"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
                        </div>
                        <div class="badge-name"><?php echo htmlspecialchars($badge['badge_name']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- My Forum Posts -->
        <?php if ($is_profile_owner): ?>
        <div class="profile-card">
            <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><path d="M8 10h8"/><path d="M8 14h5"/></svg>My Forum Posts</div>

            <?php if (count($my_posts) === 0): ?>
                <p style="color: var(--text-secondary); margin: 0;">You haven’t posted in the forum yet.</p>
                <div style="margin-top: 12px;">
                    <a href="forum.php" class="btn btn-primary">Go to Forum</a>
                </div>
            <?php else: ?>
                <div class="my-posts-list">
                    <?php foreach ($my_posts as $post): ?>
                        <div class="my-post-item">
                            <div>
                                <a class="my-post-title" href="forum_view.php?post_id=<?php echo (int)$post['post_id']; ?>">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                                <div class="my-post-meta">
                                    <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41 11 3H4v7l9.59 9.59a2 2 0 0 0 2.82 0l4.18-4.18a2 2 0 0 0 0-2.82z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg> <?php echo htmlspecialchars($post['category']); ?></span>
                                    <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg> <?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                    <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> <?php echo (int)$post['view_count']; ?></span>
                                    <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> <?php echo (int)$post['reply_count']; ?></span>
                                </div>
                            </div>
                            <div class="my-post-actions">
                                <a class="btn btn-secondary btn-sm" href="forum_edit.php?post_id=<?php echo (int)$post['post_id']; ?>">Edit</a>
                                <form method="POST" action="" onsubmit="return confirm('Delete this post? This will remove it from the forum.');" style="margin: 0;">
                                    <input type="hidden" name="post_id" value="<?php echo (int)$post['post_id']; ?>">
                                    <button type="submit" name="delete_forum_post" class="btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 14px; color: var(--text-secondary); font-size: 0.9rem;">
                    Showing your latest 50 posts.
                </div>
            <?php endif; ?>
        </div>

        <!-- My Blog Posts -->

        <div class="profile-card">
            <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><path d="M7 8h10"/><path d="M7 12h10"/><path d="M7 16h6"/></svg>My Blog Posts</div>

            <?php if (count($my_blogs) === 0): ?>
                <p style="color: var(--text-secondary); margin: 0;">You haven’t written any blog posts yet.</p>
                <div style="margin-top: 12px;">
                    <a href="blogs.php" class="btn btn-primary">Go to Blog</a>
                </div>
            <?php else: ?>
                <div class="my-posts-list">
                    <?php foreach ($my_blogs as $blog): ?>
                        <div class="my-post-item">
                            <div>
                                <a class="my-post-title" href="blog_view.php?blog_id=<?php echo (int)$blog['blog_id']; ?>">
                                    <?php echo htmlspecialchars($blog['title']); ?>
                                </a>
                                <div class="my-post-meta">
                                    <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41 11 3H4v7l9.59 9.59a2 2 0 0 0 2.82 0l4.18-4.18a2 2 0 0 0 0-2.82z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg> <?php echo htmlspecialchars($blog['category']); ?></span>
                                    <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg> <?php echo date('M j, Y', strtotime($blog['created_at'])); ?></span>
                                    <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> <?php echo (int)$blog['view_count']; ?></span>
                                    <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> <?php echo (int)$blog['comment_count']; ?></span>
                                </div>
                            </div>
                            <div class="my-post-actions">
                                <a class="btn btn-secondary btn-sm" href="blog_edit.php?blog_id=<?php echo (int)$blog['blog_id']; ?>">Edit</a>
                                <form method="POST" action="" onsubmit="return confirm('Delete this blog post? This will remove it from the blog page.');" style="margin: 0;">
                                    <input type="hidden" name="blog_id" value="<?php echo (int)$blog['blog_id']; ?>">
                                    <button type="submit" name="delete_blog_post" class="btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 14px; color: var(--text-secondary); font-size: 0.9rem;">
                    Showing your latest 50 blog posts.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
            <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
            <a href="mood_tracker.php" class="btn btn-secondary">Mood Tracker</a>
            <a href="../dashboard/logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </div>

    <script>
        setTimeout(() => {
            document.querySelector('.success-alert.show')?.classList.remove('show');
        }, 3000);

        // Updated by Shuvo - START
        // Live BMI status calculation for the owner form (informational only)
        (function initHealthMetricsBmi() {
            const heightEl = document.getElementById('healthHeight');
            const weightEl = document.getElementById('healthWeight');
            const ageEl = document.getElementById('healthAge');
            const bmiValueEl = document.getElementById('bmiValueDisplay');
            const bmiStatusEl = document.getElementById('bmiStatusDisplay');
            if (!heightEl || !weightEl || !bmiValueEl || !bmiStatusEl) return;

            function bmiStatusWho(bmi, age) {
                const ageNum = Number.isFinite(age) ? age : null;
                // WHO adult BMI categories (under-18 requires BMI-for-age)
                if (ageNum !== null && ageNum < 18) return null;
                if (bmi < 18.5) return 'Underweight';
                if (bmi < 25) return 'Normal';
                if (bmi < 30) return 'Overweight';
                return 'Obese';
            }

            function computeAndRender() {
                const h = parseFloat(heightEl.value);
                const w = parseFloat(weightEl.value);
                if (!Number.isFinite(h) || !Number.isFinite(w) || h <= 0 || w <= 0) {
                    bmiValueEl.textContent = 'Calculated BMI: —';
                    bmiStatusEl.textContent = '—';
                    return;
                }
                const a = ageEl ? parseInt(ageEl.value, 10) : NaN;
                const m = h / 100;
                const bmi = w / (m * m);
                const status = bmiStatusWho(bmi, Number.isFinite(a) ? a : null);
                const bmiRounded = Math.round(bmi * 10) / 10;
                bmiValueEl.textContent = 'Calculated BMI: ' + String(bmiRounded);
                bmiStatusEl.textContent = status ? status : '—';
            }

            heightEl.addEventListener('input', computeAndRender);
            weightEl.addEventListener('input', computeAndRender);
            ageEl?.addEventListener('input', computeAndRender);
            computeAndRender();
        })();
        // Updated by Shuvo - END
    </script>
    </div><!-- End profile-container -->
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
</body>
</html>
