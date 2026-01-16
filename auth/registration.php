<?php
/**
 * Safe Space - User Registration Page
 * Create Account – Signup with role selection (Community Member or Professional)
 */

require_once '../config/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('../dashboard/index.php');
}

$errors = [];
$success = '';
$form_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $gender = sanitize_input($_POST['gender'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = sanitize_input($_POST['user_type'] ?? '');
    $terms_accepted = isset($_POST['terms_accepted']);
    
    // Store form data for repopulation
    $form_data = [
        'full_name' => $full_name,
        'phone' => $phone,
        'email' => $email,
        'gender' => $gender,
        'user_type' => $user_type
    ];
    
    // Validation
    if (empty($full_name)) {
        $errors[] = "Full Name is required.";
    } elseif (strlen($full_name) > 100) {
        $errors[] = "Full Name must be at most 100 characters.";
    }

    if (empty($phone)) {
        $errors[] = "Phone is required.";
    } elseif (strlen($phone) > 30) {
        $errors[] = "Phone must be at most 30 characters.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!validate_email($email)) {
        $errors[] = "Invalid email format.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
    } elseif (!validate_password($password)) {
        $errors[] = "Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (!in_array($user_type, ['patient', 'professional'])) {
        $errors[] = "Please select a valid role.";
    }
    
    if (!$terms_accepted) {
        $errors[] = "You must accept the terms and conditions.";
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists.";
        }
        $stmt->close();
    }
    
    // If no errors, create the user
    if (empty($errors)) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Hash password
        $password_hash = hash_password($password);
        
        // Generate verification token
        $verification_token = generate_token(32);
        
        // Generate username from email (everything before @)
        $username = explode('@', $email)[0];
        // Make username unique by appending random numbers if needed
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row['count'] > 0) {
            $username = $username . rand(1000, 9999);
        }
        $check_stmt->close();
        
        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, gender, user_type, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $username, $email, $password_hash, $full_name, $phone, $gender, $user_type, $verification_token);
        
        if ($stmt->execute()) {
            $user_id = $db->getLastInsertId();
            
            // If user is a professional, create entry in professionals table (pending verification)
            if ($user_type === 'professional') {
                $stmt_prof = $conn->prepare("INSERT INTO professionals (user_id, full_name, specialization, license_number, degree, license_country, verification_status) VALUES (?, '', '', '', '', '', 'pending')");
                $stmt_prof->bind_param("i", $user_id);
                $stmt_prof->execute();
                $stmt_prof->close();
            }
            
            // Log activity
            log_activity($user_id, 'registration', "User registered as $user_type");
            
            set_flash_message('success', 'Registration successful! Please check your email to verify your account.');
            redirect('login.php');
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
        
        $stmt->close();
    }
}

