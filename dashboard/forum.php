<?php
/**
 * Safe Space - Community Forum
 * Allows users to create posts and comment anonymously
 */

require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

$user_id = get_user_id();
$is_professional_user = function_exists('is_professional') && is_professional();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get categories
$categories = ['Anxiety', 'Depression', 'Stress', 'Relationships', 'Sleep', 'Work/School', 'Self-Care', 'General Support'];

// Handle new post
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $title = sanitize_input($_POST['post_title'] ?? '');
    $category = sanitize_input($_POST['post_category'] ?? '');
    $content = sanitize_input($_POST['post_content'] ?? '');

    $status = 'published';
    $pin_val = 0;

    if ($is_professional_user) {
        $raw = trim((string)($_POST['post_content'] ?? ''));

        // Controlled moderation commands without adding UI.
        if (stripos($raw, '[PIN]') === 0) {
            $raw = trim(substr($raw, 5));
            $pin_val = 1;
        }

        if (stripos($raw, '[FLAG]') === 0) {
            $raw = trim(substr($raw, 6));
            $status = 'flagged';
        }

        $content = sanitize_input($raw);
        $content = ensure_professional_disclaimer($content);

        // Enforce no diagnosis/prescription language.
        if (professional_content_has_prohibited_claims($content)) {
            $status = 'flagged';
        }

        if (content_has_crisis_keywords($content)) {
            add_notification((int)$user_id, 'warning', 'Crisis Support', 'If you or someone else is in immediate danger, call your local emergency number. If you are thinking about self-harm, please contact a local crisis hotline right now.');
        }
    }
    
    if (!empty($title) && !empty($category) && !empty($content)) {
        $insert_stmt = $conn->prepare("
            INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, is_pinned)
            VALUES (?, ?, ?, ?, 1, ?, ?)
        ");
        $insert_stmt->bind_param("issssi", $user_id, $category, $title, $content, $status, $pin_val);
        if ($insert_stmt->execute()) {
                $success_message = $is_professional_user
                    ? "Professional response submitted successfully!"
                    : "Post created successfully!";

                // Gamification disabled for professionals
                if (!$is_professional_user) {
                    $points_stmt = $conn->prepare("UPDATE user_points SET total_points = total_points + 20 WHERE user_id = ?");
                    $points_stmt->bind_param("i", $user_id);
                    $points_stmt->execute();
                    $points_stmt->close();
                } elseif ($status === 'flagged') {
                    add_notification((int)$user_id, 'info', 'Post held for review', 'Your professional post was held from public view due to restricted language. Please revise and try again.');
                }
        }
        $insert_stmt->close();
    }
}

// Get selected category (validate against known list)
$selected_category = trim((string)($_GET['category'] ?? ''));
if ($selected_category !== '' && !in_array($selected_category, $categories, true)) {
    $selected_category = '';
}

// Reactions (share types with blog/forum detail)
$reaction_types = ['like', 'celebrate', 'support', 'love', 'insightful', 'curious'];
$reaction_assets = [
    'like' => '../images/reactions/like.png',
    'celebrate' => '../images/reactions/Linkedin-Celebrate-Icon-ClappingHands500.png',
    'support' => '../images/reactions/Linkedin-Support-Icon-HeartinHand500.png',
    'love' => '../images/reactions/Linkedin-Love-Icon-Heart500.png',
    'insightful' => '../images/reactions/Linkedin-Insightful-Icon-Lamp500.png',
    'curious' => '../images/reactions/Linkedin-Curious-Icon-PurpleSmiley500.png',
];

// Get posts (blog-card style preview + reactions)
$sql = "
    SELECT fp.post_id, fp.user_id, fp.title, fp.category, fp.content, fp.view_count, fp.reply_count, fp.created_at,
           u.username, u.is_anonymous, u.user_type,
           p.full_name AS professional_full_name, p.specialization AS professional_specialization, p.verification_status AS professional_verification_status,
           (SELECT COUNT(*) FROM forum_post_reactions fpr WHERE fpr.post_id = fp.post_id) AS total_reactions,
           (SELECT reaction_type FROM forum_post_reactions fpr2 WHERE fpr2.post_id = fp.post_id AND fpr2.user_id = ? LIMIT 1) AS my_reaction
    FROM forum_posts fp
    JOIN users u ON fp.user_id = u.user_id
    LEFT JOIN professionals p ON p.user_id = u.user_id
    WHERE fp.status = 'published'
