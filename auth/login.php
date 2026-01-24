<?php
/**
 * Safe Space - User Login Page
 * Authentication for patients, professionals, and volunteers
 */

require_once '../config/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('../dashboard/index.php');
}

$errors = [];
$form_data = [];

// Get flash messages
$flash = get_flash_message();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_or_email = sanitize_input($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    $login_role = sanitize_input($_POST['login_role'] ?? 'community');
    
    // Store form data for repopulation
    $form_data['username_or_email'] = $username_or_email;
    $form_data['login_role'] = $login_role;
    
    // Validation
    if (empty($username_or_email)) {
        $errors[] = "Username or email is required.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    
    // Authenticate user
    if (empty($errors)) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Check if input is email or username
        $field = validate_email($username_or_email) ? 'email' : 'username';
        
        $stmt = $conn->prepare("SELECT user_id, username, email, password_hash, user_type, is_active, is_verified FROM users WHERE $field = ?");
        $stmt->bind_param("s", $username_or_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (verify_password($password, $user['password_hash'])) {
                // Check if account is active
                if (!$user['is_active']) {
                    $errors[] = "Your account has been deactivated. Please contact support.";
                } else {
                    // If user explicitly chose Professional, enforce that the account is professional.
                    if ($login_role === 'professional' && ($user['user_type'] ?? '') !== 'professional') {
                        $errors[] = 'This account is not a professional account. Please choose Community Member sign-in.';
                    }

                    // If user chose Community Member, block professional accounts.
                    // Community includes patients/supporters/volunteers; professionals must use the Professional option.
                    if ($login_role !== 'professional' && ($user['user_type'] ?? '') === 'professional') {
                        $errors[] = 'This is a professional account. Please choose Professional sign-in.';
                    }

                    if (empty($errors)) {
                        // Successful login
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['is_verified'] = $user['is_verified'];
                        $_SESSION['last_activity'] = time();

                        // Update last login
                        $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                        $update_stmt->bind_param("i", $user['user_id']);
                        $update_stmt->execute();
                        $update_stmt->close();

                        // Log activity
                        log_activity($user['user_id'], 'login', 'User logged in successfully');

                        // Regenerate session ID for security
                        regenerate_session();

                        // Handle remember me
                        if ($remember_me) {
                            setcookie('user_id', $user['user_id'], time() + (86400 * 30), '/'); // 30 days
                        }

                        // Redirect: dashboard index supports professional/community views.
                        redirect('../dashboard/index.php');
                    }
                }
            } else {
                $errors[] = "Invalid username/email or password.";
                log_activity(null, 'login_failed', "Failed login attempt for: $username_or_email");
            }
        } else {
            $errors[] = "Invalid username/email or password.";
            log_activity(null, 'login_failed', "Failed login attempt for: $username_or_email");
        }
        
        $stmt->close();
    }
}

