<?php
/**
 * Safe Space - Blog
 * Category-based blog posts (no anonymous posting)
 */

require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

// Blog categories (must choose one)
$blog_categories = [
    'Anxiety & Worry',
    'Depression & Mood',
    'Stress & Burnout',
    'Self-Care & Habits',
    'Mindfulness & Meditation',
    'Therapy & Counseling',
    'Medication & Treatment',
    'Relationships & Family',
    'Sleep & Lifestyle',
    'Recovery Stories',
    'Kids & Teen Mental Health',
    'Professional Insights',
];

// Filters (includes a special filter for professional authors)
$filters = array_merge(['All', 'Professional'], $blog_categories);
// Updated by Shuvo - START
// NOTE: Do not use sanitize_input() for GET category; it HTML-escapes (e.g., '&' -> '&amp;')
// which breaks matching for categories like "Anxiety & Worry".
$selected = trim((string)($_GET['category'] ?? 'All'));

// Search query (title/content/author name). Keep raw text; escape only on output.
$search_query = trim((string)($_GET['q'] ?? ''));
if ($search_query !== '') {
    $search_query = preg_replace('/\s+/', ' ', $search_query);
    $search_query = function_exists('mb_substr') ? mb_substr($search_query, 0, 80) : substr($search_query, 0, 80);
}
// Updated by Shuvo - END
if (!in_array($selected, $filters, true)) {
    $selected = 'All';
}

// Determine if current user is a professional (for tag)
$me_stmt = $conn->prepare('SELECT user_type FROM users WHERE user_id = ? LIMIT 1');
$me_stmt->bind_param('i', $user_id);
$me_stmt->execute();
$me = $me_stmt->get_result()->fetch_assoc();
$me_stmt->close();
$me_is_professional = ($me && ($me['user_type'] ?? '') === 'professional');

// Reactions (feed + blog view should share the same types)
$reaction_types = ['like', 'celebrate', 'support', 'love', 'insightful', 'curious'];
$reaction_assets = [
    'like' => '../images/reactions/like.png',
    'celebrate' => '../images/reactions/Linkedin-Celebrate-Icon-ClappingHands500.png',
    'support' => '../images/reactions/Linkedin-Support-Icon-HeartinHand500.png',
    'love' => '../images/reactions/Linkedin-Love-Icon-Heart500.png',
    'insightful' => '../images/reactions/Linkedin-Insightful-Icon-Lamp500.png',
    'curious' => '../images/reactions/Linkedin-Curious-Icon-PurpleSmiley500.png',
];

// Handle new blog post
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_blog'])) {
    $title = sanitize_input($_POST['blog_title'] ?? '');
    $category = sanitize_input($_POST['blog_category'] ?? '');
    $content = sanitize_input($_POST['blog_content'] ?? '');

    if ($title === '' || $category === '' || $content === '') {
        $error_message = 'Please fill in all fields.';
    } elseif (!in_array($category, $blog_categories, true)) {
        $error_message = 'Please choose a valid category.';
    } else {
        $is_professional_post = $me_is_professional ? 1 : 0;
        $status = 'published';

        if ($me_is_professional) {
            // Professional blogs are subject to admin review (reuse existing draft workflow).
            $status = 'draft';

            $content = ensure_professional_disclaimer($content);
            if (professional_content_has_prohibited_claims($content)) {
                $error_message = 'Professional content cannot include diagnosis or prescription claims. Please rephrase.';
            }
            if (content_has_crisis_keywords($content)) {
                add_notification((int)$user_id, 'warning', 'Crisis Support', 'If you or someone else is in immediate danger, call your local emergency number. If you are thinking about self-harm, please contact a local crisis hotline right now.');
            }
        }

        if ($error_message === '') {
            $insert_stmt = $conn->prepare(
                "INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insert_stmt->bind_param('isssis', $user_id, $category, $title, $content, $is_professional_post, $status);
            if ($insert_stmt->execute()) {
                $success_message = $me_is_professional ? '✓ Expert content submitted for review.' : '✓ Blog post published!';
            } else {
                $error_message = 'Unable to publish your post.';
            }
            $insert_stmt->close();
        }
    }
}

