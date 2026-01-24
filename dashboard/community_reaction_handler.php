<?php
// Updated by Shuvo - START
require_once '../config/config.php';
require_login();
check_session_timeout();

header('Content-Type: application/json');

$db = Database::getInstance();
$conn = $db->getConnection();

require_once __DIR__ . '/community/community_lib.php';
ss_require_community_tables($conn);

$user_id = (int)get_user_id();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$community_id = (int)($input['community_id'] ?? 0);
$target_type = strtolower(trim((string)($input['target_type'] ?? '')));
$target_id = (int)($input['target_id'] ?? 0);
$reaction_type = strtolower(trim((string)($input['reaction_type'] ?? '')));

$allowed_targets = ['post', 'comment'];
$allowed_reactions = ss_allowed_reactions();

if ($community_id <= 0 || $target_id <= 0 || !in_array($target_type, $allowed_targets, true) || !in_array($reaction_type, $allowed_reactions, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

$community = ss_get_community($conn, $community_id);
if (!$community) {
    echo json_encode(['ok' => false, 'error' => 'Community not found']);
    exit;
}

if (!ss_is_community_member($conn, $community_id, $user_id)) {
    echo json_encode(['ok' => false, 'error' => 'Join required']);
    exit;
}

// Validate target belongs to community
if ($target_type === 'post') {
    $t = $conn->prepare("SELECT 1 FROM community_posts WHERE post_id = ? AND community_id = ? AND status = 'published' LIMIT 1");
    $t->bind_param('ii', $target_id, $community_id);
} else {
    $t = $conn->prepare("SELECT 1 FROM community_comments WHERE comment_id = ? AND community_id = ? AND status = 'published' LIMIT 1");
    $t->bind_param('ii', $target_id, $community_id);
}
$t->execute();
$ok = (bool)($t->get_result()->fetch_assoc() ?? false);
$t->close();

if (!$ok) {
    echo json_encode(['ok' => false, 'error' => 'Target not found']);
    exit;
}

// Upsert reaction
$ins = $conn->prepare(
    "INSERT INTO community_reactions (community_id, user_id, target_type, target_id, reaction_type)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type), updated_at = CURRENT_TIMESTAMP"
);
$ins->bind_param('iisis', $community_id, $user_id, $target_type, $target_id, $reaction_type);
$ins->execute();
$ins->close();

// Fetch updated counts
$count_stmt = $conn->prepare(
    "SELECT reaction_type, COUNT(*) AS total
     FROM community_reactions
     WHERE community_id = ? AND target_type = ? AND target_id = ?
     GROUP BY reaction_type"
);
$count_stmt->bind_param('isi', $community_id, $target_type, $target_id);
$count_stmt->execute();
$res = $count_stmt->get_result();
$counts = array_fill_keys($allowed_reactions, 0);
while ($row = $res->fetch_assoc()) {
    $type = $row['reaction_type'];
    if (isset($counts[$type])) {
        $counts[$type] = (int)$row['total'];
    }
}
$count_stmt->close();

$user_stmt = $conn->prepare(
    "SELECT reaction_type FROM community_reactions WHERE community_id = ? AND user_id = ? AND target_type = ? AND target_id = ? LIMIT 1"
);
$user_stmt->bind_param('iisi', $community_id, $user_id, $target_type, $target_id);
$user_stmt->execute();
$ur = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

echo json_encode([
    'ok' => true,
    'reaction' => $ur['reaction_type'] ?? null,
    'counts' => $counts,
    'total_reactions' => array_sum($counts),
]);
// Updated by Shuvo - END
