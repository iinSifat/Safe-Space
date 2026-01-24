<?php
/**
 * Admin – Edit Blog Post
 */
require_once '../config/config.php';
require_admin();

$db = Database::getInstance();
$conn = $db->getConnection();

$blog_id = (int)($_GET['blog_id'] ?? 0);
if ($blog_id <= 0) {
    redirect('blog_posts.php');
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

$statuses = ['published', 'draft', 'flagged', 'deleted'];

$post_stmt = $conn->prepare('SELECT blog_id, title, category, content, status, is_professional_post FROM blog_posts WHERE blog_id = ? LIMIT 1');
if (!$post_stmt) {
    set_flash_message('error', 'Database error.');
    redirect('blog_posts.php');
}
$post_stmt->bind_param('i', $blog_id);
$post_stmt->execute();
$post = $post_stmt->get_result()->fetch_assoc();
$post_stmt->close();

if (!$post) {
    redirect('blog_posts.php');
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_blog_post'])) {
        $title = sanitize_input($_POST['blog_title'] ?? '');
        $category = sanitize_input($_POST['blog_category'] ?? '');
        $content = sanitize_input($_POST['blog_content'] ?? '');
        $status = sanitize_input($_POST['blog_status'] ?? $post['status']);

        if ($title === '' || $category === '' || $content === '') {
            $message_type = 'error';
            $message = 'Please fill in all fields.';
        } elseif (!in_array($category, $blog_categories, true)) {
            $message_type = 'error';
            $message = 'Invalid category.';
        } elseif (!in_array($status, $statuses, true)) {
            $message_type = 'error';
            $message = 'Invalid status.';
        } else {
            $update_stmt = $conn->prepare('UPDATE blog_posts SET title = ?, category = ?, content = ?, status = ? WHERE blog_id = ?');
            if ($update_stmt) {
                $update_stmt->bind_param('ssssi', $title, $category, $content, $status, $blog_id);
                $update_stmt->execute();
                $update_stmt->close();

                $message = '✓ Blog post updated.';
                $post['title'] = $title;
                $post['category'] = $category;
                $post['content'] = $content;
                $post['status'] = $status;
            } else {
                $message_type = 'error';
                $message = 'Database error.';
            }
        }
    } elseif (isset($_POST['delete_blog'])) {
        $del = $conn->prepare("UPDATE blog_posts SET status = 'deleted' WHERE blog_id = ?");
        if ($del) {
            $del->bind_param('i', $blog_id);
            $del->execute();
            $del->close();
        }
        redirect('blog_posts.php?status=deleted');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Blog Post | Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body { background: var(--light-bg); padding: 24px; }
        .page { max-width: 900px; margin: 0 auto; }
        .card { background: var(--bg-card, #F8F9F7); border-radius: 20px; padding: 24px; box-shadow: var(--shadow-sm); }
        .form-group { margin-bottom: 12px; }
        .form-group label { display:block; font-weight: 900; margin-bottom: 6px; color: var(--text-primary); }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 2px solid var(--border-soft, #D8E2DD); border-radius: 12px; }
        .form-group textarea { min-height: 180px; resize: vertical; }
        .actions { display:flex; gap: 10px; flex-wrap: wrap; }
        .btn-danger { background: var(--bg-card, #F8F9F7); border: 1px solid rgba(239,68,68,0.5); color: #b91c1c; }
        .btn-link { text-decoration:none; padding: 10px 12px; border-radius: 12px; border: 1px solid var(--border-soft, #D8E2DD); background: var(--bg-card, #F8F9F7); font-weight: 900; }
        .chip { display:inline-flex; align-items:center; gap: 8px; padding: 6px 10px; border-radius: 999px; background: rgba(123,93,255,0.12); color: #4c3bb8; font-weight: 900; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div style="display:flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items:center;">
                <div style="display:flex; gap: 10px; align-items:center; flex-wrap: wrap;">
                    <h1 style="margin:0;">Edit Blog Post</h1>
                    <?php if (!empty($post['is_professional_post'])): ?><span class="chip">Professional</span><?php endif; ?>
                </div>
                <a class="btn-link" href="blog_posts.php">← Back</a>
            </div>

            <?php if ($message): ?>
                <div class="alert <?php echo $message_type === 'error' ? 'alert-error' : 'alert-success'; ?>" style="margin-top: 14px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" style="margin-top: 14px;">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="blog_title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <select name="blog_category" required>
                        <?php foreach ($blog_categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($post['category'] === $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status *</label>
                    <select name="blog_status" required>
                        <?php foreach ($statuses as $st): ?>
                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($post['status'] ?? 'published') === $st) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($st)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Content *</label>
                    <textarea name="blog_content" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                </div>

                <div class="actions">
                    <button type="submit" name="update_blog_post" class="btn btn-primary">Save Changes</button>
                    <a class="btn btn-secondary" href="../dashboard/blog_view.php?blog_id=<?php echo (int)$blog_id; ?>" target="_blank" rel="noopener">View</a>
                    <?php if (($post['status'] ?? '') !== 'deleted'): ?>
                        <button type="submit" name="delete_blog" class="btn-danger btn" onclick="return confirm('Delete this blog post?');">Delete</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
