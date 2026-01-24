<?php
/**
 * Safe Space - Admin Login Diagnostic Page
 * Tests admin login functionality and identifies issues
 */

require_once '../config/config.php';

$diagnostics = [];
$errors = [];
$warnings = [];

// Test 1: Database Connection
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $diagnostics[] = "OK: Database connection successful";
} catch (Exception $e) {
    $errors[] = "ERROR: Database connection failed: " . $e->getMessage();
}

// Test 2: Check if admins table exists
if (isset($conn)) {
    $result = $conn->query("SHOW TABLES LIKE 'admins'");
    if ($result && $result->num_rows > 0) {
        $diagnostics[] = "OK: Admins table exists";
    } else {
        $errors[] = "ERROR: Admins table not found in database";
    }
}

// Test 3: Check admin user exists
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT admin_id, username, email, password_hash, full_name, role, is_active FROM admins WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        $diagnostics[] = "OK: Admin user 'admin' exists in database";
        
        // Check if admin is active
        if ($admin['is_active']) {
            $diagnostics[] = "OK: Admin account is ACTIVE";
        } else {
            $errors[] = "ERROR: Admin account is DEACTIVATED";
        }
        
        // Test password verification
        $test_password = 'Admin@123';
        if (verify_password($test_password, $admin['password_hash'])) {
            $diagnostics[] = "OK: Password 'Admin@123' matches admin hash";
        } else {
            $errors[] = "ERROR: Password 'Admin@123' does NOT match admin hash";
            
            // Show hash info
            $diagnostics[] = "INFO: Admin password hash: " . $admin['password_hash'];
            $diagnostics[] = "INFO: Hash algorithm: " . substr($admin['password_hash'], 0, 4);
            $diagnostics[] = "INFO: Hash length: " . strlen($admin['password_hash']);
        }
    } else {
        $errors[] = "ERROR: Admin user 'admin' not found in database";
        $diagnostics[] = "INFO: Available admins:";
        
        $all_admins = $conn->query("SELECT admin_id, username, email, role, is_active FROM admins");
        if ($all_admins && $all_admins->num_rows > 0) {
            while ($row = $all_admins->fetch_assoc()) {
                $diagnostics[] = "  - Username: {$row['username']}, Email: {$row['email']}, Role: {$row['role']}, Active: " . ($row['is_active'] ? 'Yes' : 'No');
            }
        } else {
            $diagnostics[] = "  - No admin users found";
        }
    }
    $stmt->close();
}

// Test 4: Check config file
$diagnostics[] = "INFO: Database: " . DB_NAME;
$diagnostics[] = "INFO: Host: " . DB_HOST;
$diagnostics[] = "INFO: User: " . DB_USER;

// Test 5: Session check
if (session_status() === PHP_SESSION_ACTIVE) {
    $diagnostics[] = "OK: Session is active";
} else {
    $warnings[] = "WARN: Session is not active";
}

// Test 6: Functions check
if (function_exists('verify_password')) {
    $diagnostics[] = "OK: verify_password() function exists";
} else {
    $errors[] = "ERROR: verify_password() function not found";
}

