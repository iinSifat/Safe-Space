<?php
/**
 * Admin – Review Volunteer Applications
 */
require_once '../config/config.php';
require_admin();

$db = Database::getInstance();
$conn = $db->getConnection();

$action_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app_id = intval($_POST['application_id'] ?? 0);
    $decision = sanitize_input($_POST['decision'] ?? ''); // approve|decline
    $notes = sanitize_input($_POST['admin_notes'] ?? '');

    // Fetch application
    $stmt = $conn->prepare("SELECT * FROM volunteer_applications WHERE application_id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($app) {
        if ($decision === 'approve') {
            $upd = $conn->prepare("UPDATE volunteer_applications SET status='approved', admin_notes=?, reviewed_by=?, approved_at=NOW() WHERE application_id=?");
            $admin_id = get_admin_id();
            $upd->bind_param("sii", $notes, $admin_id, $app_id);
            $upd->execute();
            $upd->close();

            // Upsert volunteer record
            $check = $conn->prepare("SELECT volunteer_id FROM volunteers WHERE user_id = ?");
            $check->bind_param("i", $app['user_id']);
            $check->execute();
            $res = $check->get_result();
            $exists = $res->fetch_assoc();
            $check->close();
            if ($exists) {
                $vupd = $conn->prepare("UPDATE volunteers SET full_name=?, approval_status='approved', is_active_volunteer=TRUE, approved_at=NOW(), approved_by=? WHERE user_id = ?");
                $vupd->bind_param("sii", $app['full_name'], $admin_id, $app['user_id']);
                $vupd->execute();
                $vupd->close();
            } else {
                $vins = $conn->prepare("INSERT INTO volunteers (user_id, full_name, approval_status, is_active_volunteer, approved_at, approved_by) VALUES (?, ?, 'approved', TRUE, NOW(), ?)");
                $vins->bind_param("isi", $app['user_id'], $app['full_name'], $admin_id);
                $vins->execute();
                $vins->close();
            }

            add_notification($app['user_id'], 'volunteer_approved', 'Volunteer Application Approved', 'Congratulations! Your volunteer application has been approved.');
            $action_message = 'Application approved and volunteer activated.';
        } elseif ($decision === 'decline') {
            $upd = $conn->prepare("UPDATE volunteer_applications SET status='declined', admin_notes=?, reviewed_by=?, declined_at=NOW() WHERE application_id=?");
            $admin_id = get_admin_id();
            $upd->bind_param("sii", $notes, $admin_id, $app_id);
            $upd->execute();
            $upd->close();
            add_notification($app['user_id'], 'volunteer_declined', 'Volunteer Application Declined', 'Thank you for applying. After review, we are unable to approve your application at this time.');
            $action_message = 'Application declined.';
        }
    }
}

// List applications
$status_filter = sanitize_input($_GET['status'] ?? 'pending');
$stmt = $conn->prepare("SELECT va.*, u.email FROM volunteer_applications va JOIN users u ON va.user_id = u.user_id WHERE va.status = ? ORDER BY submitted_at DESC");
$stmt->bind_param("s", $status_filter);
$stmt->execute();
$result = $stmt->get_result();
$applications = [];
while ($row = $result->fetch_assoc()) { $applications[] = $row; }
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Applications | Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .page { max-width: 1100px; margin: 40px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 20px; padding: 24px; box-shadow: var(--shadow-sm); }
        .list { margin-top: 16px; }
        .item { padding: 16px; border: 1px solid var(--light-gray); border-radius: 12px; margin-bottom: 12px; }
        .item h3 { margin: 0 0 8px; }
        .docs { display:flex; flex-wrap:wrap; gap:8px; }
        .badge { background: rgba(20,184,166,0.12); color: var(--primary-dark); padding: 6px 10px; border-radius: 12px; font-weight: 700; font-size: 0.8rem; }
        .actions { display:flex; gap:8px; margin-top: 8px; }
        .filter { margin-bottom: 12px; }
        .note { color: var(--text-secondary); font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <h1 style="margin:0 0 12px;">Volunteer Applications</h1>
            <?php if (!empty($action_message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($action_message); ?></div><?php endif; ?>
            <div class="filter">
                <form method="GET" style="display:inline-block;">
                    <label>Status: 
                        <select name="status" onchange="this.form.submit()">
                            <option value="pending" <?php echo $status_filter==='pending'?'selected':''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter==='approved'?'selected':''; ?>>Approved</option>
                            <option value="declined" <?php echo $status_filter==='declined'?'selected':''; ?>>Declined</option>
                        </select>
                    </label>
                </form>
            </div>
            <div class="list">
                <?php if (empty($applications)): ?>
                    <p class="note">No applications found.</p>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <div class="item">
                            <h3><?php echo htmlspecialchars($app['full_name']); ?> <span class="badge"><?php echo htmlspecialchars(ucfirst($app['status'])); ?></span></h3>
                            <div class="note">Submitted: <?php echo date('M j, Y H:i', strtotime($app['submitted_at'])); ?> • Email: <?php echo htmlspecialchars($app['email']); ?></div>
                            <p><strong>Education:</strong> <?php echo nl2br(htmlspecialchars($app['education'])); ?></p>
                            <p><strong>Training / Certifications:</strong> <?php echo nl2br(htmlspecialchars($app['training_certifications'])); ?></p>
                            <p><strong>Trainee Organization:</strong> <?php echo htmlspecialchars($app['trainee_organization']); ?></p>
                            <?php if (!empty($app['experience'])): ?><p><strong>Experience:</strong> <?php echo nl2br(htmlspecialchars($app['experience'])); ?></p><?php endif; ?>
                            <p><strong>Motivation:</strong> <?php echo nl2br(htmlspecialchars($app['motivation'])); ?></p>
                            <?php $docs = json_decode($app['document_paths'] ?? '[]', true) ?: []; ?>
                            <?php if (!empty($docs)): ?>
                                <div class="docs">
                                    <?php foreach ($docs as $d): ?>
                                        <a class="btn btn-ghost" href="../<?php echo htmlspecialchars($d); ?>" target="_blank">View Document</a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($app['status'] === 'pending'): ?>
                                <div class="actions">
                                    <form method="POST">
                                        <input type="hidden" name="application_id" value="<?php echo intval($app['application_id']); ?>">
                                        <input type="hidden" name="decision" value="approve">
                                        <input type="text" name="admin_notes" placeholder="Admin notes (optional)">
                                        <button class="btn btn-primary" type="submit">Approve</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="application_id" value="<?php echo intval($app['application_id']); ?>">
                                        <input type="hidden" name="decision" value="decline">
                                        <input type="text" name="admin_notes" placeholder="Admin notes (optional)">
                                        <button class="btn btn-ghost" type="submit">Decline</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <?php if (!empty($app['admin_notes'])): ?><p class="note"><strong>Admin Notes:</strong> <?php echo htmlspecialchars($app['admin_notes']); ?></p><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
