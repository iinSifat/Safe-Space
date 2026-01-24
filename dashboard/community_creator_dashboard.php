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
$community_id = (int)($_GET['community_id'] ?? 0);
if ($community_id <= 0) {
    redirect('community.php');
}

$community = ss_get_community($conn, $community_id);
if (!$community) {
    set_flash_message('error', 'Community not found.');
    redirect('community.php');
}

$my_role = ss_get_member_role($conn, $community_id, $user_id);
$is_creator = ($my_role === 'creator');
$is_moderator = in_array($my_role, ['moderator', 'volunteer'], true);
$can_manage = ($is_creator || $is_moderator);

$join_requests_enabled = ss_join_requests_enabled($conn);
$join_answers_supported = $join_requests_enabled ? ss_join_request_answers_supported($conn) : false;

if (!$can_manage) {
    set_flash_message('error', 'This page is only available to the community creator or a moderator.');
    redirect('community_view.php?community_id=' . $community_id);
}

// Updated by Shuvo - START
// Extra guard: only allow entry after visiting the community page in this session.
$ctx = $_SESSION['community_creator_context'][(string)$community_id] ?? null;
if ($ctx === null) {
    set_flash_message('error', 'Please open the community first, then access Manage Community from inside it.');
    redirect('community_view.php?community_id=' . $community_id);
}
// Updated by Shuvo - END

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Review join requests (creator/moderator)
    if (isset($_POST['review_join_request']) && $can_manage && $join_requests_enabled) {
        $request_id = (int)($_POST['request_id'] ?? 0);
        $decision = sanitize_input((string)($_POST['decision'] ?? ''));

        if ($request_id > 0 && in_array($decision, ['approve', 'decline'], true)) {
            $stmt = $conn->prepare("SELECT request_id, user_id, status FROM community_join_requests WHERE request_id = ? AND community_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ii', $request_id, $community_id);
                $stmt->execute();
                $req = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($req && ($req['status'] === 'pending')) {
                    $target_user_id = (int)$req['user_id'];

                    if ($decision === 'approve') {
                        $mins = $conn->prepare("INSERT INTO community_members (community_id, user_id, role, join_reason)
                                               VALUES (?, ?, 'member', NULL)
                                               ON DUPLICATE KEY UPDATE role = IF(role IN ('creator','volunteer','moderator'), role, 'member')");
                        if ($mins) {
                            $mins->bind_param('ii', $community_id, $target_user_id);
                            $mins->execute();
                            $mins->close();
                        }

                        $upd = $conn->prepare("UPDATE community_join_requests SET status = 'approved', reviewed_by_user_id = ?, reviewed_at = NOW() WHERE request_id = ?");
                        if ($upd) {
                            $upd->bind_param('ii', $user_id, $request_id);
                            $upd->execute();
                            $upd->close();
                        }

                        if (function_exists('add_notification')) {
                            add_notification($target_user_id, 'community_join_approved', 'Community Join Approved', 'Your request to join \"' . $community['name'] . '\" has been approved.');
                        }

                        set_flash_message('success', 'Join request approved.');
                    } else {
                        $upd = $conn->prepare("UPDATE community_join_requests SET status = 'declined', reviewed_by_user_id = ?, reviewed_at = NOW() WHERE request_id = ?");
                        if ($upd) {
                            $upd->bind_param('ii', $user_id, $request_id);
                            $upd->execute();
                            $upd->close();
                        }

                        if (function_exists('add_notification')) {
                            add_notification($target_user_id, 'community_join_declined', 'Community Join Declined', 'Your request to join \"' . $community['name'] . '\" was declined.');
                        }

                        set_flash_message('success', 'Join request declined.');
                    }
                }
            }
        }

        redirect('community_creator_dashboard.php?community_id=' . $community_id);
    }

    // Request a volunteer need (goes to admin)
    if (isset($_POST['request_need'])) {
        if (!$is_creator) {
            set_flash_message('error', 'Only the community creator can request volunteers.');
            redirect('community_creator_dashboard.php?community_id=' . $community_id);
        }

        $justification = trim((string)($_POST['justification'] ?? ''));

        if ($justification === '') {
            set_flash_message('error', 'Please describe what volunteer help you need.');
            redirect('community_creator_dashboard.php?community_id=' . $community_id);
        }

        if (mb_strlen($justification) > 2000) { $justification = mb_substr($justification, 0, 2000); }
        $justification = sanitize_input($justification);

        $stmt = $conn->prepare("INSERT INTO community_volunteer_needs (community_id, requested_by_user_id, justification, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param('iis', $community_id, $user_id, $justification);
        $stmt->execute();
        $stmt->close();

        set_flash_message('success', 'Volunteer need sent to admin for review.');
        redirect('community_creator_dashboard.php?community_id=' . $community_id);
    }

    // Review volunteer application
    if (isset($_POST['review_application'])) {
        if (!$is_creator) {
            set_flash_message('error', 'Only the community creator can approve or decline volunteer applications.');
            redirect('community_creator_dashboard.php?community_id=' . $community_id);
        }

        $application_id = (int)($_POST['application_id'] ?? 0);
        $decision = sanitize_input($_POST['decision'] ?? ''); // approve|decline

        if ($application_id > 0 && in_array($decision, ['approve', 'decline'], true)) {
            $stmt = $conn->prepare("SELECT a.application_id, a.volunteer_user_id, a.community_id, a.status AS app_status
                                   FROM community_volunteer_applications a
                                   WHERE a.application_id = ? AND a.community_id = ? LIMIT 1");
            $stmt->bind_param('ii', $application_id, $community_id);
            $stmt->execute();
            $app = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($app && $app['app_status'] === 'pending') {
                $new_status = ($decision === 'approve') ? 'approved' : 'declined';
                $upd = $conn->prepare("UPDATE community_volunteer_applications SET status = ?, decided_by_user_id = ?, decided_at = NOW() WHERE application_id = ?");
                $upd->bind_param('sii', $new_status, $user_id, $application_id);
                $upd->execute();
                $upd->close();

                if ($decision === 'approve') {
                    // Upsert membership, promote to volunteer if already a member.
                    $check = $conn->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ? LIMIT 1");
                    $uid = (int)$app['volunteer_user_id'];
                    $check->bind_param('ii', $community_id, $uid);
                    $check->execute();
                    $existing = $check->get_result()->fetch_assoc();
                    $check->close();

                    if ($existing) {
                        if ($existing['role'] !== 'creator') {
                            $mupd = $conn->prepare("UPDATE community_members SET role = 'volunteer' WHERE community_id = ? AND user_id = ?");
                            $mupd->bind_param('ii', $community_id, $uid);
                            $mupd->execute();
                            $mupd->close();
                        }
                    } else {
                        $mins = $conn->prepare("INSERT INTO community_members (community_id, user_id, role, join_reason) VALUES (?, ?, 'volunteer', NULL)");
                        $mins->bind_param('ii', $community_id, $uid);
                        $mins->execute();
                        $mins->close();
                    }

                    add_notification((int)$app['volunteer_user_id'], 'community_volunteer_approved', 'Community Volunteer Approved', 'You have been approved as a Community Volunteer in "' . $community['name'] . '".');
                } else {
                    add_notification((int)$app['volunteer_user_id'], 'community_volunteer_declined', 'Community Volunteer Application Declined', 'Your Community Volunteer application in "' . $community['name'] . '" was declined.');
                }

                set_flash_message('success', 'Application updated.');
            }
        }

        redirect('community_creator_dashboard.php?community_id=' . $community_id);
    }
}

$flash = get_flash_message();

$is_member = ss_is_community_member($conn, $community_id, $user_id);
$member_count = (int)($stats['members'] ?? 0);
$post_count = (int)($stats['posts'] ?? 0);
$activeTab = 'manage';

$pending_join_requests = [];
if ($can_manage && $join_requests_enabled) {
    $sql = $join_answers_supported
        ? "SELECT r.request_id, r.user_id, r.join_reason, r.answers_json, r.created_at, u.username, u.full_name
           FROM community_join_requests r
           JOIN users u ON u.user_id = r.user_id
           WHERE r.community_id = ? AND r.status = 'pending'
           ORDER BY r.created_at DESC"
        : "SELECT r.request_id, r.user_id, r.join_reason, r.created_at, u.username, u.full_name
           FROM community_join_requests r
           JOIN users u ON u.user_id = r.user_id
           WHERE r.community_id = ? AND r.status = 'pending'
           ORDER BY r.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $community_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $pending_join_requests[] = $row; }
        $stmt->close();
    }
}

