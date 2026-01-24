<?php
/**
 * Safe Space - Configuration File
 * Database connection and global settings
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Small helper for environment overrides
if (!function_exists('ss_env')) {
    function ss_env($key, $default = null) {
        $val = getenv($key);
        return ($val === false) ? $default : $val;
    }
}

// Optional local config override (DO NOT COMMIT secrets)
// Create: config/config.local.php returning an array with DB_HOST/DB_USER/DB_PASS/DB_NAME
$__ss_local_config_file = __DIR__ . '/config.local.php';
$__ss_local_config = [];
if (is_file($__ss_local_config_file)) {
    $maybe = require $__ss_local_config_file;
    if (is_array($maybe)) {
        $__ss_local_config = $maybe;
    }
}

// App Debug (default on for local dev)
if (!defined('APP_DEBUG')) {
    $debugRaw = ss_env('SAFESPACE_DEBUG', '1');
    define('APP_DEBUG', in_array(strtolower((string)$debugRaw), ['1', 'true', 'yes', 'on'], true));
}

// Database Configuration
// Priority: config/config.local.php -> env vars -> defaults
if (!defined('DB_HOST')) {
    define('DB_HOST', $__ss_local_config['DB_HOST'] ?? ss_env('SAFESPACE_DB_HOST', 'localhost'));
}
if (!defined('DB_USER')) {
    define('DB_USER', $__ss_local_config['DB_USER'] ?? ss_env('SAFESPACE_DB_USER', 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', $__ss_local_config['DB_PASS'] ?? ss_env('SAFESPACE_DB_PASS', '#Sifat10919'));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', $__ss_local_config['DB_NAME'] ?? ss_env('SAFESPACE_DB_NAME', 'safe_space_db'));
}

// Site Configuration
define('SITE_NAME', 'Safe Space');
define('SITE_URL', 'http://localhost/SafeSpaceupdatedbyshuvo/');
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
error_reporting(APP_DEBUG ? E_ALL : 0);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

// Database Connection Class
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            // Prevent raw mysqli warnings leaking to the page; we'll handle errors ourselves.
            mysqli_report(MYSQLI_REPORT_OFF);
            $this->conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->conn->connect_errno) {
                // Keep details only for debug.
                $detail = $this->conn->connect_error;
                throw new Exception(APP_DEBUG ? ("Connection failed: " . $detail) : "Connection failed.");
            }
            
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            if (APP_DEBUG) {
                die("Database Connection Error: " . $e->getMessage());
            }
            die("Database Connection Error. Please check database credentials.");
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

function get_anonymous_display_name($user_id) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        return 'Anonymous';
    }

    $salt = defined('SITE_URL') ? SITE_URL : 'SafeSpace';
    $hash = hash('sha256', $salt . '|anon|' . $user_id);
    $tag = strtoupper(substr($hash, 0, 6));
    return 'Anonymous-' . $tag;
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

// Professional role helpers
function is_professional() {
    return get_user_type() === 'professional';
}

function require_not_professional($redirectUrl = '../dashboard/index.php', $flashMessage = 'This feature is not available for professional accounts.') {
    if (is_logged_in() && is_professional()) {
        set_flash_message('info', $flashMessage);
        redirect($redirectUrl);
    }
}

function get_professional_profile($user_id) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) { return null; }

    $db = Database::getInstance();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT full_name, specialization, license_number, license_state, license_country, degree, years_of_experience, credentials, availability_schedule, is_accepting_patients, verification_status FROM professionals WHERE user_id = ? LIMIT 1");
    if (!$stmt) { return null; }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function professional_authority_label($specialization = '', $verification_status = '') {
    $specialization = trim((string)$specialization);
    $verification_status = trim((string)$verification_status);

    // Product rule: professional interactions carry authority; prefer 'Verified' when available.
    $base = ($verification_status === 'verified') ? 'Verified Mental Health Professional' : 'Mental Health Professional';
    if ($specialization !== '') {
        return $base . ' • ' . $specialization;
    }
    return $base;
}

function professional_expert_content_label($specialization = '', $verification_status = '') {
    // Blog-specific labeling; keeps forum labels separate.
    return 'Expert Content • ' . professional_authority_label($specialization, $verification_status);
}

function ensure_professional_disclaimer($content) {
    $content = (string)$content;
    $prefix = "General Guidance (Not Medical Advice):";

    // Idempotent: do not double-prefix.
    if (stripos($content, $prefix) !== false) {
        return $content;
    }

    $disclaimer = $prefix . " This is informational and not a medical diagnosis.\n" .
        "If you are in immediate danger or feel you might harm yourself, call your local emergency number or a crisis hotline.\n\n";

    return $disclaimer . $content;
}

function professional_content_has_prohibited_claims($content) {
    $content = (string)$content;

    // Basic enforcement for diagnosis/prescription claims (heuristic, not perfect).
    $patterns = [
        '/\bdiagnos(e|is|ing|ed)\b/i',
        '/\bprescrib(e|es|ing|ed)\b/i',
        '/\bI\s+prescribe\b/i',
        '/\bRx\b/i',
        '/\bmg\b/i',
        '/\b(increase|decrease)\s+your\s+dose\b/i'
    ];

    foreach ($patterns as $p) {
        if (preg_match($p, $content)) {
            return true;
        }
    }
    return false;
}

function content_has_crisis_keywords($content) {
    $content = mb_strtolower((string)$content);
    $needles = [
        'suicide',
        'kill myself',
        'end my life',
        'self harm',
        'self-harm',
        'hurt myself',
        'overdose'
    ];
    foreach ($needles as $n) {
        if (mb_strpos($content, $n) !== false) {
            return true;
        }
    }
    return false;
}

function professional_client_alias($client_user_id) {
    $client_user_id = (int)$client_user_id;
    if ($client_user_id <= 0) {
        return 'Client';
    }
    return 'Client-' . substr(md5('ss-client:' . $client_user_id), 0, 6);
}

function ensure_professional_sessions_table() {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Minimal scheduling/session table to support professional workspace flows.
    $sql = "CREATE TABLE IF NOT EXISTS professional_sessions (
        session_id INT AUTO_INCREMENT PRIMARY KEY,
        professional_user_id INT NOT NULL,
        client_user_id INT NOT NULL,
        client_alias VARCHAR(32) NOT NULL,
        primary_concern VARCHAR(120) NULL,
        risk_level ENUM('low','medium','high','critical') DEFAULT 'low',
        preferred_session_type ENUM('call','video') DEFAULT 'video',
        preferred_duration_minutes INT DEFAULT 50,
        is_emergency BOOLEAN DEFAULT FALSE,
        scheduled_at DATETIME NULL,
        status ENUM('requested','accepted','declined','completed','cancelled','no_show') DEFAULT 'requested',
        private_notes TEXT NULL,
        risk_assessment ENUM('low','medium','high','critical') DEFAULT 'low',
        follow_up_required BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_professional_status (professional_user_id, status),
        INDEX idx_professional_schedule (professional_user_id, scheduled_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Best-effort; if the DB user lacks privileges, features will degrade gracefully.
    @$conn->query($sql);
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
