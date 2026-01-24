<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

$is_professional_user = function_exists('is_professional') && is_professional();
if (function_exists('ensure_professional_sessions_table')) {
    ensure_professional_sessions_table();
}

$is_emergency_mode = (!$is_professional_user && isset($_GET['emergency']) && (string)$_GET['emergency'] === '1');

// Client -> request a session with a professional (creates a request record).
if (!$is_professional_user && isset($_GET['request_session'])) {
    $pro_user_id = (int)($_GET['request_session'] ?? 0);
    if ($pro_user_id > 0) {
        $check = $conn->prepare("SELECT is_accepting_patients FROM professionals WHERE user_id = ? LIMIT 1");
        $check->bind_param('i', $pro_user_id);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();

        $accepting = (int)($row['is_accepting_patients'] ?? 0);
        if ($accepting !== 1) {
            set_flash_message('error', 'This professional is not currently accepting new clients.');
            redirect('professionals.php');
        }

        $alias = function_exists('professional_client_alias') ? professional_client_alias((int)$user_id) : ('Client-' . (int)$user_id);
        $insert = $conn->prepare("INSERT INTO professional_sessions (professional_user_id, client_user_id, client_alias, status) VALUES (?, ?, ?, 'requested')");
        if ($insert) {
            $insert->bind_param('iis', $pro_user_id, $user_id, $alias);
            $insert->execute();
            $insert->close();
        }

        set_flash_message('success', 'Session request submitted. The professional will review it.');
        redirect('professionals.php');
    }
}

// Client -> request emergency help from a professional (creates a critical request record).
if (!$is_professional_user && isset($_GET['request_emergency'])) {
    $pro_user_id = (int)($_GET['request_emergency'] ?? 0);
    if ($pro_user_id > 0) {
        $check = $conn->prepare("SELECT is_accepting_patients FROM professionals WHERE user_id = ? LIMIT 1");
        $check->bind_param('i', $pro_user_id);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();

        $accepting = (int)($row['is_accepting_patients'] ?? 0);
        if ($accepting !== 1) {
            set_flash_message('error', 'This professional is not currently accepting new clients.');
            redirect('professionals.php?emergency=1');
        }

        $alias = function_exists('professional_client_alias') ? professional_client_alias((int)$user_id) : ('Client-' . (int)$user_id);
        $primary_concern = 'Emergency support request';
        $risk_level = 'critical';
        $is_emergency = 1;

        $insert = $conn->prepare("INSERT INTO professional_sessions (professional_user_id, client_user_id, client_alias, status, primary_concern, risk_level, is_emergency) VALUES (?, ?, ?, 'requested', ?, ?, ?)");
        if ($insert) {
            $insert->bind_param('iisssi', $pro_user_id, $user_id, $alias, $primary_concern, $risk_level, $is_emergency);
            $insert->execute();
            $insert->close();
        }

        set_flash_message('success', 'Emergency request submitted. If you are in immediate danger, call your local emergency number now.');
        redirect('professionals.php?emergency=1');
    }
}

