<?php
/**
 * Volunteer Application (Community Members)
 */
require_once '../config/config.php';
require_login();

$user_id = get_user_id();
$user_type = get_user_type();
if ($user_type !== 'patient') {
    set_flash_message('error', 'Volunteer applications are available for Community Members only.');
    redirect('index.php');
}

$db = Database::getInstance();
$conn = $db->getConnection();
$errors = [];
$success = '';
$form = [
    'full_name' => '',
    'education' => '',
    'training_certifications' => '',
    'trainee_organization' => '',
    'experience' => '',
    'motivation' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['full_name'] = sanitize_input($_POST['full_name'] ?? '');
    $form['education'] = sanitize_input($_POST['education'] ?? '');
    $form['training_certifications'] = sanitize_input($_POST['training_certifications'] ?? '');
    $form['trainee_organization'] = sanitize_input($_POST['trainee_organization'] ?? '');
    $form['experience'] = sanitize_input($_POST['experience'] ?? '');
    $form['motivation'] = sanitize_input($_POST['motivation'] ?? '');

    if (empty($form['full_name'])) { $errors[] = 'Full name is required.'; }

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
        $stmt = $conn->prepare("INSERT INTO volunteer_applications (user_id, full_name, education, training_certifications, trainee_organization, experience, motivation, document_paths) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $user_id, $form['full_name'], $form['education'], $form['training_certifications'], $form['trainee_organization'], $form['experience'], $form['motivation'], $docs_json);
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
        .card { background: #fff; border-radius: 20px; padding: 24px; box-shadow: var(--shadow-sm); }
        .notice { background: rgba(20,184,166,0.1); border: 1px solid rgba(20,184,166,0.25); border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; }
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
                            🔔 Notifications
                        </a>
                    </div>
                </div>
            
                <div class="content-area">
    <div class="page">
        <div class="card">
            <h1 style="margin:0 0 8px;">Apply to Become a Volunteer</h1>
            <p class="helper">Volunteering requires admin review and approval. Submitting an application does not guarantee acceptance.</p>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name *</label>
                    <input class="form-input" id="full_name" name="full_name" value="<?php echo htmlspecialchars($form['full_name']); ?>" required>
                </div>
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
        </div>
    </div>
</body>
</html>
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
