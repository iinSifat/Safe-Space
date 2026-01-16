<?php
/**
 * Safe Space - Mood Tracker Module
 * Allows users to track their daily mood and view mood history
 */

require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get current user info
$user_stmt = $conn->prepare("SELECT username, user_type FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Handle mood submission
$mood_saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mood'])) {
    $mood_level = (int)$_POST['mood_level'] ?? 5;
    $energy_level = (int)$_POST['energy_level'] ?? 3;
    $stress_level = (int)$_POST['stress_level'] ?? 5;
    $notes = sanitize_input($_POST['mood_notes'] ?? '');
    $activities = isset($_POST['activities']) ? json_encode($_POST['activities']) : null;
    $medication_taken = isset($_POST['medication_taken']) ? 1 : 0;
    
    // Mood emoji mapping
    $mood_emojis = [
        1 => '😭', 2 => '😢', 3 => '😟', 4 => '😕', 5 => '😐',
        6 => '🙂', 7 => '😊', 8 => '😄', 9 => '😃', 10 => '😍'
    ];
    
    $mood_labels = [
        1 => 'Very Bad', 2 => 'Bad', 3 => 'Poor', 4 => 'Below Average',
        5 => 'Okay', 6 => 'Good', 7 => 'Very Good', 8 => 'Great',
        9 => 'Excellent', 10 => 'Perfect'
    ];
    
    $mood_emoji = $mood_emojis[$mood_level] ?? '😐';
    $mood_label = $mood_labels[$mood_level] ?? 'Okay';
    
    $insert_stmt = $conn->prepare("
        INSERT INTO mood_logs (user_id, mood_level, mood_emoji, mood_label, notes, activities, energy_level, stress_level, medication_taken)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $insert_stmt->bind_param(
        "iissssiii",
        $user_id, $mood_level, $mood_emoji, $mood_label, $notes, $activities, $energy_level, $stress_level, $medication_taken
    );
    
    if ($insert_stmt->execute()) {
        $mood_saved = true;
        // Award points for mood tracking
        $points_stmt = $conn->prepare("UPDATE user_points SET total_points = total_points + 5 WHERE user_id = ?");
        $points_stmt->bind_param("i", $user_id);
        $points_stmt->execute();
        $points_stmt->close();
    }
    $insert_stmt->close();
}

// Get mood history for current week
$week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
$mood_history_stmt = $conn->prepare("
    SELECT mood_id, mood_level, mood_emoji, mood_label, notes, energy_level, stress_level, logged_at
    FROM mood_logs
    WHERE user_id = ? AND logged_at >= ?
    ORDER BY logged_at DESC
    LIMIT 50
");
$mood_history_stmt->bind_param("is", $user_id, $week_ago);
$mood_history_stmt->execute();
$mood_history_result = $mood_history_stmt->get_result();
$mood_history = [];
while ($row = $mood_history_result->fetch_assoc()) {
    $mood_history[] = $row;
}
$mood_history_stmt->close();

// Get mood statistics
$today = date('Y-m-d');
$stats_stmt = $conn->prepare("
    SELECT 
        AVG(mood_level) as avg_mood,
        MAX(mood_level) as best_mood,
        MIN(mood_level) as worst_mood,
        COUNT(*) as mood_entries,
        AVG(energy_level) as avg_energy,
        AVG(stress_level) as avg_stress
    FROM mood_logs
    WHERE user_id = ? AND DATE(logged_at) = ?
");
$stats_stmt->bind_param("is", $user_id, $today);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$today_stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Get mood distribution for chart
$chart_stmt = $conn->prepare("
    SELECT DATE(logged_at) as mood_date, AVG(mood_level) as avg_mood
    FROM mood_logs
    WHERE user_id = ? AND logged_at >= ? - INTERVAL 6 DAY
    GROUP BY DATE(logged_at)
    ORDER BY mood_date ASC
");
$chart_stmt->bind_param("is", $user_id, $week_ago);
$chart_stmt->execute();
$chart_result = $chart_stmt->get_result();
$mood_chart = [];
while ($row = $chart_result->fetch_assoc()) {
    $mood_chart[] = $row;
}
$chart_stmt->close();

// Get current user points
$points_stmt = $conn->prepare("SELECT total_points, tier_level FROM user_points WHERE user_id = ?");
$points_stmt->bind_param("i", $user_id);
$points_stmt->execute();
$points_result = $points_stmt->get_result();
$user_points = $points_result->fetch_assoc();
$points_stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mood Tracker | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }
        
        .user-greeting {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .stat-box {
            background: rgba(255, 255, 255, 0.15);
            padding: 1rem;
            border-radius: var(--radius-md);
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .stat-box-number {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .stat-box-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        @media (min-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .success-message {
            background: rgba(111, 207, 151, 0.15);
            color: #2d7a4d;
            padding: 1rem;
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--success);
            margin-bottom: 1rem;
            display: none;
        }
        
        .success-message.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">😊 Mood Tracker</h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                        🔔 Notifications
                    </a>
                </div>
            </div>
            
            <div class="content-area">
    <div class="page-container" style="max-width: 1200px;">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div class="user-greeting">👋 Welcome, <?php echo htmlspecialchars($user['username']); ?></div>
            <p style="opacity: 0.9; margin-bottom: 1rem;">Track your mood and wellbeing journey</p>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-box-number"><?php echo isset($today_stats['mood_entries']) && $today_stats['mood_entries'] > 0 ? round($today_stats['avg_mood'], 1) : '—'; ?></div>
                    <div class="stat-box-label">Today's Mood</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-number"><?php echo $user_points['total_points'] ?? 0; ?></div>
                    <div class="stat-box-label">Points</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-number"><?php echo count($mood_history); ?></div>
                    <div class="stat-box-label">Entries (7d)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-number"><?php echo ucfirst($user_points['tier_level'] ?? 'bronze'); ?></div>
                    <div class="stat-box-label">Tier</div>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <div class="success-message <?php echo $mood_saved ? 'show' : ''; ?>">
            ✓ Your mood has been recorded successfully! +5 points earned.
        </div>

        <!-- Main Dashboard Grid -->
        <div class="dashboard-grid">
            
            <!-- Left Column: Mood Meter -->
            <div>
                <div class="mood-meter-container">
                    <div class="mood-meter-title">
                        <span>📊</span>
                        <span>How are you feeling today?</span>
                    </div>

                    <form method="POST" action="">
                        <!-- Mood Selector -->
                        <div class="mood-selector" id="moodSelector">
                            <?php for ($i = 1; $i <= 10; $i++): 
                                $emojis = ['😭', '😢', '😟', '😕', '😐', '🙂', '😊', '😄', '😃', '😍'];
                                $labels = ['Very Bad', 'Bad', 'Poor', 'Below Avg', 'Okay', 'Good', 'Very Good', 'Great', 'Excellent', 'Perfect'];
                            ?>
                                <label class="mood-option" onclick="selectMood(<?php echo $i; ?>)">
                                    <div class="mood-emoji"><?php echo $emojis[$i-1]; ?></div>
                                    <div class="mood-label"><?php echo $labels[$i-1]; ?></div>
                                    <input type="hidden" name="mood_level" value="<?php echo $i; ?>" style="display:none;">
                                </label>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" id="moodLevel" name="mood_level" value="5">

                        <!-- Sliders -->
                        <div class="mood-sliders">
                            <div class="slider-group">
                                <div class="slider-label">
                                    <span>⚡ Energy Level</span>
                                    <span class="slider-value" id="energyValue">3/5</span>
                                </div>
                                <input type="range" id="energySlider" name="energy_level" class="mood-slider" min="1" max="5" value="3" 
                                       oninput="updateSliderValue('energy')">
                            </div>

                            <div class="slider-group">
                                <div class="slider-label">
                                    <span>😰 Stress Level</span>
                                    <span class="slider-value" id="stressValue">5/10</span>
                                </div>
                                <input type="range" id="stressSlider" name="stress_level" class="mood-slider" min="1" max="10" value="5"
                                       oninput="updateSliderValue('stress')">
                            </div>
                        </div>

                        <!-- Mood Notes -->
                        <div class="mood-notes">
                            <label>📝 Additional Notes (Optional)</label>
                            <textarea name="mood_notes" placeholder="What happened today? Any triggers or positives?"></textarea>
                        </div>

                        <!-- Activities -->
                        <div class="mood-activities">
                            <div class="activities-title">🎯 Today's Activities</div>
                            <div class="activity-tags">
                                <label class="activity-tag">
                                    <input type="checkbox" name="activities[]" value="exercise" style="display:none;">
                                    🏃 Exercise
                                </label>
                                <label class="activity-tag">
                                    <input type="checkbox" name="activities[]" value="meditation" style="display:none;">
                                    🧘 Meditation
                                </label>
                                <label class="activity-tag">
                                    <input type="checkbox" name="activities[]" value="socializing" style="display:none;">
                                    👥 Socializing
                                </label>
                                <label class="activity-tag">
                                    <input type="checkbox" name="activities[]" value="therapy" style="display:none;">
                                    💬 Therapy
                                </label>
                                <label class="activity-tag">
                                    <input type="checkbox" name="activities[]" value="sleep" style="display:none;">
                                    😴 Good Sleep
                                </label>
                                <label class="activity-tag">
                                    <input type="checkbox" name="activities[]" value="hobby" style="display:none;">
                                    🎨 Hobby
                                </label>
                                <label class="activity-tag">
                                    <input type="checkbox" name="activities[]" value="healthy_eating" style="display:none;">
                                    🥗 Healthy Eating
                                </label>
                                <label class="activity-tag">
                                    <input type="checkbox" name="activities[]" value="reading" style="display:none;">
                                    📚 Reading
                                </label>
                            </div>
                        </div>

                        <!-- Medication Checkbox -->
                        <div class="checkbox-wrapper" style="margin: 1.5rem 0;">
                            <input type="checkbox" id="medicationCheck" name="medication_taken">
                            <label for="medicationCheck">💊 I took my medication today</label>
                        </div>

                        <!-- Submit Button -->
                        <input type="hidden" name="save_mood" value="1">
                        <button type="submit" class="mood-submit-btn">Save Mood Entry ✓</button>
                    </form>
                </div>

                <!-- Mood Chart -->
                <?php if (count($mood_chart) > 0): ?>
                <div class="mood-chart">
                    <div class="chart-title">📈 Your Mood Trend (Last 7 Days)</div>
                    <div class="mood-chart-bars" id="moodChart">
                        <?php 
                        $max_mood = 10;
                        foreach ($mood_chart as $index => $data): 
                            $height = ($data['avg_mood'] / $max_mood) * 100;
                            $day = date('D', strtotime($data['mood_date']));
                        ?>
                            <div class="chart-bar" style="height: <?php echo $height; ?>%;" title="<?php echo $data['avg_mood']; ?>">
                                <div class="chart-bar-label"><?php echo $day; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Recent Entries -->
            <div>
                <div class="mood-history">
                    <div class="section-title">📋 Recent Entries</div>

                    <?php if (count($mood_history) > 0): ?>
                        <?php foreach ($mood_history as $entry): ?>
                            <div class="mood-entry">
                                <div class="mood-entry-left">
                                    <div class="mood-entry-emoji"><?php echo htmlspecialchars($entry['mood_emoji']); ?></div>
                                    <div class="mood-entry-info">
                                        <h4><?php echo htmlspecialchars($entry['mood_label']); ?></h4>
                                        <div class="mood-entry-time"><?php echo date('M j, Y g:i A', strtotime($entry['logged_at'])); ?></div>
                                    </div>
                                </div>
                                <div class="mood-entry-right">
                                    <div class="mood-entry-stats">
                                        <div class="mood-stat">
                                            <div class="mood-stat-label">Energy</div>
                                            <div class="mood-stat-value"><?php echo $entry['energy_level']; ?>/5</div>
                                        </div>
                                        <div class="mood-stat">
                                            <div class="mood-stat-label">Stress</div>
                                            <div class="mood-stat-value"><?php echo $entry['stress_level']; ?>/10</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                            <p style="font-size: 3rem; margin-bottom: 1rem;">📊</p>
                            <p>No mood entries yet. Start tracking your mood above!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Navigation Buttons -->
        <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
            <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
            <a href="forum.php" class="btn btn-secondary">Visit Forum</a>
            <a href="../dashboard/logout.php" class="btn btn-secondary">Logout</a>
        </div>

    </div>

    <script>
        function selectMood(level) {
            document.getElementById('moodLevel').value = level;
            const options = document.querySelectorAll('.mood-option');
            options.forEach(opt => opt.classList.remove('active'));
            options[level - 1].classList.add('active');
        }

        function updateSliderValue(type) {
            if (type === 'energy') {
                const val = document.getElementById('energySlider').value;
                document.getElementById('energyValue').textContent = val + '/5';
            } else if (type === 'stress') {
                const val = document.getElementById('stressSlider').value;
                document.getElementById('stressValue').textContent = val + '/10';
            }
        }

        // Activity tag selection
        document.querySelectorAll('.activity-tag').forEach(tag => {
            tag.addEventListener('click', function(e) {
                e.preventDefault();
                const checkbox = this.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                this.classList.toggle('selected');
            });
        });

        // Set default mood
        selectMood(5);
    </script>
    </div><!-- End page-container -->
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
</body>
</html>
