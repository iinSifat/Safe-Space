<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get user
$user_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$message = '';
$error = '';

// Handle privacy settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    $update_stmt = $conn->prepare("UPDATE users SET is_anonymous = ? WHERE user_id = ?");
    $update_stmt->bind_param("ii", $is_anonymous, $user_id);
    if ($update_stmt->execute()) {
        $message = "✓ Settings updated successfully!";
        $user['is_anonymous'] = $is_anonymous;
    }
    $update_stmt->close();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (!verify_password($current_pass, $user['password_hash'])) {
        $error = "Current password is incorrect.";
    } elseif (empty($new_pass) || strlen($new_pass) < 8) {
        $error = "New password must be at least 8 characters.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Passwords do not match.";
    } else {
        $hashed_pass = hash_password($new_pass);
        $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $update_stmt->bind_param("si", $hashed_pass, $user_id);
        if ($update_stmt->execute()) {
            $message = "✓ Password changed successfully!";
        }
        $update_stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .settings-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .settings-card {
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

        .setting-group {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .setting-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .setting-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .setting-description {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
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

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-sm);
            font-family: inherit;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            border-left: 4px solid;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: rgba(111, 207, 151, 0.15);
            color: #2d7a4d;
            border-left-color: var(--success);
        }

        .alert-error {
            background: rgba(235, 87, 87, 0.15);
            color: #c72e2e;
            border-left-color: var(--error);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 10px 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">Settings</h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px; display: flex; align-items: center; gap: 8px;">
                        🔔 Notifications
                    </a>
                </div>
            </div>

            <div class="content-area">
                <div class="settings-container">
                    <!-- Header -->
                    <div class="settings-header">
                        <h1><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 12px; vertical-align: middle;"><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6M4.22 4.22l4.24 4.24m5.08 0l4.24-4.24M1 12h6m6 0h6M4.22 19.78l4.24-4.24m5.08 0l4.24 4.24M19 12a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>Settings</h1>
                        <p>Manage your account settings and preferences</p>
                    </div>

                    <!-- Alerts -->
                    <div class="alert alert-success <?php echo !empty($message) ? 'show' : ''; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <div class="alert alert-error <?php echo !empty($error) ? 'show' : ''; ?>">
                        <?php echo htmlspecialchars($error); ?>
                    </div>

                    <!-- Privacy & Anonymity Settings -->
                    <div class="settings-card">
                        <div class="card-title">🔒 Privacy & Anonymity</div>
                        
                        <form method="POST" action="">
                            <div class="setting-group">
                                <div class="setting-label">Anonymous Posting</div>
                                <div class="setting-description">
                                    When enabled, your username won't be displayed in your forum posts and comments. 
                                    Your identity remains anonymous to other users.
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="anonymous" name="is_anonymous" 
                                           <?php echo $user['is_anonymous'] ? 'checked' : ''; ?>>
                                    <label for="anonymous" style="margin-bottom: 0;">Post anonymously</label>
                                </div>
                            </div>

                            <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                        </form>
                    </div>

                    <!-- Account Settings -->
                    <div class="settings-card">
                        <div class="card-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg>Account Information</div>
                        
                        <div class="setting-group">
                            <div class="setting-label">Username</div>
                            <p style="color: var(--text-secondary); margin: 0;">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </p>
                        </div>

                        <div class="setting-group">
                            <div class="setting-label">Email Address</div>
                            <p style="color: var(--text-secondary); margin: 0;">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                        </div>

                        <div class="setting-group">
                            <div class="setting-label">Account Type</div>
                            <p style="color: var(--text-secondary); margin: 0;">
                                <?php echo ucfirst($user['user_type']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="settings-card">
                        <div class="card-title">🔐 Change Password</div>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required>
                            </div>

                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" required>
                            </div>

                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" name="confirm_password" required>
                            </div>

                            <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>

                    <!-- Danger Zone -->
                    <div class="settings-card" style="border: 2px solid var(--error);">
                        <div class="card-title" style="color: var(--error);"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3.05h16.94a2 2 0 0 0 1.71-3.05L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Danger Zone</div>
                        
                        <div class="setting-group">
                            <div class="setting-label" style="color: var(--error);">Delete Account</div>
                            <div class="setting-description">
                                Permanently delete your account and all associated data. This action cannot be undone.
                            </div>
                            <button type="button" class="btn btn-secondary" 
                                    onclick="if(confirm('Are you sure? This cannot be undone.')) { alert('Account deletion feature coming soon!'); }">
                                Delete My Account
                            </button>
                        </div>
                    </div>
                </div><!-- End settings-container -->
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->

    <script>
        setTimeout(() => {
            document.querySelectorAll('.alert.show').forEach(el => {
                el.classList.remove('show');
            });
        }, 3000);
    </script>
</body>
</html>
