<?php
/**
 * Volunteer Application (Community Members)
 */
require_once '../config/config.php';
require_login();

$user_id = get_user_id();
$user_type = get_user_type();
// Only patients/community members can apply; professionals should use the professional workspace.
if (!in_array($user_type, ['patient', 'volunteer'], true)) {
    set_flash_message('error', 'Volunteer applications are available for Community Members only.');
    redirect('index.php');
}

$db = Database::getInstance();
$conn = $db->getConnection();
$errors = [];
$success = '';
$latest_application = null;
$application_status = null;

// Updated by Shuvo - START
// Identity source-of-truth: always use the logged-in user's account name/email.
$stmt_user = $conn->prepare("SELECT username, email, full_name FROM users WHERE user_id = ? LIMIT 1");
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$account_user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$account_user) {
    set_flash_message('error', 'Unable to load your account information.');
    redirect('index.php');
}

$account_full_name = trim((string)($account_user['full_name'] ?? ''));
if ($account_full_name === '') {
    $account_full_name = (string)($account_user['username'] ?? '');
}
$account_email = (string)($account_user['email'] ?? '');
// Updated by Shuvo - END

// Determine volunteer status
$is_approved_volunteer = false;
if ($user_type === 'volunteer') {
    $is_approved_volunteer = true;
} else {
    $is_approved_volunteer = user_has_volunteer_permission($user_id);
}