// Professional -> session workflow actions (accept/decline/complete/cancel/no-show)
if ($is_professional_user && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_action'], $_POST['session_id'])) {
    $session_id = (int)($_POST['session_id'] ?? 0);
    $action = (string)($_POST['session_action'] ?? '');

    if ($session_id > 0) {
        $allowed = ['accept', 'decline', 'complete', 'cancel', 'no_show', 'update_notes'];
        if (in_array($action, $allowed, true)) {
            if ($action === 'update_notes') {
                $private_notes = sanitize_input($_POST['private_notes'] ?? '');
                $risk_assessment = sanitize_input($_POST['risk_assessment'] ?? 'low');
                $follow_up_required = isset($_POST['follow_up_required']) ? 1 : 0;
                $scheduled_at = trim((string)($_POST['scheduled_at'] ?? ''));
                $scheduled_at_val = ($scheduled_at !== '') ? date('Y-m-d H:i:s', strtotime($scheduled_at)) : null;

                $upd = $conn->prepare("UPDATE professional_sessions SET private_notes = ?, risk_assessment = ?, follow_up_required = ?, scheduled_at = COALESCE(?, scheduled_at) WHERE session_id = ? AND professional_user_id = ?");
                if ($upd) {
                    $upd->bind_param('ssisii', $private_notes, $risk_assessment, $follow_up_required, $scheduled_at_val, $session_id, $user_id);
                    $upd->execute();
                    $upd->close();
                    set_flash_message('success', 'Session notes updated.');
                }
            } else {
                $map = [
                    'accept' => 'accepted',
                    'decline' => 'declined',
                    'complete' => 'completed',
                    'cancel' => 'cancelled',
                    'no_show' => 'no_show'
                ];
                $new_status = $map[$action] ?? null;
                if ($new_status) {
                    $upd = $conn->prepare("UPDATE professional_sessions SET status = ? WHERE session_id = ? AND professional_user_id = ?");
                    if ($upd) {
                        $upd->bind_param('sii', $new_status, $session_id, $user_id);
                        $upd->execute();
                        $upd->close();
                        set_flash_message('success', 'Session updated.');
                    }
                }
            }
        }
    }
    redirect('professionals.php');
}

// Database-backed professionals
$professionals = [];
$prof_stmt = $conn->prepare("SELECT p.user_id AS id, p.full_name AS name, p.specialization, p.consultation_fee AS fee, p.verification_status, p.is_accepting_patients FROM professionals p JOIN users u ON u.user_id = p.user_id WHERE u.user_type = 'professional' AND u.is_active = 1 ORDER BY (p.verification_status = 'verified') DESC, p.full_name ASC");
if ($prof_stmt) {
    $prof_stmt->execute();
    $res = $prof_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $professionals[] = [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'specialization' => (string)($row['specialization'] ?? ''),
            'rating' => 0,
            'fee' => (float)($row['fee'] ?? 0),
            'verified' => (($row['verification_status'] ?? '') === 'verified'),
            'is_accepting_patients' => ((int)($row['is_accepting_patients'] ?? 0) === 1)
        ];
    }
    $prof_stmt->close();
}

// Keep full list for client-side suggestions/live filtering
$all_professionals = $professionals;

// Filter/search handling (ported from DBMS)
$q = trim($_GET['q'] ?? '');
$spec = trim($_GET['spec'] ?? '');

