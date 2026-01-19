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
$post_id = isset($input['post_id']) ? (int)$input['post_id'] : 0;
$reaction_type = isset($input['reaction_type']) ? strtolower(trim($input['reaction_type'])) : '';

$allowed_reactions = ['like', 'celebrate', 'support', 'love', 'insightful', 'curious'];

if ($post_id <= 0 || !in_array($reaction_type, $allowed_reactions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

// Ensure the post exists and is published
$post_check_stmt = $conn->prepare("SELECT 1 FROM forum_posts WHERE post_id = ? AND status = 'published' LIMIT 1");
$post_check_stmt->bind_param('i', $post_id);
$post_check_stmt->execute();
$post_result = $post_check_stmt->get_result();
$post_exists = $post_result && $post_result->fetch_assoc();
$post_check_stmt->close();

if (!$post_exists) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Post not found']);
    exit;
}

// Insert or update reaction
$reaction_stmt = $conn->prepare("INSERT INTO forum_post_reactions (post_id, user_id, reaction_type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type), updated_at = CURRENT_TIMESTAMP");
$reaction_stmt->bind_param('iis', $post_id, $user_id, $reaction_type);
$reaction_stmt->execute();
$reaction_stmt->close();

// Fetch updated counts
$count_stmt = $conn->prepare("SELECT reaction_type, COUNT(*) AS total FROM forum_post_reactions WHERE post_id = ? GROUP BY reaction_type");
$count_stmt->bind_param('i', $post_id);
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
