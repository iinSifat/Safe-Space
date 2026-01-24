<?php
/**
 * Admin – Review Community Volunteer Needs
 */
// Updated by Shuvo - START
require_once '../config/config.php';
require_admin();

$db = Database::getInstance();
$conn = $db->getConnection();

require_once __DIR__ . '/../dashboard/community/community_lib.php';
ss_require_community_tables($conn);

$action_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $need_id = (int)($_POST['need_id'] ?? 0);
    $decision = sanitize_input($_POST['decision'] ?? ''); // approve|decline
    $notes = sanitize_input($_POST['admin_notes'] ?? '');

    if ($need_id > 0 && in_array($decision, ['approve', 'decline'], true)) {
        $stmt = $conn->prepare(
            "SELECT n.*, c.name AS community_name
             FROM community_volunteer_needs n
             JOIN communities c ON c.community_id = n.community_id
             WHERE n.need_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $need_id);
        $stmt->execute();
        $need = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($need) {
            $admin_id = get_admin_id();
            if ($decision === 'approve' && $need['status'] === 'pending') {
                $upd = $conn->prepare("UPDATE community_volunteer_needs SET status='approved', reviewed_by=?, reviewed_at=NOW(), approved_at=NOW(), admin_notes=? WHERE need_id=?");
                $upd->bind_param('isi', $admin_id, $notes, $need_id);
                $upd->execute();
                $upd->close();

                add_notification((int)$need['requested_by_user_id'], 'community_need_approved', 'Community Volunteer Need Approved', 'Your volunteer need for "' . $need['community_name'] . '" was approved.');
                $action_message = 'Need approved.';
            } elseif ($decision === 'decline' && $need['status'] === 'pending') {
                $upd = $conn->prepare("UPDATE community_volunteer_needs SET status='declined', reviewed_by=?, reviewed_at=NOW(), declined_at=NOW(), admin_notes=? WHERE need_id=?");
                $upd->bind_param('isi', $admin_id, $notes, $need_id);
                $upd->execute();
                $upd->close();

                add_notification((int)$need['requested_by_user_id'], 'community_need_declined', 'Community Volunteer Need Declined', 'Your volunteer need for "' . $need['community_name'] . '" was declined.');
                $action_message = 'Need declined.';
            }
        }
    }
}

$status_filter = sanitize_input($_GET['status'] ?? 'pending');
if (!in_array($status_filter, ['pending', 'approved', 'declined'], true)) { $status_filter = 'pending'; }

$needs = [];
$stmt = $conn->prepare(
    "SELECT n.*, c.name AS community_name, u.username, u.full_name,
            (SELECT COUNT(*) FROM community_volunteer_applications a WHERE a.community_id = n.community_id AND a.status = 'pending') AS pending_apps
     FROM community_volunteer_needs n
     JOIN communities c ON c.community_id = n.community_id
     JOIN users u ON u.user_id = n.requested_by_user_id
     WHERE n.status = ?
     ORDER BY n.created_at DESC"
);
$stmt->bind_param('s', $status_filter);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $needs[] = $row; }
$stmt->close();
// Updated by Shuvo - END
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Volunteer Needs | Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .page { max-width: 1200px; margin: 40px auto; padding: 0 16px; }
        .card { background: var(--bg-card, #F8F9F7); border-radius: 20px; padding: 24px; box-shadow: var(--shadow-sm); }
        .item { padding: 16px; border: 1px solid var(--border-soft, #D8E2DD); border-radius: 12px; margin-top: 14px; background: #fff; }
        .row { display:flex; justify-content: space-between; align-items:flex-start; gap: 10px; flex-wrap: wrap; }
        .badge { background: rgba(127, 175, 163, 0.18); color: var(--text-primary); padding: 6px 10px; border-radius: 12px; font-weight: 900; font-size: 0.8rem; }
        .muted { color: var(--text-secondary); }
        .actions { display:flex; gap: 8px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div class="row">
                <div>
                    <h1 style="margin:0 0 8px;">Community Volunteer Needs</h1>
                    <div class="muted">Approve/decline creator requests for community volunteers.</div>
                </div>
                <div class="actions">
                    <a class="btn btn-ghost" href="dashboard.php">Admin Dashboard</a>
                </div>
            </div>

            <?php if (!empty($action_message)): ?><div class="alert alert-success" style="margin-top: 12px;"><?php echo htmlspecialchars($action_message); ?></div><?php endif; ?>

            <form method="GET" style="margin-top: 12px;">
                <label>Status:
                    <select name="status" onchange="this.form.submit()">
                        <option value="pending" <?php echo $status_filter==='pending'?'selected':''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter==='approved'?'selected':''; ?>>Approved</option>
                        <option value="declined" <?php echo $status_filter==='declined'?'selected':''; ?>>Declined</option>
                    </select>
                </label>
            </form>

            <?php if (empty($needs)): ?>
                <div class="item"><strong>No needs found.</strong></div>
            <?php else: ?>
                <?php foreach ($needs as $n): ?>
                    <div class="item">
                        <div class="row">
                            <div>
                                <div style="font-weight: 950; font-size: 1.05rem;">Volunteer Need Request</div>
                                <div class="muted" style="margin-top: 6px;">Community: <span class="badge"><?php echo htmlspecialchars($n['community_name']); ?></span> • Requested by: <strong><?php echo htmlspecialchars(($n['full_name'] ?: $n['username']) ?? 'User'); ?></strong></div>
                                <div class="muted" style="margin-top: 8px; white-space: pre-wrap;"><?php echo htmlspecialchars((string)$n['justification']); ?></div>
                                <div class="muted" style="margin-top: 8px;">Created: <?php echo date('M j, Y H:i', strtotime($n['created_at'])); ?> • Pending apps: <strong><?php echo (int)($n['pending_apps'] ?? 0); ?></strong></div>
                            </div>
                            <div>
                                <div class="muted">Status:</div>
                                <div><span class="badge"><?php echo htmlspecialchars(ucfirst($n['status'])); ?></span></div>
                            </div>
                        </div>

                        <?php if ($n['status'] === 'pending'): ?>
                            <form method="POST" style="margin-top: 12px;">
                                <input type="hidden" name="need_id" value="<?php echo (int)$n['need_id']; ?>">
                                <label class="muted" style="display:block; font-weight: 900; margin-bottom: 6px;">Admin Notes (optional)</label>
                                <textarea class="form-input" name="admin_notes" rows="2" placeholder="Optional notes"></textarea>
                                <div class="actions" style="margin-top: 8px;">
                                    <button class="btn btn-primary" type="submit" name="decision" value="approve">Approve</button>
                                    <button class="btn btn-ghost" type="submit" name="decision" value="decline" onclick="return confirm('Decline this need?');">Decline</button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <?php if (!empty($n['admin_notes'])): ?>
                            <div class="muted" style="margin-top: 10px;"><strong>Admin notes:</strong> <?php echo htmlspecialchars((string)$n['admin_notes']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>
