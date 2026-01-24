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
$focus_tags = ['support','awareness','recovery','learning','discussion'];
$sensitivity_levels = ['low','medium','high'];

$errors = [];
$form = [
    'community_name' => '',
    'focus_tag' => 'support',
    'sensitivity_level' => 'medium',
    'allow_anonymous_posts' => 0,
    'purpose_who_for' => '',
    'purpose_support_expected' => '',
    'why_needed' => '',
    'how_help' => '',
    'engagement_plan' => '',
    'safety_considerations' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_community_request'])) {
    $form['community_name'] = trim((string)($_POST['community_name'] ?? ''));
    $form['focus_tag'] = strtolower(trim((string)($_POST['focus_tag'] ?? 'support')));
    $form['sensitivity_level'] = strtolower(trim((string)($_POST['sensitivity_level'] ?? 'medium')));
    $form['allow_anonymous_posts'] = isset($_POST['allow_anonymous_posts']) ? 1 : 0;

    $form['purpose_who_for'] = trim((string)($_POST['purpose_who_for'] ?? ''));
    $form['purpose_support_expected'] = trim((string)($_POST['purpose_support_expected'] ?? ''));
    $form['why_needed'] = trim((string)($_POST['why_needed'] ?? ''));
    $form['how_help'] = trim((string)($_POST['how_help'] ?? ''));
    $form['engagement_plan'] = trim((string)($_POST['engagement_plan'] ?? ''));
    $form['safety_considerations'] = trim((string)($_POST['safety_considerations'] ?? ''));

    if ($form['community_name'] === '' || mb_strlen($form['community_name']) < 3) {
        $errors[] = 'Please enter a community name (min 3 characters).';
    }
    if (!in_array($form['focus_tag'], $focus_tags, true)) {
        $errors[] = 'Please choose a valid focus tag.';
    }
    if (!in_array($form['sensitivity_level'], $sensitivity_levels, true)) {
        $errors[] = 'Please choose a valid sensitivity level.';
    }

    $required_texts = [
        'purpose_who_for' => 'Who this community is for',
        'purpose_support_expected' => 'What kind of support is expected',
        'why_needed' => 'Why this community is needed',
        'how_help' => 'How it will help people',
        'engagement_plan' => 'How members will engage',
        'safety_considerations' => 'Sensitivity / safety considerations',
    ];
    foreach ($required_texts as $key => $label) {
        if ($form[$key] === '' || mb_strlen($form[$key]) < 10) {
            $errors[] = $label . ' is required (min 10 characters).';
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO community_requests (requested_by_user_id, community_name, focus_tag, sensitivity_level, allow_anonymous_posts, purpose_who_for, purpose_support_expected, why_needed, how_help, engagement_plan, safety_considerations, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->bind_param(
            'isssissssss',
            $user_id,
            $form['community_name'],
            $form['focus_tag'],
            $form['sensitivity_level'],
            $form['allow_anonymous_posts'],
            $form['purpose_who_for'],
            $form['purpose_support_expected'],
            $form['why_needed'],
            $form['how_help'],
            $form['engagement_plan'],
            $form['safety_considerations']
        );
        if ($stmt->execute()) {
            set_flash_message('success', 'Community creation request submitted. Admin will review it.');
            $stmt->close();
            redirect('community.php');
        }
        if (APP_DEBUG) {
            $errors[] = 'Database error: ' . ($stmt->error ?: $conn->error);
        }
        error_log('Community request insert failed: ' . ($stmt->error ?: $conn->error));
        $stmt->close();
        $errors[] = 'Unable to submit request. Please try again.';
    }
}
// Updated by Shuvo - END
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Community | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .page { max-width: 900px; margin: 0 auto; padding: 2rem 1rem; }
        .header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: #fff; padding: 2rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; }
        .card { background: var(--bg-card, #F8F9F7); border: 1px solid var(--border-soft, #D8E2DD); border-radius: 18px; padding: 18px; box-shadow: var(--shadow-sm); }
        .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }
        .helper { color: var(--text-secondary); font-size: 0.92rem; }
        textarea.form-input { min-height: 110px; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="top-bar">
            <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">Create New Community</h2>
        </div>
        <div class="content-area">
            <div class="page">
                <div class="header">
                    <h1 style="margin:0 0 8px; font-size: 2rem;">Request a New Community</h1>
                    <p style="margin:0; opacity: 0.92;">Your request will be reviewed by an admin before it becomes visible to others.</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert error">
                        <?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <form method="POST">
                        <div class="grid">
                            <div class="form-group">
                                <label>Community Name *</label>
                                <input class="form-input" name="community_name" value="<?php echo htmlspecialchars($form['community_name']); ?>" placeholder="e.g., Anxiety Support Circle" required>
                                <div class="helper">Choose a clear, respectful name.</div>
                            </div>
                            <div class="form-group">
                                <label>Focus Tag *</label>
                                <select class="form-input" name="focus_tag" required>
                                    <?php foreach ($focus_tags as $tag): ?>
                                        <option value="<?php echo htmlspecialchars($tag); ?>" <?php echo $form['focus_tag']===$tag?'selected':''; ?>><?php echo htmlspecialchars(ucfirst($tag)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="helper">Helps members understand the community’s intent.</div>
                            </div>
                            <div class="form-group">
                                <label>Sensitivity Level *</label>
                                <select class="form-input" name="sensitivity_level" required>
                                    <?php foreach ($sensitivity_levels as $lvl): ?>
                                        <option value="<?php echo htmlspecialchars($lvl); ?>" <?php echo $form['sensitivity_level']===$lvl?'selected':''; ?>><?php echo htmlspecialchars(ucfirst($lvl)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="helper">Low: general discussion. High: more careful wording expected.</div>
                            </div>
                            <div class="form-group" style="display:flex; align-items:center; gap: 10px;">
                                <input type="checkbox" id="anon" name="allow_anonymous_posts" <?php echo $form['allow_anonymous_posts'] ? 'checked' : ''; ?>>
                                <label for="anon" style="margin:0; font-weight: 800;">Allow anonymous posting (optional)</label>
                            </div>
                        </div>

                        <div class="divider" style="margin: 14px 0; color: var(--text-secondary);">Community purpose and safety</div>

                        <div class="form-group">
                            <label>Who this community is for *</label>
                            <textarea class="form-input" name="purpose_who_for" required><?php echo htmlspecialchars($form['purpose_who_for']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>What kind of support is expected *</label>
                            <textarea class="form-input" name="purpose_support_expected" required><?php echo htmlspecialchars($form['purpose_support_expected']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Why this community is needed *</label>
                            <textarea class="form-input" name="why_needed" required><?php echo htmlspecialchars($form['why_needed']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>How it will help people *</label>
                            <textarea class="form-input" name="how_help" required><?php echo htmlspecialchars($form['how_help']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>How members will engage *</label>
                            <textarea class="form-input" name="engagement_plan" required><?php echo htmlspecialchars($form['engagement_plan']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Sensitivity / safety considerations *</label>
                            <textarea class="form-input" name="safety_considerations" required><?php echo htmlspecialchars($form['safety_considerations']); ?></textarea>
                            <div class="helper">Example: tone guidelines, triggers to avoid, escalation expectations.</div>
                        </div>

                        <button class="btn btn-primary" type="submit" name="submit_community_request" style="width: 100%;">Submit Request for Admin Review</button>
                    </form>
                </div>

            </div>
        </div>
    </main>
</div>
</body>
</html>
