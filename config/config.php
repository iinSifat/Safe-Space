<?php
/**
 * Safe Space - Configuration File
 * Database connection and global settings
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '#Sifat10919');
define('DB_NAME', 'safe_space_db');

// Site Configuration
define('SITE_NAME', 'Safe Space');
define('SITE_URL', 'http://localhost/DBMS/');
define('SITE_EMAIL', 'support@safespace.com');

// Security Configuration
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_LIFETIME', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);

// File Upload Configuration
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes

// Timezone
date_default_timezone_set('UTC');

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection Class
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Database Connection Error: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }
    
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
    
    public function getLastInsertId() {
        return $this->conn->insert_id;
    }
}

// Helper Functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_password($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
    return preg_match($pattern, $password);
}

function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function is_admin_logged_in() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']);
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_user_type() {
    return $_SESSION['user_type'] ?? null;
}

function get_admin_id() {
    return $_SESSION['admin_id'] ?? null;
}

function set_flash_message($type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'];
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_type']);
        unset($_SESSION['flash_message']);
        return ['type' => $type, 'message' => $message];
    }
    return null;
}

function log_activity($user_id, $activity_type, $description, $ip_address = null, $user_agent = null) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $ip_address = $ip_address ?? $_SERVER['REMOTE_ADDR'];
    $user_agent = $user_agent ?? $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, activity_description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $activity_type, $description, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}

// Notifications
function add_notification($user_id, $type, $title, $message) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $type, $title, $message);
    $stmt->execute();
    $stmt->close();
}

function get_notifications($user_id, $limit = 20) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT notification_id, type, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) { $notifications[] = $row; }
    $stmt->close();
    return $notifications;
}

function mark_notification_read($notification_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE notification_id = ?");
    $stmt->bind_param("i", $notification_id);
    $stmt->execute();
    $stmt->close();
}

// Access control helpers
function require_login() {
    if (!is_logged_in()) { redirect('../auth/login.php'); }
}

function require_admin() {
    if (!is_admin_logged_in()) { redirect('../auth/admin_login.php'); }
}

function require_role($role) {
    if (!is_logged_in()) { redirect('../auth/login.php'); }
    if (get_user_type() !== $role) { redirect('../dashboard/index.php'); }
}

function user_has_volunteer_permission($user_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT approval_status FROM volunteers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $approved = false;
    if ($row = $result->fetch_assoc()) { $approved = ($row['approval_status'] === 'approved'); }
    $stmt->close();
    return $approved;
}

// Logo navigation target
function logo_target_url() {
    if (is_logged_in()) { return SITE_URL . 'dashboard/index.php'; }
    return SITE_URL . 'index.php';
}

// Session Security
function regenerate_session() {
    session_regenerate_id(true);
}

// Check session timeout
function check_session_timeout() {
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
            session_unset();
            session_destroy();
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// Initialize database connection
$db = Database::getInstance();
?>