$page_title = "Welcome Back";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to Safe Space - Your mental health support community">
    <title><?php echo $page_title; ?> | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        :root {
            --primary: #14b8a6;
            --primary-dark: #0e9486;
            --text: #0c1b33;
            --muted: #5a6b8a;
            --bg: #f8fbff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at 20% 30%, rgba(45, 206, 180, 0.22), transparent 35%),
                        radial-gradient(circle at 80% 40%, rgba(126, 111, 255, 0.18), transparent 38%),
                        radial-gradient(circle at 60% 80%, rgba(255, 178, 125, 0.18), transparent 40%),
                        var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        .page-shell {
            max-width: 1200px;
            margin: 0 auto;
            /* Offset for fixed header */
            padding: 118px 20px 60px;
        }
        header {
            /* Full-width, tall, stable header (matches landing header structure) */
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: 92px;
            background: rgba(248, 251, 255, 0.9);
            backdrop-filter: blur(12px);
            z-index: 50;
        }

        .header-inner {
            height: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 20px;
            color: var(--text, #0c1b33);
            text-decoration: none;
        }
        nav { display: flex; align-items: center; gap: 30px; }
        nav a { color: var(--muted, #5a6b8a); text-decoration: none; font-weight: 600; font-size: 15px; transition: color 0.2s; }
        nav a:hover { color: var(--text, #0c1b33); }

        .nav-actions { display: flex; align-items: center; gap: 12px; }
        .header-btn {
            border: 0;
            cursor: pointer;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            padding: 12px 18px;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }
        .header-btn-ghost { background: transparent; color: var(--text, #0c1b33); }
        .header-btn-primary {
            background: linear-gradient(135deg, var(--primary, #14b8a6), #3ad0be);
            color: white;
            box-shadow: 0 12px 30px rgba(20, 184, 166, 0.35);
        }
        .header-btn:hover { transform: translateY(-2px); }
        .auth-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 200px);
            padding: 40px 0;
        }
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(12, 27, 51, 0.05);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 18px 40px rgba(12, 27, 51, 0.08);
            width: 100%;
            max-width: 480px;
        }
        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .auth-header h1 {
            font-size: 32px;
            margin: 0 0 8px;
            font-weight: 800;
            color: var(--text);
        }
        .auth-header p {
            color: var(--muted);
            margin: 0;
            font-size: 16px;
        }
        .btn {
            border: 0;
            cursor: pointer;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            padding: 14px 18px;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            width: 100%;
            display: block;
            text-align: center;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #3ad0be);
            color: white;
            box-shadow: 0 12px 30px rgba(20, 184, 166, 0.35);
        }
        .btn-primary:hover { transform: translateY(-2px); }
        .text-link {
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 600;
        }
        .text-link:hover { text-decoration: underline; }

        /* Match the exact role-card UI used on registration.php */
        .role-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .role-card {
            background: var(--bg-card, #F8F9F7);
            border: 2px solid var(--border-soft, #D8E2DD);
            border-radius: 16px;
            padding: 16px;
            cursor: pointer;
            transition: all var(--transition-fast, 0.18s ease);
            text-align: center;
            user-select: none;
        }

        .role-card.active,
        .role-card:hover {
            border-color: var(--accent-primary, #7FAFA3);
            box-shadow: var(--shadow-sm, 0 6px 16px rgba(12, 27, 51, 0.08));
        }

        .role-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 8px;
            background: linear-gradient(135deg, var(--accent-primary, #7FAFA3), var(--accent-soft, #9FBFB6));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform var(--transition-normal, 0.3s ease);
        }

        .role-card:hover .role-icon,
        .role-card.active .role-icon {
            transform: scale(1.1);
        }

        .role-icon svg {
            width: 28px;
            height: 28px;
            fill: var(--white, #F8F9F7);
        }

        .role-title { font-weight: 700; color: var(--text); margin-bottom: 4px; }
        .role-description { color: var(--muted); font-size: 0.9rem; }

        @media (max-width: 768px) {
            header { height: 92px; }
            .header-inner { flex-wrap: wrap; justify-content: center; row-gap: 10px; padding: 10px 16px; }
            nav { width: 100%; justify-content: center; }
            .nav-actions { width: 100%; justify-content: center; }
            .auth-card { padding: 28px; }
            .role-selection { grid-template-columns: 1fr; }
        }
    </style>

</head>
<body>
    <div class="page-shell">
        <header>
            <div class="header-inner">
                <a class="brand" href="<?php echo is_logged_in() ? '../dashboard/index.php' : '../index.php'; ?>">
                    <img src="../images/logo.png" alt="Safe Space" style="width: 40px; height: 40px; border-radius: 12px;">
                    Safe Space
                </a>
                <nav>
                    <a href="../index.php#features">Features</a>
                    <a href="../index.php#about">About</a>
                    <a href="../index.php#stories">Stories</a>
                </nav>
                <div class="nav-actions">
                    <a class="header-btn header-btn-ghost" href="login.php" aria-current="page">Sign In</a>
                    <a class="header-btn header-btn-primary" href="registration.php">Get Started</a>
                </div>
            </div>
        </header>

        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <h1>Welcome Back</h1>
                    <p>Sign in to continue</p>
                </div>

            <!-- Flash Messages -->
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <?php if ($flash['type'] === 'success'): ?>
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        <?php else: ?>
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        <?php endif; ?>
                    </svg>
                    <div><?php echo htmlspecialchars($flash['message']); ?></div>
                </div>
            <?php endif; ?>

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

            <!-- Login Form -->
            <form method="POST" action="" id="loginForm" novalidate>
                <input type="hidden" name="login_role" id="login_role" value="<?php echo htmlspecialchars($form_data['login_role'] ?? 'community'); ?>">

                <div class="form-group">
                    <label class="form-label">I am signing in as <span class="required">*</span></label>
                    <div class="role-selection">
                        <div class="role-card <?php echo (($form_data['login_role'] ?? 'community') === 'community') ? 'active' : ''; ?>" onclick="selectLoginRole('community', event)">
                            <div class="role-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                            </div>
                            <div class="role-title">Community Member</div>
                            <div class="role-description">Seek support, share experiences, and help others</div>
                        </div>

                        <div class="role-card <?php echo (($form_data['login_role'] ?? 'community') === 'professional') ? 'active' : ''; ?>" onclick="selectLoginRole('professional', event)">
                            <div class="role-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                                </svg>
                            </div>
                            <div class="role-title">Professional</div>
                            <div class="role-description">Licensed mental health expert</div>
                        </div>
                    </div>
                </div>

                <!-- Username or Email -->
                <div class="form-group">
                    <label for="username_or_email" class="form-label">Username or Email <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </span>
                        <input 
                            type="text" 
                            id="username_or_email" 
                            name="username_or_email" 
                            class="form-input" 
                            placeholder="Enter your username or email"
                            value="<?php echo htmlspecialchars($form_data['username_or_email'] ?? ''); ?>"
                            required
                            autocomplete="username"
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
                            placeholder="Enter your password"
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

                <!-- Remember Me & Forgot Password -->
                <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="checkbox-wrapper" style="margin-bottom: 0;">
                        <input type="checkbox" id="remember_me" name="remember_me">
                        <label for="remember_me">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="text-link" style="font-size: 0.9rem;">Forgot password?</a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>

                <!-- Divider -->
                <div class="divider">Don't have an account? <a href="registration.php" class="text-link">Sign up</a></div>

                <!-- Admin Login Link -->
                <div class="text-center mt-3">
                    <a href="admin_login.php" class="text-link" style="font-size: 0.85rem; color: var(--text-secondary);">Admin Login</a>
                </div>
            </form>
            </div>
        </div>
    </div>

    <script>
        // Role selection (same interaction pattern as registration.php)
        function selectLoginRole(role, event) {
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('active');
            });

            event.currentTarget.classList.add('active');
            document.getElementById('login_role').value = role;
        }

        // Password toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
            field.setAttribute('type', type);
        }

        // Auto-hide flash messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success, .alert-info');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
    </div>
</body>
</html>