// Stats
$stats = [
    'members' => 0,
    'posts' => 0,
    'comments' => 0,
    'reports_pending' => 0,
];

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM community_members WHERE community_id = ?");
$stmt->bind_param('i', $community_id);
$stmt->execute();
$stats['members'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM community_posts WHERE community_id = ? AND status = 'published'");
$stmt->bind_param('i', $community_id);
$stmt->execute();
$stats['posts'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM community_comments WHERE community_id = ? AND status = 'published'");
$stmt->bind_param('i', $community_id);
$stmt->execute();
$stats['comments'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM community_reports WHERE community_id = ? AND status = 'pending'");
$stmt->bind_param('i', $community_id);
$stmt->execute();
$stats['reports_pending'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Volunteer needs
$needs = [];
$stmt = $conn->prepare("SELECT n.*
                        FROM community_volunteer_needs n
                        WHERE n.community_id = ?
                        ORDER BY n.created_at DESC");
$stmt->bind_param('i', $community_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $needs[] = $row; }
$stmt->close();

// Pending volunteer applications
$applications = [];
$stmt = $conn->prepare("SELECT a.application_id, a.volunteer_user_id, a.message, a.status, a.created_at,
                               u.username, u.full_name
                        FROM community_volunteer_applications a
                        JOIN users u ON u.user_id = a.volunteer_user_id
                        WHERE a.community_id = ? AND a.status = 'pending'
                        ORDER BY a.created_at DESC");
$stmt->bind_param('i', $community_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $applications[] = $row; }
$stmt->close();

// Current community volunteers
$volunteers = [];
$stmt = $conn->prepare("SELECT m.user_id, u.username, u.full_name, m.joined_at
                        FROM community_members m
                        JOIN users u ON u.user_id = m.user_id
                        WHERE m.community_id = ? AND m.role = 'volunteer'
                        ORDER BY m.joined_at DESC");
$stmt->bind_param('i', $community_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $volunteers[] = $row; }
$stmt->close();
// Updated by Shuvo - END
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Community | <?php echo htmlspecialchars($community['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        <?php include __DIR__ . '/includes/community_group_header_styles.php'; ?>
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .card { background: var(--bg-card, #F8F9F7); border: 1px solid var(--border-soft, #D8E2DD); border-radius: 18px; padding: 16px; box-shadow: var(--shadow-sm); }
        .kpi { font-weight: 950; font-size: 1.6rem; }
        .label { color: var(--text-secondary); font-weight: 800; }
        .list { margin-top: 12px; }
        .item { padding: 12px; border: 1px solid var(--border-soft, #D8E2DD); border-radius: 12px; margin-top: 10px; background: #fff; }
        .row { display:flex; justify-content: space-between; align-items:flex-start; gap: 10px; flex-wrap: wrap; }
        .badge { display:inline-block; padding: 5px 10px; border-radius: 999px; background: rgba(127, 175, 163, 0.18); font-weight: 900; font-size: 0.78rem; color: var(--text-primary); }
        .muted { color: var(--text-secondary); }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="top-bar">
            <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">Manage Community</h2>
        </div>

        <div class="content-area">
            <div class="wrap">
                <?php include __DIR__ . '/includes/community_group_header.php'; ?>

                <?php if ($flash): ?>
                    <div class="alert <?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
                <?php endif; ?>

                <div class="card" style="margin-bottom: 12px;">
                    <div style="font-weight: 950; font-size: 1.05rem;">Manage moderation, volunteers, engagement, and safety signals.</div>
                    <div class="muted" style="margin-top: 6px;">Creator has higher privileges than moderator.</div>
                </div>

                <div class="grid" style="margin-bottom: 14px;">
                    <div class="card"><div class="kpi"><?php echo (int)$stats['members']; ?></div><div class="label">Members</div></div>
                    <div class="card"><div class="kpi"><?php echo (int)$stats['posts']; ?></div><div class="label">Posts</div></div>
                    <div class="card"><div class="kpi"><?php echo (int)$stats['comments']; ?></div><div class="label">Comments</div></div>
                    <div class="card"><div class="kpi"><?php echo (int)$stats['reports_pending']; ?></div><div class="label">Pending reports</div></div>
                </div>

                <?php if ($join_requests_enabled): ?>
                    <div class="card" style="margin-bottom: 14px;">
                        <div style="font-weight: 950;">Pending Join Requests</div>
                        <div class="muted" style="margin-top: 6px;">Approve or decline requests to access posts and participate.</div>
                        <?php if (!$join_answers_supported): ?>
                            <div class="alert warning" style="margin-top: 10px;">Join-request answers are not available because the database is missing the <strong>answers_json</strong> column. Run: <strong>ALTER TABLE community_join_requests ADD COLUMN answers_json TEXT NULL AFTER join_reason;</strong></div>
                        <?php endif; ?>

                        <div class="list">
                            <?php if (empty($pending_join_requests)): ?>
                                <div class="muted">No pending join requests.</div>
                            <?php else: ?>
                                <?php foreach ($pending_join_requests as $r): ?>
                                    <?php
                                        $answers = [];
                                        if (!empty($r['answers_json'])) {
                                            $decoded = json_decode((string)$r['answers_json'], true);
                                            if (is_array($decoded)) { $answers = $decoded; }
                                        }
                                    ?>
                                    <div class="item">
                                        <div class="row">
                                            <div>
                                                <div style="font-weight: 900;">
                                                    <?php echo htmlspecialchars(($r['full_name'] ?: $r['username']) ?? 'User'); ?>
                                                    <span class="badge" style="margin-left: 8px;">Pending</span>
                                                </div>

                                                <?php if (!empty($answers['support_goal'])): ?>
                                                    <div class="muted" style="margin-top: 6px;"><strong>Support goal:</strong> <?php echo htmlspecialchars((string)$answers['support_goal']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($answers['boundaries'])): ?>
                                                    <div class="muted" style="margin-top: 6px;"><strong>Boundaries:</strong> <?php echo htmlspecialchars((string)$answers['boundaries']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($answers['belong_reason'])): ?>
                                                    <div class="muted" style="margin-top: 6px;"><strong>Belonging:</strong> <?php echo htmlspecialchars((string)$answers['belong_reason']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($answers['contribution'])): ?>
                                                    <div class="muted" style="margin-top: 6px;"><strong>Contribution:</strong> <?php echo htmlspecialchars((string)$answers['contribution']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($r['join_reason'])): ?>
                                                    <div class="muted" style="margin-top: 6px; white-space: pre-wrap;"><strong>Extra:</strong> <?php echo htmlspecialchars((string)$r['join_reason']); ?></div>
                                                <?php endif; ?>
                                                <div class="muted" style="margin-top: 6px;">Requested: <?php echo date('M j, Y H:i', strtotime($r['created_at'])); ?></div>
                                            </div>

                                            <div style="display:flex; gap: 8px; flex-wrap: wrap; align-items:flex-start;">
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                                                    <input type="hidden" name="decision" value="approve">
                                                    <button class="btn btn-primary" type="submit" name="review_join_request">Approve</button>
                                                </form>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                                                    <input type="hidden" name="decision" value="decline">
                                                    <button class="btn btn-ghost" type="submit" name="review_join_request">Decline</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card" style="margin-bottom: 14px;">
                    <div style="font-weight: 950;">Request Community Volunteers</div>
                    <div class="muted" style="margin-top: 6px;">This request goes to Admin for approval. Once approved, volunteers can apply.</div>
                    <?php if ($is_creator): ?>
                        <form method="POST" style="margin-top: 10px;">
                            <div class="form-group">
                                <label>What help do you need? *</label>
                                <textarea class="form-input" name="justification" rows="4" required placeholder="Describe what you need help with, expected tone, boundaries, and what volunteers should do/avoid."></textarea>
                            </div>
                            <button class="btn btn-primary" type="submit" name="request_need">Send to Admin</button>
                        </form>
                    <?php else: ?>
                        <div class="alert warning" style="margin-top: 10px;">Only the community creator can request volunteers.</div>
                    <?php endif; ?>
                </div>

                <div class="card" style="margin-bottom: 14px;">
                    <div class="row">
                        <div>
                            <div style="font-weight: 950;">Volunteer Needs</div>
                            <div class="muted" style="margin-top: 6px;">Track admin approval and incoming applications.</div>
                        </div>
                    </div>
                    <div class="list">
                        <?php if (empty($needs)): ?>
                            <div class="muted">No volunteer needs requested yet.</div>
                        <?php else: ?>
                            <?php foreach ($needs as $n): ?>
                                <div class="item">
                                    <div class="row">
                                        <div>
                                            <div style="font-weight: 900;">Volunteer Need Request</div>
                                            <div class="muted" style="margin-top: 4px; white-space: pre-wrap;"><?php echo htmlspecialchars((string)$n['justification']); ?></div>
                                            <div class="muted" style="margin-top: 6px;">Status: <span class="badge"><?php echo htmlspecialchars(ucfirst($n['status'])); ?></span></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 14px;">
                    <div style="font-weight: 950;">Pending Volunteer Applications</div>
                    <div class="muted" style="margin-top: 6px;">Approve or decline volunteers for this community.</div>
                    <div class="list">
                        <?php if (empty($applications)): ?>
                            <div class="muted">No pending applications.</div>
                        <?php else: ?>
                            <?php foreach ($applications as $a): ?>
                                <div class="item">
                                    <div class="row">
                                        <div>
                                            <div style="font-weight: 900;">Community Volunteer Application</div>
                                            <div class="muted" style="margin-top: 4px;">Applicant: <?php echo htmlspecialchars(($a['full_name'] ?: $a['username']) ?? 'User'); ?></div>
                                            <?php if (!empty($a['message'])): ?>
                                                <div class="muted" style="margin-top: 6px; white-space: pre-wrap;"><?php echo htmlspecialchars($a['message']); ?></div>
                                            <?php endif; ?>
                                            <div class="muted" style="margin-top: 6px;">Submitted: <?php echo date('M j, Y H:i', strtotime($a['created_at'])); ?></div>
                                        </div>
                                        <div style="display:flex; gap: 8px;">
                                            <?php if ($is_creator): ?>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="application_id" value="<?php echo (int)$a['application_id']; ?>">
                                                    <input type="hidden" name="decision" value="approve">
                                                    <button class="btn btn-primary" type="submit" name="review_application">Approve</button>
                                                </form>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="application_id" value="<?php echo (int)$a['application_id']; ?>">
                                                    <input type="hidden" name="decision" value="decline">
                                                    <button class="btn btn-ghost" type="submit" name="review_application">Decline</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge">Creator only</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div style="font-weight: 950;">Current Community Volunteers</div>
                    <div class="list">
                        <?php if (empty($volunteers)): ?>
                            <div class="muted">No community volunteers yet.</div>
                        <?php else: ?>
                            <?php foreach ($volunteers as $v): ?>
                                <div class="item">
                                    <div style="font-weight: 900;"><?php echo htmlspecialchars(($v['full_name'] ?: $v['username']) ?? 'User'); ?></div>
                                    <div class="muted" style="margin-top: 4px;">Added: <?php echo date('M j, Y', strtotime($v['joined_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>
</body>
</html>
