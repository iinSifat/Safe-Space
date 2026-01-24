<?php
/**
 * Admin – Review Community Creation Requests
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
    $request_id = (int)($_POST['request_id'] ?? 0);
    $decision = sanitize_input($_POST['decision'] ?? ''); // approve|decline
    $notes = sanitize_input($_POST['admin_notes'] ?? '');

    if ($request_id > 0 && in_array($decision, ['approve', 'decline'], true)) {
        $stmt = $conn->prepare('SELECT * FROM community_requests WHERE request_id = ? LIMIT 1');
        $stmt->bind_param('i', $request_id);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($req && $req['status'] === 'pending') {
            $admin_id = get_admin_id();

            if ($decision === 'approve') {
                $conn->begin_transaction();
                try {
                    $allow_anon = ((int)$req['allow_anonymous_posts'] === 1) ? 1 : 0;

                    $ins = $conn->prepare(
                        "INSERT INTO communities (
                            creator_user_id, name, focus_tag, sensitivity_level, allow_anonymous_posts,
                            purpose_who_for, purpose_support_expected, safety_considerations, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved')"
                    );
                    $ins->bind_param(
                        'isssisss',
                        $req['requested_by_user_id'],
                        $req['community_name'],
                        $req['focus_tag'],
                        $req['sensitivity_level'],
                        $allow_anon,
                        $req['purpose_who_for'],
                        $req['purpose_support_expected'],
                        $req['safety_considerations']
                    );
                    $ins->execute();
                    $community_id = (int)$ins->insert_id;
                    $ins->close();

                    $mem = $conn->prepare("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'creator')");
                    $mem->bind_param('ii', $community_id, $req['requested_by_user_id']);
                    $mem->execute();
                    $mem->close();

                    $upd = $conn->prepare("UPDATE community_requests SET status='approved', reviewed_by=?, reviewed_at=NOW(), admin_notes=?, community_id=? WHERE request_id=?");
                    $upd->bind_param('isii', $admin_id, $notes, $community_id, $request_id);
                    $upd->execute();
                    $upd->close();

                    $conn->commit();

                    add_notification((int)$req['requested_by_user_id'], 'community_request_approved', 'Community Request Approved', 'Your community "' . $req['community_name'] . '" has been approved.');
                    $action_message = 'Community approved and created.';
                } catch (Throwable $e) {
                    $conn->rollback();
                    $action_message = APP_DEBUG
                        ? ('Error approving request: ' . $e->getMessage())
                        : 'Error approving request (maybe duplicate community name?).';
                }
            } else {
                $upd = $conn->prepare("UPDATE community_requests SET status='declined', reviewed_by=?, reviewed_at=NOW(), admin_notes=? WHERE request_id=?");
                $upd->bind_param('isi', $admin_id, $notes, $request_id);
                $upd->execute();
                $upd->close();

                add_notification((int)$req['requested_by_user_id'], 'community_request_declined', 'Community Request Declined', 'Your community request "' . $req['community_name'] . '" was declined.');
                $action_message = 'Request declined.';
            }
        }
    }
}

$status_filter = sanitize_input($_GET['status'] ?? 'pending');
if (!in_array($status_filter, ['pending', 'approved', 'declined'], true)) { $status_filter = 'pending'; }

$requests = [];
$sql =
    "SELECT r.*, u.username, u.full_name
     FROM community_requests r
     JOIN users u ON u.user_id = r.requested_by_user_id ";

if ($status_filter === 'pending') {
    // Support older rows where status might be NULL
    $sql .= "WHERE (r.status = 'pending' OR r.status IS NULL) ";
    $sql .= "ORDER BY r.created_at DESC";
    $stmt = $conn->prepare($sql);
} else {
    $sql .= "WHERE r.status = ? ORDER BY r.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $status_filter);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $requests[] = $row; }
$stmt->close();
// Updated by Shuvo - END
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Requests | Admin</title>
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
                    <h1 style="margin:0 0 8px;">Community Creation Requests</h1>
                    <div class="muted">Approve or decline community requests. Approved requests become visible in the Community list.</div>
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

            <?php if (empty($requests)): ?>
                <div class="item"><strong>No requests found.</strong></div>
            <?php else: ?>
                <?php foreach ($requests as $r): ?>
                    <div class="item">
                        <div class="row">
                            <div>
                                <div style="font-weight: 950; font-size: 1.1rem;"><?php echo htmlspecialchars($r['community_name']); ?></div>
                                <div class="muted" style="margin-top: 6px;">Requested by: <strong><?php echo htmlspecialchars(($r['full_name'] ?: $r['username']) ?? 'User'); ?></strong> • <?php echo date('M j, Y H:i', strtotime($r['created_at'])); ?></div>
                                <div style="margin-top: 8px;">
                                    <span class="badge"><?php echo htmlspecialchars(ucfirst($r['focus_tag'])); ?></span>
                                    <span class="badge">Sensitivity: <?php echo htmlspecialchars(ucfirst($r['sensitivity_level'])); ?></span>
                                    <?php if ((int)$r['allow_anonymous_posts'] === 1): ?><span class="badge">Anonymous allowed</span><?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 12px;" class="muted"><strong>Who it’s for:</strong><br><?php echo nl2br(htmlspecialchars((string)$r['purpose_who_for'])); ?></div>
                        <div style="margin-top: 10px;" class="muted"><strong>Expected support:</strong><br><?php echo nl2br(htmlspecialchars((string)$r['purpose_support_expected'])); ?></div>
                        <div style="margin-top: 10px;" class="muted"><strong>Why needed:</strong><br><?php echo nl2br(htmlspecialchars((string)$r['why_needed'])); ?></div>
                        <div style="margin-top: 10px;" class="muted"><strong>How it will help:</strong><br><?php echo nl2br(htmlspecialchars((string)$r['how_help'])); ?></div>
                        <div style="margin-top: 10px;" class="muted"><strong>Engagement plan:</strong><br><?php echo nl2br(htmlspecialchars((string)$r['engagement_plan'])); ?></div>
                        <?php if (!empty($r['safety_considerations'])): ?>
                            <div style="margin-top: 10px;" class="muted"><strong>Safety considerations:</strong><br><?php echo nl2br(htmlspecialchars((string)$r['safety_considerations'])); ?></div>
                        <?php endif; ?>

                        <?php if ($r['status'] === 'pending'): ?>
                            <form method="POST" style="margin-top: 12px;">
                                <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                                <label class="muted" style="display:block; font-weight: 900; margin-bottom: 6px;">Admin Notes (optional)</label>
                                <textarea class="form-input" name="admin_notes" rows="2" placeholder="Optional notes to the requester"></textarea>
                                <div class="actions" style="margin-top: 8px;">
                                    <button class="btn btn-primary" type="submit" name="decision" value="approve">Approve</button>
                                    <button class="btn btn-ghost" type="submit" name="decision" value="decline" onclick="return confirm('Decline this request?');">Decline</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="muted" style="margin-top: 12px;">Status: <span class="badge"><?php echo htmlspecialchars(ucfirst($r['status'])); ?></span></div>
                            <?php if (!empty($r['admin_notes'])): ?>
                                <div class="muted" style="margin-top: 8px;"><strong>Admin notes:</strong> <?php echo htmlspecialchars((string)$r['admin_notes']); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>