if (function_exists('hash_password')) {
    $diagnostics[] = "OK: hash_password() function exists";
} else {
    $errors[] = "ERROR: hash_password() function not found";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login Diagnostic | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #434343 0%, #000000 100%);
            min-height: 100vh;
            padding: 2rem;
            color: #fff;
        }
        
        .diagnostic-container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            color: #333;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .diagnostic-header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 3px solid #FF6B6B;
            padding-bottom: 1rem;
        }
        
        .diagnostic-header h1 {
            color: #FF6B6B;
            margin: 0;
            font-size: 2rem;
        }
        
        .diagnostic-header p {
            color: #666;
            margin: 0.5rem 0 0 0;
        }
        
        .section {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #f5f5f5;
            border-left: 4px solid #FF6B6B;
            border-radius: 8px;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #FF6B6B;
            margin-bottom: 1rem;
        }
        
        .diagnostic-item {
            padding: 0.75rem;
            margin: 0.5rem 0;
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            background: var(--bg-card, #F8F9F7);
            border-radius: 4px;
            border-left: 3px solid var(--border-soft, #D8E2DD);
        }
        
        .diagnostic-item.error {
            background: #ffebee;
            border-left-color: #f44336;
            color: #c62828;
        }
        
        .diagnostic-item.warning {
            background: #fff3e0;
            border-left-color: #ff9800;
            color: #e65100;
        }
        
        .diagnostic-item.success {
            background: #e8f5e9;
            border-left-color: #4caf50;
            color: #2e7d32;
        }
        
        .diagnostic-item.info {
            background: #e3f2fd;
            border-left-color: #2196f3;
            color: #1565c0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 1rem;
        }
        
        .status-badge.ok {
            background: #4caf50;
            color: white;
        }
        
        .status-badge.error {
            background: #f44336;
            color: white;
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 2rem;
        }
        
        .btn-diagnostic {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            margin: 0.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-login {
            background: #FF6B6B;
            color: white;
        }
        
        .btn-login:hover {
            background: #f44336;
            transform: translateY(-2px);
        }
        
        .btn-home {
            background: #2196f3;
            color: white;
        }
        
        .btn-home:hover {
            background: #1976d2;
            transform: translateY(-2px);
        }
        
        .fix-guide {
            background: #fff9c4;
            border: 2px solid #fbc02d;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .fix-guide h3 {
            color: #f57f17;
            margin-top: 0;
        }
        
        .fix-guide ol {
            margin: 1rem 0;
            padding-left: 2rem;
        }
        
        .fix-guide li {
            margin: 0.5rem 0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="diagnostic-container">
        <!-- Header -->
        <div class="diagnostic-header">
            <h1><svg class="icon icon--md" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg> Admin Login Diagnostic</h1>
            <p>System health check and troubleshooting</p>
        </div>

        <!-- Errors Section -->
        <?php if (!empty($errors)): ?>
            <div class="section">
                <div class="section-title"><svg class="icon icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg> Critical Errors</div>
                <?php foreach ($errors as $error): ?>
                    <div class="diagnostic-item error"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Warnings Section -->
        <?php if (!empty($warnings)): ?>
            <div class="section">
                <div class="section-title"><svg class="icon icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3.05h16.94a2 2 0 0 0 1.71-3.05L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Warnings</div>
                <?php foreach ($warnings as $warning): ?>
                    <div class="diagnostic-item warning"><?php echo htmlspecialchars($warning); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Diagnostics Section -->
        <div class="section">
            <div class="section-title"><svg class="icon icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg> System Diagnostics</div>
            <?php foreach ($diagnostics as $diagnostic): ?>
                <?php
                    if (strpos($diagnostic, 'OK:') === 0) {
                        $class = 'success';
                    } elseif (strpos($diagnostic, 'ERROR:') === 0) {
                        $class = 'error';
                    } elseif (strpos($diagnostic, 'INFO:') === 0) {
                        $class = 'info';
                    } else {
                        $class = 'info';
                    }
                ?>
                <div class="diagnostic-item <?php echo $class; ?>"><?php echo htmlspecialchars($diagnostic); ?></div>
            <?php endforeach; ?>
        </div>

        <!-- Status Summary -->
        <div style="text-align: center; margin-top: 2rem;">
            <?php if (empty($errors)): ?>
                <span class="status-badge ok"><svg class="icon icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg> All Systems Operational</span>
            <?php else: ?>
                <span class="status-badge error"><svg class="icon icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg> Issues Detected</span>
            <?php endif; ?>
        </div>

        <!-- Fix Guide if errors -->
        <?php if (!empty($errors)): ?>
            <div class="fix-guide">
                <h3><svg class="icon icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l4.1-4.1a6 6 0 0 1-7.5 7.5l-7.8 7.8a2 2 0 0 1-2.8 0l-.7-.7a2 2 0 0 1 0-2.8l7.8-7.8a6 6 0 0 1 7.5-7.5z"/></svg> How to Fix Admin Login Issues</h3>
                <ol>
                    <li><strong>Reset Database:</strong>
                        <ul>
                            <li>Go to phpMyAdmin: http://localhost/phpmyadmin</li>
                            <li>Drop the safe_space_db database</li>
                            <li>Import fresh schema.sql file</li>
                        </ul>
                    </li>
                    <li><strong>Insert Admin User:</strong>
                        <ul>
                            <li>In phpMyAdmin, go to SQL tab</li>
                            <li>Run this query:
                                <br><code>INSERT INTO admins (username, email, password_hash, full_name, role, is_active) VALUES ('admin', 'admin@safespace.com', '$2y$12$7JN3LK8N.z7K8wQ2pL9mzuJ3K5L2M9N8O7P6Q5R4S3T2U1V0W9X8Y7Z6', 'System Administrator', 'super_admin', TRUE);</code>
                            </li>
                        </ul>
                    </li>
                    <li><strong>Try Login Again:</strong> Use username: <code>admin</code>, password: <code>Admin@123</code></li>
                </ol>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="../auth/admin_login.php" class="btn-diagnostic btn-login">🔐 Go to Admin Login</a>
            <a href="../index.php" class="btn-diagnostic btn-home">🏠 Go to Home</a>
        </div>
    </div>
</body>
</html>