// Get posts
$sql = "
    SELECT bp.blog_id, bp.title, bp.category, bp.content, bp.view_count, bp.comment_count, bp.created_at,
           bp.is_professional_post,
           u.username, u.user_type, u.full_name AS user_full_name,
           p.full_name AS professional_full_name,
           p.specialization AS professional_specialization, p.verification_status AS professional_verification_status,
           (SELECT COUNT(*) FROM blog_post_reactions bpr WHERE bpr.blog_id = bp.blog_id) AS total_reactions,
           (SELECT reaction_type FROM blog_post_reactions bpr2 WHERE bpr2.blog_id = bp.blog_id AND bpr2.user_id = ? LIMIT 1) AS my_reaction
    FROM blog_posts bp
    JOIN users u ON bp.user_id = u.user_id
    LEFT JOIN professionals p ON p.user_id = u.user_id
    WHERE bp.status = 'published'
";

$params = [$user_id];
$types = 'i';

if ($selected !== 'All' && $selected !== 'Professional') {
    $sql .= " AND bp.category = ?";
    $types .= 's';
    $params[] = $selected;
} elseif ($selected === 'Professional') {
    $sql .= " AND u.user_type = 'professional'";
}

// Updated by Shuvo - START
// Search across title/content + author/professional name (full or partial)
if ($search_query !== '') {
    $sql .= " AND (bp.title LIKE ? OR bp.content LIKE ? OR u.username LIKE ? OR COALESCE(u.full_name,'') LIKE ? OR COALESCE(p.full_name,'') LIKE ?)";
    $like = '%' . $search_query . '%';
    $types .= 'sssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
// Updated by Shuvo - END

if ($selected === 'All') {
    $sql .= ' ORDER BY bp.is_professional_post DESC, bp.created_at DESC LIMIT 50';
} else {
    $sql .= ' ORDER BY bp.created_at DESC LIMIT 50';
}

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$posts = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Updated by Shuvo - START
// AJAX partial: return just the post list HTML for smooth filtering (no full page reload)
$__ss_is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($__ss_is_ajax && (string)($_GET['ajax'] ?? '') === '1') {
    require __DIR__ . '/includes/blog_post_list.php';
    exit;
}
// Updated by Shuvo - END

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .blog-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .blog-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .blog-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filters {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            text-decoration: none;
            padding: 8px 16px;
            border: 2px solid var(--border-soft, #D8E2DD);
            background: var(--bg-card, #F8F9F7);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--transition-fast);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .filter-btn:hover,
        .filter-btn.active {
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
            font-weight: 700;
        }

        /* Updated by Shuvo - START */
        .blog-nav-left {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            flex: 1;
            min-width: 280px;
        }

        .blog-search {
            width: 100%;
            max-width: 100%;
            min-height: 44px;
            padding: 14px 16px;
            border-radius: 14px;
            border: 2px solid var(--border-soft, #D8E2DD);
            background: #FFFFFF;
            font-weight: 800;
            font-size: 1.08rem;
            line-height: 1.15;
            box-sizing: border-box;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
        }

        .blog-search::placeholder {
            color: rgba(36, 59, 59, 0.55);
            font-weight: 700;
        }

        .blog-search:focus {
            outline: none;
            border-color: var(--accent-primary, #7FAFA3);
            box-shadow: 0 0 0 3px rgba(127, 175, 163, 0.18), 0 10px 22px rgba(0, 0, 0, 0.08);
        }
        /* Updated by Shuvo - END */

        .post-list {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        /* Facebook-like post card */
        .post-card {
            background: var(--bg-card, #F8F9F7);
            border: 1px solid var(--border-soft, #D8E2DD);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .post-open {
            display: block;
            padding: 1.25rem 1.25rem 0.75rem;
            text-decoration: none;
            color: inherit;
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

        .post-title {
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

        .feed-reaction {
            position: relative;
        }

        .reaction-trigger {
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

        .reaction-trigger img {
            width: 20px;
            height: 20px;
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

        .reaction-option img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .feed-comment {
            padding: 0 1.25rem 1.25rem;
        }

        .feed-comment-inner {
            border-top: 1px solid var(--border-soft, #D8E2DD);
            padding-top: 1rem;
            display: grid;
            gap: 10px;
        }

        .feed-comment textarea {
            width: 100%;
            min-height: 90px;
            resize: vertical;
            padding: 10px 12px;
            border-radius: 12px;
            border: 2px solid var(--border-soft, #D8E2DD);
            font-family: inherit;
            font-size: 1rem;
            background: var(--bg-card, #F8F9F7);
        }

        .feed-comment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .feed-comment-actions button {
            border-radius: 12px;
            padding: 10px 12px;
            font-weight: 900;
            border: 1px solid var(--border-soft, #D8E2DD);
            background: var(--bg-card, #F8F9F7);
            cursor: pointer;
        }

        .feed-comment-actions .btn-primary-mini {
            background: var(--accent-primary, #7FAFA3);
            border-color: var(--accent-primary, #7FAFA3);
            color: #fff;
        }

        .toast {
            position: fixed;
            right: 20px;
            bottom: 20px;
            padding: 12px 14px;
            background: rgba(36, 59, 59, 0.92);
            color: #fff;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            display: none;
            z-index: 2000;
            font-weight: 800;
        }

        .toast.show { display: block; }

        .alert {
            border-radius: var(--radius-sm);
            padding: 1rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .alert.success {
            background: rgba(111, 207, 151, 0.15);
            color: #2d7a4d;
            border-left: 4px solid var(--success);
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
            border-left: 4px solid var(--error);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show { display: flex; }

        .modal-dialog {
            background: var(--bg-card, #F8F9F7);
            border-radius: var(--radius-lg);
            padding: 2rem;
            max-width: 700px;
            width: 92%;
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

        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 1rem;
        }
        .form-group textarea {
            min-height: 180px;
            resize: vertical;
        }

        @media (max-width: 768px) {
            .blog-nav {
                flex-direction: column;
                align-items: stretch;
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
                    Blog
                </h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;">
                        <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Notifications
                    </a>
                </div>
            </div>

            <div class="content-area">
                <div class="blog-container">
                    <div class="blog-header">
                        <h1 style="margin: 0 0 0.5rem; font-size: 2rem;">Mental Health Blog</h1>
                        <p style="margin: 0; opacity: 0.92;">Stories, advice, and professional insights — categorized for easy reading.</p>
                    </div>

                    <?php if ($success_message): ?>
                        <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>

                    <?php // Updated by Shuvo - START ?>
                    <div class="blog-nav">
                        <div class="blog-nav-left">
                            <input
                                type="search"
                                id="blogSearchInput"
                                class="blog-search"
                                placeholder="Search blogs by title, keywords, or author…"
                                aria-label="Search blogs"
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                autocomplete="off"
                            >

                            <div class="filters" id="blogFilters" aria-label="Blog filters">
                                <?php foreach ($filters as $cat): ?>
                                    <?php
                                        $url = $cat === 'All' ? 'blogs.php' : 'blogs.php?category=' . urlencode($cat);
                                        $active = $selected === $cat || ($cat === 'All' && $selected === 'All');
                                    ?>
                                    <a class="filter-btn <?php echo $active ? 'active' : ''; ?>"
                                       href="<?php echo $url; ?>"
                                       data-category="<?php echo htmlspecialchars($cat); ?>"
                                       aria-current="<?php echo $active ? 'true' : 'false'; ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <button class="new-post-btn" onclick="openNewBlogModal()">+ New Blog</button>
                        </div>
                    </div>
                    <?php // Updated by Shuvo - END ?>

                    <!-- Updated by Shuvo - START -->
                    <div class="post-list" id="blogPostList">
                        <?php require __DIR__ . '/includes/blog_post_list.php'; ?>
                    </div>
                    <!-- Updated by Shuvo - END -->

                    <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
                        <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
                        <a href="profile.php" class="btn btn-secondary">My Profile</a>
                    </div>
                </div>

                <!-- New Blog Modal -->
                <div class="modal-overlay" id="newBlogModal">
                    <div class="modal-dialog">
                        <div class="modal-header">
                            <h2 style="margin: 0;">Create a Blog Post</h2>
                            <button class="modal-close" onclick="closeNewBlogModal()">✕</button>
                        </div>

                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Title *</label>
                                <input type="text" name="blog_title" required placeholder="Give your post a clear title">
                            </div>

                            <div class="form-group">
                                <label>Category *</label>
                                <select name="blog_category" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($blog_categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 6px;">
                                    Anonymous posting is disabled for blogs.
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Content *</label>
                                <textarea name="blog_content" required placeholder="Write your blog post..."></textarea>
                            </div>

                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <button type="submit" name="create_blog" class="btn btn-primary">Publish</button>
                                <button type="button" class="btn btn-secondary" onclick="closeNewBlogModal()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function openNewBlogModal() {
                        document.getElementById('newBlogModal').classList.add('show');
                    }

                    function closeNewBlogModal() {
                        document.getElementById('newBlogModal').classList.remove('show');
                    }

                    document.getElementById('newBlogModal')?.addEventListener('click', function(e) {
                        if (e.target === this) {
                            closeNewBlogModal();
                        }
                    });

                    (function initFeedActions() {
                        const toast = document.createElement('div');
                        toast.className = 'toast';
                        document.body.appendChild(toast);

                        function showToast(message) {
                            toast.textContent = message;
                            toast.classList.add('show');
                            window.clearTimeout(showToast._t);
                            showToast._t = window.setTimeout(() => toast.classList.remove('show'), 1800);
                        }

                        async function copyToClipboard(text) {
                            if (navigator.clipboard?.writeText) {
                                await navigator.clipboard.writeText(text);
                                return;
                            }
                            const el = document.createElement('textarea');
                            el.value = text;
                            el.style.position = 'fixed';
                            el.style.top = '-9999px';
                            document.body.appendChild(el);
                            el.select();
                            document.execCommand('copy');
                            document.body.removeChild(el);
                        }

                        // Updated by Shuvo - START
                        // Bind actions in a container (used on first load and after AJAX filter refresh)
                        function bindFeedActions(container) {
                            // Reactions
                            container.querySelectorAll('.feed-reaction').forEach(wrapper => {
                                if (wrapper.dataset.bound === '1') return;
                                wrapper.dataset.bound = '1';

                                const blogId = parseInt(wrapper.dataset.blogId, 10);
                                const trigger = wrapper.querySelector('.reaction-trigger');
                                const popup = wrapper.querySelector('.reaction-popup');
                                const iconEl = wrapper.querySelector('.active-reaction-icon');
                                const labelEl = wrapper.querySelector('.active-reaction-label');
                                const reactionTotalEl = wrapper.closest('.post-card')?.querySelector('[data-reaction-total]');

                                popup?.querySelectorAll('.reaction-option').forEach(btn => {
                                    btn.addEventListener('click', async (e) => {
                                        e.preventDefault();
                                        const reactionType = btn.dataset.reaction;

                                        try {
                                            const response = await fetch('blog_reaction_handler.php', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest',
                                                },
                                                body: JSON.stringify({ blog_id: blogId, reaction_type: reactionType }),
                                            });
                                            const data = await response.json();
                                            if (!data.success) throw new Error(data.message || 'Reaction failed');

                                            const chosenIcon = btn.querySelector('img')?.getAttribute('src');
                                            if (chosenIcon) iconEl?.setAttribute('src', chosenIcon);
                                            if (labelEl) {
                                                labelEl.textContent = reactionType ? (reactionType.charAt(0).toUpperCase() + reactionType.slice(1)) : 'React';
                                            }
                                            if (reactionTotalEl) reactionTotalEl.textContent = String(data.total_reactions ?? 0);
                                            showToast('Reaction saved');
                                        } catch (err) {
                                            console.error(err);
                                            showToast('Could not react');
                                        }
                                    });
                                });
                            });

                            // Comments
                            container.querySelectorAll('.post-card').forEach(card => {
                                if (card.dataset.boundComments === '1') return;
                                card.dataset.boundComments = '1';

                                const commentBtn = card.querySelector('.post-action-comment');
                                const commentBox = card.querySelector('.feed-comment');
                                const cancelBtn = card.querySelector('.btn-cancel-mini');
                                const postBtn = card.querySelector('.btn-primary-mini');
                                const textarea = card.querySelector('.feed-comment-text');
                                const commentCountEl = card.querySelector('[data-comment-count]');
                                const blogId = parseInt(card.querySelector('.feed-reaction')?.dataset.blogId || '0', 10);

                                function openBox() {
                                    if (!commentBox) return;
                                    commentBox.hidden = false;
                                    textarea?.focus();
                                }

                                function closeBox() {
                                    if (!commentBox) return;
                                    commentBox.hidden = true;
                                    if (textarea) textarea.value = '';
                                }

                                commentBtn?.addEventListener('click', () => {
                                    if (!commentBox) return;
                                    commentBox.hidden ? openBox() : closeBox();
                                });

                                cancelBtn?.addEventListener('click', closeBox);

                                postBtn?.addEventListener('click', async () => {
                                    const content = (textarea?.value || '').trim();
                                    if (!content) {
                                        showToast('Write a comment first');
                                        return;
                                    }

                                    try {
                                        postBtn.disabled = true;
                                        const response = await fetch('blog_comment_handler.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-Requested-With': 'XMLHttpRequest',
                                            },
                                            body: JSON.stringify({ blog_id: blogId, content }),
                                        });
                                        const data = await response.json();
                                        if (!data.success) throw new Error(data.message || 'Comment failed');

                                        if (commentCountEl && typeof data.comment_count !== 'undefined') {
                                            commentCountEl.textContent = String(data.comment_count);
                                        }

                                        showToast('Comment posted');
                                        closeBox();
                                    } catch (err) {
                                        console.error(err);
                                        showToast('Could not post comment');
                                    } finally {
                                        postBtn.disabled = false;
                                    }
                                });
                            });

                            // Share (copy link)
                            container.querySelectorAll('.post-action-share').forEach(btn => {
                                if (btn.dataset.bound === '1') return;
                                btn.dataset.bound = '1';
                                btn.addEventListener('click', async () => {
                                    const blogId = btn.dataset.blogId;
                                    const url = new URL('blog_view.php?blog_id=' + encodeURIComponent(blogId), window.location.href).href;
                                    try {
                                        await copyToClipboard(url);
                                        showToast('Link copied');
                                    } catch (err) {
                                        console.error(err);
                                        showToast('Could not copy link');
                                    }
                                });
                            });
                        }

                        const blogFilters = document.getElementById('blogFilters');
                        const blogPostList = document.getElementById('blogPostList');
                        const blogSearchInput = document.getElementById('blogSearchInput');

                        function setActiveFilter(category) {
                            blogFilters?.querySelectorAll('.filter-btn').forEach(a => {
                                const isActive = (a.dataset.category || '') === category;
                                a.classList.toggle('active', isActive);
                                a.setAttribute('aria-current', isActive ? 'true' : 'false');
                            });
                        }

                        function getActiveCategory() {
                            const active = blogFilters?.querySelector('.filter-btn.active');
                            return (active?.dataset.category || 'All');
                        }

                        function buildListUrl(category, query) {
                            const url = new URL('blogs.php', window.location.href);
                            if (category && category !== 'All') {
                                url.searchParams.set('category', category);
                            }
                            if (query && query.trim() !== '') {
                                url.searchParams.set('q', query.trim());
                            }
                            return url.pathname + (url.search ? url.search : '');
                        }

                        async function loadFiltered(url) {
                            if (!blogPostList) return;
                            blogPostList.style.opacity = '0.6';
                            try {
                                const ajaxUrl = new URL(url, window.location.href);
                                ajaxUrl.searchParams.set('ajax', '1');
                                const res = await fetch(ajaxUrl.toString(), {
                                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                });
                                const html = await res.text();
                                blogPostList.innerHTML = html;
                                bindFeedActions(blogPostList);
                            } finally {
                                blogPostList.style.opacity = '1';
                            }
                        }

                        blogFilters?.addEventListener('click', async (e) => {
                            const link = e.target.closest('a.filter-btn');
                            if (!link) return;
                            e.preventDefault();

                            const category = link.dataset.category || 'All';
                            const q = (blogSearchInput?.value || '').trim();
                            const url = buildListUrl(category, q);
                            setActiveFilter(category);
                            history.pushState({ url }, '', url);
                            await loadFiltered(url);
                        });

                        // Debounced search that works with category filters
                        let searchTimer = null;
                        blogSearchInput?.addEventListener('input', () => {
                            if (searchTimer) window.clearTimeout(searchTimer);
                            searchTimer = window.setTimeout(async () => {
                                const category = getActiveCategory();
                                const q = (blogSearchInput.value || '').trim();
                                const url = buildListUrl(category, q);
                                history.replaceState({ url }, '', url);
                                await loadFiltered(url);
                            }, 280);
                        });

                        window.addEventListener('popstate', async () => {
                            // Back/forward should reflect the URL and refresh the list
                            const url = window.location.href;
                            const u = new URL(url);
                            const category = u.searchParams.get('category') || 'All';
                            const q = u.searchParams.get('q') || '';
                            setActiveFilter(category);
                            if (blogSearchInput) blogSearchInput.value = q;
                            await loadFiltered(url);
                        });

                        // Initial bind
                        bindFeedActions(document);
                        // Updated by Shuvo - END
                    })();
                </script>
            </div>
        </main>
    </div>
</body>
</html>
