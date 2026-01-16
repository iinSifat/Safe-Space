<?php
/**
 * User Notifications
 */
require_once '../config/config.php';
require_login();

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $nid = intval($_POST['mark_read']);
    mark_notification_read($nid);
}

$notifications = get_notifications($user_id, 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
        <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .page { max-width: 900px; margin: 40px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 20px; padding: 24px; box-shadow: var(--shadow-sm); }
        .notif-item { padding: 12px 0; border-bottom: 1px solid var(--light-gray); display: flex; justify-content: space-between; align-items: center; }
        .notif-item:last-child { border-bottom: none; }
        .notif-title { font-weight: 700; }
        .notif-meta { color: var(--text-secondary); font-size: 0.85rem; }
        .badge { background: rgba(20,184,166,0.12); color: var(--primary-dark); padding: 6px 10px; border-radius: 12px; font-weight: 700; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">🔔 Notifications</h2>
            </div>
            
            <div class="content-area">
    <div class="page">
        <div class="card">
            <h1 style="margin:0 0 12px;">Notifications</h1>
            <?php if (empty($notifications)): ?>
                <p class="notif-meta">No notifications yet.</p>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="notif-item">
                        <div>
                            <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                            <div class="notif-meta"><?php echo htmlspecialchars($n['message']); ?> • <?php echo date('M j, Y H:i', strtotime($n['created_at'])); ?></div>
                        </div>
                        <div>
                            <?php if (!$n['is_read']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="mark_read" value="<?php echo intval($n['notification_id']); ?>">
                                    <button class="btn btn-ghost" type="submit">Mark Read</button>
                                </form>
                            <?php else: ?>
                                <span class="badge">Read</span>
                            <?php endif; ?>
                                    </div><!-- End content-area -->
                                </main><!-- End main-content -->
                            </div><!-- End dashboard-wrapper -->
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
