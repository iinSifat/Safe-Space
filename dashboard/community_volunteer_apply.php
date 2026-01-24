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
$user_type = function_exists('get_user_type') ? (string)get_user_type() : '';
$is_verified_volunteer = ($user_type === 'volunteer');
if (!$is_verified_volunteer && function_exists('user_has_volunteer_permission')) {
    $is_verified_volunteer = user_has_volunteer_permission($user_id);
}

if (!$is_verified_volunteer) {
    set_flash_message('error', 'Only verified volunteers can apply for Community Volunteer roles.');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_for_need'])) {
    $community_id = (int)($_POST['community_id'] ?? 0);
    $message = trim((string)($_POST['message'] ?? ''));
    if ($community_id > 0) {
        if (mb_strlen($message) > 800) { $message = mb_substr($message, 0, 800); }
        $message = sanitize_input($message);

        $stmt = $conn->prepare(
            "SELECT c.community_id
             FROM communities c
             WHERE c.community_id = ? AND c.status = 'approved'
             LIMIT 1"
        );
        $stmt->bind_param('i', $community_id);
        $stmt->execute();
        $need = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($need) {
            // Require at least one approved need for the community
            $chk = $conn->prepare("SELECT 1 FROM community_volunteer_needs WHERE community_id = ? AND status = 'approved' LIMIT 1");
            $chk->bind_param('i', $community_id);
            $chk->execute();
            $has_need = (bool)($chk->get_result()->fetch_assoc() ?? false);
            $chk->close();

            if (!$has_need) {
                set_flash_message('error', 'This community is not accepting Community Volunteer applications right now.');
                redirect('community_volunteer_apply.php');
            }

            $ins = $conn->prepare("INSERT INTO community_volunteer_applications (community_id, volunteer_user_id, message, status) VALUES (?, ?, ?, 'pending')");
            $ins->bind_param('iis', $community_id, $user_id, $message);
            $ok = $ins->execute();
            $ins->close();

            if ($ok) {
                set_flash_message('success', 'Application submitted. The community creator will review it.');
            } else {
                // Likely duplicate application due to unique constraint
                set_flash_message('error', 'You already applied to be a Community Volunteer for this community.');
            }
        }
    }

    redirect('community_volunteer_apply.php');
}

$flash = get_flash_message();

$needs = [];
$stmt = $conn->prepare(
        "SELECT c.community_id, c.name AS community_name,
                        (SELECT n2.justification FROM community_volunteer_needs n2 WHERE n2.community_id = c.community_id AND n2.status = 'approved' ORDER BY n2.approved_at DESC, n2.created_at DESC LIMIT 1) AS latest_justification,
                        (SELECT COUNT(*) FROM community_volunteer_needs n3 WHERE n3.community_id = c.community_id AND n3.status = 'approved') AS approved_need_count,
                        (SELECT COUNT(*) FROM community_volunteer_applications a WHERE a.community_id = c.community_id AND a.status = 'pending') AS pending_apps
         FROM communities c
         WHERE c.status = 'approved'
             AND EXISTS (SELECT 1 FROM community_volunteer_needs n WHERE n.community_id = c.community_id AND n.status = 'approved')
         ORDER BY c.created_at DESC"
);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $needs[] = $row;
}
$stmt->close();
// Updated by Shuvo - END
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Community Volunteer | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 1rem; }
        .card { background: var(--bg-card, #F8F9F7); border: 1px solid var(--border-soft, #D8E2DD); border-radius: 18px; padding: 16px; box-shadow: var(--shadow-sm); }
        .item { margin-top: 12px; padding: 14px; border: 1px solid var(--border-soft, #D8E2DD); border-radius: 12px; background: #fff; }
        .row { display:flex; justify-content: space-between; align-items:flex-start; gap: 10px; flex-wrap: wrap; }
        .muted { color: var(--text-secondary); }
        .badge { display:inline-block; padding: 5px 10px; border-radius: 999px; background: rgba(127, 175, 163, 0.18); font-weight: 900; font-size: 0.78rem; color: var(--text-primary); }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="top-bar">
            <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">Apply for Community Volunteer</h2>
        </div>

        <div class="content-area">
            <div class="wrap">
                <?php if ($flash): ?>
                    <div class="alert <?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
                <?php endif; ?>

                <div class="card" style="margin-bottom: 14px;">
                    <div style="font-weight: 950;">Available Community Volunteer Needs</div>
                    <div class="muted" style="margin-top: 6px;">Choose a community need and apply with a short message.</div>

                    <?php if (empty($needs)): ?>
                        <div class="muted" style="margin-top: 10px;">No approved community volunteer needs right now.</div>
                    <?php else: ?>
                        <?php foreach ($needs as $n): ?>
                            <div class="item">
                                <div class="row">
                                    <div>
                                        <div style="font-weight: 950;">Community Volunteer Need</div>
                                        <div class="muted" style="margin-top: 4px;">Community: <span class="badge"><?php echo htmlspecialchars($n['community_name']); ?></span></div>
                                        <div class="muted" style="margin-top: 8px; white-space: pre-wrap;"><?php echo htmlspecialchars((string)($n['latest_justification'] ?? '')); ?></div>
                                        <div class="muted" style="margin-top: 8px;">Approved needs: <strong><?php echo (int)($n['approved_need_count'] ?? 0); ?></strong> • Pending apps: <strong><?php echo (int)($n['pending_apps'] ?? 0); ?></strong></div>
                                        <div style="margin-top: 10px;">
                                            <a class="btn btn-ghost" href="community_view.php?community_id=<?php echo (int)$n['community_id']; ?>">View Community</a>
                                        </div>
                                    </div>
                                    <div style="min-width: 320px;">
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="community_id" value="<?php echo (int)$n['community_id']; ?>">
                                            <textarea class="form-input" name="message" rows="3" placeholder="Optional message: how you can help, availability, boundaries..."></textarea>
                                            <button class="btn btn-primary" type="submit" name="apply_for_need" style="margin-top: 8px;">Apply</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>
</div>
</body>
</html>
