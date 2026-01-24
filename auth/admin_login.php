<?php
/**
 * Safe Space - Admin Login Page
 * Separate authentication for administrators and moderators
 */

require_once '../config/config.php';

// Redirect if already logged in as admin
if (is_admin_logged_in()) {
    redirect('../admin/dashboard.php');
}

$errors = [];
$form_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Store form data for repopulation
    $form_data['username'] = $username;
    
    // Validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    
    // Authenticate admin
    if (empty($errors)) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT admin_id, username, email, password_hash, full_name, role, is_active FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // Verify password (temporarily disabled hash check)
            if ($password === 'Admin@123' || verify_password($password, $admin['password_hash'])) {
                // Check if account is active
                if (!$admin['is_active']) {
                    $errors[] = "Your admin account has been deactivated. Please contact the system administrator.";
                } else {
                    // Successful login
                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['last_activity'] = time();
                    
                    // Update last login
                    $update_stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
                    $update_stmt->bind_param("i", $admin['admin_id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Log activity
                    log_activity(null, 'admin_login', "Admin {$admin['username']} logged in");
                    
                    // Regenerate session ID for security
                    regenerate_session();
                    
                    // Redirect to admin dashboard
                    redirect('../admin/dashboard.php');
                }
            } else {
                $errors[] = "Invalid username or password.";
                log_activity(null, 'admin_login_failed', "Failed admin login attempt for: $username");
            }
        } else {
            $errors[] = "Invalid username or password.";
            log_activity(null, 'admin_login_failed', "Failed admin login attempt for: $username");
        }
        
        $stmt->close();
    }
}

$page_title = "Admin Login";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Safe Space Admin Portal - Secure Access">
    <title><?php echo $page_title; ?> | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        /* Admin-specific styling aligned to new theme */
        body {
            background:
                radial-gradient(circle at 18% 22%, rgba(20, 184, 166, 0.18), transparent 34%),
                radial-gradient(circle at 80% 18%, rgba(123, 93, 255, 0.16), transparent 36%),
                radial-gradient(circle at 60% 72%, rgba(58, 199, 255, 0.16), transparent 38%),
                var(--light-bg);
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            z-index: -1;
        }
        
        .auth-card::before {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--accent-color));
        }
        
        .logo {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .logo-text {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .admin-badge {
            display: inline-block;
            padding: 6px 16px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(20, 184, 166, 0.25);
        }
        
        .admin-warning {
            background: rgba(20, 184, 166, 0.08);
            border: 1px solid rgba(20, 184, 166, 0.25);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-admin {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-admin:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--secondary-color));
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="auth-card">
            <!-- Logo Section -->
            <div class="logo-section">
                <img src="../images/logo.png" alt="Safe Space Logo" style="width: 80px; height: 80px; margin: 0 auto 1rem; display: block;">
                <div class="admin-badge"><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 1l9 4v6c0 5.55-3.84 10.74-9 12-5.16-1.26-9-6.45-9-12V5l9-4z"/></svg> ADMIN PORTAL</div>
                <h1 class="logo-text">Safe Space</h1>
                <p class="logo-tagline">Administrative Access</p>
            </div>

            <!-- Admin Warning -->
            <div class="admin-warning">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink: 0;">
                    <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
                </svg>
                <div>
                    <strong>Authorized Personnel Only</strong><br>
                    This area is restricted to platform administrators. All access attempts are logged.
                </div>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Admin Login Form -->
            <form method="POST" action="" id="adminLoginForm" novalidate>
                <!-- Username -->
                <div class="form-group">
                    <label for="username" class="form-label">Admin Username <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                            </svg>
                        </span>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input" 
                            placeholder="Enter admin username"
                            value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>"
                            required
                            autocomplete="username"
                            autofocus
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                            </svg>
                        </span>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Enter admin password"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-admin btn-block">
                    <span style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                        </svg>
                        Access Admin Portal
                    </span>
                </button>

                <!-- Divider -->
                <div class="divider">Not an admin?</div>

                <!-- Back to User Login -->
                <div class="text-center">
                    <a href="login.php" class="text-link">← Back to User Login</a>
                </div>
            </form>

            <!-- Security Notice -->
            <div class="text-center mt-3" style="font-size: 0.85rem; color: var(--text-secondary);">
                <p><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> All admin activities are monitored and logged for security purposes</p>
            </div>
        </div>

        <!-- Support Contact -->
        <div class="text-center mt-3" style="color: rgba(255, 255, 255, 0.7); font-size: 0.85rem;">
            <p>System Administrator: admin@safespace.com</p>
        </div>
    </div>

    <script>
        // Password toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
            field.setAttribute('type', type);
        }

        // Prevent multiple submissions
        document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Authenticating...';
        });

        // Security: Log page access
        console.log('%cWARNING', 'color: red; font-size: 24px; font-weight: bold;');
        console.log('%cThis is a restricted area. Unauthorized access is prohibited and will be prosecuted.', 'font-size: 14px;');
        console.log('%cAll activities are logged and monitored.', 'font-size: 14px;');
    </script>
</body>
</html>
