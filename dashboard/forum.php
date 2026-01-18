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
    
    if (!empty($title) && !empty($category) && !empty($content)) {
        $insert_stmt = $conn->prepare("
            INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status)
            VALUES (?, ?, ?, ?, 1, 'published')
        ");
        $insert_stmt->bind_param("isss", $user_id, $category, $title, $content);
        if ($insert_stmt->execute()) {
            $success_message = "✓ Post created successfully!";
            // Award points
            $points_stmt = $conn->prepare("UPDATE user_points SET total_points = total_points + 20 WHERE user_id = ?");
            $points_stmt->bind_param("i", $user_id);
            $points_stmt->execute();
            $points_stmt->close();
        }
        $insert_stmt->close();
    }
}

// Get selected category
$selected_category = sanitize_input($_GET['category'] ?? '');

// Get posts
$query = "SELECT fp.post_id, fp.title, fp.category, fp.view_count, fp.reply_count, fp.created_at, 
                 u.username
          FROM forum_posts fp
          JOIN users u ON fp.user_id = u.user_id
          WHERE fp.status = 'published'";

if (!empty($selected_category)) {
    $query .= " AND fp.category = '$selected_category'";
}

$query .= " ORDER BY fp.created_at DESC LIMIT 50";

$posts_result = $conn->query($query);
$posts = [];
while ($row = $posts_result->fetch_assoc()) {
    $posts[] = $row;
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
            border: 2px solid var(--light-gray);
            background: white;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--transition-fast);
            font-weight: 500;
        }
        <?php // Updated by Shuvo - END ?>

        .category-btn:hover,
        .category-btn.active {
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
            font-weight: 600;
            transition: all var(--transition-fast);
        }

        .new-post-btn:hover {
            background: var(--primary-dark);
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
            background: white;
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
            display: inline-block;
            background: rgba(107, 155, 209, 0.15);
            color: var(--primary-color);
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .post-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .post-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .post-stat {
            display: flex;
            gap: 0.25rem;
            align-items: center;
        }

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
                    <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">💬 Community Forum</h2>
                    <div class="top-bar-right">
                        <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                            🔔 Notifications
                        </a>
                    </div>
                </div>
            
                <div class="content-area">
    <div class="forum-container">
        <!-- Header -->
        <div class="forum-header">
            <h1>💬 Community Forum</h1>
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
                    <div class="post-card" onclick="location.href='forum_view.php?post_id=<?php echo $post['post_id']; ?>'">
                        <div class="post-category"><?php echo htmlspecialchars($post['category']); ?></div>
                        <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                        <div class="post-meta">
                            <span class="post-stat">👤 <?php echo htmlspecialchars($post['username']); ?></span>
                            <span class="post-stat">👁️ <?php echo $post['view_count']; ?> views</span>
                            <span class="post-stat">💬 <?php echo $post['reply_count']; ?> replies</span>
                            <span class="post-stat">📅 <?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                    <p style="font-size: 2rem; margin-bottom: 1rem;">💭</p>
                    <p>No posts yet in this category. Be the first to share!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navigation Buttons -->
        <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
            <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
            <a href="mood_tracker.php" class="btn btn-secondary">Mood Tracker</a>
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

    <script>
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

        // Auto-hide success message
        setTimeout(() => {
            document.querySelector('.success-alert.show')?.classList.remove('show');
        }, 3000);
    </script>
</body>
</html>
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
