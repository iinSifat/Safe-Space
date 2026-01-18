<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get user profile
$user_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// Get points
$points_stmt = $conn->prepare("SELECT * FROM user_points WHERE user_id = ?");
$points_stmt->bind_param("i", $user_id);
$points_stmt->execute();
$points_data = $points_stmt->get_result()->fetch_assoc();
$points_stmt->close();

// Get badges
$badge_stmt = $conn->prepare("SELECT * FROM user_badges WHERE user_id = ? ORDER BY earned_at DESC");
$badge_stmt->bind_param("i", $user_id);
$badge_stmt->execute();
$badges = [];
while ($row = $badge_stmt->get_result()->fetch_assoc()) {
    $badges[] = $row;
}
$badge_stmt->close();

// Handle profile update
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $bio = sanitize_input($_POST['bio'] ?? '');
    $country = sanitize_input($_POST['country'] ?? '');
    
    $update_stmt = $conn->prepare("UPDATE users SET bio = ?, country = ? WHERE user_id = ?");
    $update_stmt->bind_param("ssi", $bio, $country, $user_id);
    if ($update_stmt->execute()) {
        $update_message = "✓ Profile updated successfully!";
        $user['bio'] = $bio;
        $user['country'] = $country;
    }
    $update_stmt->close();
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

        .profile-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

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
                        🔔 Notifications
                    </a>
                </div>
            </div>
            
            <div class="content-area">
    <div class="profile-container" style="max-width: 1000px; margin: 0 auto;">
        <!-- Success Alert -->
        <div class="success-alert <?php echo !empty($update_message) ? 'show' : ''; ?>">
            <?php echo htmlspecialchars($update_message); ?>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="2" class="profile-avatar"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                <p style="opacity: 0.9; margin-bottom: 0.5rem;">
                    Role: <?php echo ucfirst($user['user_type']); ?>
                </p>
                <p style="opacity: 0.8; font-size: 0.95rem;">
                    Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                </p>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="profile-card">
            <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><line x1="12" y1="2" x2="12" y2="22"/><polyline points="4 7 12 2 20 7"/><polyline points="4 17 12 22 20 17"/><line x1="2" y1="12" x2="22" y2="12"/></svg>Your Statistics</div>
            <div class="stat-row">
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
            </div>
        </div>

        <!-- Edit Profile -->
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

        <!-- Badges Section -->
        <?php if (count($badges) > 0): ?>
        <div class="profile-card">
            <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M6 9c0-1 .895-2 2-2h8c1.105 0 2 .895 2 2v8c0 1.105-.895 2-2 2H8c-1.105 0-2-.895-2-2V9z"/><path d="M9 5c0-.552.448-1 1-1h4c.552 0 1 .448 1 1"/><path d="M12 14v-3M10 11h4"/></svg>Your Badges</div>
            <div class="badges-grid">
                <?php foreach ($badges as $badge): ?>
                    <div class="badge-item" title="<?php echo htmlspecialchars($badge['badge_description']); ?>">
                        <div class="badge-emoji">🎖️</div>
                        <div class="badge-name"><?php echo htmlspecialchars($badge['badge_name']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
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
    </script>
    </div><!-- End profile-container -->
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
</body>
</html>
