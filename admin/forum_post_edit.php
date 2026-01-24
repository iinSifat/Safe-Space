<?php
/**
 * Admin – Edit Forum Post
 */
require_once '../config/config.php';
require_admin();

$db = Database::getInstance();
$conn = $db->getConnection();

$post_id = (int)($_GET['post_id'] ?? 0);
if ($post_id <= 0) {
    redirect('forum_posts.php');
}

$categories = ['Anxiety', 'Depression', 'Stress', 'Relationships', 'Sleep', 'Work/School', 'Self-Care', 'General Support'];
$statuses = ['published', 'draft', 'flagged', 'deleted'];

$post_stmt = $conn->prepare("SELECT post_id, title, category, content, status FROM forum_posts WHERE post_id = ? LIMIT 1");
if (!$post_stmt) {
    set_flash_message('error', 'Database error.');
    redirect('forum_posts.php');
}
$post_stmt->bind_param('i', $post_id);
$post_stmt->execute();
$post = $post_stmt->get_result()->fetch_assoc();
$post_stmt->close();

if (!$post) {
    redirect('forum_posts.php');
}

$update_message = '';
$update_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_forum_post'])) {
        $title = sanitize_input($_POST['post_title'] ?? '');
        $category = sanitize_input($_POST['post_category'] ?? '');
        $content = sanitize_input($_POST['post_content'] ?? '');
        $status = sanitize_input($_POST['post_status'] ?? $post['status']);

        if ($title === '' || $category === '' || $content === '') {
            $update_type = 'error';
            $update_message = 'Please fill in all fields.';
        } elseif (!in_array($category, $categories, true)) {
            $update_type = 'error';
            $update_message = 'Invalid category.';
        } elseif (!in_array($status, $statuses, true)) {
            $update_type = 'error';
            $update_message = 'Invalid status.';
        } else {
            $update_stmt = $conn->prepare('UPDATE forum_posts SET title = ?, category = ?, content = ?, status = ? WHERE post_id = ?');
            if ($update_stmt) {
                $update_stmt->bind_param('ssssi', $title, $category, $content, $status, $post_id);
                $update_stmt->execute();
                $update_stmt->close();

                $update_message = '✓ Post updated.';
                $post['title'] = $title;
                $post['category'] = $category;
                $post['content'] = $content;
                $post['status'] = $status;
            } else {
                $update_type = 'error';
                $update_message = 'Database error.';
            }
        }
    } elseif (isset($_POST['delete_post'])) {
        $del = $conn->prepare("UPDATE forum_posts SET status = 'deleted' WHERE post_id = ?");
        if ($del) {
            $del->bind_param('i', $post_id);
            $del->execute();
            $del->close();
        }
        redirect('forum_posts.php?status=deleted');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Forum Post | Admin</title>
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
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div style="display:flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items:center;">
                <h1 style="margin:0;">Edit Forum Post</h1>
                <a class="btn-link" href="forum_posts.php">← Back</a>
            </div>

            <?php if ($update_message): ?>
                <div class="alert <?php echo $update_type === 'error' ? 'alert-error' : 'alert-success'; ?>" style="margin-top: 14px;">
                    <?php echo htmlspecialchars($update_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" style="margin-top: 14px;">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="post_title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <select name="post_category" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($post['category'] === $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status *</label>
                    <select name="post_status" required>
                        <?php foreach ($statuses as $st): ?>
                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($post['status'] ?? 'published') === $st) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($st)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Content *</label>
                    <textarea name="post_content" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                </div>

                <div class="actions">
                    <button type="submit" name="update_forum_post" class="btn btn-primary">Save Changes</button>
                    <a class="btn btn-secondary" href="../dashboard/forum_view.php?post_id=<?php echo (int)$post_id; ?>" target="_blank" rel="noopener">View</a>
                    <?php if (($post['status'] ?? '') !== 'deleted'): ?>
                        <button type="submit" name="delete_post" class="btn-danger btn" onclick="return confirm('Delete this post?');">Delete</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
