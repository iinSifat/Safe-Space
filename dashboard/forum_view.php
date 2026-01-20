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
           fp.view_count, fp.user_id, u.username, u.is_anonymous
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
    SELECT fr.reply_id, fr.user_id, fr.content, fr.created_at, fr.helpful_count, u.username, u.is_anonymous
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

// Reaction data for the current post
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

// Get the current user's reaction (if any)
$user_reaction_stmt = $conn->prepare("SELECT reaction_type FROM forum_post_reactions WHERE post_id = ? AND user_id = ? LIMIT 1");
$user_reaction_stmt->bind_param("ii", $post_id, $user_id);
$user_reaction_stmt->execute();
$user_reaction_result = $user_reaction_stmt->get_result();
if ($user_reaction_row = $user_reaction_result->fetch_assoc()) {
    $user_reaction = $user_reaction_row['reaction_type'];
}
$user_reaction_stmt->close();

// Get aggregate reaction counts for the post
$reaction_counts_stmt = $conn->prepare("SELECT reaction_type, COUNT(*) as total FROM forum_post_reactions WHERE post_id = ? GROUP BY reaction_type");
$reaction_counts_stmt->bind_param("i", $post_id);
$reaction_counts_stmt->execute();
$reaction_counts_result = $reaction_counts_stmt->get_result();
while ($reaction_row = $reaction_counts_result->fetch_assoc()) {
    $type = $reaction_row['reaction_type'];
    if (in_array($type, $reaction_types, true)) {
        $reaction_counts[$type] = (int)$reaction_row['total'];
    }
}
$reaction_counts_stmt->close();

