<?php
/**
 * Mental Health Test Suite - Test Results Page
 * Displays test results with supportive messaging
 */

session_start();
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : 0;

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Fetch result
$stmt = $conn->prepare("
    SELECT utr.result_id, utr.test_id, utr.total_score, utr.result_category, utr.result_message, 
           utr.completed_at, mht.test_name, mht.test_slug, mht.test_icon
    FROM user_test_results utr
    JOIN mental_health_tests mht ON utr.test_id = mht.test_id
    WHERE utr.result_id = ? AND utr.user_id = ?
");
$stmt->bind_param("ii", $result_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$result = $res->fetch_assoc();
$stmt->close();

if (!$result) {
    set_flash_message('error', 'Result not found.');
    redirect('mental_health_tests.php');
}

$page_title = 'Test Results';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .results-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
        }

        .results-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(12, 27, 51, 0.05);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(12, 27, 51, 0.08);
            text-align: center;
            margin-bottom: 32px;
        }

        .test-icon-large {
            font-size: 64px;
            margin-bottom: 24px;
        }

        .test-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 16px;
        }

        .result-category {
            display: inline-block;
            background: linear-gradient(135deg, rgba(20, 184, 166, 0.15), rgba(126, 111, 255, 0.15));
            color: var(--primary);
            padding: 12px 28px;
            border-radius: 24px;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .result-message {
            font-size: 15px;
            line-height: 1.8;
            color: var(--text);
            margin-bottom: 32px;
            padding: 24px;
            background: rgba(20, 184, 166, 0.05);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
        }

        .disclaimer {
            background: rgba(255, 193, 7, 0.08);
            border-left: 4px solid #FFC107;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 32px;
            font-size: 13px;
            color: var(--text);
            line-height: 1.6;
        }

        .result-timestamp {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 24px;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 32px;
        }

        .btn {
            padding: 14px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #3ad0be);
            color: white;
            box-shadow: 0 4px 12px rgba(20, 184, 166, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(20, 184, 166, 0.35);
        }

        .btn-secondary {
            background: rgba(12, 27, 51, 0.05);
            color: var(--text);
            border: 1px solid rgba(12, 27, 51, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(12, 27, 51, 0.08);
        }

        .resources-section {
            background: rgba(20, 184, 166, 0.05);
            border: 1px solid rgba(20, 184, 166, 0.1);
            border-radius: 12px;
            padding: 24px;
            margin-top: 32px;
        }

        .resources-section h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 16px;
        }

        .resource-item {
            padding: 12px;
            background: white;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .resource-item:last-child {
            margin-bottom: 0;
        }

        .resource-icon {
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .results-container {
                padding: 12px;
            }

            .results-card {
                padding: 24px;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .test-name {
                font-size: 20px;
            }

            .result-category {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">Test Results</h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                        🔔 Notifications
                    </a>
                </div>
            </div>
            
            <div class="content-area">

        <div class="results-container">
            <div class="results-card">
                <div class="test-icon-large"><?php echo $result['test_icon']; ?></div>
                <h1 class="test-name"><?php echo htmlspecialchars($result['test_name']); ?></h1>

                <div class="result-category">
                    <?php echo htmlspecialchars($result['result_category']); ?>
                </div>

                <div class="result-timestamp">
                    Completed on <?php echo date('F d, Y \a\t g:i A', strtotime($result['completed_at'])); ?>
                </div>

                <div class="result-message">
                    <strong>Your Result:</strong><br><br>
                    <?php echo htmlspecialchars($result['result_message']); ?>
                </div>

                <div class="disclaimer">
                    <strong>⚠️ Important Reminder:</strong> This assessment is not a medical diagnosis. It is intended for self-reflection only. If you feel distressed or unsafe, please seek professional help or contact a mental health professional immediately.
                </div>

                <div class="resources-section">
                    <h3>📚 Next Steps</h3>
                    <div class="resource-item">
                        <span class="resource-icon">💙</span>
                        <span><strong>Explore Coping Resources:</strong> Discover techniques tailored to your results</span>
                    </div>
                    <div class="resource-item">
                        <span class="resource-icon">👥</span>
                        <span><strong>Connect with Community:</strong> Share experiences in safe support groups</span>
                    </div>
                    <div class="resource-item">
                        <span class="resource-icon">🎓</span>
                        <span><strong>Learn More:</strong> Access educational content about mental wellness</span>
                    </div>
                    <div class="resource-item">
                        <span class="resource-icon">🤝</span>
                        <span><strong>Seek Professional Help:</strong> Talk to a licensed mental health professional</span>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="mental_health_tests.php" class="btn btn-secondary">← Back to Tests</a>
                    <button type="button" class="btn btn-primary" onclick="window.print()" style="background: rgba(20, 184, 166, 0.1); color: var(--primary); border: 1px solid rgba(20, 184, 166, 0.3);">🖨️ Print Result</button>
                </div>
            </div>
        </div><!-- End results-container -->
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
</body>
</html>
