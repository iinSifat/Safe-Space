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
$target_type = strtolower(trim((string)($_POST['target_type'] ?? '')));
$target_id = (int)($_POST['target_id'] ?? 0);
$reason = strtolower(trim((string)($_POST['reason'] ?? 'other')));
$details = trim((string)($_POST['details'] ?? ''));

$allowed_targets = ['post','comment','reply'];
$allowed_reasons = ['harassment','self_harm','hate','spam','misinformation','other'];

if ($community_id <= 0 || $target_id <= 0 || !in_array($target_type, $allowed_targets, true) || !in_array($reason, $allowed_reasons, true)) {
    redirect('community.php');
}

$community = ss_get_community($conn, $community_id);
if (!$community) {
    redirect('community.php');
}

if (!ss_is_community_member($conn, $community_id, $user_id)) {
    set_flash_message('error', 'Please join the community before reporting content.');
    redirect('community_view.php?community_id=' . $community_id);
}

if ($details !== '' && mb_strlen($details) > 1000) {
    $details = mb_substr($details, 0, 1000);
}

$stmt = $conn->prepare(
    "INSERT INTO community_reports (community_id, reporter_user_id, target_type, target_id, reason, details)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param('iisiss', $community_id, $user_id, $target_type, $target_id, $reason, $details);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    set_flash_message('success', 'Report submitted. Thank you for helping keep the community safe.');
} else {
    set_flash_message('error', 'Unable to submit report right now.');
}

redirect('community_view.php?community_id=' . $community_id);
// Updated by Shuvo - END
