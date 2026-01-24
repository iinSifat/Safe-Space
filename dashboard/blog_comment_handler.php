<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$blog_id = isset($input['blog_id']) ? (int)$input['blog_id'] : 0;
$content = isset($input['content']) ? trim((string)$input['content']) : '';

if ($blog_id <= 0 || $content === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Basic length guard (avoids accidental huge payloads)
if (function_exists('mb_strlen')) {
    if (mb_strlen($content) > 2000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Comment is too long']);
        exit;
    }
} else {
    if (strlen($content) > 2000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Comment is too long']);
        exit;
    }
}

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

$is_professional_user = function_exists('is_professional') && is_professional();

// Ensure the blog exists and is published
$check_stmt = $conn->prepare("SELECT 1 FROM blog_posts WHERE blog_id = ? AND status = 'published' LIMIT 1");
$check_stmt->bind_param('i', $blog_id);
$check_stmt->execute();
$exists = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if (!$exists) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Blog post not found']);
    exit;
}

// Insert comment (professional comments may be held for review)
$status = 'published';
$sanitized = sanitize_input($content);

if ($is_professional_user) {
    $sanitized = ensure_professional_disclaimer($sanitized);
    if (professional_content_has_prohibited_claims($sanitized)) {
        $status = 'flagged';
    }
    if (content_has_crisis_keywords($sanitized)) {
        add_notification((int)$user_id, 'warning', 'Crisis Support', 'If you or someone else is in immediate danger, call your local emergency number. If you are thinking about self-harm, please contact a local crisis hotline right now.');
    }
}

$comment_sql = "INSERT INTO blog_comments (blog_id, user_id, content, status) VALUES (?, ?, ?, ?)";
$comment_stmt = $conn->prepare($comment_sql);
if (!$comment_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$comment_stmt->bind_param('iiss', $blog_id, $user_id, $sanitized, $status);
$comment_stmt->execute();
$comment_stmt->close();

if ($status === 'published') {
    // Increment comment count (keep existing behavior consistent with blog_view.php)
    $count_stmt = $conn->prepare('UPDATE blog_posts SET comment_count = comment_count + 1 WHERE blog_id = ?');
    $count_stmt->bind_param('i', $blog_id);
    $count_stmt->execute();
    $count_stmt->close();
}

// Fetch updated comment count
$get_stmt = $conn->prepare('SELECT comment_count FROM blog_posts WHERE blog_id = ? LIMIT 1');
$get_stmt->bind_param('i', $blog_id);
$get_stmt->execute();
$row = $get_stmt->get_result()->fetch_assoc();
$get_stmt->close();

echo json_encode([
    'success' => ($status === 'published'),
    'message' => ($status === 'published') ? null : 'Your professional comment was held from public view due to restricted language. Please revise and try again.',
    'comment_count' => (int)($row['comment_count'] ?? 0),
]);