// Fetch latest application status (to avoid duplicate submissions and to treat approved apps as volunteer)
$stmt_latest = $conn->prepare("SELECT status, submitted_at FROM volunteer_applications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt_latest->bind_param("i", $user_id);
$stmt_latest->execute();
$latest_application = $stmt_latest->get_result()->fetch_assoc();
$stmt_latest->close();

if ($latest_application) {
    $application_status = $latest_application['status'] ?? null;
    if ($application_status === 'approved') {
        $is_approved_volunteer = true;
    }
}

$has_pending_application = ($application_status === 'pending');
$can_submit_application = (!$is_approved_volunteer && !$has_pending_application);
$form = [
    'education' => '',
    'training_certifications' => '',
    'trainee_organization' => '',
    'experience' => '',
    'motivation' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_submit_application) {
        // Block submissions if already volunteer or already applied.
        redirect('volunteer_apply.php');
    }

    $form['education'] = sanitize_input($_POST['education'] ?? '');
    $form['training_certifications'] = sanitize_input($_POST['training_certifications'] ?? '');
    $form['trainee_organization'] = sanitize_input($_POST['trainee_organization'] ?? '');
    $form['experience'] = sanitize_input($_POST['experience'] ?? '');
    $form['motivation'] = sanitize_input($_POST['motivation'] ?? '');

    // Updated by Shuvo - START
    // Do not accept name/email from the form; always bind application to account identity.
    if (empty($account_full_name) || empty($account_email)) {
        $errors[] = 'Your account name and email are required before applying.';
    }
    // Updated by Shuvo - END

    // Handle file uploads (multiple)
    $uploaded_paths = [];
    if (!empty($_FILES['documents']['name'][0])) {
        $count = count($_FILES['documents']['name']);
        for ($i = 0; $i < $count; $i++) {
            $name = $_FILES['documents']['name'][$i];
            $tmp = $_FILES['documents']['tmp_name'][$i];
            $size = $_FILES['documents']['size'][$i];
            $err = $_FILES['documents']['error'][$i];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($err === UPLOAD_ERR_OK) {
                if ($size > MAX_FILE_SIZE) { $errors[] = "File $name exceeds max size."; continue; }
                $allowed = ['pdf','jpg','jpeg','png'];
                if (!in_array($ext, $allowed)) { $errors[] = "File $name has invalid type."; continue; }
                $destDir = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'volunteer_docs';
                if (!is_dir($destDir)) { @mkdir($destDir, 0777, true); }
                $safeName = time() . '_' . $user_id . '_' . preg_replace('/[^a-zA-Z0-9_\.\-]/','_', $name);
                $dest = $destDir . DIRECTORY_SEPARATOR . $safeName;
                if (move_uploaded_file($tmp, $dest)) {
                    // Store relative path
                    $rel = str_replace(dirname(__DIR__,1) . DIRECTORY_SEPARATOR, '', $dest);
                    $uploaded_paths[] = $rel;
                } else {
                    $errors[] = "Failed to upload $name.";
                }
            } else {
                $errors[] = "Error uploading $name.";
            }
        }
    }

    if (empty($errors)) {
        $docs_json = json_encode($uploaded_paths);
        // Updated by Shuvo - START
        $stmt = $conn->prepare("INSERT INTO volunteer_applications (user_id, full_name, education, training_certifications, trainee_organization, experience, motivation, document_paths) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $user_id, $account_full_name, $form['education'], $form['training_certifications'], $form['trainee_organization'], $form['experience'], $form['motivation'], $docs_json);
        // Updated by Shuvo - END
        if ($stmt->execute()) {
            add_notification($user_id, 'volunteer_submitted', 'Volunteer Application Submitted', 'Your application has been submitted for review. We will notify you upon a decision.');
            set_flash_message('success', 'Application submitted successfully. Admin will review your application.');
            redirect('index.php');
        } else {
            $errors[] = 'Submission failed. Please try again.';
        }
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply to Become a Volunteer | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
        <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .page { max-width: 800px; margin: 40px auto; padding: 0 16px; }
        .card { background: var(--bg-card, #F8F9F7); border-radius: 20px; padding: 24px; box-shadow: var(--shadow-sm); }
        .notice { background: rgba(127, 175, 163, 0.15); border: 1px solid rgba(127, 175, 163, 0.35); border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-label { font-weight: 700; display:block; margin-bottom: 8px; }
        .helper { color: var(--text-secondary); font-size: 0.9rem; }
        .divider { margin: 16px 0; color: var(--text-secondary); }
    </style>
</head>
<body>
        <div class="dashboard-wrapper">
            <?php include 'includes/sidebar.php'; ?>
        
            <main class="main-content">
                <div class="top-bar">
                    <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M16 11a2 2 0 100-4 2 2 0 000 4zM9 7a4 4 0 100 8 4 4 0 000-8z"/></svg>Apply to Volunteer</h2>
                    <div class="top-bar-right">
                        <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                            <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            Notifications
                        </a>
                    </div>
                </div>
            
                <div class="content-area">
    <div class="page">
        <div class="card">
            <h1 style="margin:0 0 8px;">Apply to Become a Volunteer</h1>
            <p class="helper">Volunteering requires admin review and approval. Submitting an application does not guarantee acceptance.</p>
            <?php if ($is_approved_volunteer): ?>
                <div class="notice">
                    <strong>You are already a volunteer.</strong>
                    <div class="helper" style="margin-top:6px;">Thank you for supporting the community.</div>
                </div>
            <?php elseif ($has_pending_application): ?>
                <div class="notice">
                    <strong>Your volunteer application is already submitted and pending review.</strong>
                    <div class="helper" style="margin-top:6px;">We’ll notify you once an admin approves or declines it.</div>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($can_submit_application): ?>
            <form method="POST" enctype="multipart/form-data">
                <!-- Updated by Shuvo - START -->
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name</label>
                    <input class="form-input" id="full_name" value="<?php echo htmlspecialchars($account_full_name); ?>" readonly>
                    <div class="helper">Taken from your account (read-only).</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-input" id="email" value="<?php echo htmlspecialchars($account_email); ?>" readonly>
                    <div class="helper">Taken from your account (read-only).</div>
                </div>
                <!-- Updated by Shuvo - END -->
                <div class="form-group">
                    <label class="form-label" for="education">Educational Background *</label>
                    <textarea class="form-input" id="education" name="education" rows="3" required><?php echo htmlspecialchars($form['education']); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="training_certifications">Relevant Training / Certifications *</label>
                    <textarea class="form-input" id="training_certifications" name="training_certifications" rows="3" required><?php echo htmlspecialchars($form['training_certifications']); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="trainee_organization">Trainee Workplace / Organization *</label>
                    <input class="form-input" id="trainee_organization" name="trainee_organization" value="<?php echo htmlspecialchars($form['trainee_organization']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="experience">Relevant Experience (optional)</label>
                    <textarea class="form-input" id="experience" name="experience" rows="3"><?php echo htmlspecialchars($form['experience']); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="motivation">Motivation / Short Statement *</label>
                    <textarea class="form-input" id="motivation" name="motivation" rows="3" required><?php echo htmlspecialchars($form['motivation']); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="documents">Upload Certificates / Proof (PDF/JPG/PNG)</label>
                    <input type="file" id="documents" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                    <div class="helper">Max file size 5MB each</div>
                </div>
                <button type="submit" class="btn btn-primary">Submit Application</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
