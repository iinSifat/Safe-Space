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
$selected = sanitize_input($_GET['category'] ?? 'All');
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
        $insert_stmt = $conn->prepare(
            "INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status) VALUES (?, ?, ?, ?, ?, 'published')"
        );
        $insert_stmt->bind_param('isssi', $user_id, $category, $title, $content, $is_professional_post);
        if ($insert_stmt->execute()) {
            $success_message = '✓ Blog post published!';
        } else {
            $error_message = 'Unable to publish your post.';
        }
        $insert_stmt->close();
    }
}

// Get posts
$sql = "
    SELECT bp.blog_id, bp.title, bp.category, bp.view_count, bp.comment_count, bp.created_at,
           bp.is_professional_post,
           u.username, u.user_type
    FROM blog_posts bp
    JOIN users u ON bp.user_id = u.user_id
    WHERE bp.status = 'published'
";

$params = [];
$types = '';

if ($selected !== 'All' && $selected !== 'Professional') {
    $sql .= " AND bp.category = ?";
    $types .= 's';
    $params[] = $selected;
} elseif ($selected === 'Professional') {
    $sql .= " AND u.user_type = 'professional'";
}

$sql .= ' ORDER BY bp.created_at DESC LIMIT 50';

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$posts = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
            border: 2px solid var(--light-gray);
            background: white;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--transition-fast);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .new-post-btn {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 700;
        }

        .post-list {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .post-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-normal);
            cursor: pointer;
        }

        .post-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .post-category {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(107, 155, 209, 0.15);
            color: var(--primary-color);
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            width: fit-content;
        }

        .pro-badge {
            background: rgba(123, 93, 255, 0.14);
            color: var(--secondary-color);
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
        }

        .post-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .post-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.88rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }

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
            background: white;
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
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">🔔 Notifications</a>
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

                    <div class="blog-nav">
                        <div class="filters">
                            <?php foreach ($filters as $cat): ?>
                                <?php
                                    $url = $cat === 'All' ? 'blogs.php' : 'blogs.php?category=' . urlencode($cat);
                                    $active = $selected === $cat || ($cat === 'All' && $selected === 'All');
                                ?>
                                <a class="filter-btn <?php echo $active ? 'active' : ''; ?>" href="<?php echo $url; ?>">
                                    <?php echo htmlspecialchars($cat); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <button class="new-post-btn" onclick="openNewBlogModal()">+ New Blog</button>
                    </div>

                    <div class="post-list">
                        <?php if (count($posts) > 0): ?>
                            <?php foreach ($posts as $post): ?>
                                <div class="post-card" onclick="location.href='blog_view.php?blog_id=<?php echo (int)$post['blog_id']; ?>'">
                                    <div class="post-category">
                                        <span><?php echo htmlspecialchars($post['category']); ?></span>
                                        <?php if (($post['user_type'] ?? '') === 'professional'): ?>
                                            <span class="pro-badge">Professional</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                                    <div class="post-meta">
                                        <span>👤 <?php echo htmlspecialchars($post['username']); ?></span>
                                        <span>📅 <?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                        <span>👁️ <?php echo (int)$post['view_count']; ?> views</span>
                                        <span>💬 <?php echo (int)$post['comment_count']; ?> comments</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                                <p style="margin: 0;">No blog posts yet in this category.</p>
                            </div>
                        <?php endif; ?>
                    </div>

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
                </script>
            </div>
        </main>
    </div>
</body>
</html>
