<?php
/**
 * Safe Space - Logout
 * Destroys session and redirects to home page
 */

require_once '../config/config.php';

// Log activity before logout
if (is_logged_in()) {
    log_activity(get_user_id(), 'logout', 'User logged out');
} elseif (is_admin_logged_in()) {
    log_activity(null, 'admin_logout', 'Admin logged out');
}

// Destroy session completely
$_SESSION = [];

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Clear remember-me cookies if exist
if (isset($_COOKIE['user_id'])) {
    setcookie('user_id', '', time() - 3600, '/');
}

if (isset($_COOKIE['user_token'])) {
    setcookie('user_token', '', time() - 3600, '/');
}

// Redirect to home page
header("Location: ../index.php");
exit();
?>