";

$params = [$user_id];
$types = 'i';

if ($selected_category !== '') {
    $sql .= ' AND fp.category = ?';
    $params[] = $selected_category;
    $types .= 's';
}

$sql .= ' ORDER BY fp.is_pinned DESC, fp.created_at DESC LIMIT 50';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $posts = [];
} else {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
        <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .forum-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .forum-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .forum-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .forum-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .category-filters {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        <?php // Updated by Shuvo - START ?>
        .category-btn {
            text-decoration: none;
            padding: 8px 16px;
            border: 2px solid var(--border-soft, #D8E2DD);
            background: var(--bg-card, #F8F9F7);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--transition-fast);
            font-weight: 500;
        }
        <?php // Updated by Shuvo - END ?>

        .category-btn:hover,
        .category-btn.active {
            background: var(--accent-primary, #7FAFA3);
            border-color: var(--accent-primary, #7FAFA3);
            color: #FFFFFF;
        }

        .new-post-btn {
            padding: 10px 20px;
            background: var(--accent-primary, #7FAFA3);
            color: #FFFFFF;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 600;
            transition: all var(--transition-fast);
        }

        .new-post-btn:hover {
            background: var(--primary-dark, #6A9A8E);
        }

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
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 1rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }

        .post-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Blog-style post cards (match blog feed) */
        .post-card {
            background: var(--bg-card, #F8F9F7);
            border: 1px solid var(--border-soft, #D8E2DD);
            border-radius: 18px;
            padding: 16px;
            box-shadow: var(--shadow-sm);
            transition: transform var(--transition-fast), box-shadow var(--transition-fast);
        }
        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .post-open {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            color: inherit;
            padding: 0;
            cursor: pointer;
        }

        .post-header-row {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .post-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(127, 175, 163, 0.18);
            color: var(--text-primary);
            font-weight: 950;
            flex: 0 0 auto;
        }

        .post-head-text { flex: 1; min-width: 0; }

        .post-name-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .post-name { font-weight: 950; color: var(--text-primary); }

        .pro-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(123, 93, 255, 0.14);
            color: var(--secondary-color);
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 900;
            font-size: 0.8rem;
        }

        .post-sub {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            color: var(--text-secondary);
            margin-top: 4px;
            font-size: 0.92rem;
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

        .post-title {
            margin-top: 12px;
            font-size: 1.15rem;
            font-weight: 950;
            color: var(--text-primary);
        }

        .post-excerpt {
            margin-top: 8px;
            color: var(--text-secondary);
            line-height: 1.65;
            white-space: pre-wrap;
        }

        .post-stats {
            display: flex;
            gap: 14px;
            margin-top: 12px;
            color: var(--text-secondary);
            font-size: 0.92rem;
            flex-wrap: wrap;
        }

        .post-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            border-top: 1px solid var(--border-soft, #D8E2DD);
            margin-top: 12px;
            padding-top: 12px;
        }

        .post-action,
        .post-actions .reaction-trigger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-soft, #D8E2DD);
            background: var(--bg-card, #F8F9F7);
            border-radius: 999px;
            padding: 8px 12px;
            cursor: pointer;
            font-weight: 800;
        }

        .feed-reaction { position: relative; }
        .feed-reaction .reaction-trigger img { width: 22px; height: 22px; object-fit: contain; }

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
        .reaction-option:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: var(--shadow-sm);
        }
        .reaction-option img { width: 100%; height: 100%; object-fit: contain; }

        /* Post Details Overlay (DBMS-style effect, loads existing post page in iframe) */
        .post-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1000;
            opacity: 0;
            transition: opacity var(--transition-normal);
            backdrop-filter: blur(4px);
        }

        .post-modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
            overflow-y: auto;
        }

        .post-modal-content {
            background: var(--bg-card, #F8F9F7);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 60px rgba(12, 27, 51, 0.3);
            max-width: 900px;
            width: 92%;
            max-height: 92vh;
            overflow: hidden;
            animation: slideUp var(--transition-normal) ease;
            position: relative;
        }

        .post-modal-iframe {
            width: 100%;
            height: 92vh;
            border: 0;
            display: block;
            background: var(--bg-surface, #F3F5F2);
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .post-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--bg-card, #F8F9F7);
            border: 1px solid var(--border-soft, #D8E2DD);
            width: 40px;
            height: 40px;
            border-radius: 999px;
            font-size: 1.4rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color var(--transition-fast), box-shadow var(--transition-fast);
            z-index: 1001;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }

        .post-modal-close:hover {
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
        }

        @media (max-width: 768px) {
            .post-modal-content {
                width: 96%;
                max-height: 95vh;
                border-radius: var(--radius-md);
            }

            .post-modal-iframe {
                height: 95vh;
            }
        }

        /* legacy forum list styles removed in favor of blog-card */

        .success-alert {
            background: rgba(111, 207, 151, 0.15);
            color: #2d7a4d;
            padding: 1rem;
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--success);
            margin-bottom: 1rem;
            display: none;
        }

        .success-alert.show {
            display: block;
        }

        @media (max-width: 768px) {
            .modal-dialog {
                width: 95%;
                padding: 1.5rem;
            }

            .forum-nav {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
        <div class="dashboard-wrapper">
            <?php include 'includes/sidebar.php'; ?>
        
            <main class="main-content">
                <div class="top-bar">
                    <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>Community Forum</h2>
                    <div class="top-bar-right">
                        <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                            <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            Notifications
                        </a>
                    </div>
                </div>
            
                <div class="content-area">
    <div class="forum-container">
        <!-- Header -->
        <div class="forum-header">
            <h1><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 12px; vertical-align: middle;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>Community Forum</h1>
            <p>Anonymous, supportive discussions about mental health and wellness</p>
        </div>

        <!-- Navigation -->
        <div class="forum-nav">
            <div class="category-filters">
                <a href="forum.php" class="category-btn <?php echo empty($selected_category) ? 'active' : ''; ?>">All</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="forum.php?category=<?php echo urlencode($cat); ?>" 
                       class="category-btn <?php echo $selected_category === $cat ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <button class="new-post-btn" onclick="openNewPostModal()">+ New Post</button>
        </div>

        <!-- Success Message -->
        <div class="success-alert <?php echo !empty($success_message) ? 'show' : ''; ?>">
            <?php echo htmlspecialchars($success_message); ?>
        </div>

        <!-- Posts List -->
        <div class="post-list">
            <?php if (count($posts) > 0): ?>
                <?php foreach ($posts as $post): ?>
                    <?php
                        $is_post_professional = (($post['user_type'] ?? '') === 'professional');
                        $post_author = $is_post_professional
                            ? (!empty($post['professional_full_name']) ? $post['professional_full_name'] : ($post['username'] ?? 'User'))
                            : (!empty($post['is_anonymous']) ? get_anonymous_display_name((int)$post['user_id']) : ($post['username'] ?? 'User'));
                        $post_author_label = $is_post_professional
                            ? professional_authority_label(($post['professional_specialization'] ?? ''), ($post['professional_verification_status'] ?? ''))
                            : '';

                        $avatarLetter = strtoupper(substr((string)$post_author, 0, 1));
                        $rawContent = (string)($post['content'] ?? '');
                        $excerpt = function_exists('mb_substr') ? mb_substr($rawContent, 0, 180) : substr($rawContent, 0, 180);
                        $excerpt = trim($excerpt);
                        $hasMore = (function_exists('mb_strlen') ? mb_strlen($rawContent) : strlen($rawContent)) > (function_exists('mb_strlen') ? mb_strlen($excerpt) : strlen($excerpt));

                        $myReaction = !empty($post['my_reaction']) ? (string)$post['my_reaction'] : null;
                        $activeReaction = $myReaction && isset($reaction_assets[$myReaction]) ? $myReaction : 'like';
                        $activeReactionLabel = $myReaction ? ucfirst($myReaction) : 'React';
                        $reactionTotal = (int)($post['total_reactions'] ?? 0);
                    ?>
                    <div class="post-card">
                        <button type="button" class="post-open" data-open-post-id="<?php echo (int)$post['post_id']; ?>" aria-label="Open forum post">
                            <div class="post-header-row">
                                <div class="post-avatar" aria-hidden="true"><?php echo htmlspecialchars($avatarLetter); ?></div>
                                <div class="post-head-text">
                                    <div class="post-name-row">
                                        <span class="post-name"><?php echo htmlspecialchars((string)$post_author); ?></span>
                                        <?php if ($is_post_professional && $post_author_label !== ''): ?>
                                            <span class="pro-badge"><?php echo htmlspecialchars($post_author_label); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="post-sub">
                                        <span><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                        <span aria-hidden="true">·</span>
                                        <span class="category-chip"><?php echo htmlspecialchars((string)$post['category']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="post-title"><?php echo htmlspecialchars((string)$post['title']); ?></div>
                            <?php if ($excerpt !== ''): ?>
                                <p class="post-excerpt"><?php echo htmlspecialchars($excerpt); ?><?php echo $hasMore ? '…' : ''; ?></p>
                            <?php endif; ?>
                        </button>

                        <div class="post-stats">
                            <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> <?php echo (int)$post['view_count']; ?> views</span>
                            <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg> <strong data-reaction-total><?php echo $reactionTotal; ?></strong> reactions</span>
                            <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> <?php echo (int)$post['reply_count']; ?> replies</span>
                        </div>

                        <div class="post-actions" aria-label="Post actions">
                            <div class="feed-reaction" data-post-id="<?php echo (int)$post['post_id']; ?>">
                                <button type="button" class="reaction-trigger" aria-haspopup="true" aria-expanded="false">
                                    <img class="active-reaction-icon" src="<?php echo htmlspecialchars($reaction_assets[$activeReaction]); ?>" alt="Reaction">
                                    <span class="active-reaction-label"><?php echo htmlspecialchars($activeReactionLabel); ?></span>
                                </button>
                                <div class="reaction-popup" role="menu" aria-label="Choose a reaction">
                                    <?php foreach ($reaction_types as $reaction_type): ?>
                                        <button class="reaction-option" type="button" data-reaction="<?php echo $reaction_type; ?>" aria-label="React with <?php echo ucfirst($reaction_type); ?>">
                                            <img src="<?php echo htmlspecialchars($reaction_assets[$reaction_type]); ?>" alt="<?php echo ucfirst($reaction_type); ?>">
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <button type="button" class="post-action post-action-comment" data-comment-post-id="<?php echo (int)$post['post_id']; ?>">
                                <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                Comment
                            </button>

                            <button type="button" class="post-action post-action-share" data-share-post-id="<?php echo (int)$post['post_id']; ?>">↗ Share</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 1rem; display: block;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><circle cx="9" cy="10" r="1"/><circle cx="12" cy="10" r="1"/><circle cx="15" cy="10" r="1"/></svg>
                    <p>No posts yet in this category. Be the first to share!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navigation Buttons -->
        <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
            <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
            <?php if (!$is_professional_user): ?>
                <a href="mood_tracker.php" class="btn btn-secondary">Mood Tracker</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- New Post Modal -->
    <div class="modal-overlay" id="newPostModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h2>Create New Post</h2>
                <button class="modal-close" onclick="closeNewPostModal()">✕</button>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="post_title" required placeholder="What's on your mind?">
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <select name="post_category" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="post_content" required placeholder="Share your thoughts..."></textarea>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="create_post" class="btn btn-primary" style="flex: 1;">Post</button>
                    <button type="button" class="btn btn-secondary" onclick="closeNewPostModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Post Details Overlay Modal -->
    <div class="post-modal-overlay" id="postModalOverlay">
        <div class="post-modal-content" id="postModalContent">
            <button class="post-modal-close" type="button" onclick="closePostModal()">✕</button>
            <iframe class="post-modal-iframe" id="postModalFrame" title="Post details"></iframe>
        </div>
    </div>

    <script>
        function openPostModal(postId, hash) {
            const overlay = document.getElementById('postModalOverlay');
            const frame = document.getElementById('postModalFrame');

            const hashPart = hash ? ('#' + String(hash).replace(/^#/, '')) : '';
            frame.src = `forum_view.php?post_id=${postId}${hashPart}`;
            overlay.classList.add('active');
        }

        function closePostModal() {
            const overlay = document.getElementById('postModalOverlay');
            const frame = document.getElementById('postModalFrame');
            overlay.classList.remove('active');
            // Clear src so audio/video/requests stop when closing
            frame.src = '';
        }

        function openNewPostModal() {
            document.getElementById('newPostModal').classList.add('show');
        }

        function closeNewPostModal() {
            document.getElementById('newPostModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('newPostModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeNewPostModal();
            }
        });

        document.getElementById('postModalOverlay')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closePostModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePostModal();
            }
        });

        // Auto-hide success message
        setTimeout(() => {
            document.querySelector('.success-alert.show')?.classList.remove('show');
        }, 3000);

        // Blog-style card interactions
        document.querySelectorAll('[data-open-post-id]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const postId = parseInt(btn.getAttribute('data-open-post-id') || '0', 10);
                if (postId > 0) openPostModal(postId);
            });
            btn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const postId = parseInt(btn.getAttribute('data-open-post-id') || '0', 10);
                    if (postId > 0) openPostModal(postId);
                }
            });
        });

        // Comment opens detail modal and scrolls to form
        document.querySelectorAll('[data-comment-post-id]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const postId = parseInt(btn.getAttribute('data-comment-post-id') || '0', 10);
                if (postId > 0) openPostModal(postId, 'replyFormSection');
            });
        });

        // Share copies link to the post details page
        document.querySelectorAll('[data-share-post-id]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const postId = parseInt(btn.getAttribute('data-share-post-id') || '0', 10);
                if (postId <= 0) return;
                const link = `${window.location.origin}${window.location.pathname.replace(/forum\.php$/i, 'forum_view.php')}?post_id=${postId}`;
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

        // Reactions on list cards (same behavior as blog feed)
        const reactionAssets = <?php echo json_encode($reaction_assets); ?>;
        const reactionLabels = <?php echo json_encode(array_combine($reaction_types, array_map('ucfirst', $reaction_types))); ?>;

        async function sendForumReaction(wrapper, reactionType) {
            const postId = parseInt(wrapper?.dataset?.postId || '0', 10);
            if (postId <= 0) return;

            const payload = { post_id: postId, reaction_type: reactionType };
            const response = await fetch('forum_reaction_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!data?.success) throw new Error(data?.message || 'Reaction failed');

            const selected = data.reaction || reactionType;
            wrapper.querySelector('.active-reaction-icon').src = reactionAssets[selected] || reactionAssets.like;
            wrapper.querySelector('.active-reaction-label').textContent = reactionLabels[selected] || 'React';
            const totalEl = wrapper.closest('.post-card')?.querySelector('[data-reaction-total]');
            if (totalEl) totalEl.textContent = String(data.total_reactions ?? 0);
        }

        document.querySelectorAll('.feed-reaction').forEach((wrapper) => {
            const trigger = wrapper.querySelector('.reaction-trigger');
            const popup = wrapper.querySelector('.reaction-popup');

            trigger?.addEventListener('click', async (e) => {
                e.preventDefault();
                try {
                    await sendForumReaction(wrapper, 'like');
                } catch (err) {
                    console.error(err);
                }
            });

            popup?.querySelectorAll('.reaction-option').forEach((btn) => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const reactionType = btn.dataset.reaction;
                    try {
                        await sendForumReaction(wrapper, reactionType);
                    } catch (err) {
                        console.error(err);
                    }
                });
            });
        });
    </script>
</body>
</html>
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
