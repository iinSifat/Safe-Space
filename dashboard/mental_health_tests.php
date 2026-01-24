<?php
/**
 * Mental Health Test Suite - Test Selection & Start Page
 * Displays available tests and manages test flow
 */

session_start();
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

// Professionals do not use self-help modules
if (function_exists('is_professional') && is_professional()) {
    set_flash_message('info', 'Mental health tests are disabled for professional accounts.');
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];
$page_title = 'Mental Health Self-Assessment';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Fetch all available tests
$stmt = $conn->prepare("
    SELECT test_id, test_name, test_slug, description, test_icon, color_code, instructions, ethical_disclaimer
    FROM mental_health_tests
    WHERE is_active = TRUE
    ORDER BY test_id ASC
");
$stmt->execute();
$result = $stmt->get_result();
$tests = $result->fetch_all(MYSQLI_ASSOC);

// Fetch user's recent test results (last 5)
$stmt = $conn->prepare("
    SELECT utr.test_id, mht.test_name, utr.total_score, utr.result_category, utr.completed_at
    FROM user_test_results utr
    JOIN mental_health_tests mht ON utr.test_id = mht.test_id
    WHERE utr.user_id = ?
    ORDER BY utr.completed_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_results = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .tests-container {
            display: grid;
            grid-template-columns: repeat(2, minmax(280px, 340px));
            justify-content: center;
            gap: 22px;
        }

        .test-card {
            position: relative;
            display: flex;
            align-items: stretch;
            aspect-ratio: 1 / 1;
            border-radius: 18px;
            overflow: hidden;
            padding: 0;
            background-color: rgba(12, 27, 51, 0.12);
            background-size: cover;
            background-position: center;
            box-shadow: var(--shadow-md);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .test-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .test-card:hover::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(12, 27, 51, 0.12);
            pointer-events: none;
        }

        .test-card:focus-visible {
            outline: 3px solid rgba(20, 184, 166, 0.6);
            outline-offset: 3px;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Background images per test (relative to /dashboard) */
        .test-card.stress-test {
            background-image:
                url('../images/stressed1.png');
        }

        .test-card.anxiety-test {
            background-image:
                url('../images/anxiety1.png');
        }

        .test-card.ocd-test {
            background-image:
                url('../images/ocd.png');
        }

        .test-card.depression-test {
            background-image:
                url('../images/depression.png');
        }

        .test-info {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: #e9edf5;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.25);
        }
        .test-info span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .start-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            width: 100%;
            font-size: 14px;
        }
        .start-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(20, 184, 166, 0.3);
        }
        
        .disclaimer-box {
            background: rgba(255, 193, 7, 0.08);
            border-left: 4px solid #FFC107;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 32px;
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.6;
        }
        .disclaimer-box strong {
            color: #FF9800;
        }
        
        .recent-results {
            background: rgba(20, 184, 166, 0.05);
            border-radius: 12px;
            padding: 24px;
            margin-top: 32px;
        }
        .recent-results h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 16px;
        }
        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: var(--bg-card, #F8F9F7);
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .result-item:last-child {
            margin-bottom: 0;
        }
        .result-test-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .result-category {
            background: rgba(20, 184, 166, 0.15);
            color: var(--primary-color);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .result-date {
            color: var(--text-secondary);
            font-size: 12px;
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        .page-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 8px;
        }
        .page-header p {
            font-size: 16px;
            color: var(--text-secondary);
            margin: 0;
        }

        .page-shell {
            max-width: 1100px;
            margin: 0 auto;
        }
        
        @media (max-width: 720px) {
            .tests-container {
                grid-template-columns: 1fr;
            }
            .page-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M9 11l3 3L22 4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Mental Health Self-Assessment</h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                        <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Notifications
                    </a>
                </div>
            </div>
            
            <div class="content-area">
                <div class="page-shell">
                    <div class="page-header">
                        <h1><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 12px; vertical-align: middle;"><path d="M9 11l3 3L22 4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Mental Health Self-Assessment</h1>
                        <p>Reflect on your well-being with these self-guided assessments</p>
                    </div>

                    <div class="disclaimer-box">
                        <strong><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 6px; vertical-align: middle;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3.05h16.94a2 2 0 0 0 1.71-3.05L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Important:</strong> These assessments are not medical diagnoses. They are intended for self-reflection only. If you feel distressed or unsafe, please seek professional help or contact a mental health professional immediately.
                    </div>

                    <div class="tests-container">
                        <?php foreach ($tests as $test): ?>
                            <?php $slug_class = preg_replace('~[^a-z0-9\-]~i', '', $test['test_slug']); ?>
                            <a href="take_test.php?test_id=<?php echo (int)$test['test_id']; ?>" class="test-card <?php echo htmlspecialchars($slug_class); ?>" style="text-decoration: none; color: inherit;" aria-label="Start <?php echo htmlspecialchars($test['test_name']); ?>">
                                <span class="sr-only">Start <?php echo htmlspecialchars($test['test_name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($recent_results)): ?>
                        <div class="recent-results">
                            <h3><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><line x1="12" y1="2" x2="12" y2="22"/><polyline points="4 7 12 2 20 7"/><polyline points="4 17 12 22 20 17"/><line x1="2" y1="12" x2="22" y2="12"/></svg>Your Recent Results</h3>
                            <?php foreach ($recent_results as $result): ?>
                                <div class="result-item">
                                    <div>
                                        <div class="result-test-name"><?php echo htmlspecialchars($result['test_name']); ?></div>
                                        <div class="result-date"><?php echo date('M d, Y', strtotime($result['completed_at'])); ?></div>
                                    </div>
                                    <span class="result-category"><?php echo htmlspecialchars($result['result_category']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div><!-- End page-shell -->
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
</body>
</html>
