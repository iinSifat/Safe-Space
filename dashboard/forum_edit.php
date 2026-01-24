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
if ($post_id <= 0) {
    redirect('profile.php');
}

// Categories (keep in sync with forum.php)
$categories = ['Anxiety', 'Depression', 'Stress', 'Relationships', 'Sleep', 'Work/School', 'Self-Care', 'General Support'];

// Fetch post (must belong to current user)
$post_stmt = $conn->prepare("SELECT post_id, title, category, content, status FROM forum_posts WHERE post_id = ? AND user_id = ? AND status = 'published' LIMIT 1");
$post_stmt->bind_param('ii', $post_id, $user_id);
$post_stmt->execute();
$post = $post_stmt->get_result()->fetch_assoc();
$post_stmt->close();

if (!$post) {
    redirect('profile.php');
}

$update_message = '';
$update_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_forum_post'])) {
    $title = sanitize_input($_POST['post_title'] ?? '');
    $category = sanitize_input($_POST['post_category'] ?? '');
    $content = sanitize_input($_POST['post_content'] ?? '');

    if ($title === '' || $category === '' || $content === '') {
        $update_type = 'error';
        $update_message = 'Please fill in all fields.';
    } elseif (!in_array($category, $categories, true)) {
        $update_type = 'error';
        $update_message = 'Invalid category.';
    } else {
        $update_stmt = $conn->prepare('UPDATE forum_posts SET title = ?, category = ?, content = ? WHERE post_id = ? AND user_id = ?');
        $update_stmt->bind_param('sssii', $title, $category, $content, $post_id, $user_id);
        $update_stmt->execute();
        $affected = $update_stmt->affected_rows;
        $update_stmt->close();

        if ($affected >= 0) {
            $update_message = '✓ Post updated successfully!';
            $post['title'] = $title;
            $post['category'] = $category;
            $post['content'] = $content;
        } else {
            $update_type = 'error';
            $update_message = 'Unable to update your post.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .edit-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        .card {
            background: var(--bg-card, #F8F9F7);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
        }
        .card-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            border-left: 4px solid var(--success);
            background: rgba(111, 207, 151, 0.15);
            color: #2d7a4d;
        }
        .alert.error {
            border-left-color: var(--error);
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
        }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-weight: 700;
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
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                    Edit Forum Post
                </h2>
            </div>

            <div class="content-area">
                <div class="edit-container">
                    <?php if (!empty($update_message)): ?>
                        <div class="alert <?php echo $update_type === 'error' ? 'error' : ''; ?>">
                            <?php echo htmlspecialchars($update_message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-title">Update your post</div>

                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Title *</label>
                                <input type="text" name="post_title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Category *</label>
                                <select name="post_category" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $post['category'] === $cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Message *</label>
                                <textarea name="post_content" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                            </div>

                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <button type="submit" name="update_forum_post" class="btn btn-primary">Save Changes</button>
                                <a href="profile.php" class="btn btn-secondary">Back to Profile</a>
                                <a href="forum_view.php?post_id=<?php echo (int)$post['post_id']; ?>" class="btn btn-secondary">View Post</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
