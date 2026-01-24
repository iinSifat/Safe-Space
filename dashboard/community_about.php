<?php
require_once '../config/config.php';
require_login();
check_session_timeout();

$db = Database::getInstance();
$conn = $db->getConnection();

require_once __DIR__ . '/community/community_lib.php';
ss_require_community_tables($conn);

$user_id = (int)get_user_id();
$community_id = (int)($_GET['community_id'] ?? 0);
if ($community_id <= 0) {
    redirect('community.php');
}

$community = ss_get_community($conn, $community_id);
if (!$community) {
    set_flash_message('error', 'Community not found.');
    redirect('community.php');
}

$is_member = ss_is_community_member($conn, $community_id, $user_id);
$my_role = ss_get_member_role($conn, $community_id, $user_id);
$is_creator = ($my_role === 'creator');
$is_moderator = in_array($my_role, ['moderator', 'volunteer'], true);
$can_manage = ($is_creator || $is_moderator);

$member_count = 0;
$post_count = 0;
$cstmt = $conn->prepare("SELECT COUNT(*) AS c FROM community_members WHERE community_id = ?");
if ($cstmt) {
    $cstmt->bind_param('i', $community_id);
    $cstmt->execute();
    $member_count = (int)($cstmt->get_result()->fetch_assoc()['c'] ?? 0);
    $cstmt->close();
}
$pcount = $conn->prepare("SELECT COUNT(*) AS c FROM community_posts WHERE community_id = ?");
if ($pcount) {
    $pcount->bind_param('i', $community_id);
    $pcount->execute();
    $post_count = (int)($pcount->get_result()->fetch_assoc()['c'] ?? 0);
    $pcount->close();
}

$join_requests_enabled = ss_join_requests_enabled($conn);
$join_answers_supported = $join_requests_enabled ? ss_join_request_answers_supported($conn) : false;
$my_join_request = (!$is_member && $join_requests_enabled) ? ss_get_join_request($conn, $community_id, $user_id) : null;
$my_join_status = $my_join_request['status'] ?? null;

// Allow Manage Community only after visiting a community page in this session.
if ($can_manage) {
    if (!isset($_SESSION['community_creator_context']) || !is_array($_SESSION['community_creator_context'])) {
        $_SESSION['community_creator_context'] = [];
    }
    $_SESSION['community_creator_context'][(string)$community_id] = time();
}