$total_reactions = array_sum($reaction_counts);

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

        /* LinkedIn-style reaction bar */
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

        .post-actions-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .action-chip {
            border: 1px solid var(--light-gray);
            background: var(--light-bg);
            color: var(--text-primary);
            border-radius: 999px;
            padding: 0.4rem 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all var(--transition-fast);
        }

        .action-chip:hover {
            background: white;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-sm);
            color: var(--primary-dark);
        }

        .reaction-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .reaction-trigger {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: white;
            border: 1px solid var(--light-gray);
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: transform var(--transition-fast), box-shadow var(--transition-fast), border-color var(--transition-fast);
        }

        .reaction-trigger img {
            width: 22px;
            height: 22px;
            object-fit: contain;
        }

        .reaction-trigger:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .reaction-popup {
            position: absolute;
            bottom: 100%;
            left: 0;
            transform: translateY(-2px) scale(0.98);
            display: flex;
            gap: 0.25rem;
            padding: 0.5rem 0.6rem;
            background: white;
            border-radius: 999px;
            box-shadow: 0 12px 30px rgba(12, 27, 51, 0.15);
            border: 1px solid var(--light-gray);
            opacity: 0;
            pointer-events: none;
            transition: opacity var(--transition-fast), transform var(--transition-fast);
            z-index: 20;
        }

        .reaction-wrapper:hover .reaction-popup,
        .reaction-wrapper.show-popup .reaction-popup {
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

        .reaction-count-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: var(--light-bg);
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            border: 1px solid var(--light-gray);
        }

        .reaction-count-chip img {
            width: 18px;
            height: 18px;
        }

        .post-date-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .post-header,
            .post-content,
            .replies-section,
            .new-reply-section {
                padding: 1.5rem;
            }

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
            <?php
                $post_author = !empty($post['is_anonymous'])
                    ? get_anonymous_display_name($post['user_id'])
                    : $post['username'];
            ?>
            <div class="post-header-meta">
                <span><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 4px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg><?php echo htmlspecialchars($post_author); ?></span>
                <span>📅 <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?></span>
                <span><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 4px; vertical-align: middle;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><?php echo $post['view_count']; ?> views</span>
            </div>
        </div>

        <!-- Post Content -->
        <div class="post-content">
            <?php echo htmlspecialchars($post['content']); ?>
        </div>

        <!-- Reactions & Actions -->
        <div class="post-actions" aria-label="Post actions">
            <div class="post-actions-left">
                <div class="reaction-wrapper" id="reactionWrapper" data-post-id="<?php echo $post_id; ?>">
                    <button type="button" class="reaction-trigger" id="reactionTrigger" aria-haspopup="true" aria-expanded="false">
                        <img id="activeReactionIcon" src="<?php echo htmlspecialchars($reaction_assets[$user_reaction ?? 'like']); ?>" alt="Current reaction">
                        <span id="activeReactionLabel" style="font-weight: 700; color: var(--text-primary);">
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
                <button class="action-chip" type="button" data-scroll-target="#replyFormSection">💬 Comment</button>
                <button class="action-chip" type="button" id="sharePostButton">↗ Share</button>
            </div>
            <div class="post-actions-meta">
                <span class="reaction-count-chip" id="reactionCountChip">
                    <img src="<?php echo htmlspecialchars($reaction_assets['like']); ?>" alt="Reactions">
                    <span><strong id="reactionTotal"><?php echo $total_reactions; ?></strong> reactions</span>
                </span>
                <span class="post-date-chip">Posted <?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
            </div>
        </div>

        <!-- Replies Section -->
        <div class="replies-section">
            <div class="replies-title"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>Replies (<?php echo count($replies); ?>)</div>

            <?php if (count($replies) > 0): ?>
                <?php foreach ($replies as $reply): ?>
                    <?php
                        $reply_author = !empty($reply['is_anonymous'])
                            ? get_anonymous_display_name($reply['user_id'])
                            : $reply['username'];
                    ?>
                    <div class="reply-item">
                        <div class="reply-meta">
                            <span class="reply-author"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 4px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg><?php echo htmlspecialchars($reply_author); ?></span>
                            <span class="reply-date"><?php echo date('M j, Y \a\t g:i A', strtotime($reply['created_at'])); ?></span>
                        </div>
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
        <div class="new-reply-section" id="replyFormSection">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem;">
                Add Your Support
            </h3>
            <form method="POST" action="">
                <div class="reply-form-group">
                    <textarea id="replyForm" name="reply_content" placeholder="Share your thoughts, experiences, or advice..." required></textarea>
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const reactionWrapper = document.getElementById('reactionWrapper');
            const reactionPopup = document.getElementById('reactionPopup');
            const reactionTrigger = document.getElementById('reactionTrigger');
            const activeReactionIcon = document.getElementById('activeReactionIcon');
            const activeReactionLabel = document.getElementById('activeReactionLabel');
            const reactionTotalEl = document.getElementById('reactionTotal');
            const sharePostButton = document.getElementById('sharePostButton');
            const scrollButtons = document.querySelectorAll('[data-scroll-target]');

            const reactionAssets = <?php echo json_encode($reaction_assets); ?>;
            const reactionLabels = <?php echo json_encode(array_combine($reaction_types, array_map('ucfirst', $reaction_types))); ?>;
            let reactionCounts = <?php echo json_encode($reaction_counts); ?>;
            let userReaction = <?php echo $user_reaction ? json_encode($user_reaction) : 'null'; ?>;
            const postId = <?php echo $post_id; ?>;

            const getTotalReactions = () => Object.values(reactionCounts).reduce((total, value) => total + parseInt(value || 0, 10), 0);

            const setActiveReaction = (type) => {
                const fallback = 'like';
                const safeType = reactionAssets[type] ? type : fallback;
                activeReactionIcon.src = reactionAssets[safeType];
                activeReactionLabel.textContent = reactionLabels[safeType] || 'Like';
                reactionTotalEl.textContent = getTotalReactions();
            };

            const showPopup = () => {
                reactionWrapper.classList.add('show-popup');
                reactionTrigger.setAttribute('aria-expanded', 'true');
            };

            const hidePopup = () => {
                reactionWrapper.classList.remove('show-popup');
                reactionTrigger.setAttribute('aria-expanded', 'false');
            };

            // Desktop hover behavior
            reactionTrigger.addEventListener('mouseenter', showPopup);
            reactionWrapper.addEventListener('mouseleave', hidePopup);
            reactionTrigger.addEventListener('focus', showPopup);
            reactionTrigger.addEventListener('blur', hidePopup);

            // Mobile long-press behavior
            let pressTimer;
            reactionTrigger.addEventListener('touchstart', () => {
                pressTimer = setTimeout(showPopup, 260);
            });
            reactionTrigger.addEventListener('touchend', () => {
                clearTimeout(pressTimer);
                setTimeout(hidePopup, 240);
            });

            // Quick tap defaults to Like (LinkedIn behavior)
            reactionTrigger.addEventListener('click', () => {
                if (!reactionWrapper.classList.contains('show-popup')) {
                    sendReaction(userReaction || 'like');
                }
            });

            // Reaction selection
            reactionPopup.querySelectorAll('.reaction-option').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const selected = btn.getAttribute('data-reaction');
                    sendReaction(selected);
                    hidePopup();
                });
            });

            const sendReaction = async (reactionType) => {
                try {
                    const response = await fetch('forum_reaction_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            post_id: postId,
                            reaction_type: reactionType,
                        }),
                    });

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.message || 'Unable to update reaction');
                    }

                    reactionCounts = result.counts || reactionCounts;
                    userReaction = result.reaction || reactionType;
                    reactionTotalEl.textContent = result.total_reactions ?? getTotalReactions();
                    setActiveReaction(userReaction);
                } catch (error) {
                    console.error(error);
                    alert('Unable to save your reaction right now. Please try again.');
                }
            };

            // Initialize view
            setActiveReaction(userReaction || 'like');

            // Scroll to comment/reply
            scrollButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const target = document.querySelector(btn.getAttribute('data-scroll-target'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });

            // Share / copy link
            sharePostButton?.addEventListener('click', async () => {
                const link = window.location.href;
                try {
                    await navigator.clipboard.writeText(link);
                    sharePostButton.textContent = 'Link copied!';
                    setTimeout(() => {
                        sharePostButton.textContent = '↗ Share';
                    }, 1500);
                } catch (err) {
                    console.error(err);
                    window.prompt('Copy this link', link);
                }
            });
        });
    </script>
</body>
</html>
