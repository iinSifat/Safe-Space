<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();
$post_id = (int)($_GET['post_id'] ?? 0);

// Get post details
$post_stmt = $conn->prepare("
    SELECT fp.post_id, fp.title, fp.category, fp.content, fp.created_at, 
           fp.view_count, u.username
    FROM forum_posts fp
    JOIN users u ON fp.user_id = u.user_id
    WHERE fp.post_id = ? AND fp.status = 'published'
");
$post_stmt->bind_param("i", $post_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();
$post = $post_result->fetch_assoc();
$post_stmt->close();

if (!$post) {
    redirect('forum.php');
}

// Update view count
$view_stmt = $conn->prepare("UPDATE forum_posts SET view_count = view_count + 1 WHERE post_id = ?");
$view_stmt->bind_param("i", $post_id);
$view_stmt->execute();
$view_stmt->close();

// Handle new reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reply'])) {
    $content = sanitize_input($_POST['reply_content'] ?? '');
    if (!empty($content)) {
        $reply_stmt = $conn->prepare("
            INSERT INTO forum_replies (post_id, user_id, content, is_encrypted, status)
            VALUES (?, ?, ?, 1, 'published')
        ");
        $reply_stmt->bind_param("iss", $post_id, $user_id, $content);
        $reply_stmt->execute();
        $reply_stmt->close();

        // Award points
        $points_stmt = $conn->prepare("UPDATE user_points SET total_points = total_points + 10 WHERE user_id = ?");
        $points_stmt->bind_param("i", $user_id);
        $points_stmt->execute();
        $points_stmt->close();

        // Update reply count
        $count_stmt = $conn->prepare("UPDATE forum_posts SET reply_count = reply_count + 1 WHERE post_id = ?");
        $count_stmt->bind_param("i", $post_id);
        $count_stmt->execute();
        $count_stmt->close();
    }
}

// Get replies
$replies_stmt = $conn->prepare("
    SELECT fr.reply_id, fr.content, fr.created_at, fr.helpful_count, u.username
    FROM forum_replies fr
    JOIN users u ON fr.user_id = u.user_id
    WHERE fr.post_id = ? AND fr.status = 'published'
    ORDER BY fr.created_at ASC
");
$replies_stmt->bind_param("i", $post_id);
$replies_stmt->execute();
$replies_result = $replies_stmt->get_result();
$replies = [];
while ($row = $replies_result->fetch_assoc()) {
    $replies[] = $row;
}
$replies_stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .forum-view-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .post-header {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .post-header-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .post-content {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            line-height: 1.8;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .replies-section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .replies-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
        }

        .reply-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 1rem;
        }

        .reply-item:last-child {
            border-bottom: none;
        }

        .reply-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .reply-author {
            font-weight: 600;
            color: var(--text-primary);
        }

        .reply-date {
            color: var(--text-secondary);
        }

        .reply-content {
            color: var(--text-primary);
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .new-reply-section {
            background: linear-gradient(135deg, rgba(107, 155, 209, 0.05), rgba(184, 166, 217, 0.05));
            padding: 2rem;
            border-radius: var(--radius-lg);
        }

        .reply-form-group {
            margin-bottom: 1rem;
        }

        .reply-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .reply-form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 1rem;
            resize: vertical;
            min-height: 120px;
        }

        @media (max-width: 768px) {
            .post-header,
            .post-content,
            .replies-section,
            .new-reply-section {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="forum-view-container">
        <!-- Post Header -->
        <div class="post-header">
            <div style="display: inline-block; background: rgba(107, 155, 209, 0.15); color: var(--primary-color); 
                        padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($post['category']); ?>
            </div>
            <h1 style="font-size: 2rem; color: var(--text-primary); margin-bottom: 1rem;">
                <?php echo htmlspecialchars($post['title']); ?>
            </h1>
            <div class="post-header-meta">
                <span><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 4px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg><?php echo htmlspecialchars($post['username']); ?></span>
                <span>📅 <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?></span>
                <span><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 4px; vertical-align: middle;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><?php echo $post['view_count']; ?> views</span>
            </div>
        </div>

        <!-- Post Content -->
        <div class="post-content">
            <?php echo htmlspecialchars($post['content']); ?>
        </div>

        <!-- Replies Section -->
        <div class="replies-section">
            <div class="replies-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>Replies (<?php echo count($replies); ?>)</div>

            <?php if (count($replies) > 0): ?>
                <?php foreach ($replies as $reply): ?>
                    <div class="reply-item">
                        <div class="reply-meta">
                            <span class="reply-author"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 4px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg><?php echo htmlspecialchars($reply['username']); ?></span>
                            <span class="reply-date"><?php echo date('M j, Y \a\t g:i A', strtotime($reply['created_at'])); ?></span>
                        </span>
                        <div class="reply-content" style="margin-top: 0.5rem;">
                            <?php echo htmlspecialchars($reply['content']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">
                    No replies yet. Be the first to respond!
                </p>
            <?php endif; ?>
        </div>

        <!-- New Reply Section -->
        <div class="new-reply-section">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem;">
                Add Your Support
            </h3>
            <form method="POST" action="">
                <div class="reply-form-group">
                    <textarea name="reply_content" placeholder="Share your thoughts, experiences, or advice..." required></textarea>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="add_reply" class="btn btn-primary">Post Reply (+10 pts)</button>
                    <a href="forum.php" class="btn btn-secondary">Back to Forum</a>
                </div>
            </form>
        </div>

        <!-- Bottom Navigation -->
        <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
            <a href="index.php" class="btn btn-secondary">Dashboard</a>
            <a href="mood_tracker.php" class="btn btn-secondary">Mood Tracker</a>
        </div>
    </div>
</body>
</html>