// Latest weekly prompt
$weekly_prompt = null;
$pstmt = $conn->prepare("SELECT prompt_text, week_start_date, created_at FROM community_weekly_prompts WHERE community_id = ? AND status = 'active' ORDER BY week_start_date DESC, created_at DESC LIMIT 1");
if ($pstmt) {
    $pstmt->bind_param('i', $community_id);
    $pstmt->execute();
    $weekly_prompt = $pstmt->get_result()->fetch_assoc() ?: null;
    $pstmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set weekly prompt (creator/moderator)
    if (isset($_POST['set_weekly_prompt']) && $can_manage) {
        if (!$is_member) {
            set_flash_message('error', 'Please join this community to participate.');
            redirect('community_about.php?community_id=' . $community_id);
        }

        $prompt = trim((string)($_POST['prompt_text'] ?? ''));
        if ($prompt !== '') {
            if (mb_strlen($prompt) > 500) { $prompt = mb_substr($prompt, 0, 500); }
            $week_start = date('Y-m-d', strtotime('monday this week'));

            $arch = $conn->prepare("UPDATE community_weekly_prompts SET status = 'archived' WHERE community_id = ? AND status = 'active'");
            if ($arch) {
                $arch->bind_param('i', $community_id);
                $arch->execute();
                $arch->close();
            }

            $stmt = $conn->prepare("INSERT INTO community_weekly_prompts (community_id, prompt_text, week_start_date, created_by_user_id, status) VALUES (?, ?, ?, ?, 'active')");
            if ($stmt) {
                $stmt->bind_param('issi', $community_id, $prompt, $week_start, $user_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        redirect('community_about.php?community_id=' . $community_id);
    }
}

$flash = get_flash_message();
$activeTab = 'about';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($community['name']); ?> | Community</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        <?php include __DIR__ . '/includes/community_group_header_styles.php'; ?>

        .card { background: var(--bg-card, #F8F9F7); border: 1px solid var(--border-soft, #D8E2DD); border-radius: 18px; padding: 16px; box-shadow: var(--shadow-sm); }
        .badges { display:flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .badge { display:inline-block; padding: 5px 10px; border-radius: 999px; background: rgba(127, 175, 163, 0.18); font-weight: 900; font-size: 0.78rem; color: var(--text-primary); }
        .purpose { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 900px) { .purpose { grid-template-columns: 1fr; } }
        .meta { color: var(--text-secondary); font-size: 0.92rem; }
        .divider { margin: 14px 0; border-top: 1px solid var(--border-soft, #D8E2DD); }
        .row { display:flex; justify-content: space-between; align-items: flex-start; gap: 10px; flex-wrap: wrap; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="top-bar">
            <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);"><?php echo htmlspecialchars($community['name']); ?></h2>
        </div>

        <div class="content-area">
            <div class="wrap">
                <?php include __DIR__ . '/includes/community_group_header.php'; ?>

                <?php if ($flash): ?>
                    <div class="alert <?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
                <?php endif; ?>

                <div class="card" style="margin-bottom: 14px;">
                    <div class="row">
                        <div style="font-weight: 950;">Community Purpose</div>
                    </div>

                    <div class="divider"></div>

                    <div class="purpose">
                        <div>
                            <div style="font-weight: 900;">Who this community is for</div>
                            <div class="meta" style="margin-top: 6px;"><?php echo nl2br(htmlspecialchars((string)$community['purpose_who_for'])); ?></div>
                        </div>
                        <div>
                            <div style="font-weight: 900;">What kind of support is expected</div>
                            <div class="meta" style="margin-top: 6px;"><?php echo nl2br(htmlspecialchars((string)$community['purpose_support_expected'])); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($community['safety_considerations'])): ?>
                        <div class="divider"></div>
                        <div>
                            <div style="font-weight: 900;">Sensitivity / safety considerations</div>
                            <div class="meta" style="margin-top: 6px; white-space: pre-wrap;"><?php echo htmlspecialchars((string)$community['safety_considerations']); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($weekly_prompt): ?>
                        <div class="divider"></div>
                        <div>
                            <div style="font-weight: 900;">Weekly Discussion Prompt</div>
                            <div class="meta" style="margin-top: 6px; white-space: pre-wrap;"><?php echo htmlspecialchars((string)$weekly_prompt['prompt_text']); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_member && $can_manage): ?>
                        <div class="divider"></div>
                        <form method="POST">
                            <div class="form-group" style="margin:0;">
                                <label style="font-weight:900;">Set Weekly Prompt</label>
                                <textarea class="form-input" name="prompt_text" rows="2" placeholder="Write a short, supportive weekly prompt..."></textarea>
                            </div>
                            <button class="btn btn-secondary" type="submit" name="set_weekly_prompt" style="margin-top: 8px;">Publish Prompt</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($is_member && $can_manage && $join_requests_enabled): ?>
                        <div class="divider"></div>
                        <div style="font-weight: 950;">Join Requests</div>
                        <div class="meta" style="margin-top: 6px;">Approve or decline join requests from <strong>Manage Community</strong>.</div>
                        <div style="margin-top: 10px;">
                            <a class="btn btn-secondary" href="community_creator_dashboard.php?community_id=<?php echo (int)$community_id; ?>">Open Manage Community</a>
                        </div>
                    <?php endif; ?>

                    <div class="divider"></div>
                    <div class="row" id="membership">
                        <div style="font-weight: 950;">Join / Membership</div>
                        <?php if (!$is_member): ?>
                            <?php if ($join_requests_enabled && $my_join_status === 'pending'): ?>
                                <div style="display:flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                                    <span class="badge">Request Pending</span>
                                    <form method="POST" action="community_join_handler.php" style="margin:0;">
                                        <input type="hidden" name="community_id" value="<?php echo (int)$community_id; ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button class="btn btn-ghost" type="submit" onclick="return confirm('Cancel your join request?');">Cancel request</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <form method="POST" action="community_join_handler.php" style="margin:0; width: 100%; max-width: 720px;">
                                    <input type="hidden" name="community_id" value="<?php echo (int)$community_id; ?>">
                                    <?php if ($join_requests_enabled): ?>
                                        <div class="meta" style="margin-top: 2px;">Answer these to help the creator/moderator review your request.</div>
                                        <div class="form-group" style="margin-top: 10px;">
                                            <label>What kind of support are you looking for? *</label>
                                            <textarea class="form-input" name="support_goal" rows="2" required placeholder="Example: I want a supportive space to discuss anxiety and learn coping strategies."></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label>Any boundaries / topics to avoid when responding to you? *</label>
                                            <textarea class="form-input" name="boundaries" rows="2" required placeholder="Example: Please avoid graphic descriptions; I prefer gentle, practical suggestions."></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label>Are you sure you belong to this community? Tell us briefly why. *</label>
                                            <textarea class="form-input" name="belong_reason" rows="2" required placeholder="Example: I relate to this community’s focus and want to participate respectfully."></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label>How will you contribute to the community? (optional)</label>
                                            <textarea class="form-input" name="contribution" rows="2" placeholder="Example: I can share what worked for me and support others respectfully."></textarea>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label>Anything else? (optional)</label>
                                            <input class="form-input" name="join_reason" placeholder="Optional: Anything else to add?">
                                        </div>
                                        <div style="display:flex; justify-content:flex-end; margin-top: 10px;">
                                            <button class="btn btn-primary" type="submit">Request to Join</button>
                                        </div>
                                    <?php else: ?>
                                        <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                                            <input class="form-input" name="join_reason" placeholder="Optional: Why are you joining?" style="min-width: 260px;">
                                            <button class="btn btn-primary" type="submit">Join</button>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if (!$is_creator): ?>
                                <form method="POST" action="community_join_handler.php" style="margin:0;">
                                    <input type="hidden" name="community_id" value="<?php echo (int)$community_id; ?>">
                                    <input type="hidden" name="action" value="leave">
                                    <button class="btn btn-ghost" type="submit" onclick="return confirm('Leave this community?');">Leave</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$is_member): ?>
                    <div class="card">
                        <strong><?php echo $join_requests_enabled ? 'Approval required to access posts.' : 'Join to access posts.'; ?></strong>
                        <div class="meta" style="margin-top: 6px;">
                            <?php if ($join_requests_enabled && $my_join_status === 'pending'): ?>
                                Your request is pending. Once the creator/moderator approves it, you’ll be able to view posts and use all community features.
                            <?php else: ?>
                                You can read the purpose above, then <?php echo $join_requests_enabled ? 'request to join' : 'join'; ?> to view posts, comment, react, and report.
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
