<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

$blog_id = (int)($_GET['blog_id'] ?? 0);
if ($blog_id <= 0) {
    redirect('blogs.php');
}

// Get blog details
$post_sql = "
    SELECT bp.blog_id, bp.title, bp.category, bp.content, bp.created_at, bp.view_count, bp.comment_count,
           bp.user_id, bp.is_professional_post,
           u.username, u.user_type
    FROM blog_posts bp
    JOIN users u ON bp.user_id = u.user_id
    WHERE bp.blog_id = ? AND bp.status = 'published'
";
$post_stmt = $conn->prepare($post_sql);
if (!$post_stmt) {
    error_log('blog_view.php: failed to prepare blog query: ' . $conn->error);
    set_flash_message('error', 'Unable to load that blog post right now.');
    redirect('blogs.php');
}
$post_stmt->bind_param('i', $blog_id);
$post_stmt->execute();
$post = $post_stmt->get_result()->fetch_assoc();
$post_stmt->close();

if (!$post) {
    redirect('blogs.php');
}

// Update view count
$view_stmt = $conn->prepare('UPDATE blog_posts SET view_count = view_count + 1 WHERE blog_id = ?');
$view_stmt->bind_param('i', $blog_id);
$view_stmt->execute();
$view_stmt->close();

// Handle new comment
$comment_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $content = sanitize_input($_POST['comment_content'] ?? '');
    if ($content !== '') {
        $c_stmt = $conn->prepare("INSERT INTO blog_comments (blog_id, user_id, content, status) VALUES (?, ?, ?, 'published')");
        $c_stmt->bind_param('iis', $blog_id, $user_id, $content);
        $c_stmt->execute();
        $c_stmt->close();

        $count_stmt = $conn->prepare('UPDATE blog_posts SET comment_count = comment_count + 1 WHERE blog_id = ?');
        $count_stmt->bind_param('i', $blog_id);
        $count_stmt->execute();
        $count_stmt->close();

        redirect('blog_view.php?blog_id=' . $blog_id);
    } else {
        $comment_error = 'Comment cannot be empty.';
    }
}

// Reaction data for the current blog post
$reaction_types = ['like', 'celebrate', 'support', 'love', 'insightful', 'curious'];
$reaction_assets = [
    'like' => '../images/reactions/like.png',
    'celebrate' => '../images/reactions/Linkedin-Celebrate-Icon-ClappingHands500.png',
    'support' => '../images/reactions/Linkedin-Support-Icon-HeartinHand500.png',
    'love' => '../images/reactions/Linkedin-Love-Icon-Heart500.png',
    'insightful' => '../images/reactions/Linkedin-Insightful-Icon-Lamp500.png',
    'curious' => '../images/reactions/Linkedin-Curious-Icon-PurpleSmiley500.png',
];

$reaction_counts = array_fill_keys($reaction_types, 0);
$user_reaction = null;

$user_reaction_stmt = $conn->prepare('SELECT reaction_type FROM blog_post_reactions WHERE blog_id = ? AND user_id = ? LIMIT 1');
$user_reaction_stmt->bind_param('ii', $blog_id, $user_id);
$user_reaction_stmt->execute();
$user_reaction_result = $user_reaction_stmt->get_result();
if ($row = $user_reaction_result->fetch_assoc()) {
    $user_reaction = $row['reaction_type'];
}
$user_reaction_stmt->close();

$reaction_counts_stmt = $conn->prepare('SELECT reaction_type, COUNT(*) as total FROM blog_post_reactions WHERE blog_id = ? GROUP BY reaction_type');
$reaction_counts_stmt->bind_param('i', $blog_id);
$reaction_counts_stmt->execute();
$rc_result = $reaction_counts_stmt->get_result();
while ($r = $rc_result->fetch_assoc()) {
    $type = $r['reaction_type'];
    if (in_array($type, $reaction_types, true)) {
        $reaction_counts[$type] = (int)$r['total'];
    }
}
$reaction_counts_stmt->close();
$total_reactions = array_sum($reaction_counts);

// Get comments
$comments_sql = "
    SELECT bc.comment_id, bc.content, bc.created_at,
           u.username, u.user_type
    FROM blog_comments bc
    JOIN users u ON bc.user_id = u.user_id
    WHERE bc.blog_id = ? AND bc.status = 'published'
    ORDER BY bc.created_at ASC
