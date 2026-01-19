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

// Filter/search handling
$q = trim($_GET['q'] ?? '');
$spec = trim($_GET['spec'] ?? '');

// Updated by Shuvo - START
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

        /* Updated by Shuvo - START */
        .suggestions-list {
            position: absolute;
            background: white;
            border: 1px solid var(--light-gray);
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
        /* Updated by Shuvo - END */
    </style>
</head>
<body>
        <div class="dashboard-wrapper">
            <?php include 'includes/sidebar.php'; ?>
        
            <main class="main-content">
                <div class="top-bar">
                    <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg>Find Professionals</h2>
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
            <h1><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 12px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg>Mental Health Professionals</h1>
            <p>Connect with verified, licensed mental health professionals for personalized support</p>
        </div>

        <!-- Search & Filters -->
        <?php // Updated by Shuvo - START ?>
        <form class="filters" method="GET" action="professionals.php">
            <div class="search-wrap" style="position: relative; flex:1;">
                <input id="prof-search" type="text" name="q" class="filter-input" placeholder="Search by name or specialization..." autocomplete="off" value="<?php echo htmlspecialchars($q ?? ''); ?>">
                <div id="suggestions" class="suggestions-list" style="display:none;"></div>
            </div>
            <select name="spec" class="filter-input" style="max-width: 250px;" onchange="this.form.submit()">
                <option<?php echo ($spec === '' || $spec === 'All Specializations') ? ' selected' : ''; ?>>All Specializations</option>
                <option value="Depression & Anxiety"<?php echo ($spec === 'Depression & Anxiety') ? ' selected' : ''; ?>>Depression & Anxiety</option>
                <option value="Trauma & PTSD"<?php echo ($spec === 'Trauma & PTSD') ? ' selected' : ''; ?>>Trauma & PTSD</option>
                <option value="Relationship Issues"<?php echo ($spec === 'Relationship Issues') ? ' selected' : ''; ?>>Relationship Issues</option>
                <option value="Work Stress & Burnout"<?php echo ($spec === 'Work Stress & Burnout') ? ' selected' : ''; ?>>Work Stress & Burnout</option>
            </select>
        </form>
        <?php // Updated by Shuvo - END ?>

        <!-- Professionals Grid -->
        <div class="professionals-grid">
            <?php foreach ($professionals as $prof): ?>
                <div class="professional-card">
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

    <?php // Updated by Shuvo - START ?>
    <script>
        // Build suggestion source from server-side data
        const suggestionSource = <?php
            $items = [];
            foreach ($professionals as $p) {
                $items[] = $p['name'];
                $items[] = $p['specialization'];
            }
            $items = array_values(array_unique($items));
            echo json_encode($items, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        ?>;

        const searchInput = document.getElementById('prof-search');
        const suggestionsEl = document.getElementById('suggestions');
        let activeIndex = -1;
        let currentList = [];

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
            // submit the form to apply search
            searchInput.form.submit();
        }

        const update = debounce(function(){
            const q = (searchInput.value || '').trim().toLowerCase();
            if (q === '') { renderSuggestions([]); return; }
            const matches = suggestionSource.filter(s => s.toLowerCase().includes(q));
            renderSuggestions(matches.slice(0,8));
        }, 150);

        searchInput.addEventListener('input', update);

        // Click suggestions
        suggestionsEl.addEventListener('click', function(e){
            const item = e.target.closest('.suggestion-item');
            if (item) pickSuggestion(item.textContent.trim());
        });

        // Keyboard navigation
        searchInput.addEventListener('keydown', function(e){
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
    </script>
    <?php // Updated by Shuvo - END ?>
</body>
</html>
            </div><!-- End content-area -->
        </main><!-- End main-content -->
    </div><!-- End dashboard-wrapper -->
