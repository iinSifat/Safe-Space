<?php
// Community Post Details - blog/forum style UI
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
$post_id = (int)($_GET['post_id'] ?? 0);

if ($community_id <= 0 || $post_id <= 0) {
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

$join_requests_enabled = ss_join_requests_enabled($conn);
$my_join_status = (!$is_member && $join_requests_enabled) ? ss_get_join_request_status($conn, $community_id, $user_id) : null;

// Hide posts until approved membership
if (!$is_member) {
    if ($join_requests_enabled && $my_join_status === 'pending') {
        set_flash_message('error', 'Your join request is pending approval.');
    } else {
        set_flash_message('error', $join_requests_enabled ? 'You must be approved to view posts in this community.' : 'Please join this community to view posts.');
    }
    redirect('community_about.php?community_id=' . $community_id . '#membership');
}

$allowed_reactions = ss_allowed_reactions();
$reaction_assets = ss_reaction_assets();
$views_supported = ss_community_post_views_supported($conn);

function ss_get_reaction_counts(mysqli $conn, int $community_id, string $target_type, int $target_id, array $allowed): array {
    $counts = array_fill_keys($allowed, 0);
    $stmt = $conn->prepare("SELECT reaction_type, COUNT(*) AS total FROM community_reactions WHERE community_id = ? AND target_type = ? AND target_id = ? GROUP BY reaction_type");
    $stmt->bind_param('isi', $community_id, $target_type, $target_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $t = $r['reaction_type'];
        if (isset($counts[$t])) {
            $counts[$t] = (int)$r['total'];
        }
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

// Load post
$post_sql = $views_supported
    ? "SELECT p.post_id, p.title, p.content, p.is_anonymous, p.created_at, p.user_id,
        p.view_count,
        u.username, u.user_type
    FROM community_posts p
    JOIN users u ON u.user_id = p.user_id
    WHERE p.community_id = ? AND p.post_id = ? AND p.status = 'published'
    LIMIT 1"
    : "SELECT p.post_id, p.title, p.content, p.is_anonymous, p.created_at, p.user_id,
        0 AS view_count,
        u.username, u.user_type
    FROM community_posts p
    JOIN users u ON u.user_id = p.user_id
    WHERE p.community_id = ? AND p.post_id = ? AND p.status = 'published'
    LIMIT 1";

$pstmt = $conn->prepare($post_sql);
$pstmt->bind_param('ii', $community_id, $post_id);
$pstmt->execute();
$post = $pstmt->get_result()->fetch_assoc() ?: null;
$pstmt->close();

if (!$post) {
    set_flash_message('error', 'Post not found.');
    redirect('community_view.php?community_id=' . $community_id);
}

// Increment view counter (if supported)
if ($views_supported) {
    $vstmt = $conn->prepare("UPDATE community_posts SET view_count = view_count + 1 WHERE community_id = ? AND post_id = ? AND status = 'published'");
    if ($vstmt) {
        $vstmt->bind_param('ii', $community_id, $post_id);
        $vstmt->execute();
        $vstmt->close();
    }
    $post['view_count'] = (int)($post['view_count'] ?? 0) + 1;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_member) {
        set_flash_message('error', 'Please join this community to participate.');
        redirect('community_post_view.php?community_id=' . $community_id . '&post_id=' . $post_id);
    }

    // Add comment
    if (isset($_POST['add_community_comment'])) {
        $content_raw = trim((string)($_POST['comment_content'] ?? ''));
        $is_anon = isset($_POST['comment_is_anonymous']) ? 1 : 0;

        if (!(int)$community['allow_anonymous_posts']) {
            $is_anon = 0;
        }

        if ($content_raw !== '') {
            $content = sanitize_input($content_raw);
            $stmt = $conn->prepare("INSERT INTO community_comments (post_id, community_id, user_id, content, is_anonymous, status) VALUES (?, ?, ?, ?, ?, 'published')");
            $stmt->bind_param('iiisi', $post_id, $community_id, $user_id, $content, $is_anon);
            $stmt->execute();
            $stmt->close();
        }

        redirect('community_post_view.php?community_id=' . $community_id . '&post_id=' . $post_id . '#commentFormSection');
    }

    // Add comment reply
    if (isset($_POST['add_community_comment_reply'])) {
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        $content_raw = trim((string)($_POST['reply_content'] ?? ''));
        $is_anon = isset($_POST['reply_is_anonymous']) ? 1 : 0;

        if (!(int)$community['allow_anonymous_posts']) {
            $is_anon = 0;
        }

        if ($comment_id > 0 && $content_raw !== '') {
            // Ensure comment belongs to this post/community
            $ccheck = $conn->prepare("SELECT 1 FROM community_comments WHERE comment_id = ? AND community_id = ? AND post_id = ? AND status = 'published' LIMIT 1");
            $ccheck->bind_param('iii', $comment_id, $community_id, $post_id);
            $ccheck->execute();
            $ok = (bool)($ccheck->get_result()->fetch_assoc() ?? false);
            $ccheck->close();

            if ($ok) {
                $content = sanitize_input($content_raw);
                $stmt = $conn->prepare("INSERT INTO community_comment_replies (comment_id, community_id, user_id, content, is_anonymous, status) VALUES (?, ?, ?, ?, ?, 'published')");
                $stmt->bind_param('iiisi', $comment_id, $community_id, $user_id, $content, $is_anon);
                $stmt->execute();
                $stmt->close();
            }
        }

        redirect('community_post_view.php?community_id=' . $community_id . '&post_id=' . $post_id . '#comment-' . $comment_id);
    }

    // Toggle highlight (creator or approved community volunteer)
    if (isset($_POST['toggle_highlight']) && ($is_creator || $is_comm_volunteer)) {
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        if ($comment_id > 0) {
            $stmt = $conn->prepare("UPDATE community_comments SET is_highlighted = NOT is_highlighted WHERE comment_id = ? AND community_id = ? AND post_id = ?");
            $stmt->bind_param('iii', $comment_id, $community_id, $post_id);
            $stmt->execute();
            $stmt->close();
        }
        redirect('community_post_view.php?community_id=' . $community_id . '&post_id=' . $post_id . '#comment-' . $comment_id);
    }
}

// Display names / role
$post_author_is_anon = ((int)$post['is_anonymous'] === 1);
$post_name = ss_display_name((string)$post['username'], $post['user_type'] ?? null, (int)$post['user_id'], $post_author_is_anon);

$author_role = ss_get_member_role($conn, $community_id, (int)$post['user_id']);
$role_badge = '';
if ($author_role === 'creator') $role_badge = 'Creator';
if ($author_role === 'volunteer') $role_badge = 'Community Volunteer';

// Reactions (post)
$post_counts = ss_get_reaction_counts($conn, $community_id, 'post', $post_id, $allowed_reactions);
$post_total_reactions = array_sum($post_counts);
$post_user_reaction = $is_member ? ss_get_user_reaction($conn, $community_id, $user_id, 'post', $post_id) : null;

// Comments
$comments = [];
$cstmt = $conn->prepare(
    "SELECT c.comment_id, c.content, c.created_at, c.user_id, c.is_anonymous, c.is_highlighted,
            u.username, u.user_type
     FROM community_comments c
     JOIN users u ON u.user_id = c.user_id
     WHERE c.community_id = ? AND c.post_id = ? AND c.status = 'published'
     ORDER BY c.is_highlighted DESC, c.created_at ASC"
);
$cstmt->bind_param('ii', $community_id, $post_id);
$cstmt->execute();
$cres = $cstmt->get_result();
while ($r = $cres->fetch_assoc()) {
    $comments[] = $r;
}
$cstmt->close();

$comment_replies = [];
if (count($comments) > 0) {
    $ids = array_map(static fn($c) => (int)$c['comment_id'], $comments);
    $ids = array_values(array_filter($ids, static fn($v) => $v > 0));
    if (count($ids) > 0) {
        $id_list = implode(',', $ids);
        $rsql = "
            SELECT r.reply_id, r.comment_id, r.content, r.created_at, r.user_id, r.is_anonymous,
                   u.username, u.user_type
            FROM community_comment_replies r
            JOIN users u ON u.user_id = r.user_id
            WHERE r.community_id = " . (int)$community_id . " AND r.status = 'published' AND r.comment_id IN ($id_list)
            ORDER BY r.created_at ASC
        ";
        $rres = @$conn->query($rsql);
        if ($rres) {
            while ($row = $rres->fetch_assoc()) {
                $cid = (int)$row['comment_id'];
                if (!isset($comment_replies[$cid])) {
                    $comment_replies[$cid] = [];
                }
                $comment_replies[$cid][] = $row;
            }
        }
    }
}

$comments_count = count($comments);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars((string)$post['title']); ?> | Community</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .wrap {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .post-header,
        .post-content,
        .comments-section,
        .new-comment {
            background: var(--bg-card, #F8F9F7);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .post-header { padding: 2rem; margin-bottom: 1.5rem; }
        .post-content { padding: 2rem; margin-bottom: 1.25rem; line-height: 1.9; white-space: pre-wrap; }

        .badge-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .category-chip {
            display: inline-flex;
            align-items: center;
            background: rgba(127, 175, 163, 0.15);
            color: var(--accent-primary, #7FAFA3);
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 900;
            font-size: 0.85rem;
        }

        .role-chip {
            display: inline-flex;
            align-items: center;
            background: rgba(255,255,255,0.75);
            color: var(--text-primary);
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 900;
            font-size: 0.85rem;
            border: 1px solid var(--border-soft, #D8E2DD);
        }

        .post-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            color: var(--text-secondary);
            margin-top: 0.75rem;
            font-size: 0.95rem;
        }

        .post-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: var(--bg-card, #F8F9F7);
            border: 1px solid var(--border-soft, #D8E2DD);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }

        .post-actions-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .reaction-wrapper { position: relative; }

        .reaction-trigger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid var(--border-soft, #D8E2DD);
            background: var(--bg-card, #F8F9F7);
            border-radius: 999px;
            cursor: pointer;
            font-weight: 800;
        }

        .reaction-trigger img {
            width: 22px;
            height: 22px;
            object-fit: contain;
        }

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

        .reaction-wrapper:hover .reaction-popup,
        .reaction-wrapper:focus-within .reaction-popup {
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

        .reaction-option:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: var(--shadow-sm);
        }

        .reaction-option img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .action-chip {
            border: 1px solid var(--border-soft, #D8E2DD);
            background: var(--bg-card, #F8F9F7);
            border-radius: 999px;
            padding: 8px 12px;
            cursor: pointer;
            font-weight: 800;
        }

        .post-actions-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .reaction-count-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--light-bg);
        }

        .reaction-count-chip img { width: 20px; height: 20px; }

        .comments-section { padding: 2rem; margin-bottom: 1.25rem; }
        .comment-item { padding: 1rem 0; border-bottom: 1px solid var(--light-gray); }
        .comment-item:last-child { border-bottom: none; }

        .comment-meta {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
            flex-wrap: wrap;
        }

        .comment-author { font-weight: 900; color: var(--text-primary); }

        .comment-highlight {
            border-left: 4px solid var(--accent-primary, #7FAFA3);
            padding-left: 12px;
        }

        .comment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
            align-items: center;
        }

        .new-comment { padding: 2rem; }

        .nested-replies {
            margin-top: 10px;
            padding-left: 14px;
            border-left: 2px solid var(--border-soft, #D8E2DD);
        }

        @media (max-width: 768px) {
            .post-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            .post-actions-left {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="top-bar">
            <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">Community Post</h2>
            <div class="top-bar-right">
                <a class="btn btn-secondary" href="community_view.php?community_id=<?php echo (int)$community_id; ?>">Back to Community</a>
            </div>
        </div>

        <div class="content-area">
            <div class="wrap">
                <div class="post-header">
                    <div class="badge-row">
                        <span class="category-chip"><?php echo htmlspecialchars(ucfirst((string)($community['focus_tag'] ?? 'community'))); ?></span>
                        <?php if ($role_badge !== ''): ?>
                            <span class="role-chip"><?php echo htmlspecialchars($role_badge); ?></span>
                        <?php endif; ?>
                    </div>

                    <h1 style="margin: 0; font-size: 2rem; color: var(--text-primary);">
                        <?php echo htmlspecialchars((string)$post['title']); ?>
                    </h1>

                    <div class="post-meta">
                        <span>
                            <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><path d="M12 3a4 4 0 1 1 0 8 4 4 0 0 1 0-8z"/></svg>
                            <?php echo htmlspecialchars($post_name); ?>
                        </span>
                        <span>
                            <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                            <?php echo date('M j, Y \a\t g:i A', strtotime((string)$post['created_at'])); ?>
                        </span>
                    </div>
                </div>

                <div class="post-content">
                    <?php echo htmlspecialchars((string)$post['content']); ?>
                </div>

                <div class="post-actions" aria-label="Post actions">
                    <div class="post-actions-left">
                        <div class="reaction-wrapper" id="reactionWrapper" data-community-id="<?php echo (int)$community_id; ?>" data-target-type="post" data-target-id="<?php echo (int)$post_id; ?>">
                            <button type="button" class="reaction-trigger" id="reactionTrigger" aria-haspopup="true" aria-expanded="false">
                                <img id="activeReactionIcon" src="<?php echo htmlspecialchars($reaction_assets[$post_user_reaction ?: 'like']); ?>" alt="Current reaction">
                                <span id="activeReactionLabel" style="font-weight: 900; color: var(--text-primary);">
                                    <?php echo $post_user_reaction ? ucfirst((string)$post_user_reaction) : 'Like'; ?>
                                </span>
                            </button>
                            <div class="reaction-popup" id="reactionPopup" role="menu" aria-label="Choose a reaction">
                                <?php foreach ($allowed_reactions as $rt): ?>
                                    <button class="reaction-option" type="button" data-reaction="<?php echo htmlspecialchars($rt); ?>" aria-label="React with <?php echo htmlspecialchars(ucfirst($rt)); ?>">
                                        <img src="<?php echo htmlspecialchars($reaction_assets[$rt]); ?>" alt="<?php echo htmlspecialchars($rt); ?>">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button class="action-chip" type="button" data-scroll-target="#commentFormSection">
                            <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            Comment
                        </button>

                        <button class="action-chip" type="button" id="sharePostButton">↗ Share</button>

                        <button class="action-chip" type="button" id="toggleReportPost">Report</button>
                    </div>

                    <div class="post-actions-meta">
                        <span class="reaction-count-chip" id="reactionCountChip">
                            <img src="<?php echo htmlspecialchars($reaction_assets['like']); ?>" alt="Reactions">
                            <span><strong id="reactionTotal"><?php echo (int)$post_total_reactions; ?></strong> reactions</span>
                        </span>
                        <span style="color: var(--text-secondary);">
                            <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <?php echo (int)$comments_count; ?> comments
                        </span>
                    </div>
                </div>

                <div id="reportPostBox" style="display:none; margin-bottom: 1.25rem;" aria-label="Report post">
                    <div class="post-header" style="padding: 1.25rem;">
                        <div style="font-weight: 950; margin-bottom: 8px;">Report this post</div>
                        <form method="POST" action="community_report_handler.php" style="margin:0;">
                            <input type="hidden" name="community_id" value="<?php echo (int)$community_id; ?>">
                            <input type="hidden" name="target_type" value="post">
                            <input type="hidden" name="target_id" value="<?php echo (int)$post_id; ?>">
                            <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                                <select class="form-input" name="reason" style="max-width: 260px;">
                                    <option value="harassment">Harassment</option>
                                    <option value="self_harm">Self-harm risk</option>
                                    <option value="hate">Hate</option>
                                    <option value="spam">Spam</option>
                                    <option value="misinformation">Misinformation</option>
                                    <option value="other" selected>Other</option>
                                </select>
                                <button class="btn btn-secondary" type="submit">Submit Report</button>
                            </div>
                            <textarea class="form-input" name="details" rows="2" placeholder="Optional details (short)"></textarea>
                        </form>
                    </div>
                </div>

                <div class="comments-section" id="commentsSection">
                    <div style="font-size: 1.25rem; font-weight: 900; color: var(--text-primary); margin-bottom: 1rem;">Comments (<?php echo (int)$comments_count; ?>)</div>

                    <?php if ($comments_count > 0): ?>
                        <?php foreach ($comments as $c): ?>
                            <?php
                                $c_name = ss_display_name((string)$c['username'], $c['user_type'] ?? null, (int)$c['user_id'], ((int)$c['is_anonymous'] === 1));
                                $c_role = ss_get_member_role($conn, $community_id, (int)$c['user_id']);
                                $c_role_badge = '';
                                if ($c_role === 'creator') $c_role_badge = 'Creator';
                                if ($c_role === 'volunteer') $c_role_badge = 'Community Volunteer';

                                $comment_counts = ss_get_reaction_counts($conn, $community_id, 'comment', (int)$c['comment_id'], $allowed_reactions);
                                $comment_total = array_sum($comment_counts);
                                $comment_user_reaction = $is_member ? ss_get_user_reaction($conn, $community_id, $user_id, 'comment', (int)$c['comment_id']) : null;

                                $replies = $comment_replies[(int)$c['comment_id']] ?? [];
                            ?>
                            <div class="comment-item<?php echo ((int)$c['is_highlighted'] === 1) ? ' comment-highlight' : ''; ?>" id="comment-<?php echo (int)$c['comment_id']; ?>">
                                <div class="comment-meta">
                                    <span class="comment-author">
                                        <?php echo htmlspecialchars($c_name); ?>
                                        <?php if ($c_role_badge !== ''): ?>
                                            <span class="role-chip" style="margin-left: 8px; padding: 2px 10px; font-size: 0.8rem;"><?php echo htmlspecialchars($c_role_badge); ?></span>
                                        <?php endif; ?>
                                        <?php if ((int)$c['is_highlighted'] === 1): ?>
                                            <span class="role-chip" style="margin-left: 8px; padding: 2px 10px; font-size: 0.8rem;">Helpful highlight</span>
                                        <?php endif; ?>
                                    </span>
                                    <span><?php echo date('M j, Y \a\t g:i A', strtotime((string)$c['created_at'])); ?></span>
                                </div>
                                <div style="margin-top: 0.5rem; white-space: pre-wrap; color: var(--text-primary);">
                                    <?php echo htmlspecialchars((string)$c['content']); ?>
                                </div>

                                <div class="comment-actions">
                                    <div class="reaction-wrapper" data-community-id="<?php echo (int)$community_id; ?>" data-target-type="comment" data-target-id="<?php echo (int)$c['comment_id']; ?>">
                                        <button type="button" class="reaction-trigger" aria-haspopup="true" aria-expanded="false">
                                            <img class="active-reaction-icon" src="<?php echo htmlspecialchars($reaction_assets[$comment_user_reaction ?: 'like']); ?>" alt="Reaction">
                                            <span class="active-reaction-label"><?php echo htmlspecialchars($comment_user_reaction ? ucfirst((string)$comment_user_reaction) : 'React'); ?></span>
                                        </button>
                                        <div class="reaction-popup" role="menu" aria-label="Choose a reaction">
                                            <?php foreach ($allowed_reactions as $rt): ?>
                                                <button class="reaction-option" type="button" data-reaction="<?php echo htmlspecialchars($rt); ?>" aria-label="React with <?php echo htmlspecialchars(ucfirst($rt)); ?>">
                                                    <img src="<?php echo htmlspecialchars($reaction_assets[$rt]); ?>" alt="<?php echo htmlspecialchars($rt); ?>">
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <span class="reaction-count-chip" style="padding: 6px 10px;"><span><strong class="commentReactionTotal"><?php echo (int)$comment_total; ?></strong> reactions</span></span>

                                    <button type="button" class="action-chip" data-toggle-report="comment" data-comment-id="<?php echo (int)$c['comment_id']; ?>">Report</button>

                                    <?php if ($is_member && ($is_creator || $is_comm_volunteer)): ?>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="comment_id" value="<?php echo (int)$c['comment_id']; ?>">
                                            <button class="action-chip" type="submit" name="toggle_highlight">Toggle highlight</button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <div id="report-comment-<?php echo (int)$c['comment_id']; ?>" style="display:none; margin-top: 10px;">
                                    <form method="POST" action="community_report_handler.php" style="margin-top: 8px;">
                                        <input type="hidden" name="community_id" value="<?php echo (int)$community_id; ?>">
                                        <input type="hidden" name="target_type" value="comment">
                                        <input type="hidden" name="target_id" value="<?php echo (int)$c['comment_id']; ?>">
                                        <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                                            <select class="form-input" name="reason" style="max-width: 260px;">
                                                <option value="harassment">Harassment</option>
                                                <option value="self_harm">Self-harm risk</option>
                                                <option value="hate">Hate</option>
                                                <option value="spam">Spam</option>
                                                <option value="misinformation">Misinformation</option>
                                                <option value="other" selected>Other</option>
                                            </select>
                                            <button class="btn btn-secondary" type="submit">Submit Report</button>
                                        </div>
                                        <textarea class="form-input" name="details" rows="2" placeholder="Optional details (short)"></textarea>
                                    </form>
                                </div>

                                <?php if ($is_member): ?>
                                    <form method="POST" style="margin-top: 12px;">
                                        <input type="hidden" name="comment_id" value="<?php echo (int)$c['comment_id']; ?>">
                                        <textarea class="form-input" name="reply_content" rows="2" placeholder="Reply..." required></textarea>
                                        <?php if ((int)$community['allow_anonymous_posts'] === 1): ?>
                                            <label class="meta" style="display:flex; gap: 8px; align-items:center; margin: 8px 0;">
                                                <input type="checkbox" name="reply_is_anonymous"> Reply anonymously
                                            </label>
                                        <?php endif; ?>
                                        <button class="btn btn-secondary" type="submit" name="add_community_comment_reply">Reply</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!empty($replies)): ?>
                                    <div class="nested-replies" aria-label="Replies">
                                        <?php foreach ($replies as $r): ?>
                                            <?php $r_name = ss_display_name((string)$r['username'], $r['user_type'] ?? null, (int)$r['user_id'], ((int)$r['is_anonymous'] === 1)); ?>
                                            <div class="comment-meta" style="margin-top: 10px;">
                                                <span class="comment-author"><?php echo htmlspecialchars($r_name); ?></span>
                                                <span><?php echo date('M j, Y \a\t g:i A', strtotime((string)$r['created_at'])); ?></span>
                                            </div>
                                            <div style="margin-top: 0.4rem; white-space: pre-wrap; color: var(--text-primary);">
                                                <?php echo htmlspecialchars((string)$r['content']); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="margin: 0; color: var(--text-secondary); text-align: center; padding: 1rem;">No comments yet. Be the first to comment.</p>
                    <?php endif; ?>
                </div>

                <div class="new-comment" id="commentFormSection">
                    <div style="font-size: 1.1rem; font-weight: 900; color: var(--text-primary); margin-bottom: 0.75rem;">Add a comment</div>
                    <?php if (!$is_member): ?>
                        <p style="margin: 0; color: var(--text-secondary);">Join this community to comment and react.</p>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div class="form-group">
                                <textarea name="comment_content" required placeholder="Write a supportive comment..." style="min-height: 120px;"></textarea>
                            </div>
                            <?php if ((int)$community['allow_anonymous_posts'] === 1): ?>
                                <label class="meta" style="display:flex; gap: 8px; align-items:center; margin: 8px 0;">
                                    <input type="checkbox" name="comment_is_anonymous"> Comment anonymously
                                </label>
                            <?php endif; ?>
                            <div style="display:flex; gap: 1rem; flex-wrap: wrap;">
                                <button type="submit" name="add_community_comment" class="btn btn-primary">Post Comment</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const allowed = <?php echo json_encode($allowed_reactions); ?>;
    const reactionAssets = <?php echo json_encode($reaction_assets); ?>;
    const reactionLabels = <?php echo json_encode(array_combine($allowed_reactions, array_map('ucfirst', $allowed_reactions))); ?>;

    const sharePostButton = document.getElementById('sharePostButton');
    const toggleReportPost = document.getElementById('toggleReportPost');
    const reportPostBox = document.getElementById('reportPostBox');

    toggleReportPost?.addEventListener('click', () => {
        if (!reportPostBox) return;
        reportPostBox.style.display = (reportPostBox.style.display === 'none' || reportPostBox.style.display === '') ? 'block' : 'none';
        reportPostBox.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    });

    document.querySelectorAll('[data-scroll-target]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = document.querySelector(btn.getAttribute('data-scroll-target'));
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    sharePostButton?.addEventListener('click', async () => {
        const link = window.location.href;
        try {
            await navigator.clipboard.writeText(link);
            const old = sharePostButton.textContent;
            sharePostButton.textContent = 'Link copied!';
            setTimeout(() => { sharePostButton.textContent = old; }, 1400);
        } catch {
            window.prompt('Copy this link', link);
        }
    });

    // Toggle comment report boxes
    document.querySelectorAll('[data-toggle-report="comment"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const cid = btn.getAttribute('data-comment-id');
            const box = document.getElementById('report-comment-' + cid);
            if (!box) return;
            box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
        });
    });

    async function sendReaction(wrapper, reactionType) {
        const communityId = parseInt(wrapper?.dataset?.communityId || '0', 10);
        const targetType = String(wrapper?.dataset?.targetType || '').toLowerCase();
        const targetId = parseInt(wrapper?.dataset?.targetId || '0', 10);
        if (communityId <= 0 || targetId <= 0 || !['post', 'comment'].includes(targetType)) return;
        if (!allowed.includes(reactionType)) return;

        const payload = {
            community_id: communityId,
            target_type: targetType,
            target_id: targetId,
            reaction_type: reactionType,
        };

        const res = await fetch('community_reaction_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (!data?.ok) throw new Error(data?.error || 'Reaction failed');

        const selected = data.reaction || reactionType;
        const icon = wrapper.querySelector('.active-reaction-icon');
        const label = wrapper.querySelector('.active-reaction-label');
        if (icon) icon.src = reactionAssets[selected] || reactionAssets.like;
        if (label) label.textContent = reactionLabels[selected] || 'React';

        if (targetType === 'post') {
            const totalEl = document.getElementById('reactionTotal');
            if (totalEl) totalEl.textContent = String(data.total_reactions ?? 0);
        } else {
            const totalEl = wrapper.closest('.comment-item')?.querySelector('.commentReactionTotal');
            if (totalEl) totalEl.textContent = String(data.total_reactions ?? 0);
        }
    }

    document.querySelectorAll('.reaction-wrapper').forEach((wrapper) => {
        const trigger = wrapper.querySelector('.reaction-trigger');
        const popup = wrapper.querySelector('.reaction-popup');

        trigger?.addEventListener('click', async (e) => {
            e.preventDefault();
            // quick tap defaults to like
            try {
                await sendReaction(wrapper, 'like');
            } catch (err) {
                console.error(err);
            }
        });

        popup?.querySelectorAll('.reaction-option').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const reactionType = btn.getAttribute('data-reaction');
                try {
                    await sendReaction(wrapper, reactionType);
                } catch (err) {
                    console.error(err);
                }
            });
        });
    });
});
</script>
</body>
</html>
// Updated by Shuvo - END
