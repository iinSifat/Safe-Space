<?php
/**
 * Admin – Manage Blog Posts
 */
require_once '../config/config.php';
require_admin();

$db = Database::getInstance();
$conn = $db->getConnection();

$action_message = '';
$action_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_blog'])) {
    $blog_id = (int)($_POST['blog_id'] ?? 0);
    if ($blog_id > 0) {
        $stmt = $conn->prepare("UPDATE blog_posts SET status = 'deleted' WHERE blog_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $blog_id);
            $stmt->execute();
            $stmt->close();
            $action_message = 'Blog post deleted.';
        } else {
            $action_type = 'error';
            $action_message = 'Database error.';
        }
    }
}

$status_filter = sanitize_input($_GET['status'] ?? 'published');
$allowed_status = ['published', 'draft', 'flagged', 'deleted'];
if (!in_array($status_filter, $allowed_status, true)) {
    $status_filter = 'published';
}

$filter_professional = sanitize_input($_GET['pro'] ?? ''); // 1 for professional only
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT bp.blog_id, bp.title, bp.category, bp.status, bp.created_at, bp.is_professional_post,
           u.username, u.user_type
    FROM blog_posts bp
    JOIN users u ON bp.user_id = u.user_id
    WHERE bp.status = ?
";

$params = [$status_filter];
$types = 's';

if ($filter_professional === '1') {
    $sql .= ' AND bp.is_professional_post = 1';
}

if ($search !== '') {
    $sql .= ' AND (bp.title LIKE ? OR bp.category LIKE ? OR u.username LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$sql .= ' ORDER BY bp.created_at DESC LIMIT 200';

$stmt = $conn->prepare($sql);
$rows = [];
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Posts | Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body { background: var(--light-bg); padding: 24px; }
        .page { max-width: 1200px; margin: 0 auto; }
        .card { background:#fff; border-radius: 20px; padding: 20px; box-shadow: var(--shadow-sm); }
        .toolbar { display:flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 14px 0; }
        .row { display:flex; gap: 12px; flex-wrap: wrap; align-items:center; }
        table { width:100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid var(--light-gray); text-align:left; vertical-align: top; }
        th { color: var(--text-secondary); font-size: 0.9rem; }
        .badge { display:inline-block; padding: 6px 10px; border-radius: 999px; font-weight: 800; font-size: 0.8rem; background: rgba(20,184,166,0.12); color: var(--primary-dark); }
        .badge.deleted { background: rgba(239,68,68,0.12); color: #b91c1c; }
        .badge.flagged { background: rgba(255,193,7,0.15); color: #8a6d00; }
        .badge.draft { background: rgba(123,93,255,0.12); color: #4c3bb8; }
        .chip { display:inline-flex; align-items:center; gap: 8px; padding: 6px 10px; border-radius: 999px; background: rgba(123,93,255,0.12); color: #4c3bb8; font-weight: 900; font-size: 0.8rem; }
        .actions { display:flex; gap: 8px; flex-wrap: wrap; }
        .btn-link { text-decoration:none; padding: 8px 12px; border-radius: 10px; border: 1px solid var(--light-gray); background: #fff; font-weight: 800; }
        .btn-danger { background: #fff; border: 1px solid rgba(239,68,68,0.5); color: #b91c1c; }
        input[type="text"], select { padding: 10px; border: 2px solid var(--light-gray); border-radius: 12px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div style="display:flex; justify-content: space-between; align-items:center; gap: 12px; flex-wrap: wrap;">
                <h1 style="margin:0;">Manage Blog Posts</h1>
                <a class="btn-link" href="dashboard.php">← Back to Admin Dashboard</a>
            </div>

            <?php if ($action_message): ?>
                <div class="alert <?php echo $action_type === 'error' ? 'alert-error' : 'alert-success'; ?>" style="margin-top: 14px;">
                    <?php echo htmlspecialchars($action_message); ?>
                </div>
            <?php endif; ?>

            <div class="toolbar">
                <form method="GET" class="row">
                    <label>
                        Status
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach ($allowed_status as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status_filter === $s ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($s)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Professional only
                        <select name="pro" onchange="this.form.submit()">
                            <option value="" <?php echo $filter_professional !== '1' ? 'selected' : ''; ?>>All</option>
                            <option value="1" <?php echo $filter_professional === '1' ? 'selected' : ''; ?>>Yes</option>
                        </select>
                    </label>

                    <input type="text" name="q" placeholder="Search title/category/author" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit">Search</button>
                </form>
            </div>

            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="6" style="color: var(--text-secondary);">No posts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <?php $st = $r['status'] ?? 'published'; ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($r['title']); ?>
                                        <?php if (!empty($r['is_professional_post'])): ?>
                                            <span class="chip" style="margin-left: 8px;">Professional</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['category']); ?></td>
                                    <td><?php echo htmlspecialchars($r['username']); ?></td>
                                    <td><span class="badge <?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars(ucfirst($st)); ?></span></td>
                                    <td><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($r['created_at']))); ?></td>
                                    <td>
                                        <div class="actions">
                                            <a class="btn-link" href="../dashboard/blog_view.php?blog_id=<?php echo (int)$r['blog_id']; ?>" target="_blank" rel="noopener">View</a>
                                            <a class="btn-link" href="blog_post_edit.php?blog_id=<?php echo (int)$r['blog_id']; ?>">Edit</a>
                                            <?php if (($r['status'] ?? '') !== 'deleted'): ?>
                                                <form method="POST" onsubmit="return confirm('Delete this blog post?');" style="display:inline;">
                                                    <input type="hidden" name="blog_id" value="<?php echo (int)$r['blog_id']; ?>">
                                                    <button class="btn-link btn-danger" type="submit" name="delete_blog">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
