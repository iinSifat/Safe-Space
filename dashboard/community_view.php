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
$is_comm_volunteer = ($my_role === 'volunteer');
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

// Updated by Shuvo - START
// Manage Community page must be reachable only from inside the community context.
if ($can_manage) {
    if (!isset($_SESSION['community_creator_context']) || !is_array($_SESSION['community_creator_context'])) {
        $_SESSION['community_creator_context'] = [];
    }
    $_SESSION['community_creator_context'][(string)$community_id] = time();
}
// Updated by Shuvo - END

$allowed_reactions = ss_allowed_reactions();
$reaction_assets = ss_reaction_assets();
$views_supported = ss_community_post_views_supported($conn);

// Latest weekly prompt
$weekly_prompt = null;
$pstmt = $conn->prepare("SELECT prompt_text, week_start_date, created_at FROM community_weekly_prompts WHERE community_id = ? AND status = 'active' ORDER BY week_start_date DESC, created_at DESC LIMIT 1");
$pstmt->bind_param('i', $community_id);
$pstmt->execute();
$weekly_prompt = $pstmt->get_result()->fetch_assoc() ?: null;
$pstmt->close();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_member) {
        set_flash_message('error', 'Please join this community to participate.');
        redirect('community_about.php?community_id=' . $community_id . '#membership');
    }

    // Create post
    if (isset($_POST['create_community_post'])) {
        $title = trim((string)($_POST['post_title'] ?? ''));
        $content_raw = trim((string)($_POST['post_content'] ?? ''));
        $is_anon = isset($_POST['is_anonymous']) ? 1 : 0;

        if (!$community['allow_anonymous_posts']) {
            $is_anon = 0;
        }

        if ($title === '' || $content_raw === '') {
            set_flash_message('error', 'Title and content are required.');
            redirect('community_view.php?community_id=' . $community_id);
        }

        $content = sanitize_input($content_raw);
        $stmt = $conn->prepare("INSERT INTO community_posts (community_id, user_id, title, content, is_anonymous, status) VALUES (?, ?, ?, ?, ?, 'published')");
        $stmt->bind_param('iissi', $community_id, $user_id, $title, $content, $is_anon);
        $stmt->execute();
        $stmt->close();

        set_flash_message('success', 'Post created.');
        redirect('community_view.php?community_id=' . $community_id);
    }

    // Add comment
    if (isset($_POST['add_community_comment'])) {
        $post_id = (int)($_POST['post_id'] ?? 0);
        $content_raw = trim((string)($_POST['comment_content'] ?? ''));
        $is_anon = isset($_POST['comment_is_anonymous']) ? 1 : 0;

        if (!$community['allow_anonymous_posts']) {
            $is_anon = 0;
        }

        if ($post_id <= 0 || $content_raw === '') {
            redirect('community_view.php?community_id=' . $community_id);
        }

        $pcheck = $conn->prepare("SELECT 1 FROM community_posts WHERE post_id = ? AND community_id = ? AND status = 'published' LIMIT 1");
        $pcheck->bind_param('ii', $post_id, $community_id);
        $pcheck->execute();
        $ok = (bool)($pcheck->get_result()->fetch_assoc() ?? false);
        $pcheck->close();

        if ($ok) {
            $content = sanitize_input($content_raw);
            $stmt = $conn->prepare("INSERT INTO community_comments (post_id, community_id, user_id, content, is_anonymous, status) VALUES (?, ?, ?, ?, ?, 'published')");
            $stmt->bind_param('iiisi', $post_id, $community_id, $user_id, $content, $is_anon);
            $stmt->execute();
            $stmt->close();
        }

        redirect('community_view.php?community_id=' . $community_id . '#post-' . $post_id);
    }

    // Add comment reply
    if (isset($_POST['add_community_comment_reply'])) {
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        $content_raw = trim((string)($_POST['reply_content'] ?? ''));
        $is_anon = isset($_POST['reply_is_anonymous']) ? 1 : 0;

        if (!$community['allow_anonymous_posts']) {
            $is_anon = 0;
        }

        if ($comment_id > 0 && $content_raw !== '') {
            $ccheck = $conn->prepare("SELECT post_id FROM community_comments WHERE comment_id = ? AND community_id = ? AND status = 'published' LIMIT 1");
            $ccheck->bind_param('ii', $comment_id, $community_id);
            $ccheck->execute();
            $crow = $ccheck->get_result()->fetch_assoc();
            $ccheck->close();

            if ($crow) {
                $content = sanitize_input($content_raw);
                $stmt = $conn->prepare("INSERT INTO community_comment_replies (comment_id, community_id, user_id, content, is_anonymous, status) VALUES (?, ?, ?, ?, ?, 'published')");
                $stmt->bind_param('iiisi', $comment_id, $community_id, $user_id, $content, $is_anon);
                $stmt->execute();
                $stmt->close();

                redirect('community_view.php?community_id=' . $community_id . '#comment-' . $comment_id);
            }
        }

        redirect('community_view.php?community_id=' . $community_id);
    }

    // Toggle highlight (creator or approved community volunteer)
    if (isset($_POST['toggle_highlight']) && $can_manage) {
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        if ($comment_id > 0) {
            $stmt = $conn->prepare("UPDATE community_comments SET is_highlighted = NOT is_highlighted WHERE comment_id = ? AND community_id = ?");
            $stmt->bind_param('ii', $comment_id, $community_id);
            $stmt->execute();
            $stmt->close();
        }
        redirect('community_view.php?community_id=' . $community_id);
    }

    // Set weekly prompt (creator or approved community volunteer)
    if (isset($_POST['set_weekly_prompt']) && $can_manage) {
        $prompt = trim((string)($_POST['prompt_text'] ?? ''));
        if ($prompt !== '') {
            if (mb_strlen($prompt) > 500) { $prompt = mb_substr($prompt, 0, 500); }
            $week_start = date('Y-m-d', strtotime('monday this week'));

            // Archive previous active prompts
            $arch = $conn->prepare("UPDATE community_weekly_prompts SET status = 'archived' WHERE community_id = ? AND status = 'active'");
            $arch->bind_param('i', $community_id);
            $arch->execute();
            $arch->close();

            $stmt = $conn->prepare("INSERT INTO community_weekly_prompts (community_id, prompt_text, week_start_date, created_by_user_id, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->bind_param('issi', $community_id, $prompt, $week_start, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        redirect('community_view.php?community_id=' . $community_id);
    }
}

$flash = get_flash_message();

// Fetch posts
$posts = [];
if ($is_member) {
    $sql = $views_supported
        ? "SELECT p.post_id, p.title, p.content, p.is_anonymous, p.created_at, p.user_id,
                  p.view_count,
                  u.username, u.user_type,
                  (SELECT COUNT(*) FROM community_comments cc WHERE cc.community_id = p.community_id AND cc.post_id = p.post_id AND cc.status = 'published') AS comment_count
           FROM community_posts p
           JOIN users u ON u.user_id = p.user_id
           WHERE p.community_id = ? AND p.status = 'published'
           ORDER BY p.created_at DESC
           LIMIT 20"
        : "SELECT p.post_id, p.title, p.content, p.is_anonymous, p.created_at, p.user_id,
                  0 AS view_count,
                  u.username, u.user_type,
                  (SELECT COUNT(*) FROM community_comments cc WHERE cc.community_id = p.community_id AND cc.post_id = p.post_id AND cc.status = 'published') AS comment_count
           FROM community_posts p
           JOIN users u ON u.user_id = p.user_id
           WHERE p.community_id = ? AND p.status = 'published'
           ORDER BY p.created_at DESC
           LIMIT 20";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $community_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $posts[] = $row; }
    $stmt->close();
}

function ss_get_reaction_counts(mysqli $conn, int $community_id, string $target_type, int $target_id, array $allowed): array {
    $counts = array_fill_keys($allowed, 0);
    $stmt = $conn->prepare("SELECT reaction_type, COUNT(*) AS total FROM community_reactions WHERE community_id = ? AND target_type = ? AND target_id = ? GROUP BY reaction_type");
    $stmt->bind_param('isi', $community_id, $target_type, $target_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $t = $r['reaction_type'];
        if (isset($counts[$t])) { $counts[$t] = (int)$r['total']; }
    }
    $stmt->close();
    return $counts;
}

function ss_get_user_reaction(mysqli $conn, int $community_id, int $user_id, string $target_type, int $target_id): ?string {
    $stmt = $conn->prepare("SELECT reaction_type FROM community_reactions WHERE community_id = ? AND user_id = ? AND target_type = ? AND target_id = ? LIMIT 1");
    $stmt->bind_param('iisi', $community_id, $user_id, $target_type, $target_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['reaction_type'] ?? null;
}
// Updated by Shuvo - END
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
        .post { margin-top: 14px; }
        .post-title { margin: 0 0 6px; font-size: 1.1rem; font-weight: 950; }
        .meta { color: var(--text-secondary); font-size: 0.92rem; }
        .content { margin-top: 8px; color: var(--text-primary); }
        .divider { margin: 14px 0; border-top: 1px solid var(--border-soft, #D8E2DD); }
        .row { display:flex; justify-content: space-between; align-items: flex-start; gap: 10px; flex-wrap: wrap; }
        .inline { display:flex; gap: 10px; align-items:center; flex-wrap: wrap; }
        .role { background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.25); color:#fff; border-radius: 999px; padding: 4px 10px; font-weight: 900; font-size: 0.8rem; }
        .comment { margin-top: 10px; padding: 10px; border: 1px solid var(--border-soft, #D8E2DD); border-radius: 12px; background: #fff; }
        .comment.highlight { border-color: var(--accent-primary, #7FAFA3); box-shadow: 0 0 0 2px rgba(127,175,163,0.18); }
        .comment-actions { display:flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .mini { font-size: 0.85rem; }

        /* Blog-style post cards for community feed */
        .post-card {
            background: var(--bg-card, #F8F9F7);
            border: 1px solid var(--border-soft, #D8E2DD);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .post-card:hover { box-shadow: var(--shadow-md); }

        .post-open {
            display: block;
            text-decoration: none;
            color: inherit;
            padding: 1.25rem 1.25rem 0.75rem;
        }

        .post-header-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .post-avatar {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            background: rgba(127, 175, 163, 0.25);
            border: 1px solid rgba(127, 175, 163, 0.35);
            display: grid;
            place-items: center;
            font-weight: 900;
            color: var(--text-primary);
        }

        .post-head-text { min-width: 0; }

        .post-name-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .post-name {
            font-weight: 900;
            color: var(--text-primary);
            line-height: 1.1;
        }

        .pro-badge {
            background: rgba(123, 93, 255, 0.14);
            color: var(--secondary-color);
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
        }

        .post-sub {
            margin-top: 4px;
            color: var(--text-secondary);
            font-size: 0.9rem;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .category-chip {
            display: inline-flex;
            align-items: center;
            background: rgba(127, 175, 163, 0.15);
            color: var(--accent-primary, #7FAFA3);
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 900;
            font-size: 0.8rem;
        }

        .post-card-title {
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--text-primary);
            margin: 0 0 0.35rem;
        }

        .post-excerpt {
            color: var(--text-primary);
            opacity: 0.92;
            margin: 0;
            line-height: 1.65;
        }

        .post-stats {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 0.75rem 1.25rem;
            border-top: 1px solid var(--border-soft, #D8E2DD);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .post-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding: 0.75rem 1.25rem 1rem;
            border-top: 1px solid var(--border-soft, #D8E2DD);
            background: rgba(127, 175, 163, 0.05);
        }

        .post-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 900;
            color: var(--text-primary);
            border: 1px solid var(--border-soft, #D8E2DD);
            background: var(--bg-card, #F8F9F7);
            transition: background 0.15s ease, transform 0.15s ease;
            cursor: pointer;
        }

        .post-action:hover {
            background: rgba(127, 175, 163, 0.14);
            transform: translateY(-1px);
        }

        .post-actions a.post-action,
        .post-actions a.post-action:visited,
        .post-actions a.post-action:hover,
        .post-actions a.post-action:focus {
            color: var(--text-primary);
            text-decoration: none;
        }

        .feed-reaction { position: relative; }

        .feed-reaction .reaction-trigger {
            width: 100%;
            border: 1px solid var(--border-soft, #D8E2DD);
            background: var(--bg-card, #F8F9F7);
            border-radius: 12px;
            padding: 10px 12px;
            font-weight: 900;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }

        .feed-reaction .reaction-trigger img { width: 20px; height: 20px; object-fit: contain; }

        .reaction-popup {
            position: absolute;
            bottom: 100%;
            left: 0;
            transform: translateY(-2px) scale(0.98);
            display: flex;
            gap: 0.25rem;
            padding: 0.5rem 0.6rem;
            background: var(--bg-card, #F8F9F7);
            border-radius: 999px;
            box-shadow: 0 12px 30px rgba(12, 27, 51, 0.15);
            border: 1px solid var(--border-soft, #D8E2DD);
            opacity: 0;
            pointer-events: none;
            transition: opacity var(--transition-fast), transform var(--transition-fast);
            z-index: 50;
        }
        .feed-reaction:hover .reaction-popup,
        .feed-reaction:focus-within .reaction-popup {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(-2px) scale(1);
        }
        .reaction-option {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: none;
            background: transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }
        .reaction-option:hover { transform: translateY(-2px) scale(1.05); box-shadow: var(--shadow-sm); }
        .reaction-option img { width: 100%; height: 100%; object-fit: contain; }
        .report-box { display:none; margin-top: 10px; }
        .report-box.show { display:block; }

        .hero-actions { display:none; }

        /* Discussion composer (Facebook-like "Write something...") */
        .composer {
            background: rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 18px;
            padding: 14px;
            margin-bottom: 14px;
        }
        .composer-top { display:flex; gap: 12px; align-items:center; }
        .composer-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display:flex;
            align-items:center;
            justify-content:center;
            background: rgba(0,0,0,0.10);
            color: var(--text-primary);
            font-weight: 950;
            flex: 0 0 auto;
        }
        .composer-input {
            flex: 1;
            border: 0;
            background: rgba(255,255,255,0.9);
            border-radius: 999px;
            padding: 12px 14px;
            text-align: left;
            cursor: pointer;
            color: var(--text-secondary);
            font-weight: 700;
        }
        .composer-actions {
            display:flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(0,0,0,0.10);
        }
        .composer-action {
            flex: 1;
            border: 0;
            background: transparent;
            cursor: pointer;
            padding: 10px 8px;
            border-radius: 12px;
            font-weight: 850;
            color: var(--text-primary);
        }
        .composer-action:hover { background: rgba(255,255,255,0.55); }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-dialog {
            background: var(--bg-card, #F8F9F7);
            border-radius: var(--radius-lg);
            padding: 1.4rem;
            max-width: 720px;
            width: 92%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            color: var(--text-secondary);
        }
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
                <?php $activeTab = 'discussion'; include __DIR__ . '/includes/community_group_header.php'; ?>

                <?php if ($flash): ?>
                    <div class="alert <?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
                <?php endif; ?>

                <?php if (!$is_member): ?>
                    <div class="card">
                        <strong><?php echo $join_requests_enabled ? 'Approval required to access posts.' : 'Join to access posts.'; ?></strong>
                        <div class="meta" style="margin-top: 6px;">
                            <?php if ($join_requests_enabled && $my_join_status === 'pending'): ?>
                                Your request is pending. Once the creator/moderator approves it, you’ll be able to view posts and use all community features.
                            <?php else: ?>
                                Open the About page to read the purpose and <?php echo $join_requests_enabled ? 'request to join' : 'join'; ?>.
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 12px;">
                            <a class="btn btn-secondary" href="community_about.php?community_id=<?php echo (int)$community_id; ?>#membership">Go to About / Join</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                        $meName = ss_display_name((string)($_SESSION['username'] ?? ''), $_SESSION['user_type'] ?? null, $user_id, false);
                        $meLetter = strtoupper(substr((string)$meName, 0, 1));
                    ?>

                    <?php if ($weekly_prompt): ?>
                        <div class="card" style="margin-bottom: 14px;">
                            <div style="font-weight: 950;">Weekly Discussion Prompt</div>
                            <div class="meta" style="margin-top: 6px; white-space: pre-wrap;"><?php echo htmlspecialchars((string)$weekly_prompt['prompt_text']); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($can_manage && !$views_supported): ?>
                        <div class="alert warning" style="margin-bottom: 14px;">
                            Views are not available because the database is missing the <strong>view_count</strong> column.
                            Run: <strong>ALTER TABLE community_posts ADD COLUMN view_count INT DEFAULT 0 AFTER updated_at;</strong>
                        </div>
                    <?php endif; ?>

                    <div class="composer" role="region" aria-label="Create a post">
                        <div class="composer-top">
                            <div class="composer-avatar" aria-hidden="true"><?php echo htmlspecialchars($meLetter); ?></div>
                            <button class="composer-input" type="button" onclick="openCommunityPostModal()">Write something...</button>
                        </div>
                    </div>

                    <?php foreach ($posts as $post): ?>
                    <?php
                        $post_author_is_anon = ((int)$post['is_anonymous'] === 1);
                        $post_name = ss_display_name((string)$post['username'], $post['user_type'] ?? null, (int)$post['user_id'], $post_author_is_anon);

                        $author_role = ss_get_member_role($conn, $community_id, (int)$post['user_id']);
                        $role_badge = '';
                        if ($author_role === 'creator') $role_badge = 'Creator';
                        if ($author_role === 'volunteer') $role_badge = 'Community Volunteer';

                        $avatarLetter = strtoupper(substr((string)$post_name, 0, 1));
                        $rawContent = (string)($post['content'] ?? '');
                        $excerpt = function_exists('mb_substr') ? mb_substr($rawContent, 0, 180) : substr($rawContent, 0, 180);
                        $excerpt = trim($excerpt);
                        $hasMore = (function_exists('mb_strlen') ? mb_strlen($rawContent) : strlen($rawContent)) > (function_exists('mb_strlen') ? mb_strlen($excerpt) : strlen($excerpt));

                        $post_counts = ss_get_reaction_counts($conn, $community_id, 'post', (int)$post['post_id'], $allowed_reactions);
                        $reactionTotal = array_sum($post_counts);
                        $post_user_reaction = $is_member ? ss_get_user_reaction($conn, $community_id, $user_id, 'post', (int)$post['post_id']) : null;
                        $activeReaction = $post_user_reaction && isset($reaction_assets[$post_user_reaction]) ? $post_user_reaction : 'like';
                        $activeReactionLabel = $post_user_reaction ? ucfirst((string)$post_user_reaction) : 'React';

                        $commentCount = (int)($post['comment_count'] ?? 0);
                        $detailsHref = 'community_post_view.php?community_id=' . (int)$community_id . '&post_id=' . (int)$post['post_id'];
                    ?>

                    <div class="post-card" id="post-<?php echo (int)$post['post_id']; ?>" style="margin-top: 14px;">
                        <a class="post-open" href="<?php echo htmlspecialchars($detailsHref); ?>" aria-label="Open community post">
                            <div class="post-header-row">
                                <div class="post-avatar" aria-hidden="true"><?php echo htmlspecialchars($avatarLetter); ?></div>
                                <div class="post-head-text">
                                    <div class="post-name-row">
                                        <span class="post-name"><?php echo htmlspecialchars($post_name); ?></span>
                                        <?php if ($role_badge !== ''): ?>
                                            <span class="pro-badge"><?php echo htmlspecialchars($role_badge); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="post-sub">
                                        <span><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                        <span aria-hidden="true">·</span>
                                        <span class="category-chip"><?php echo htmlspecialchars(ucfirst((string)($community['focus_tag'] ?? 'community'))); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="post-card-title"><?php echo htmlspecialchars($post['title']); ?></div>
                            <?php if ($excerpt !== ''): ?>
                                <p class="post-excerpt"><?php echo htmlspecialchars($excerpt); ?><?php echo $hasMore ? '…' : ''; ?></p>
                            <?php endif; ?>
                        </a>

                        <div class="post-stats">
                            <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> <?php echo (int)($post['view_count'] ?? 0); ?> views</span>
                            <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg> <strong data-reaction-total><?php echo (int)$reactionTotal; ?></strong> reactions</span>
                            <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> <?php echo (int)$commentCount; ?> comments</span>
                        </div>

                        <div class="post-actions" aria-label="Post actions">
                            <div class="feed-reaction" data-post-id="<?php echo (int)$post['post_id']; ?>" data-community-id="<?php echo (int)$community_id; ?>">
                                <button type="button" class="reaction-trigger" aria-haspopup="true" aria-expanded="false">
                                    <img class="active-reaction-icon" src="<?php echo htmlspecialchars($reaction_assets[$activeReaction]); ?>" alt="Reaction">
                                    <span class="active-reaction-label"><?php echo htmlspecialchars($activeReactionLabel); ?></span>
                                </button>
                                <div class="reaction-popup" role="menu" aria-label="Choose a reaction">
                                    <?php foreach ($allowed_reactions as $rt): ?>
                                        <button class="reaction-option" type="button" data-reaction="<?php echo htmlspecialchars($rt); ?>" aria-label="React with <?php echo htmlspecialchars(ucfirst($rt)); ?>">
                                            <img src="<?php echo htmlspecialchars($reaction_assets[$rt]); ?>" alt="<?php echo htmlspecialchars($rt); ?>">
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <a class="post-action" href="<?php echo htmlspecialchars($detailsHref . '#commentFormSection'); ?>">
                                <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                Comment
                            </a>

                            <button type="button" class="post-action post-action-share" data-share-href="<?php echo htmlspecialchars($detailsHref); ?>">↗ Share</button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php endif; ?>

            </div>
        </div>
    </main>
</div>

<?php if ($is_member): ?>
    <!-- New Community Post Modal (forum-style) -->
    <div class="modal-overlay" id="communityPostModal" aria-hidden="true">
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="communityPostModalTitle">
            <div class="modal-header">
                <h2 id="communityPostModalTitle" style="margin:0;">Create Post</h2>
                <button class="modal-close" type="button" onclick="closeCommunityPostModal()">✕</button>
            </div>
            <div class="meta" style="margin-top: -4px;">Keep wording supportive and respectful. If you’re in immediate danger, use Emergency resources.</div>

            <form method="POST" style="margin-top: 14px;">
                <div class="form-group">
                    <label>Title *</label>
                    <input class="form-input" name="post_title" required>
                </div>
                <div class="form-group">
                    <label>Content *</label>
                    <textarea class="form-input" name="post_content" rows="5" required></textarea>
                </div>
                <?php if ((int)$community['allow_anonymous_posts'] === 1): ?>
                    <label class="meta" style="display:flex; gap: 8px; align-items:center; margin-bottom: 10px;">
                        <input type="checkbox" name="is_anonymous"> Post anonymously
                    </label>
                <?php endif; ?>
                <div style="display:flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;">
                    <button class="btn btn-primary" type="submit" name="create_community_post">Publish</button>
                    <button class="btn btn-secondary" type="button" onclick="closeCommunityPostModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
// Updated by Shuvo - START
// Community feed reactions (blog-style)
(function initCommunityFeedReactions() {
    const allowed = <?php echo json_encode($allowed_reactions); ?>;
    const reactionAssets = <?php echo json_encode($reaction_assets); ?>;
    const reactionLabels = <?php echo json_encode(array_combine($allowed_reactions, array_map('ucfirst', $allowed_reactions))); ?>;

    async function sendReaction(wrapper, reactionType) {
        const postId = parseInt(wrapper?.dataset?.postId || '0', 10);
        const communityId = parseInt(wrapper?.dataset?.communityId || '0', 10);
        if (postId <= 0 || communityId <= 0) return;
        if (!allowed.includes(reactionType)) return;

        const payload = {
            community_id: communityId,
            target_type: 'post',
            target_id: postId,
            reaction_type: reactionType,
        };

        const res = await fetch('community_reaction_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data?.ok) throw new Error(data?.error || 'Reaction failed');

        const selected = data.reaction || reactionType;
        const icon = wrapper.querySelector('.active-reaction-icon');
        const label = wrapper.querySelector('.active-reaction-label');
        if (icon) icon.src = reactionAssets[selected] || reactionAssets.like;
        if (label) label.textContent = reactionLabels[selected] || 'React';

        const totalEl = wrapper.closest('.post-card')?.querySelector('[data-reaction-total]');
        if (totalEl) totalEl.textContent = String(data.total_reactions ?? 0);
    }

    document.querySelectorAll('.feed-reaction').forEach((wrapper) => {
        const trigger = wrapper.querySelector('.reaction-trigger');
        const popup = wrapper.querySelector('.reaction-popup');

        trigger?.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await sendReaction(wrapper, 'like');
            } catch (err) {
                console.error(err);
            }
        });

        popup?.querySelectorAll('.reaction-option').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const reactionType = btn.dataset.reaction;
                try {
                    await sendReaction(wrapper, reactionType);
                } catch (err) {
                    console.error(err);
                }
            });
        });
    });

    // Share copies link
    document.querySelectorAll('.post-action-share[data-share-href]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const href = btn.getAttribute('data-share-href');
            if (!href) return;
            const link = new URL(href, window.location.href).toString();
            try {
                await navigator.clipboard.writeText(link);
                const oldText = btn.textContent;
                btn.textContent = 'Link copied!';
                setTimeout(() => { btn.textContent = oldText; }, 1400);
            } catch {
                window.prompt('Copy this link', link);
            }
        });
    });
})();
// Updated by Shuvo - END

// Forum-style modal for creating a community post
function openCommunityPostModal() {
    const modal = document.getElementById('communityPostModal');
    if (!modal) return;
    modal.classList.add('show');
}

function closeCommunityPostModal() {
    const modal = document.getElementById('communityPostModal');
    if (!modal) return;
    modal.classList.remove('show');
}

document.getElementById('communityPostModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeCommunityPostModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCommunityPostModal();
    }
});
</script>
</body>
</html>