";
$comments_stmt = $conn->prepare($comments_sql);
if (!$comments_stmt) {
    error_log('blog_view.php: failed to prepare comments query: ' . $conn->error);
    $comments = [];
} else {
$comments_stmt->bind_param('i', $blog_id);
$comments_stmt->execute();
$comments = $comments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$comments_stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> | Blog</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .blog-view-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .post-header,
        .post-content,
        .comments-section,
        .new-comment {
            background: white;
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
            background: rgba(107, 155, 209, 0.15);
            color: var(--primary-color);
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 900;
            font-size: 0.85rem;
        }

        .pro-chip {
            display: inline-flex;
            align-items: center;
            background: rgba(123, 93, 255, 0.14);
            color: var(--secondary-color);
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 900;
            font-size: 0.85rem;
        }

        .post-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            color: var(--text-secondary);
            margin-top: 0.75rem;
            font-size: 0.95rem;
        }

        /* Reuse reaction bar styling from forum_view */
        .post-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: white;
            border: 1px solid var(--light-gray);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }

        .post-actions-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .reaction-wrapper { position: relative; }

        .reaction-trigger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid var(--light-gray);
            background: white;
            border-radius: 999px;
            cursor: pointer;
        }

        .reaction-trigger img {
            width: 22px;
            height: 22px;
            object-fit: contain;
        }

        .reaction-popup {
            display: none;
            position: absolute;
            bottom: 52px;
            left: 0;
            background: white;
            border: 1px solid var(--light-gray);
            border-radius: 999px;
            padding: 8px;
            gap: 10px;
            box-shadow: var(--shadow-md);
            z-index: 50;
        }

        .reaction-popup.show { display: flex; }

        .reaction-option {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
        }

        .reaction-option img {
            width: 28px;
            height: 28px;
        }

        .action-chip {
            border: 1px solid var(--light-gray);
            background: white;
            border-radius: 999px;
            padding: 8px 12px;
            cursor: pointer;
            font-weight: 800;
        }

        .post-actions-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .reaction-count-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--light-bg);
        }

        .reaction-count-chip img {
            width: 20px;
            height: 20px;
        }

        .comments-section { padding: 2rem; margin-bottom: 1.25rem; }
        .comment-item { padding: 1rem 0; border-bottom: 1px solid var(--light-gray); }
        .comment-item:last-child { border-bottom: none; }
        .comment-meta { display: flex; justify-content: space-between; gap: 1rem; color: var(--text-secondary); font-size: 0.9rem; }
        .comment-author { font-weight: 800; color: var(--text-primary); }

        .new-comment { padding: 2rem; }

        @media (max-width: 768px) {
            .post-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            .post-actions-left {
                width: 100%;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M4 4h16v16H4z"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/></svg>
                    Blog Post
                </h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">🔔 Notifications</a>
                </div>
            </div>

            <div class="content-area">
                <div class="blog-view-container">
                    <div class="post-header">
                        <div class="badge-row">
                            <span class="category-chip"><?php echo htmlspecialchars($post['category']); ?></span>
                            <?php if (($post['user_type'] ?? '') === 'professional'): ?>
                                <span class="pro-chip">Professional</span>
                            <?php endif; ?>
                        </div>

                        <h1 style="margin: 0; font-size: 2rem; color: var(--text-primary);">
                            <?php echo htmlspecialchars($post['title']); ?>
                        </h1>

                        <div class="post-meta">
                            <span>👤 <?php echo htmlspecialchars($post['username']); ?></span>
                            <span>📅 <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?></span>
                            <span>👁️ <?php echo (int)$post['view_count']; ?> views</span>
                        </div>
                    </div>

                    <div class="post-content">
                        <?php echo htmlspecialchars($post['content']); ?>
                    </div>

                    <div class="post-actions" aria-label="Post actions">
                        <div class="post-actions-left">
                            <div class="reaction-wrapper" id="reactionWrapper" data-blog-id="<?php echo $blog_id; ?>">
                                <button type="button" class="reaction-trigger" id="reactionTrigger" aria-haspopup="true" aria-expanded="false">
                                    <img id="activeReactionIcon" src="<?php echo htmlspecialchars($reaction_assets[$user_reaction ?? 'like']); ?>" alt="Current reaction">
                                    <span id="activeReactionLabel" style="font-weight: 900; color: var(--text-primary);">
                                        <?php echo $user_reaction ? ucfirst($user_reaction) : 'Like'; ?>
                                    </span>
                                </button>
                                <div class="reaction-popup" id="reactionPopup" role="menu" aria-label="Choose a reaction">
                                    <?php foreach ($reaction_types as $reaction_type): ?>
                                        <button class="reaction-option" data-reaction="<?php echo $reaction_type; ?>" aria-label="React with <?php echo ucfirst($reaction_type); ?>">
                                            <img src="<?php echo htmlspecialchars($reaction_assets[$reaction_type]); ?>" alt="<?php echo ucfirst($reaction_type); ?> icon">
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button class="action-chip" type="button" data-scroll-target="#commentFormSection">💬 Comment</button>
                        </div>
                        <div class="post-actions-meta">
                            <span class="reaction-count-chip" id="reactionCountChip">
                                <img src="<?php echo htmlspecialchars($reaction_assets['like']); ?>" alt="Reactions">
                                <span><strong id="reactionTotal"><?php echo $total_reactions; ?></strong> reactions</span>
                            </span>
                            <span style="color: var(--text-secondary);">💬 <?php echo (int)$post['comment_count']; ?> comments</span>
                        </div>
                    </div>

                    <div class="comments-section" id="commentsSection">
                        <div style="font-size: 1.25rem; font-weight: 900; color: var(--text-primary); margin-bottom: 1rem;">Comments (<?php echo count($comments); ?>)</div>
                        <?php if ($comment_error): ?>
                            <div style="color: #b91c1c; font-weight: 800; margin-bottom: 1rem;"><?php echo htmlspecialchars($comment_error); ?></div>
                        <?php endif; ?>

                        <?php if (count($comments) > 0): ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-item">
                                    <div class="comment-meta">
                                        <span class="comment-author">
                                            <?php echo htmlspecialchars($comment['username']); ?>
                                            <?php if (($comment['user_type'] ?? '') === 'professional'): ?>
                                                <span style="margin-left: 8px; font-weight: 900; color: var(--secondary-color);">Professional</span>
                                            <?php endif; ?>
                                        </span>
                                        <span><?php echo date('M j, Y \a\t g:i A', strtotime($comment['created_at'])); ?></span>
                                    </div>
                                    <div style="margin-top: 0.5rem; white-space: pre-wrap; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($comment['content']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="margin: 0; color: var(--text-secondary); text-align: center; padding: 1rem;">No comments yet. Be the first to comment.</p>
                        <?php endif; ?>
                    </div>

                    <div class="new-comment" id="commentFormSection">
                        <div style="font-size: 1.1rem; font-weight: 900; color: var(--text-primary); margin-bottom: 0.75rem;">Add a comment</div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <textarea name="comment_content" required placeholder="Write your comment..." style="min-height: 120px;"></textarea>
                            </div>
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <button type="submit" name="add_comment" class="btn btn-primary">Post Comment</button>
                                <a href="blogs.php" class="btn btn-secondary">Back to Blog</a>
                            </div>
                        </form>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap;">
                        <a href="profile.php" class="btn btn-secondary">My Profile</a>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const reactionWrapper = document.getElementById('reactionWrapper');
                        const reactionPopup = document.getElementById('reactionPopup');
                        const reactionTrigger = document.getElementById('reactionTrigger');
                        const activeReactionIcon = document.getElementById('activeReactionIcon');
                        const activeReactionLabel = document.getElementById('activeReactionLabel');
                        const reactionTotalEl = document.getElementById('reactionTotal');
                        const scrollButtons = document.querySelectorAll('[data-scroll-target]');

                        const reactionAssets = <?php echo json_encode($reaction_assets); ?>;
                        const reactionLabels = <?php echo json_encode(array_combine($reaction_types, array_map('ucfirst', $reaction_types))); ?>;

                        function togglePopup() {
                            const isOpen = reactionPopup.classList.toggle('show');
                            reactionTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                        }

                        reactionTrigger?.addEventListener('click', (e) => {
                            e.preventDefault();
                            togglePopup();
                        });

                        document.addEventListener('click', (e) => {
                            if (!reactionWrapper.contains(e.target)) {
                                reactionPopup.classList.remove('show');
                                reactionTrigger.setAttribute('aria-expanded', 'false');
                            }
                        });

                        async function sendReaction(reactionType) {
                            const payload = {
                                blog_id: parseInt(reactionWrapper.dataset.blogId, 10),
                                reaction_type: reactionType,
                            };

                            const response = await fetch('blog_reaction_handler.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify(payload),
                            });

                            const data = await response.json();
                            if (!data.success) {
                                throw new Error(data.message || 'Reaction failed');
                            }

                            const selected = data.reaction;
                            activeReactionIcon.src = reactionAssets[selected] || reactionAssets.like;
                            activeReactionLabel.textContent = reactionLabels[selected] || 'Like';
                            reactionTotalEl.textContent = String(data.total_reactions ?? 0);
                        }

                        reactionPopup?.querySelectorAll('.reaction-option')?.forEach(btn => {
                            btn.addEventListener('click', async (e) => {
                                e.preventDefault();
                                const reactionType = btn.dataset.reaction;
                                reactionPopup.classList.remove('show');
                                reactionTrigger.setAttribute('aria-expanded', 'false');

                                try {
                                    await sendReaction(reactionType);
                                } catch (err) {
                                    console.error(err);
                                }
                            });
                        });

                        scrollButtons.forEach(btn => {
                            btn.addEventListener('click', () => {
                                const target = document.querySelector(btn.dataset.scrollTarget);
                                target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            });
                        });
                    });
                </script>
            </div>
        </main>
    </div>
</body>
</html>
