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
$reaction_type = isset($input['reaction_type']) ? strtolower(trim($input['reaction_type'])) : '';

$allowed_reactions = ['like', 'celebrate', 'support', 'love', 'insightful', 'curious'];

if ($blog_id <= 0 || !in_array($reaction_type, $allowed_reactions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

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

// Insert or update reaction
$reaction_sql = "
    INSERT INTO blog_post_reactions (blog_id, user_id, reaction_type)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type), updated_at = CURRENT_TIMESTAMP
";
$reaction_stmt = $conn->prepare($reaction_sql);
if (!$reaction_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$reaction_stmt->bind_param('iis', $blog_id, $user_id, $reaction_type);
$reaction_stmt->execute();
$reaction_stmt->close();

// Fetch updated counts
$count_stmt = $conn->prepare('SELECT reaction_type, COUNT(*) AS total FROM blog_post_reactions WHERE blog_id = ? GROUP BY reaction_type');
$count_stmt->bind_param('i', $blog_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();

$counts = array_fill_keys($allowed_reactions, 0);
while ($row = $count_result->fetch_assoc()) {
    $type = $row['reaction_type'];
    if (isset($counts[$type])) {
        $counts[$type] = (int)$row['total'];
    }
}
$count_stmt->close();

$total_reactions = array_sum($counts);

echo json_encode([
    'success' => true,
    'reaction' => $reaction_type,
    'counts' => $counts,
    'total_reactions' => $total_reactions,
]);
