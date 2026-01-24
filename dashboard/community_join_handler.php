<?php
// Updated by Shuvo - START
require_once '../config/config.php';
require_login();
check_session_timeout();

$db = Database::getInstance();
$conn = $db->getConnection();

require_once __DIR__ . '/community/community_lib.php';
ss_require_community_tables($conn);

$user_id = (int)get_user_id();
$community_id = (int)($_POST['community_id'] ?? 0);
$action = (string)($_POST['action'] ?? 'join');

if ($community_id <= 0) {
    redirect('community.php');
}

$community = ss_get_community($conn, $community_id);
if (!$community) {
    set_flash_message('error', 'Community not found.');
    redirect('community.php');
}

$join_requests_enabled = ss_join_requests_enabled($conn);
$join_answers_supported = $join_requests_enabled ? ss_join_request_answers_supported($conn) : false;

if ($action === 'leave') {
    $stmt = $conn->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ? AND role <> 'creator'");
    $stmt->bind_param('ii', $community_id, $user_id);
    $stmt->execute();
    $stmt->close();
    set_flash_message('success', 'You left the community.');
    redirect('community_about.php?community_id=' . $community_id . '#membership');
}

if ($action === 'cancel') {
    if ($join_requests_enabled) {
        $stmt = $conn->prepare("DELETE FROM community_join_requests WHERE community_id = ? AND user_id = ? AND status = 'pending'");
        $stmt->bind_param('ii', $community_id, $user_id);
        $stmt->execute();
        $stmt->close();
        set_flash_message('success', 'Join request cancelled.');
    }
    redirect('community_about.php?community_id=' . $community_id . '#membership');
}

// Join / Request to Join
$join_reason = trim((string)($_POST['join_reason'] ?? ''));
if ($join_reason !== '' && mb_strlen($join_reason) > 160) {
    $join_reason = mb_substr($join_reason, 0, 160);
}

$support_goal = trim((string)($_POST['support_goal'] ?? ''));
$boundaries = trim((string)($_POST['boundaries'] ?? ''));
$belong_reason = trim((string)($_POST['belong_reason'] ?? ''));
$contribution = trim((string)($_POST['contribution'] ?? ''));

if (mb_strlen($support_goal) > 1000) { $support_goal = mb_substr($support_goal, 0, 1000); }
if (mb_strlen($boundaries) > 1000) { $boundaries = mb_substr($boundaries, 0, 1000); }
if (mb_strlen($belong_reason) > 1000) { $belong_reason = mb_substr($belong_reason, 0, 1000); }
if (mb_strlen($contribution) > 1000) { $contribution = mb_substr($contribution, 0, 1000); }

$answers_json = null;
$answers = [
    'support_goal' => $support_goal,
    'boundaries' => $boundaries,
    'belong_reason' => $belong_reason,
    'contribution' => $contribution,
];
// Only store if at least one is provided
if ($support_goal !== '' || $boundaries !== '' || $belong_reason !== '' || $contribution !== '') {
    $answers_json = json_encode($answers, JSON_UNESCAPED_UNICODE);
}

if ($join_requests_enabled) {
    if (!$join_answers_supported) {
        set_flash_message('error', "Join requests now require an update. Please run: ALTER TABLE community_join_requests ADD COLUMN answers_json TEXT NULL AFTER join_reason;");
        redirect('community_about.php?community_id=' . $community_id . '#membership');
    }

    // If already a member, no need to request again.
    if (ss_is_community_member($conn, $community_id, $user_id)) {
        set_flash_message('success', 'You are already a member of this community.');
        redirect('community_about.php?community_id=' . $community_id . '#membership');
    }

    // Require some answers for review
    if ($support_goal === '' || $boundaries === '' || $belong_reason === '') {
        set_flash_message('error', 'Please answer the required questions (including the belonging question) before sending a join request.');
        redirect('community_about.php?community_id=' . $community_id . '#membership');
    }

    // Upsert request as pending.
    $stmt = $conn->prepare("INSERT INTO community_join_requests (community_id, user_id, join_reason, answers_json, status) VALUES (?, ?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE join_reason = VALUES(join_reason), answers_json = VALUES(answers_json), status = IF(status = 'approved', status, 'pending'), updated_at = CURRENT_TIMESTAMP");
    $stmt->bind_param('iiss', $community_id, $user_id, $join_reason, $answers_json);
    $stmt->execute();
    $stmt->close();

    set_flash_message('success', 'Join request sent. Please wait for approval.');
    redirect('community_about.php?community_id=' . $community_id . '#membership');
}

// Fallback: immediate join when join-requests feature is not installed.
$stmt = $conn->prepare("INSERT INTO community_members (community_id, user_id, role, join_reason) VALUES (?, ?, 'member', ?) ON DUPLICATE KEY UPDATE join_reason = VALUES(join_reason)");
$stmt->bind_param('iis', $community_id, $user_id, $join_reason);
$stmt->execute();
$stmt->close();

set_flash_message('success', 'Joined community.');
redirect('community_about.php?community_id=' . $community_id . '#membership');
// Updated by Shuvo - END
