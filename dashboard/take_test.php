<?php
/**
 * Mental Health Test Suite - Test Taking Page
 * Displays questions one at a time with scoring
 */

session_start();
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Fetch test details
$stmt = $conn->prepare("
    SELECT test_id, test_name, test_slug, description, test_icon, ethical_disclaimer, instructions
    FROM mental_health_tests
    WHERE test_id = ? AND is_active = TRUE
");
$stmt->bind_param("i", $test_id);
$stmt->execute();
$result = $stmt->get_result();
$test = $result->fetch_assoc();
$stmt->close();

if (!$test) {
    set_flash_message('error', 'Test not found.');
    redirect('mental_health_tests.php');
}

// Fetch all questions for this test
$stmt = $conn->prepare("
    SELECT question_id, question_text, question_number
    FROM test_questions
    WHERE test_id = ?
    ORDER BY question_number ASC
");
$stmt->bind_param("i", $test_id);
$stmt->execute();
$result = $stmt->get_result();
$questions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answers'])) {
    $answers = $_POST['answers'];
    $total_score = 0;
    
    // Calculate total score
    foreach ($answers as $question_id => $score) {
        $total_score += (int)$score;
    }
    
    // Determine result category based on score
    $result_category = '';
    $result_message = '';
    
    // Define result interpretations
    $interpretations = [
        'stress-test' => [
            ['score' => 10, 'category' => 'Low Stress', 'message' => 'You\'re managing stress well. Keep up your healthy habits!'],
            ['score' => 20, 'category' => 'Mild Stress', 'message' => 'You have some stress, but it\'s manageable. Consider regular relaxation activities.'],
            ['score' => 30, 'category' => 'Moderate Stress', 'message' => 'This test suggests moderate stress. You may benefit from rest, support, or stress-management techniques.'],
            ['score' => 40, 'category' => 'High Stress', 'message' => 'You\'re experiencing significant stress. Professional support may be helpful.']
        ],
        'anxiety-test' => [
            ['score' => 10, 'category' => 'Minimal Anxiety', 'message' => 'Your anxiety levels appear to be well-managed.'],
            ['score' => 20, 'category' => 'Mild Anxiety', 'message' => 'You experience some anxiety. Breathing exercises and grounding techniques may help.'],
            ['score' => 30, 'category' => 'Moderate Anxiety', 'message' => 'This test suggests moderate anxiety. Consider exploring coping strategies or professional support.'],
            ['score' => 40, 'category' => 'Severe Anxiety', 'message' => 'You\'re experiencing significant anxiety. Speaking with a mental health professional is recommended.']
        ],
        'ocd-test' => [
            ['score' => 10, 'category' => 'No Significant OCD Traits', 'message' => 'You don\'t show significant signs of OCD tendencies.'],
            ['score' => 20, 'category' => 'Mild OCD Tendencies', 'message' => 'You show some mild traits. Many people have these; they\'re typically manageable.'],
            ['score' => 30, 'category' => 'Moderate OCD Tendencies', 'message' => 'This test suggests moderate OCD-like patterns. Professional evaluation could be beneficial.'],
            ['score' => 40, 'category' => 'High OCD Tendencies', 'message' => 'You\'re experiencing significant OCD-like symptoms. Speaking with a specialist is recommended.']
        ],
        'depression-test' => [
            ['score' => 10, 'category' => 'Stable Mood', 'message' => 'Your emotional well-being appears to be good. Keep engaging in activities that bring you joy.'],
            ['score' => 20, 'category' => 'Mild Depressive Symptoms', 'message' => 'You\'re experiencing some mild symptoms. Support from loved ones or activities you enjoy can help.'],
            ['score' => 30, 'category' => 'Moderate Depressive Symptoms', 'message' => 'This test suggests moderate depressive symptoms. Speaking with a professional would be valuable.'],
            ['score' => 40, 'category' => 'Severe Depressive Symptoms', 'message' => 'You may be experiencing significant depression. Please reach out to a mental health professional.']
        ]
    ];
    
    // Get interpretations for this test
    $test_interpretations = $interpretations[$test['test_slug']] ?? [];
    
    foreach ($test_interpretations as $level) {
        if ($total_score <= $level['score']) {
            $result_category = $level['category'];
            $result_message = $level['message'];
            break;
        }
    }
    
    // Save result to database
    $stmt = $conn->prepare("
        INSERT INTO user_test_results (user_id, test_id, total_score, result_category, result_message, individual_answers)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $answers_json = json_encode($answers);
    $stmt->bind_param("iiisss", $user_id, $test_id, $total_score, $result_category, $result_message, $answers_json);
    $stmt->execute();
    $result_id = $conn->insert_id;
    $stmt->close();
    
    // Redirect to results page
    redirect("test_results.php?result_id=$result_id");
}

$page_title = htmlspecialchars($test['test_name']);

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
        .test-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .test-header {
            background: linear-gradient(135deg, rgba(20, 184, 166, 0.1), rgba(126, 111, 255, 0.1));
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 32px;
            text-align: center;
            border-left: 4px solid var(--primary);
        }
        
        .test-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .test-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
            margin: 0 0 8px;
        }
        
        .test-header p {
            color: var(--muted);
            margin: 0;
            font-size: 14px;
        }
        
        .disclaimer {
            background: rgba(255, 193, 7, 0.08);
            border-left: 4px solid #FFC107;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 13px;
            color: var(--text);
            line-height: 1.6;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(12, 27, 51, 0.08);
            border-radius: 4px;
            margin-bottom: 32px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #3ad0be);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .questions-form {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(12, 27, 51, 0.05);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 16px rgba(12, 27, 51, 0.06);
        }
        
        .question-number {
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .question-text {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 24px;
            line-height: 1.5;
        }
        
        .answer-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .answer-option {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border: 2px solid rgba(20, 184, 166, 0.15);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
        }
        
        .answer-option:hover {
            border-color: var(--primary);
            background: rgba(20, 184, 166, 0.05);
        }
        
        .answer-option input[type="radio"] {
            margin-right: 12px;
            cursor: pointer;
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        
        .answer-option input[type="radio"]:checked + label {
            color: var(--text);
            font-weight: 600;
        }
        
        .answer-option label {
            flex: 1;
            cursor: pointer;
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }
        
        .answer-option input[type="radio"]:checked ~ .option-label {
            color: var(--primary);
            font-weight: 600;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 32px;
            justify-content: space-between;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #14b8a6, #3ad0be);
            color: white;
            flex: 1;
            box-shadow: 0 4px 12px rgba(20, 184, 166, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(20, 184, 166, 0.3);
        }
        
        .btn-secondary {
            background: rgba(12, 27, 51, 0.05);
            color: #0c1b33;
            border: 1px solid rgba(12, 27, 51, 0.1);
        }
        
        .btn-secondary:hover {
            background: rgba(12, 27, 51, 0.08);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .test-container {
                padding: 12px;
            }
            
            .questions-form {
                padding: 20px;
            }
            
            .question-text {
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
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">Taking Test</h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                        🔔 Notifications
                    </a>
                </div>
            </div>
            
            <div class="content-area">
        <div class="test-container">
            <div class="test-header">
                <div class="test-icon"><?php echo $test['test_icon']; ?></div>
                <h1><?php echo htmlspecialchars($test['test_name']); ?></h1>
                <p><?php echo htmlspecialchars($test['description']); ?></p>
            </div>

            <div class="disclaimer">
                <strong><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 6px; vertical-align: middle;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3.05h16.94a2 2 0 0 0 1.71-3.05L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Important:</strong> <?php echo htmlspecialchars($test['ethical_disclaimer']); ?>
            </div>

            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>

            <form method="POST" action="" class="questions-form" id="testForm">
                <div id="questionsContainer">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-container" style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>;" data-question-id="<?php echo $question['question_id']; ?>">
                            <div class="question-number">Question <?php echo $question['question_number']; ?> of <?php echo count($questions); ?></div>
                            <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                            
                            <div class="answer-options">
                                <label class="answer-option">
                                    <input type="radio" name="answers[<?php echo $question['question_id']; ?>]" value="0" required>
                                    <span class="option-label">Never / Not at all</span>
                                </label>
                                <label class="answer-option">
                                    <input type="radio" name="answers[<?php echo $question['question_id']; ?>]" value="1">
                                    <span class="option-label">Rarely</span>
                                </label>
                                <label class="answer-option">
                                    <input type="radio" name="answers[<?php echo $question['question_id']; ?>]" value="2">
                                    <span class="option-label">Sometimes</span>
                                </label>
                                <label class="answer-option">
                                    <input type="radio" name="answers[<?php echo $question['question_id']; ?>]" value="3">
                                    <span class="option-label">Often</span>
                                </label>
                                <label class="answer-option">
                                    <input type="radio" name="answers[<?php echo $question['question_id']; ?>]" value="4">
                                    <span class="option-label">Almost Always / Yes</span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-secondary" id="prevBtn" onclick="previousQuestion()" style="display: none;">← Previous</button>
                    <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextQuestion()">Next →</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">Submit Test</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentQuestion = 0;
        const questions = document.querySelectorAll('.question-container');
        const totalQuestions = questions.length;

        function updateProgress() {
            const progress = ((currentQuestion + 1) / totalQuestions) * 100;
            document.getElementById('progressFill').style.width = progress + '%';
        }

        function showQuestion(index) {
            questions.forEach(q => q.style.display = 'none');
            questions[index].style.display = 'block';

            document.getElementById('prevBtn').style.display = index > 0 ? 'block' : 'none';
            document.getElementById('nextBtn').style.display = index < totalQuestions - 1 ? 'block' : 'none';
            document.getElementById('submitBtn').style.display = index === totalQuestions - 1 ? 'block' : 'none';

            updateProgress();
        }

        function nextQuestion() {
            const currentInputs = questions[currentQuestion].querySelectorAll('input[type="radio"]');
            const isAnswered = Array.from(currentInputs).some(input => input.checked);

            if (!isAnswered) {
                alert('Please answer this question before proceeding.');
                return;
            }

            if (currentQuestion < totalQuestions - 1) {
                currentQuestion++;
                showQuestion(currentQuestion);
            }
        }

        function previousQuestion() {
            if (currentQuestion > 0) {
                currentQuestion--;
                showQuestion(currentQuestion);
            }
        }

        document.getElementById('testForm').addEventListener('submit', function(e) {
            const currentInputs = questions[currentQuestion].querySelectorAll('input[type="radio"]');
            const isAnswered = Array.from(currentInputs).some(input => input.checked);

            if (!isAnswered) {
                e.preventDefault();
                alert('Please answer this question before submitting.');
                return;
            }

            // Check all questions are answered
            let allAnswered = true;
            for (let i = 0; i < totalQuestions; i++) {
                const inputs = questions[i].querySelectorAll('input[type="radio"]');
                const answered = Array.from(inputs).some(input => input.checked);
                if (!answered) {
                    allAnswered = false;
                    break;
                }
            }

            if (!allAnswered) {
                e.preventDefault();
                alert('Please answer all questions before submitting.');
                return;
            }
        });

        // Initialize
        showQuestion(0);
    </script>
</body>
</html>
