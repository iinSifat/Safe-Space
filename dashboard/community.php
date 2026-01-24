<?php
// Updated by Shuvo - START
require_once '../config/config.php';
require_login();
check_session_timeout();

$db = Database::getInstance();
$conn = $db->getConnection();

require_once __DIR__ . '/community/community_lib.php';
ss_require_community_tables($conn);

$user_id = (int)get_user_id();

$communities = [];
$stmt = $conn->prepare(
    "SELECT c.community_id, c.name, c.focus_tag, c.sensitivity_level, c.allow_anonymous_posts,
            c.purpose_who_for, c.purpose_support_expected, c.created_at,
            (SELECT COUNT(*) FROM community_members m WHERE m.community_id = c.community_id) AS member_count
     FROM communities c
     WHERE c.status = 'approved'
     ORDER BY c.created_at DESC"
);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $communities[] = $row; }
$stmt->close();

$flash = get_flash_message();
// Updated by Shuvo - END
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .wrap { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
        .hero { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: #fff; padding: 2rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; }
        .actions { display:flex; justify-content: space-between; align-items:center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; }
        .card { background: var(--bg-card, #F8F9F7); border: 1px solid var(--border-soft, #D8E2DD); border-radius: 18px; padding: 16px; box-shadow: var(--shadow-sm); }
        .card-link { display: block; color: inherit; text-decoration: none; }
        .card-link:hover { border-color: var(--primary-color); box-shadow: var(--shadow-md); }
        .meta { color: var(--text-secondary); font-size: 0.92rem; margin-top: 6px; }
        .badge { display:inline-block; padding: 5px 10px; border-radius: 999px; background: rgba(127, 175, 163, 0.18); font-weight: 800; font-size: 0.78rem; color: var(--text-primary); margin-right: 6px; }
        .row { display:flex; justify-content: space-between; align-items:flex-start; gap: 10px; }
        .title { font-size: 1.05rem; font-weight: 900; margin: 0; }
        .desc { margin: 10px 0 0; color: var(--text-secondary); }
        .btnrow { display:flex; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
        .small { font-size: 0.88rem; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="top-bar">
            <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
                Community
            </h2>
        </div>

        <div class="content-area">
            <div class="wrap">
                <div class="hero">
                    <h1 style="margin:0 0 8px; font-size: 2rem;">Communities</h1>
                    <p style="margin:0; opacity: 0.92;">Supportive spaces with clear purpose, safety expectations, and gentle engagement.</p>
                </div>

                <?php if ($flash): ?>
                    <div class="alert <?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
                <?php endif; ?>

                <div class="actions">
                    <div class="small" style="color: var(--text-secondary); font-weight: 800;">Only admin-approved communities appear here.</div>
                    <a class="btn btn-primary" href="community_create.php">Create New Community</a>
                </div>

                <?php if (empty($communities)): ?>
                    <div class="card">
                        <strong>No approved communities yet.</strong>
                        <div class="meta">You can request one using “Create New Community”.</div>
                    </div>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($communities as $c): ?>
                            <a class="card card-link" href="community_view.php?community_id=<?php echo (int)$c['community_id']; ?>">
                                <div class="row">
                                    <div>
                                        <h3 class="title"><?php echo htmlspecialchars($c['name']); ?></h3>
                                        <div class="meta">
                                            <span class="badge"><?php echo htmlspecialchars(ucfirst($c['focus_tag'])); ?></span>
                                            <span class="badge">Sensitivity: <?php echo htmlspecialchars(ucfirst($c['sensitivity_level'])); ?></span>
                                            <span class="badge"><?php echo ((int)($c['member_count'] ?? 0)); ?> members</span>
                                        </div>
                                    </div>
                                </div>

                                <p class="desc">
                                    <strong>Who it’s for:</strong> <?php echo nl2br(htmlspecialchars(mb_strimwidth((string)$c['purpose_who_for'], 0, 150, '…'))); ?>
                                </p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>
</div>
</body>
</html>