if ($q !== '' || ($spec !== '' && $spec !== 'All Specializations')) {
    $filtered = [];
    $qLower = mb_strtolower($q);
    foreach ($professionals as $p) {
        $matchesQ = true;
        $matchesSpec = true;

        if ($q !== '') {
            $nameLower = mb_strtolower($p['name']);
            $specLower = mb_strtolower($p['specialization']);
            $matchesQ = (mb_strpos($nameLower, $qLower) !== false) || (mb_strpos($specLower, $qLower) !== false);
        }

        if ($spec !== '' && $spec !== 'All Specializations') {
            $matchesSpec = ($p['specialization'] === $spec);
        }

        if ($matchesQ && $matchesSpec) {
            $filtered[] = $p;
        }
    }

    $professionals = $filtered;
}

    // Professional workspace: fetch your session queue (shown using existing card styles)
    $my_session_requests = [];
    $my_upcoming_sessions = [];
    if ($is_professional_user) {
        $req_stmt = $conn->prepare("SELECT session_id, client_alias, primary_concern, risk_level, preferred_session_type, preferred_duration_minutes, is_emergency, scheduled_at, status, private_notes, risk_assessment, follow_up_required, created_at FROM professional_sessions WHERE professional_user_id = ? AND status IN ('requested','accepted') ORDER BY (status='requested') DESC, COALESCE(scheduled_at, created_at) ASC LIMIT 20");
        if ($req_stmt) {
            $req_stmt->bind_param('i', $user_id);
            $req_stmt->execute();
            $rows = $req_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as $r) {
                if (($r['status'] ?? '') === 'requested') {
                    $my_session_requests[] = $r;
                } else {
                    $my_upcoming_sessions[] = $r;
                }
            }
            $req_stmt->close();
        }
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mental Health Professionals | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
        <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .professionals-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .filter-input {
            padding: 10px 16px;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            flex: 1;
            min-width: 200px;
        }

        .professionals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .professional-card {
            background: var(--bg-card, #F8F9F7);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-normal);
            display: flex;
            flex-direction: column;
        }

        .professional-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }

        .professional-header {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .professional-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }

        .professional-info h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .professional-spec {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .rating {
            color: #FFB84D;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .rating-star {
            color: #FFB84D;
        }

        .verified-badge {
            display: inline-block;
            background: rgba(111, 207, 151, 0.15);
            color: #2d7a4d;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .professional-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            flex: 1;
        }

        .professional-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--light-gray);
            padding-top: 1rem;
        }

        .fee {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .book-btn {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 600;
            transition: all var(--transition-fast);
        }

        .book-btn:hover {
            background: var(--primary-dark);
        }

        /* Search suggestions (ported from DBMS) */
        .suggestions-list {
            position: absolute;
            background: var(--bg-card, #F8F9F7);
            border: 1px solid var(--border-soft, #D8E2DD);
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            width: 100%;
            max-height: 240px;
            overflow: auto;
            z-index: 60;
            box-sizing: border-box;
        }

        .suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.95rem;
            color: var(--text-primary);
        }

        .suggestion-item:hover,
        .suggestion-item.active {
            background: rgba(0,0,0,0.04);
        }

        /* Keep original search input sizing inside wrapper */
        .search-wrap .filter-input {
            width: 100%;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
        <div class="dashboard-wrapper">
            <?php include 'includes/sidebar.php'; ?>
        
            <main class="main-content">
                <div class="top-bar">
                    <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg><?php echo $is_professional_user ? 'Session Workspace' : 'Find Professionals'; ?></h2>
                    <div class="top-bar-right">
                        <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                            <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            Notifications
                        </a>
                    </div>
                </div>
            
                <div class="content-area">
    <div class="professionals-container">
        <?php if (!$is_professional_user): ?>
            <!-- Header -->
            <div class="header">
                <h1><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 12px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg>Mental Health Professionals</h1>
                <?php if ($is_emergency_mode): ?>
                    <p>If you are in immediate danger, call your local emergency number now. You can request urgent support below.</p>
                <?php else: ?>
                    <p>Connect with verified, licensed mental health professionals for personalized support</p>
                <?php endif; ?>
            </div>

            <!-- Search & Filters -->
            <form class="filters" method="GET" action="professionals.php">
                <div class="search-wrap" style="position: relative; flex:1;">
                    <input id="prof-search" type="text" name="q" class="filter-input" placeholder="Search by name or specialization..." autocomplete="off" value="<?php echo htmlspecialchars($q ?? ''); ?>">
                    <div id="suggestions" class="suggestions-list" style="display:none;"></div>
                </div>
                <select id="prof-spec" name="spec" class="filter-input" style="max-width: 250px;">
                    <option<?php echo ($spec === '' || $spec === 'All Specializations') ? ' selected' : ''; ?>>All Specializations</option>
                    <option value="Depression & Anxiety"<?php echo ($spec === 'Depression & Anxiety') ? ' selected' : ''; ?>>Depression & Anxiety</option>
                    <option value="Trauma & PTSD"<?php echo ($spec === 'Trauma & PTSD') ? ' selected' : ''; ?>>Trauma & PTSD</option>
                    <option value="Relationship Issues"<?php echo ($spec === 'Relationship Issues') ? ' selected' : ''; ?>>Relationship Issues</option>
                    <option value="Work Stress & Burnout"<?php echo ($spec === 'Work Stress & Burnout') ? ' selected' : ''; ?>>Work Stress & Burnout</option>
                </select>
                <?php if ($is_emergency_mode): ?>
                    <input type="hidden" name="emergency" value="1">
                <?php endif; ?>
            </form>

            <?php if ($is_emergency_mode): ?>
                <div class="professional-card" data-skip-filter="1" style="grid-column: 1 / -1;">
                    <div class="professional-header">
                        <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" class="professional-avatar"><path d="M12 2l7 4v6c0 5-3 9-7 10-4-1-7-5-7-10V6l7-4z"/></svg>
                        <div class="professional-info">
                            <h3>Emergency & Crisis Support</h3>
                            <p class="professional-spec">If you are in immediate danger, call your local emergency number now.</p>
                            <div class="rating"><span class="rating-star">★</span><span>Immediate help</span></div>
                        </div>
                    </div>
                    <div class="professional-description">
                        View hotline numbers on the Emergency page, then choose a professional below to send an urgent request.
                        <div style="margin-top: 10px;">
                            <a class="btn btn-secondary btn-small" style="text-decoration:none; display:inline-flex; align-items:center;" href="emergency.php">Open Emergency page</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Professionals Grid -->
        <?php if ($is_professional_user): ?>
            <div class="professional-card" data-skip-filter="1" style="grid-column: 1 / -1;">
                <div class="professional-header">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" class="professional-avatar"><path d="M12 2l7 4v6c0 5-3 9-7 10-4-1-7-5-7-10V6l7-4z"/></svg>
                    <div class="professional-info">
                        <h3>Schedule & Session Requests</h3>
                        <p class="professional-spec">Manage client requests, schedule sessions, and record private notes.</p>
                        <div class="rating"><span class="rating-star">★</span><span><?php echo count($my_session_requests) + count($my_upcoming_sessions); ?></span></div>
                    </div>
                </div>

                <?php if (count($my_session_requests) === 0 && count($my_upcoming_sessions) === 0): ?>
                    <div class="professional-description">No session requests yet. When clients request sessions, they will appear here.</div>
                <?php endif; ?>

                <?php foreach ($my_session_requests as $s): ?>
                    <div class="professional-description" style="margin-bottom: 12px;">
                        <strong><?php echo htmlspecialchars($s['client_alias'] ?? 'Client'); ?></strong>
                        • Risk: <?php echo htmlspecialchars($s['risk_level'] ?? 'low'); ?>
                        <?php if (!empty($s['primary_concern'])): ?>
                            • Concern: <?php echo htmlspecialchars($s['primary_concern']); ?>
                        <?php endif; ?>
                        <?php if (!empty($s['is_emergency'])): ?>
                            • Emergency
                        <?php endif; ?>
                        <div style="margin-top: 10px; display:flex; gap: 10px; flex-wrap: wrap;">
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="session_id" value="<?php echo (int)($s['session_id'] ?? 0); ?>">
                                <input type="hidden" name="session_action" value="accept">
                                <button type="submit" class="btn btn-primary btn-small">Accept</button>
                            </form>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="session_id" value="<?php echo (int)($s['session_id'] ?? 0); ?>">
                                <input type="hidden" name="session_action" value="decline">
                                <button type="submit" class="btn btn-secondary btn-small">Decline</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($my_upcoming_sessions as $s): ?>
                    <div class="professional-description" style="margin-bottom: 12px;">
                        <strong><?php echo htmlspecialchars($s['client_alias'] ?? 'Client'); ?></strong>
                        • Status: <?php echo htmlspecialchars($s['status'] ?? 'accepted'); ?>
                        <?php if (!empty($s['scheduled_at'])): ?>
                            • Scheduled: <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($s['scheduled_at']))); ?>
                        <?php endif; ?>
                        <div style="margin-top: 10px;">
                            <form method="POST" style="margin:0; display:grid; gap: 10px;">
                                <input type="hidden" name="session_id" value="<?php echo (int)($s['session_id'] ?? 0); ?>">
                                <input type="hidden" name="session_action" value="update_notes">
                                <input type="datetime-local" name="scheduled_at" class="filter-input" style="max-width: 320px;" value="<?php echo !empty($s['scheduled_at']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($s['scheduled_at']))) : ''; ?>">
                                <textarea name="private_notes" class="filter-input" style="min-height: 90px;" placeholder="Private professional notes..."><?php echo htmlspecialchars($s['private_notes'] ?? ''); ?></textarea>
                                <select name="risk_assessment" class="filter-input" style="max-width: 250px;">
                                    <option value="low" <?php echo (($s['risk_assessment'] ?? 'low') === 'low') ? 'selected' : ''; ?>>Risk assessment: low</option>
                                    <option value="medium" <?php echo (($s['risk_assessment'] ?? '') === 'medium') ? 'selected' : ''; ?>>Risk assessment: medium</option>
                                    <option value="high" <?php echo (($s['risk_assessment'] ?? '') === 'high') ? 'selected' : ''; ?>>Risk assessment: high</option>
                                    <option value="critical" <?php echo (($s['risk_assessment'] ?? '') === 'critical') ? 'selected' : ''; ?>>Risk assessment: critical</option>
                                </select>
                                <label style="display:flex; align-items:center; gap: 10px; color: var(--text-secondary); font-size: 0.95rem;">
                                    <input type="checkbox" name="follow_up_required" <?php echo !empty($s['follow_up_required']) ? 'checked' : ''; ?>> Follow-up recommended
                                </label>
                                <button type="submit" class="btn btn-secondary btn-small" style="justify-self:start;">Save Notes</button>
                            </form>

                            <div style="margin-top: 10px; display:flex; gap: 10px; flex-wrap: wrap;">
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="session_id" value="<?php echo (int)($s['session_id'] ?? 0); ?>">
                                    <input type="hidden" name="session_action" value="complete">
                                    <button type="submit" class="btn btn-primary btn-small">Mark Completed</button>
                                </form>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="session_id" value="<?php echo (int)($s['session_id'] ?? 0); ?>">
                                    <input type="hidden" name="session_action" value="cancel">
                                    <button type="submit" class="btn btn-secondary btn-small">Cancel</button>
                                </form>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="session_id" value="<?php echo (int)($s['session_id'] ?? 0); ?>">
                                    <input type="hidden" name="session_action" value="no_show">
                                    <button type="submit" class="btn btn-secondary btn-small">No-show</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$is_professional_user): ?>
        <div class="professionals-grid">
            <?php foreach ($all_professionals as $prof): ?>
                <div class="professional-card" data-name="<?php echo htmlspecialchars($prof['name']); ?>" data-specialization="<?php echo htmlspecialchars($prof['specialization']); ?>">
                    <div class="professional-header">
                        <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" class="professional-avatar"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg>
                        <div class="professional-info">
                            <h3><?php echo htmlspecialchars($prof['name']); ?></h3>
                            <p class="professional-spec"><?php echo htmlspecialchars($prof['specialization']); ?></p>
                            <div class="rating">
                                <span class="rating-star">★</span>
                                <span><?php echo $prof['rating']; ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($prof['verified']): ?>
                        <div class="verified-badge">✓ Verified Professional</div>
                    <?php endif; ?>

                    <div class="professional-description">
                        Experienced mental health professional dedicated to providing compassionate, evidence-based care tailored to your unique needs.
                    </div>

                    <div class="professional-footer">
                        <div class="fee">৳<?php echo $prof['fee']; ?>/session</div>
                        <?php if ($is_professional_user): ?>
                            <button class="book-btn" onclick="alert('Scheduling is managed in your workspace above.')">Workspace</button>
                        <?php else: ?>
                            <?php if (!empty($prof['is_accepting_patients'])): ?>
                                <?php if ($is_emergency_mode): ?>
                                    <a class="book-btn" href="professionals.php?request_emergency=<?php echo (int)$prof['id']; ?>">Request</a>
                                <?php else: ?>
                                    <a class="book-btn" href="professionals.php?request_session=<?php echo (int)$prof['id']; ?>">Request</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="book-btn" onclick="alert('This professional is not currently accepting new clients.')">Unavailable</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
            <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
            <?php if (!$is_professional_user): ?>
                <a href="mood_tracker.php" class="btn btn-secondary">Mood Tracker</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Build suggestion source from server-side data
        const suggestionSource = <?php
            $items = [];
            foreach ($all_professionals as $p) {
                $items[] = $p['name'];
                $items[] = $p['specialization'];
            }
            $items = array_values(array_unique($items));
            echo json_encode($items, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        ?>;

        const searchInput = document.getElementById('prof-search');
        const specSelect = document.getElementById('prof-spec');
        const suggestionsEl = document.getElementById('suggestions');
        let activeIndex = -1;
        let currentList = [];

        function applyLiveFilter(){
            const q = (searchInput?.value || '').trim().toLowerCase();
            const selectedSpec = (specSelect?.value || '').trim();
            const hasSpecFilter = selectedSpec !== '' && selectedSpec !== 'All Specializations';
            const cards = document.querySelectorAll('.professional-card');
            cards.forEach(card => {
                if (card.getAttribute('data-skip-filter') === '1') {
                    return;
                }
                const name = (card.getAttribute('data-name') || '').toLowerCase();
                const spec = (card.getAttribute('data-specialization') || '').toLowerCase();
                const matchesQuery = q === '' || name.includes(q) || spec.includes(q);
                const matchesSpec = !hasSpecFilter || (card.getAttribute('data-specialization') === selectedSpec);
                const matches = matchesQuery && matchesSpec;
                card.style.display = matches ? '' : 'none';
            });
        }

        function debounce(fn, wait){
            let t; return function(...args){ clearTimeout(t); t = setTimeout(()=>fn.apply(this,args), wait); };
        }

        function renderSuggestions(list){
            currentList = list;
            activeIndex = -1;
            if (!list || list.length === 0) {
                suggestionsEl.style.display = 'none';
                suggestionsEl.innerHTML = '';
                return;
            }
            suggestionsEl.style.display = 'block';
            suggestionsEl.innerHTML = list.map((it, idx) =>
                `<div class="suggestion-item" data-idx="${idx}" role="option">${it}</div>`
            ).join('');
        }

        function pickSuggestion(value){
            searchInput.value = value;
            suggestionsEl.style.display = 'none';
            applyLiveFilter();
        }

        const update = debounce(function(){
            const q = (searchInput.value || '').trim().toLowerCase();
            if (q === '') { renderSuggestions([]); return; }
            const matches = suggestionSource.filter(s => s.toLowerCase().includes(q));
            renderSuggestions(matches.slice(0,8));
        }, 150);

        searchInput?.addEventListener('input', () => {
            update();
            applyLiveFilter();
        });

        // Live filter on specialization change (no page reload)
        specSelect?.addEventListener('change', () => {
            applyLiveFilter();
        });

        // Click suggestions
        suggestionsEl?.addEventListener('click', function(e){
            const item = e.target.closest('.suggestion-item');
            if (item) pickSuggestion(item.textContent.trim());
        });

        // Keyboard navigation
        searchInput?.addEventListener('keydown', function(e){
            const items = suggestionsEl.querySelectorAll('.suggestion-item');
            if (items.length === 0) return;
            if (e.key === 'ArrowDown'){
                e.preventDefault(); activeIndex = Math.min(activeIndex + 1, items.length -1);
                items.forEach(i=>i.classList.remove('active'));
                items[activeIndex].classList.add('active');
                items[activeIndex].scrollIntoView({block:'nearest'});
            } else if (e.key === 'ArrowUp'){
                e.preventDefault(); activeIndex = Math.max(activeIndex - 1, 0);
                items.forEach(i=>i.classList.remove('active'));
                items[activeIndex].classList.add('active');
                items[activeIndex].scrollIntoView({block:'nearest'});
            } else if (e.key === 'Enter'){
                if (activeIndex >= 0 && items[activeIndex]){
                    e.preventDefault(); pickSuggestion(items[activeIndex].textContent.trim());
                }
            } else if (e.key === 'Escape'){
                suggestionsEl.style.display = 'none';
            }
        });

        // hide on outside click
        document.addEventListener('click', function(e){
            if (!e.target.closest('.search-wrap')) {
                suggestionsEl.style.display = 'none';
            }
        });

        // Apply filtering on initial load (covers prefilled q/spec)
        applyLiveFilter();
    </script>
</body>
</html>
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
