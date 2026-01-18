<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

// Updated by Shuvo - START
// Sample professionals (in a real app, these would be from database)
$professionals = [
    [
        'id' => 1,
        'name' => 'Dr. Sarah Johnson',
        'specialization' => 'Depression & Anxiety',
        'rating' => 4.8,
        'fee' => 500,
        'verified' => true
    ],
    [
        'id' => 2,
        'name' => 'Dr. Michael Chen',
        'specialization' => 'Trauma & PTSD',
        'rating' => 4.9,
        'fee' => 1000,
        'verified' => true
    ],
    [
        'id' => 3,
        'name' => 'Dr. Emma Rodriguez',
        'specialization' => 'Relationship Issues',
        'rating' => 4.7,
        'fee' => 800,
        'verified' => true
    ],
    [
        'id' => 4,
        'name' => 'Dr. James Williams',
        'specialization' => 'Work Stress & Burnout',
        'rating' => 4.6,
        'fee' => 400,
        'verified' => true
    ]
];
// Updated by Shuvo - END

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
            background: white;
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
    </style>
</head>
<body>
        <div class="dashboard-wrapper">
            <?php include 'includes/sidebar.php'; ?>
        
            <main class="main-content">
                <div class="top-bar">
                    <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">👨‍⚕️ Find Professionals</h2>
                    <div class="top-bar-right">
                        <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                            🔔 Notifications
                        </a>
                    </div>
                </div>
            
                <div class="content-area">
    <div class="professionals-container">
        <!-- Header -->
        <div class="header">
            <h1>👨‍⚕️ Mental Health Professionals</h1>
            <p>Connect with verified, licensed mental health professionals for personalized support</p>
        </div>

        <!-- Search & Filters -->
        <div class="filters">
            <input type="text" class="filter-input" placeholder="Search by name or specialization...">
            <select class="filter-input" style="max-width: 250px;">
                <option>All Specializations</option>
                <option>Depression & Anxiety</option>
                <option>Trauma & PTSD</option>
                <option>Relationship Issues</option>
                <option>Work Stress & Burnout</option>
            </select>
        </div>

        <!-- Professionals Grid -->
        <div class="professionals-grid">
            <?php foreach ($professionals as $prof): ?>
                <div class="professional-card">
                    <div class="professional-header">
                        <div class="professional-avatar">👨‍⚕️</div>
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
                        <div class="fee">$<?php echo $prof['fee']; ?>/session</div>
                        <button class="book-btn" onclick="alert('Booking system coming soon! 🎉')">Book</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Navigation -->
        <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
            <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
            <a href="mood_tracker.php" class="btn btn-secondary">Mood Tracker</a>
        </div>
    </div>
</body>
</html>
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
