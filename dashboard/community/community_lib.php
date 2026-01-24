<?php
// Updated by Shuvo - START
/**
 * Community module shared helpers
 */

require_once __DIR__ . '/../../config/config.php';

function ss_table_exists(mysqli $conn, string $table): bool {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table === '') { return false; }
    $res = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    if (!$res) { return false; }
    $row = $res->fetch_row();
    $res->free();
    return !empty($row);
}

function ss_column_exists(mysqli $conn, string $table, string $column): bool {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') { return false; }

    $stmt = @$conn->prepare(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) { return false; }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return !empty($row);
}

function ss_require_community_tables(mysqli $conn): void {
    $required = [
        'communities',
        'community_members',
        'community_posts',
        'community_comments',
        'community_comment_replies',
        'community_reactions',
        'community_requests',
        'community_reports',
        'community_weekly_prompts',
        'community_volunteer_needs',
        'community_volunteer_applications',
    ];
    foreach ($required as $t) {
        if (!ss_table_exists($conn, $t)) {
            set_flash_message('error', 'Community feature is not available right now (missing database tables).');
            redirect('index.php');
        }
    }
}

function ss_get_community(mysqli $conn, int $community_id): ?array {
    $stmt = $conn->prepare("SELECT * FROM communities WHERE community_id = ? AND status = 'approved' LIMIT 1");
    $stmt->bind_param('i', $community_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function ss_get_member_role(mysqli $conn, int $community_id, int $user_id): ?string {
    $stmt = $conn->prepare('SELECT role FROM community_members WHERE community_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $community_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['role'] ?? null;
}

function ss_is_community_member(mysqli $conn, int $community_id, int $user_id): bool {
    return ss_get_member_role($conn, $community_id, $user_id) !== null;
}

function ss_is_community_creator(mysqli $conn, int $community_id, int $user_id): bool {
    return ss_get_member_role($conn, $community_id, $user_id) === 'creator';
}

function ss_is_community_volunteer(mysqli $conn, int $community_id, int $user_id): bool {
    return ss_get_member_role($conn, $community_id, $user_id) === 'volunteer';
}

function ss_display_name(string $username, ?string $user_type, int $user_id, bool $is_anonymous): string {
    if ($is_anonymous) {
        return get_anonymous_display_name($user_id);
    }
    return $username !== '' ? $username : 'User';
}

function ss_allowed_reactions(): array {
    return ['like', 'celebrate', 'support', 'love', 'insightful', 'curious'];
}

function ss_reaction_assets(): array {
    return [
        'like' => '../images/reactions/like.png',
        'celebrate' => '../images/reactions/Linkedin-Celebrate-Icon-ClappingHands500.png',
        'support' => '../images/reactions/Linkedin-Support-Icon-HeartinHand500.png',
        'love' => '../images/reactions/Linkedin-Love-Icon-Heart500.png',
        'insightful' => '../images/reactions/Linkedin-Insightful-Icon-Lamp500.png',
        'curious' => '../images/reactions/Linkedin-Curious-Icon-PurpleSmiley500.png',
    ];
}

function ss_join_requests_enabled(mysqli $conn): bool {
    return ss_table_exists($conn, 'community_join_requests');
}

function ss_join_request_answers_supported(mysqli $conn): bool {
    if (!ss_join_requests_enabled($conn)) {
        return false;
    }
    return ss_column_exists($conn, 'community_join_requests', 'answers_json');
}

function ss_get_join_request(mysqli $conn, int $community_id, int $user_id): ?array {
    if (!ss_join_requests_enabled($conn)) {
        return null;
    }

    $stmt = $conn->prepare("SELECT request_id, status, join_reason, answers_json, created_at, reviewed_at FROM community_join_requests WHERE community_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1");
    if (!$stmt) {
        // Backward compatibility (older DB without answers_json)
        $stmt = $conn->prepare("SELECT request_id, status, join_reason, created_at, reviewed_at FROM community_join_requests WHERE community_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1");
    }
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $community_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function ss_get_join_request_status(mysqli $conn, int $community_id, int $user_id): ?string {
    $row = ss_get_join_request($conn, $community_id, $user_id);
    return $row['status'] ?? null;
}

function ss_community_post_views_supported(mysqli $conn): bool {
    return ss_column_exists($conn, 'community_posts', 'view_count');
}
// Updated by Shuvo - END