$page_title = "Create an Account";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Join Safe Space - A supportive mental health community for everyone">
    <title><?php echo $page_title; ?> | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
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
            padding: 26px 20px 60px;
        }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0 22px;
            position: sticky;
            top: 0;
            background: rgba(248, 251, 255, 0.9);
            backdrop-filter: blur(12px);
            z-index: 10;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 20px;
            color: var(--text);
            text-decoration: none;
        }
        nav { display: flex; align-items: center; gap: 30px; }
        nav a { color: var(--muted); text-decoration: none; font-weight: 600; font-size: 15px; transition: color 0.2s; }
        nav a:hover { color: var(--text); }
        .auth-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 200px);
            padding: 40px 0;
        }
        .auth-card {\n            background: rgba(255, 255, 255, 0.95);\n            border: 1px solid rgba(12, 27, 51, 0.05);\n            border-radius: 24px;\n            padding: 40px;\n            box-shadow: 0 18px 40px rgba(12, 27, 51, 0.08);\n            width: 100%;\n            max-width: 480px;\n        }\n        .auth-header {\n            text-align: center;\n            margin-bottom: 32px;\n        }\n        .auth-header h1 {\n            font-size: 32px;\n            margin: 0 0 8px;\n            font-weight: 800;\n            color: var(--text);\n        }\n        .auth-header p {\n            color: var(--muted);\n            margin: 0;\n            font-size: 16px;\n        }\n        .btn {\n            border: 0;\n            cursor: pointer;\n            border-radius: 14px;\n            font-weight: 700;\n            font-size: 15px;\n            padding: 14px 18px;\n            text-decoration: none;\n            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;\n            width: 100%;\n            display: block;\n            text-align: center;\n        }\n        .btn-primary {\n            background: linear-gradient(135deg, var(--primary), #3ad0be);\n            color: white;\n            box-shadow: 0 12px 30px rgba(20, 184, 166, 0.35);\n        }\n        .btn-primary:hover { transform: translateY(-2px); }\n        .text-link {\n            color: var(--primary-dark);\n            text-decoration: none;\n            font-weight: 600;\n        }\n        .text-link:hover { text-decoration: underline; }\n        .role-selection { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 24px; }\n        .role-card { \n            background: white; \n            border: 2px solid rgba(20, 184, 166, 0.15); \n            border-radius: 16px; \n            padding: 20px; \n            cursor: pointer; \n            transition: all 0.2s ease;\n            text-align: center;\n        }\n        .role-card:hover { \n            border-color: var(--primary); \n            box-shadow: 0 8px 20px rgba(20, 184, 166, 0.15);\n            transform: translateY(-2px);\n        }\n        .role-card.active { \n            border-color: var(--primary); \n            background: rgba(20, 184, 166, 0.05);\n            box-shadow: 0 8px 20px rgba(20, 184, 166, 0.15);\n        }\n        .role-icon svg { width: 32px; height: 32px; fill: var(--primary); margin-bottom: 8px; }\n        .role-title { font-weight: 700; color: var(--text); margin-bottom: 4px; }\n        .role-description { color: var(--muted); font-size: 0.9rem; }\n        @media (max-width: 768px) {\n            header { position: static; flex-wrap: wrap; gap: 12px; }\n            nav { width: 100%; justify-content: center; }\n            .auth-card { padding: 28px; }\n        }        .page-header { text-align: center; margin-bottom: 24px; }
        .page-header h1 { font-weight: 800; margin: 0; }
        .page-header p { color: var(--text-secondary); margin-top: 8px; }
        .role-selection { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .role-card { background: white; border: 2px solid var(--light-gray); border-radius: 16px; padding: 16px; cursor: pointer; transition: all var(--transition-fast); }
        .role-card.active, .role-card:hover { border-color: var(--primary-color); box-shadow: var(--shadow-sm); }
        .role-title { font-weight: 700; }
        .role-description { color: var(--text-secondary); font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="page-shell">
        <header>
            <a class="brand" href="<?php echo is_logged_in() ? '../dashboard/index.php' : '../index.php'; ?>">
                <img src="../images/logo.png" alt="Safe Space" style="width: 40px; height: 40px; border-radius: 12px;">
                Safe Space
            </a>
            <nav>
                <a href="../index.php#features">Features</a>
                <a href="../index.php#about">About</a>
                <a href="../index.php#stories">Stories</a>
                <a href="login.php" class="text-link">Sign In</a>
            </nav>
        </header>

        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <h1>Create an Account</h1>
                    <p>Let's get started on a new journey</p>
                </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" action="" id="registrationForm" novalidate>
                <!-- Role Selection -->
                <div class="form-group">
                    <label class="form-label">I am joining as <span class="required">*</span></label>
                    <div class="role-selection">
                        <div class="role-card <?php echo ($form_data['user_type'] ?? '') === 'patient' ? 'active' : ''; ?>" onclick="selectRole('patient', event)">
                            <div class="role-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                            </div>
                            <div class="role-title">Community Member</div>
                            <div class="role-description">Seek support, share experiences, and help others</div>
                        </div>
                        
                        <div class="role-card <?php echo ($form_data['user_type'] ?? '') === 'professional' ? 'active' : ''; ?>" onclick="selectRole('professional', event)">
                            <div class="role-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                                </svg>
                            </div>
                            <div class="role-title">Professional</div>
                            <div class="role-description">Licensed mental health expert</div>
                        </div>
                    </div>
                    <input type="hidden" name="user_type" id="user_type" value="<?php echo htmlspecialchars($form_data['user_type'] ?? ''); ?>">
                </div>
                <!-- Full Name -->
                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="full_name" name="full_name" class="form-input" placeholder="Your full name" value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label for="phone" class="form-label">Phone <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="phone" name="phone" class="form-input" placeholder="e.g., +8801XXXXXXXXX" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" required>
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="form-label">Email <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                        </span>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="your.email@example.com"
                            value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                            required
                        >
                    </div>
                    <p class="helper-text">For account recovery only. We respect your privacy.</p>
                </div>

                <!-- Gender -->
                <div class="form-group">
                    <label class="form-label">Gender <span class="required">*</span></label>
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <label><input type="radio" name="gender" value="male" <?php echo (($form_data['gender'] ?? '')==='male') ? 'checked' : ''; ?>> Male</label>
                        <label><input type="radio" name="gender" value="female" <?php echo (($form_data['gender'] ?? '')==='female') ? 'checked' : ''; ?>> Female</label>
                        <label><input type="radio" name="gender" value="other" <?php echo (($form_data['gender'] ?? '')==='other') ? 'checked' : ''; ?>> Other</label>
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
                            placeholder="Create a strong password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>
                    <p class="helper-text">Min. 8 characters with uppercase, lowercase, number & special character</p>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                            </svg>
                        </span>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-input" 
                            placeholder="Re-enter your password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div class="checkbox-wrapper">
                    <input type="checkbox" id="terms_accepted" name="terms_accepted" required>
                    <label for="terms_accepted">
                        I agree to the <a href="#" class="text-link">Terms & Conditions</a> and <a href="#" class="text-link">Privacy Policy</a>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-block">Create Account</button>

                <!-- Divider -->
                <div class="divider">Already have an account?</div>

                <!-- Login Link -->
                <div class="text-center">
                    <a href="login.php" class="text-link">Sign in to Safe Space</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Role selection
        function selectRole(role, event) {
            // Remove active class from all cards
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Add active class to selected card
            event.currentTarget.classList.add('active');
            
            // Set hidden input value
            document.getElementById('user_type').value = role;
        }

        // Password toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
            field.setAttribute('type', type);
        }

        // Form validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const userType = document.getElementById('user_type').value;
            if (!userType) {
                e.preventDefault();
                alert('Please select your role to continue');
                return false;
            }

            if (!confirm('Create your Safe Space account now?')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
    </div>
</body>
</html>
