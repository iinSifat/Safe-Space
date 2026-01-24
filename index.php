<?php
/**
 * Safe Space - Landing Page (Green)
 */

require_once 'config/config.php';

if (is_logged_in()) {
    redirect('dashboard/index.php');
}

if (is_admin_logged_in()) {
    redirect('admin/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Safe Space - A comprehensive mental health support platform offering anonymous peer support, professional consultations, and community resources">
    <meta name="keywords" content="mental health, support, therapy, counseling, peer support, wellness">
    <title>Safe Space | Your Mental Health Support Community</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        :root {
            --primary: #14b8a6;
            --primary-dark: #0e9486;
            --text: #0c1b33;
            --muted: #5a6b8a;
            --bg: #f8fbff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at 20% 30%, rgba(45, 206, 180, 0.22), transparent 35%),
                        radial-gradient(circle at 80% 40%, rgba(126, 111, 255, 0.18), transparent 38%),
                        radial-gradient(circle at 60% 80%, rgba(255, 178, 125, 0.18), transparent 40%),
                        var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        .page-shell {
            width: 100%;
            max-width: none;
            margin: 0;
            /* Offset for fixed header (keeps content from sliding under it) */
            padding: 118px 0 60px;
        }
        header {
            /* Full-width, tall, stable header (reference-like structure) */
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: 92px;
            background: rgba(248, 251, 255, 0.9);
            backdrop-filter: blur(12px);
            z-index: 50;
        }

        .header-inner {
            height: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 20px;
            color: var(--text);
        }
        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: linear-gradient(135deg, #1ad6c5, #14b8a6);
            display: grid;
            place-items: center;
            box-shadow: 0 12px 30px rgba(20, 184, 166, 0.35);
        }
        nav { display: flex; align-items: center; gap: 30px; }
        nav a { color: var(--muted); text-decoration: none; font-weight: 600; font-size: 15px; }
        nav a:hover { color: var(--text); }
        .nav-actions { display: flex; align-items: center; gap: 12px; }
        .btn {
            border: 0;
            cursor: pointer;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            padding: 12px 18px;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .btn-ghost { background: transparent; color: var(--text); }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #3ad0be);
            color: white;
            box-shadow: 0 12px 30px rgba(20, 184, 166, 0.35);
        }
        .btn:hover { transform: translateY(-2px); }
        .hero {
            padding: 40px 0 28px;
        }
        .hero-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            gap: 22px;
            text-align: center;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(20, 184, 166, 0.12);
            color: var(--primary-dark);
            padding: 10px 16px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 8px 20px rgba(20, 184, 166, 0.15);
        }
        .hero h1 {
            font-size: clamp(36px, 5vw, 62px);
            line-height: 1.08;
            margin: 10px 0;
            letter-spacing: -0.02em;
        }
        .accent { color: var(--primary); }
        .hero p {
            margin: 0 auto;
            max-width: 740px;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.6;
        }
        .hero-actions { display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 10px; }
        .btn-outline {
            border: 2px solid var(--primary);
            background: transparent;
            color: var(--primary-dark);
            box-shadow: none;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 18px;
            margin: 26px 0 12px;
        }
        .stat {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(12, 27, 51, 0.05);
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 18px 38px rgba(12, 27, 51, 0.06);
            text-align: center;
        }
        .stat strong { display: block; font-size: 28px; color: var(--text); }
        .stat span { color: var(--muted); font-size: 15px; }
        .section-title { text-align: center; margin: 60px 0 14px; }
        .section-title h2 { font-size: 32px; margin: 0 0 8px; }
        .section-title p { color: var(--muted); margin: 0; }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
        }
        .feature-card {
            border-radius: 26px;
            padding: 22px;
            color: white;
            min-height: 190px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 24px 50px rgba(12, 27, 51, 0.14);
        }
        .feature-card h3 { margin: 0 0 10px; font-size: 20px; }
        .feature-card p { margin: 0; font-size: 15px; line-height: 1.55; }
        .feature-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.14);
            display: grid;
            place-items: center;
            margin-bottom: 14px;
        }
        .bg-teal { background: linear-gradient(135deg, #1ad6c5, #13bba7); }
        .bg-purple { background: linear-gradient(135deg, #7b5dff, #9c6bff); }
        .bg-coral { background: linear-gradient(135deg, #ff986f, #f96d6f); }
        .bg-sky { background: linear-gradient(135deg, #3ac7ff, #2bb6ff); }
        .bg-lime { background: linear-gradient(135deg, #86e3a1, #4ccf8f); color: #0c1b33; }
        .bg-indigo { background: linear-gradient(135deg, #5b6bff, #4b5ae6); }
        .assurance {
            background: var(--bg-card, #F8F9F7);
            border-radius: 24px;
            padding: 26px;
            width: calc(100% - 40px);
            max-width: 1200px;
            margin: 52px auto 18px;
            box-shadow: 0 18px 40px rgba(12, 27, 51, 0.08);
            text-align: center;
        }
        .assurance p { margin: 0 0 10px; color: var(--muted); }
        footer {
            text-align: center;
            color: var(--muted);
            font-size: 14px;
            margin: 18px auto 0;
            max-width: 1200px;
            padding: 0 20px;
        }
        @media (max-width: 768px) {
            header { height: 92px; }
            .page-shell { padding: 118px 0 60px; }
            .header-inner { flex-wrap: wrap; justify-content: center; row-gap: 10px; padding: 10px 16px; }
            nav { width: 100%; justify-content: center; }
            .hero h1 { font-size: 36px; }
            .nav-actions { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-inner">
            <a class="brand" href="<?php echo is_logged_in() ? 'dashboard/index.php' : 'index.php'; ?>" style="text-decoration:none;">
                <img src="images/logo.png" alt="Safe Space" style="width: 44px; height: 44px; border-radius: 14px;">
                Safe Space
            </a>
            <nav>
                <a href="#features">Features</a>
                <a href="#about">About</a>
                <a href="#stories">Stories</a>
            </nav>
            <div class="nav-actions">
                <a class="btn btn-ghost" href="auth/login.php">Sign In</a>
                <a class="btn btn-primary" href="auth/registration.php">Get Started</a>
            </div>
        </div>
    </header>

    <div class="page-shell">

        <main>
            <section class="hero" id="about">
                <div class="hero-inner">
                    <div class="pill">
                        <span aria-hidden="true"><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l1.8 6.1L20 12l-6.2 3.9L12 22l-1.8-6.1L4 12l6.2-3.9L12 2z"/></svg></span>
                        <span>Your mental wellness matters</span>
                    </div>
                    <h1>A Safe Space for Your <span class="accent">Mental Health</span> Journey</h1>
                    <p>Connect anonymously, share openly, and heal together with a supportive community that understands. You are never alone here.</p>
                    <div class="hero-actions">
                        <a class="btn btn-primary" href="auth/registration.php">Join Safe Space</a>
                        <a class="btn btn-outline" href="auth/login.php">Learn More</a>
                    </div>
                    <div class="stats" id="stories">
                        <div class="stat"><strong>50K+</strong><span>Community Members</span></div>
                        <div class="stat"><strong>24/7</strong><span>Support Available</span></div>
                        <div class="stat"><strong>100+</strong><span>Professional Counselors</span></div>
                        <div class="stat"><strong>98%</strong><span>User Satisfaction</span></div>
                    </div>
                </div>
            </section>

            <!-- Healing Cards Auto-Scroll Component -->
            <?php include 'assets/components/healing-cards-section.php'; ?>

            <!-- User Stories/Testimonials Section -->
            <?php include 'assets/components/stories-section.php'; ?>

            <section class="assurance">
                <h3>Ready when you are</h3>
                <p>Join thousands finding support, hope, and healing. Your privacy is our priority.</p>
                <div class="hero-actions" style="margin-top: 8px;">
                    <a class="btn btn-primary" href="auth/registration.php">Get Started</a>
                    <a class="btn btn-outline" href="auth/login.php">Sign In</a>
                </div>
            </section>
        </main>

        <footer>
            Crisis Helpline: 988 (US) · Available 24/7
        </footer>
    </div>
</body>
</html>
