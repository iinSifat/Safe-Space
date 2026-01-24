<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

$is_professional_user = function_exists('is_professional') && is_professional();

$blog_id = (int)($_GET['blog_id'] ?? 0);
if ($blog_id <= 0) {
    redirect('profile.php');
}

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

$post_sql = $is_professional_user
    ? "SELECT blog_id, title, category, content, status FROM blog_posts WHERE blog_id = ? AND user_id = ? LIMIT 1"
    : "SELECT blog_id, title, category, content, status FROM blog_posts WHERE blog_id = ? AND user_id = ? AND status = 'published' LIMIT 1";
$post_stmt = $conn->prepare($post_sql);
$post_stmt->bind_param('ii', $blog_id, $user_id);
$post_stmt->execute();
$post = $post_stmt->get_result()->fetch_assoc();
$post_stmt->close();

if (!$post) {
    redirect('profile.php');
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_blog_post'])) {
    $title = sanitize_input($_POST['blog_title'] ?? '');
    $category = sanitize_input($_POST['blog_category'] ?? '');
    $content = sanitize_input($_POST['blog_content'] ?? '');

    if ($title === '' || $category === '' || $content === '') {
        $message_type = 'error';
        $message = 'Please fill in all fields.';
    } elseif (!in_array($category, $blog_categories, true)) {
        $message_type = 'error';
        $message = 'Invalid category.';
    } else {
        if ($is_professional_user) {
            $content = ensure_professional_disclaimer($content);
            if (professional_content_has_prohibited_claims($content)) {
                $message_type = 'error';
                $message = 'Your professional post contains restricted language (e.g., diagnosis/prescribing). Please revise and try again.';
            } else {
                if (content_has_crisis_keywords($content)) {
                    add_notification((int)$user_id, 'warning', 'Crisis Support', 'If you or someone else is in immediate danger, call your local emergency number. If you are thinking about self-harm, please contact a local crisis hotline right now.');
                }

                $new_status = 'draft';
                $update_stmt = $conn->prepare('UPDATE blog_posts SET title = ?, category = ?, content = ?, status = ?, is_professional_post = 1 WHERE blog_id = ? AND user_id = ?');
                $update_stmt->bind_param('ssssii', $title, $category, $content, $new_status, $blog_id, $user_id);
                $update_stmt->execute();
                $update_stmt->close();

                $message = '✓ Changes saved. Your professional post is now in draft for review.';
                $post['status'] = $new_status;
                $post['title'] = $title;
                $post['category'] = $category;
                $post['content'] = $content;
            }
        } else {
            $update_stmt = $conn->prepare('UPDATE blog_posts SET title = ?, category = ?, content = ? WHERE blog_id = ? AND user_id = ?');
            $update_stmt->bind_param('sssii', $title, $category, $content, $blog_id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();

            $message = '✓ Blog post updated successfully!';
            $post['title'] = $title;
            $post['category'] = $category;
            $post['content'] = $content;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Blog | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .edit-container { max-width: 900px; margin: 0 auto; padding: 2rem 1rem; }
        .card { background: var(--bg-card, #F8F9F7); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: 2rem; }
        .card-title { font-size: 1.3rem; font-weight: 900; color: var(--text-primary); margin-bottom: 1rem; }
        .alert { padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem; border-left: 4px solid var(--success); background: rgba(111, 207, 151, 0.15); color: #2d7a4d; font-weight: 800; }
        .alert.error { border-left-color: var(--error); background: rgba(239, 68, 68, 0.12); color: #b91c1c; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 900; margin-bottom: 0.5rem; color: var(--text-primary); }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 2px solid var(--border-soft, #D8E2DD); border-radius: var(--radius-sm); font-family: inherit; font-size: 1rem; }
        .form-group textarea { min-height: 180px; resize: vertical; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                    Edit Blog Post
                </h2>
            </div>

            <div class="content-area">
                <div class="edit-container">
                    <?php if ($message): ?>
                        <div class="alert <?php echo $message_type === 'error' ? 'error' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-title">Update your blog</div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Title *</label>
                                <input type="text" name="blog_title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Category *</label>
                                <select name="blog_category" required>
                                    <?php foreach ($blog_categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $post['category'] === $cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Content *</label>
                                <textarea name="blog_content" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                            </div>

                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <button type="submit" name="update_blog_post" class="btn btn-primary">Save Changes</button>
                                <a href="profile.php" class="btn btn-secondary">Back to Profile</a>
                                <?php if (($post['status'] ?? '') === 'published'): ?>
                                    <a href="blog_view.php?blog_id=<?php echo (int)$post['blog_id']; ?>" class="btn btn-secondary">View Post</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
