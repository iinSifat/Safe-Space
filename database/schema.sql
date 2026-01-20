
DROP DATABASE IF EXISTS safe_space_db;
CREATE DATABASE safe_space_db;
USE safe_space_db;


-- 1. ADMINS TABLE
CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'moderator', 'content_manager') DEFAULT 'moderator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USERS TABLE (Main user account)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    user_type ENUM('patient', 'professional', 'volunteer', 'supporter', 'community_healer') NOT NULL,
    is_anonymous BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    profile_picture VARCHAR(255) NULL,
    bio TEXT NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'non_binary', 'prefer_not_to_say', 'other') NULL,
    country VARCHAR(100) NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    email_verified_at TIMESTAMP NULL,
    verification_token VARCHAR(100) NULL,
    reset_token VARCHAR(100) NULL,
    reset_token_expiry TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_user_type (user_type),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. PROFESSIONALS TABLE (Mental Health Professionals)
CREATE TABLE professionals (
    professional_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    license_number VARCHAR(50) NOT NULL,
    license_state VARCHAR(50) NULL,
    license_country VARCHAR(50) NOT NULL,
    degree VARCHAR(100) NOT NULL,
    years_of_experience INT DEFAULT 0,
    credentials TEXT NULL COMMENT 'JSON array of credentials',
    consultation_fee DECIMAL(10, 2) DEFAULT 0.00,
    bio_detailed TEXT NULL,
    languages_spoken VARCHAR(255) NULL COMMENT 'Comma-separated languages',
    availability_schedule TEXT NULL COMMENT 'JSON schedule object',
    is_accepting_patients BOOLEAN DEFAULT TRUE,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verification_documents TEXT NULL COMMENT 'JSON array of document URLs',
    verified_at TIMESTAMP NULL,
    verified_by INT NULL COMMENT 'Admin ID who verified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_verification_status (verification_status),
    INDEX idx_specialization (specialization),
    INDEX idx_is_accepting (is_accepting_patients)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. VOLUNTEERS TABLE (Peer Support Volunteers)
CREATE TABLE volunteers (
    volunteer_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    age INT NULL,
    education_level VARCHAR(100) NULL,
    motivation TEXT NULL COMMENT 'Why they want to volunteer',
    lived_experience TEXT NULL COMMENT 'Personal mental health journey (optional)',
    availability_hours_per_week INT DEFAULT 5,
    preferred_support_areas VARCHAR(255) NULL COMMENT 'Comma-separated areas (anxiety, depression, etc.)',
    training_completed BOOLEAN DEFAULT FALSE,
    training_completion_date TIMESTAMP NULL,
    is_active_volunteer BOOLEAN DEFAULT FALSE,
    approval_status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    approved_at TIMESTAMP NULL,
    approved_by INT NULL COMMENT 'Admin ID who approved',
    background_check_status ENUM('not_started', 'in_progress', 'completed', 'failed') DEFAULT 'not_started',
    total_support_hours DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_approval_status (approval_status),
    INDEX idx_is_active (is_active_volunteer),
    INDEX idx_training_completed (training_completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4b. VOLUNTEER APPLICATIONS TABLE (Post-login applications)
CREATE TABLE volunteer_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    education TEXT NULL,
    training_certifications TEXT NULL,
    trainee_organization VARCHAR(150) NULL,
    experience TEXT NULL,
    motivation TEXT NULL,
    document_paths TEXT NULL COMMENT 'JSON array of uploaded certificate/proof file paths',
    status ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
    admin_notes TEXT NULL,
    reviewed_by INT NULL COMMENT 'Admin ID who reviewed',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    declined_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_user_status (user_id, status),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4c. NOTIFICATIONS TABLE
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'volunteer_submitted, volunteer_approved, volunteer_declined, etc.',
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. USER POINTS TABLE (Gamification)
CREATE TABLE user_points (
    point_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_points INT DEFAULT 0,
    tier_level ENUM('bronze', 'silver', 'gold') DEFAULT 'bronze',
    points_spent INT DEFAULT 0,
    last_activity_date DATE NULL,
    streak_days INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_total_points (total_points),
    INDEX idx_tier_level (tier_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. USER BADGES TABLE
CREATE TABLE user_badges (
    badge_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_name VARCHAR(100) NOT NULL,
    badge_description TEXT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    badge_icon VARCHAR(255) NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_badge (user_id, badge_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. ACTIVITY LOG TABLE
CREATE TABLE activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    activity_type VARCHAR(50) NOT NULL,
    activity_description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_activity (user_id, created_at),
    INDEX idx_activity_type (activity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. MOOD LOGS TABLE (Mood Tracking)
CREATE TABLE mood_logs (
    mood_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mood_level TINYINT NOT NULL COMMENT '1–10 validated in app layer',
    mood_emoji VARCHAR(10) NULL,
    mood_label VARCHAR(50) NULL,
    notes TEXT NULL,
    activities VARCHAR(255) NULL COMMENT 'JSON array of activities',
    energy_level TINYINT NOT NULL DEFAULT 3 COMMENT '1–5 validated in app layer',
    stress_level TINYINT NOT NULL DEFAULT 5 COMMENT '1–10 validated in app layer',
    triggers VARCHAR(255) NULL COMMENT 'Comma-separated mood triggers',
    medication_taken BOOLEAN DEFAULT FALSE,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_mood (user_id, logged_at),
    INDEX idx_mood_level (mood_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 9. COMMUNITY HEALERS TABLE
CREATE TABLE community_healers (
    healer_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    healing_level ENUM('beginner', 'experienced', 'master') DEFAULT 'beginner',
    total_people_helped INT DEFAULT 0,
    helpful_responses INT DEFAULT 0,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    badges_earned INT DEFAULT 0,
    total_points_earned INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_healing_level (healing_level),
    INDEX idx_is_active (is_active),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. FORUM POSTS TABLE
CREATE TABLE forum_posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    is_encrypted BOOLEAN DEFAULT TRUE,
    view_count INT DEFAULT 0,
    reply_count INT DEFAULT 0,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_locked BOOLEAN DEFAULT FALSE,
    status ENUM('published', 'draft', 'deleted', 'flagged') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_user_posts (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. FORUM REPLIES TABLE
CREATE TABLE forum_replies (
    reply_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content LONGTEXT NOT NULL,
    is_encrypted BOOLEAN DEFAULT TRUE,
    is_helpful_marked BOOLEAN DEFAULT FALSE,
    helpful_count INT DEFAULT 0,
    status ENUM('published', 'deleted', 'flagged') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_post_replies (post_id, created_at),
    INDEX idx_is_helpful (is_helpful_marked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. FORUM POST REACTIONS TABLE (LinkedIn-style reactions)
CREATE TABLE forum_post_reactions (
    reaction_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction_type ENUM('like', 'celebrate', 'support', 'love', 'insightful', 'curious') NOT NULL DEFAULT 'like',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_post_user (post_id, user_id),
    INDEX idx_post_reaction (post_id, reaction_type),
    FOREIGN KEY (post_id) REFERENCES forum_posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin account
-- Password: Admin@123 (hashed with bcrypt)
INSERT INTO admins (username, email, password_hash, full_name, role, is_active) 
VALUES 
    ('admin', 'admin@safespace.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin', TRUE),
    ('moderator1', 'moderator@safespace.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Content Moderator', 'moderator', TRUE);

-- 13. BLOG TABLES (No anonymous blogging)

-- Blog Posts Table
CREATE TABLE blog_posts (
    blog_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category VARCHAR(120) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    is_professional_post BOOLEAN DEFAULT FALSE,
    view_count INT DEFAULT 0,
    comment_count INT DEFAULT 0,
    status ENUM('published', 'draft', 'deleted', 'flagged') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_user_blogs (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog Comments Table
CREATE TABLE blog_comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    user_id INT NOT NULL,
    content LONGTEXT NOT NULL,
    status ENUM('published', 'deleted', 'flagged') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_id) REFERENCES blog_posts(blog_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_blog_comments (blog_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog Reactions Table (LinkedIn-style reactions)
CREATE TABLE blog_post_reactions (
    reaction_id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction_type ENUM('like', 'celebrate', 'support', 'love', 'insightful', 'curious') NOT NULL DEFAULT 'like',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_blog_user (blog_id, user_id),
    INDEX idx_blog_reaction (blog_id, reaction_type),
    FOREIGN KEY (blog_id) REFERENCES blog_posts(blog_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MENTAL HEALTH TEST SUITE TABLES

-- Test Definitions Table
CREATE TABLE mental_health_tests (
    test_id INT AUTO_INCREMENT PRIMARY KEY,
    test_name VARCHAR(100) NOT NULL,
    test_slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT NOT NULL,
    test_icon VARCHAR(50) NOT NULL,
    color_code VARCHAR(20) NOT NULL,
    instructions TEXT NOT NULL,
    ethical_disclaimer TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_test_slug (test_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Test Questions Table
CREATE TABLE test_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    question_text VARCHAR(255) NOT NULL,
    question_number INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES mental_health_tests(test_id) ON DELETE CASCADE,
    INDEX idx_test_id (test_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Test Results Table
CREATE TABLE user_test_results (
    result_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    test_id INT NOT NULL,
    total_score INT NOT NULL,
    result_category VARCHAR(50) NOT NULL,
    result_message TEXT NOT NULL,
    individual_answers JSON NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES mental_health_tests(test_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_test_id (test_id),
    INDEX idx_completed_at (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Test Definitions
INSERT INTO mental_health_tests (test_name, test_slug, description, test_icon, color_code, instructions, ethical_disclaimer) VALUES
(
    'Stress Level Test',
    'stress-test',
    'Assess your daily-life stress from workload, emotions, and environment',
    '⚠️',
    '#FF9800',
    'Answer the following 10 questions honestly based on how you''ve been feeling recently. There are no right or wrong answers.',
    'This assessment is not a medical diagnosis. It is intended for self-reflection only. If you feel distressed, please seek professional help.'
),
(
    'Anxiety Test',
    'anxiety-test',
    'Detect persistent worry, nervousness, and fear patterns',
    '😰',
    '#FFC107',
    'Answer the following 10 questions honestly based on how you''ve been feeling recently. There are no right or wrong answers.',
    'This assessment is not a medical diagnosis. It is intended for self-reflection only. If you feel distressed, please seek professional help.'
),
(
    'OCD Tendencies Test',
    'ocd-test',
    'Identify obsessive thoughts and compulsive behaviors (non-diagnostic)',
    '🔄',
    '#2196F3',
    'Answer the following 10 questions honestly based on how you''ve been feeling recently. There are no right or wrong answers.',
    'This assessment is not a medical diagnosis. It is intended for self-reflection only. If you feel distressed, please seek professional help.'
),
(
    'Depression Screening Test',
    'depression-test',
    'Screen emotional well-being and depressive symptoms',
    '😔',
    '#F44336',
    'Answer the following 10 questions honestly based on how you''ve been feeling recently. There are no right or wrong answers.',
    'This assessment is not a medical diagnosis. It is intended for self-reflection only. If you feel distressed, please seek professional help.'
);

-- Insert Questions for Stress Test (test_id = 1)
INSERT INTO test_questions (test_id, question_number, question_text) VALUES
(1, 1, 'I feel overwhelmed by my daily responsibilities'),
(1, 2, 'I find it difficult to relax even during free time'),
(1, 3, 'I feel pressure to meet expectations'),
(1, 4, 'I feel tired even after resting'),
(1, 5, 'I get irritated over small issues'),
(1, 6, 'I worry about unfinished tasks'),
(1, 7, 'I feel mentally exhausted'),
(1, 8, 'I feel stressed thinking about the future'),
(1, 9, 'I have trouble focusing due to stress'),
(1, 10, 'I feel I have too many things to handle at once');

-- Insert Questions for Anxiety Test (test_id = 2)
INSERT INTO test_questions (test_id, question_number, question_text) VALUES
(2, 1, 'I feel nervous without a clear reason'),
(2, 2, 'I worry excessively about future events'),
(2, 3, 'I feel restless or on edge'),
(2, 4, 'I experience sudden feelings of fear'),
(2, 5, 'My heart races when I feel anxious'),
(2, 6, 'I avoid situations due to fear'),
(2, 7, 'I overthink small problems'),
(2, 8, 'I feel tense most of the time'),
(2, 9, 'I struggle to control anxious thoughts'),
(2, 10, 'Anxiety interferes with my daily activities');

-- Insert Questions for OCD Test (test_id = 3)
INSERT INTO test_questions (test_id, question_number, question_text) VALUES
(3, 1, 'I repeatedly check things (locks, switches, etc.)'),
(3, 2, 'I feel anxious if things are not arranged properly'),
(3, 3, 'I have thoughts that I can''t easily get rid of'),
(3, 4, 'I repeat actions to feel relieved'),
(3, 5, 'I feel uncomfortable with disorder'),
(3, 6, 'I overthink cleanliness or contamination'),
(3, 7, 'I feel a strong urge to do things a certain way'),
(3, 8, 'I repeat tasks even when unnecessary'),
(3, 9, 'I feel distress if routines are interrupted'),
(3, 10, 'These thoughts or behaviors take significant time');

-- Insert Questions for Depression Test (test_id = 4)
INSERT INTO test_questions (test_id, question_number, question_text) VALUES
(4, 1, 'I feel sad or low most of the time'),
(4, 2, 'I have lost interest in activities I used to enjoy'),
(4, 3, 'I feel hopeless about the future'),
(4, 4, 'I struggle to find motivation'),
(4, 5, 'I feel worthless or guilty'),
(4, 6, 'I have trouble sleeping'),
(4, 7, 'I feel tired most days'),
(4, 8, 'I find it hard to concentrate'),
(4, 9, 'I feel emotionally numb'),
(4, 10, 'My mood affects my daily functioning');

DELIMITER //

CREATE TRIGGER after_user_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO user_points (user_id, total_points, tier_level)
    VALUES (NEW.user_id, 0, 'bronze');
    
    INSERT INTO activity_log (user_id, activity_type, activity_description)
    VALUES (NEW.user_id, 'registration', CONCAT('New user registered as ', NEW.user_type));
END//

DELIMITER ;

-- DEVELOPMENT SEED DATA (Dummy Content)
-- Password for all dummy users: password
-- NOTE: Importing this schema DROPS and recreates safe_space_db.

INSERT INTO users (username, email, password_hash, full_name, user_type, is_anonymous, is_verified, is_active, bio, country, timezone)
VALUES
('ava_patient', 'ava.patient@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ava Patel', 'patient', TRUE, TRUE, TRUE, 'Trying to build steadier routines and ask for help sooner.', 'US', 'UTC'),
('noah_patient', 'noah.patient@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Noah Kim', 'patient', TRUE, TRUE, TRUE, 'Working on anxiety management and sleep hygiene.', 'US', 'UTC'),
('mina_supporter', 'mina.supporter@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mina Rahman', 'supporter', FALSE, TRUE, TRUE, 'Here to support friends and learn healthier communication.', 'UK', 'UTC'),
('liam_patient', 'liam.patient@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Liam Chen', 'patient', TRUE, TRUE, TRUE, 'Taking small steps: journaling, walks, and reaching out.', 'CA', 'UTC'),
('sam_volunteer', 'sam.volunteer@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sam Okoye', 'volunteer', FALSE, TRUE, TRUE, 'Peer support volunteer focusing on listening and validation.', 'NG', 'UTC'),
('dr_faye', 'dr.faye@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Faye Morgan', 'professional', FALSE, TRUE, TRUE, 'Licensed therapist sharing practical, evidence-informed guidance.', 'US', 'UTC');

INSERT INTO professionals (user_id, full_name, specialization, license_number, license_state, license_country, degree, years_of_experience, verification_status)
SELECT u.user_id, 'Dr. Faye Morgan', 'Cognitive Behavioral Therapy (CBT)', 'LIC-FA-10293', 'CA', 'US', 'PhD Clinical Psychology', 9, 'verified'
FROM users u
WHERE u.username = 'dr_faye'
LIMIT 1;

-- 15 dummy forum posts
INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Anxiety', 'Feeling tense for no clear reason',
'Has anyone found a simple way to calm down when anxiety hits without warning? I am trying breathing exercises but I still feel on edge. Any practical routines that helped you over time?',
TRUE, 'published', (NOW() - INTERVAL 14 DAY)
FROM users u WHERE u.username = 'ava_patient' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Sleep', 'Trouble sleeping after stressful days',
'I notice that my thoughts keep looping at night. What are your best wind-down habits that actually stick? I am aiming for something realistic, not perfect.',
TRUE, 'published', (NOW() - INTERVAL 13 DAY)
FROM users u WHERE u.username = 'noah_patient' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Work/School', 'Burnout creeping in again',
'I keep pushing through and then crash. How do you set boundaries when deadlines are constant? Looking for small changes I can start this week.',
TRUE, 'published', (NOW() - INTERVAL 12 DAY)
FROM users u WHERE u.username = 'liam_patient' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'General Support', 'Trying to be kinder to myself',
'I would like to reduce self-criticism and stop replaying mistakes. What helped you build self-compassion without feeling like you are making excuses?',
TRUE, 'published', (NOW() - INTERVAL 11 DAY)
FROM users u WHERE u.username = 'mina_supporter' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Depression', 'Low energy and feeling stuck',
'Some days it takes all my effort to do basic tasks. What is one tiny habit that helped you regain momentum when motivation is low?',
TRUE, 'published', (NOW() - INTERVAL 10 DAY)
FROM users u WHERE u.username = 'ava_patient' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Relationships', 'How to ask for support without guilt',
'I want to talk to my partner about how I feel, but I worry I am a burden. How do you start that conversation in a healthy way?',
TRUE, 'published', (NOW() - INTERVAL 9 DAY)
FROM users u WHERE u.username = 'noah_patient' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Stress', 'Overthinking every decision',
'I second-guess myself constantly, even for small choices. If you have dealt with this, what helped you trust your judgment again?',
TRUE, 'published', (NOW() - INTERVAL 8 DAY)
FROM users u WHERE u.username = 'liam_patient' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Self-Care', 'Self-care that is not expensive',
'What are your favorite low-cost self-care ideas that actually reduce stress? I am looking for options I can do at home.',
TRUE, 'published', (NOW() - INTERVAL 7 DAY)
FROM users u WHERE u.username = 'sam_volunteer' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Anxiety', 'Panic-like symptoms while commuting',
'I sometimes feel my heart race on public transport. Any tips for grounding in the moment so I can get where I need to go?',
TRUE, 'published', (NOW() - INTERVAL 6 DAY)
FROM users u WHERE u.username = 'ava_patient' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'General Support', 'Checking in: what helped today?',
'Sharing a small win: I took a short walk and drank water. What is one thing that helped you today, even if it was small?',
TRUE, 'published', (NOW() - INTERVAL 5 DAY)
FROM users u WHERE u.username = 'mina_supporter' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Sleep', 'Waking up too early',
'I wake up at 4–5am and cannot fall back asleep. Have you found a strategy that helps you return to sleep without spiraling?',
TRUE, 'published', (NOW() - INTERVAL 4 DAY)
FROM users u WHERE u.username = 'noah_patient' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Relationships', 'Friends do not understand mental health days',
'I struggle to explain why I need to cancel plans sometimes. How do you communicate boundaries without losing friendships?',
TRUE, 'published', (NOW() - INTERVAL 3 DAY)
FROM users u WHERE u.username = 'liam_patient' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Work/School', 'Imposter syndrome in a new role',
'I got a new role and now I feel like I will be exposed as not good enough. What helped you manage imposter thoughts?',
TRUE, 'published', (NOW() - INTERVAL 2 DAY)
FROM users u WHERE u.username = 'ava_patient' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Stress', 'Too many tabs open in my brain',
'When everything feels urgent, I freeze. What planning methods help you prioritize without feeling overwhelmed?',
TRUE, 'published', (NOW() - INTERVAL 1 DAY)
FROM users u WHERE u.username = 'sam_volunteer' LIMIT 1;

INSERT INTO forum_posts (user_id, category, title, content, is_encrypted, status, created_at)
SELECT u.user_id, 'Depression', 'Hard to enjoy things I used to like',
'I am not feeling excitement for hobbies lately. If you have been there, what helped you reconnect with enjoyment over time?',
TRUE, 'published', (NOW() - INTERVAL 0 DAY)
FROM users u WHERE u.username = 'noah_patient' LIMIT 1;

-- 15 dummy blog posts (regular)
INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Self-Care & Habits', 'Small habits that reduce stress',
'I started with one habit: a 5-minute reset after lunch. I step away from screens, stretch, and name three things I can control today. It is simple, but it helps me return calmer.',
FALSE, 'published', (NOW() - INTERVAL 20 DAY)
FROM users u WHERE u.username = 'ava_patient' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Sleep & Lifestyle', 'A realistic evening routine',
'I used to aim for a perfect routine and fail. Now I do a “minimum routine”: dim lights, one cup of tea, and write tomorrow’s top three tasks. Consistency beats intensity.',
FALSE, 'published', (NOW() - INTERVAL 19 DAY)
FROM users u WHERE u.username = 'noah_patient' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Mindfulness & Meditation', 'Mindfulness for people who overthink',
'If sitting still makes your mind louder, try mindful movement. A slow walk and focusing on sounds or footsteps can be easier than silent meditation.',
FALSE, 'published', (NOW() - INTERVAL 18 DAY)
FROM users u WHERE u.username = 'liam_patient' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Relationships & Family', 'How I started asking for support',
'I practiced one sentence: “I don’t need you to fix it, I just need you to listen.” It reduced my fear of being a burden and made conversations clearer.',
FALSE, 'published', (NOW() - INTERVAL 17 DAY)
FROM users u WHERE u.username = 'mina_supporter' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Stress & Burnout', 'Noticing burnout early',
'For me, burnout starts with irritability and skipping meals. I made a checklist of early warning signs and set reminders to take short breaks before I crash.',
FALSE, 'published', (NOW() - INTERVAL 16 DAY)
FROM users u WHERE u.username = 'sam_volunteer' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Anxiety & Worry', 'My “worry window” experiment',
'I set a 10-minute slot each day to write worries down. Outside that time, I tell myself: “Not now, later.” It does not erase anxiety, but it contains it.',
FALSE, 'published', (NOW() - INTERVAL 15 DAY)
FROM users u WHERE u.username = 'ava_patient' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Depression & Mood', 'Doing the next smallest thing',
'When everything feels heavy, I focus on the next smallest action: open the curtains, drink water, sit up. Momentum builds from tiny, repeatable steps.',
FALSE, 'published', (NOW() - INTERVAL 14 DAY)
FROM users u WHERE u.username = 'noah_patient' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Self-Care & Habits', 'A gentle checklist for hard days',
'My checklist is short: eat something, hydrate, shower or wash face, text one person, and step outside. If I do two items, I count it as progress.',
FALSE, 'published', (NOW() - INTERVAL 13 DAY)
FROM users u WHERE u.username = 'liam_patient' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Recovery Stories', 'What helped me stay consistent',
'I stopped trying to change everything at once. I picked one habit per month. The slow pace felt boring, but it actually worked.',
FALSE, 'published', (NOW() - INTERVAL 12 DAY)
FROM users u WHERE u.username = 'mina_supporter' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Sleep & Lifestyle', 'Phone settings that improved sleep',
'I set my phone to grayscale at night and moved social apps off the home screen. It reduced doomscrolling and made it easier to put the phone down.',
FALSE, 'published', (NOW() - INTERVAL 11 DAY)
FROM users u WHERE u.username = 'sam_volunteer' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Relationships & Family', 'Boundaries: a simple script',
'I use: “I can’t do that today, but I can do X.” Offering an alternative helps me feel less guilty while still protecting my energy.',
FALSE, 'published', (NOW() - INTERVAL 10 DAY)
FROM users u WHERE u.username = 'ava_patient' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Mindfulness & Meditation', 'Breathing that works for me',
'I prefer box breathing: inhale 4, hold 4, exhale 4, hold 4. I do three rounds and then check if my shoulders dropped even a little.',
FALSE, 'published', (NOW() - INTERVAL 9 DAY)
FROM users u WHERE u.username = 'noah_patient' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Stress & Burnout', 'Reducing overwhelm with a “done list”',
'Instead of only tracking tasks, I track what I completed. It gives my brain evidence of progress and reduces the feeling that nothing changes.',
FALSE, 'published', (NOW() - INTERVAL 8 DAY)
FROM users u WHERE u.username = 'liam_patient' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Kids & Teen Mental Health', 'Supporting a younger sibling',
'I learned to listen first and avoid immediate advice. Asking “Do you want help, comfort, or space?” made conversations safer and calmer.',
FALSE, 'published', (NOW() - INTERVAL 7 DAY)
FROM users u WHERE u.username = 'mina_supporter' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Therapy & Counseling', 'How I prepared for my first session',
'I wrote down three goals and a few examples of what I was struggling with. It helped me feel less nervous and made the first session more focused.',
FALSE, 'published', (NOW() - INTERVAL 6 DAY)
FROM users u WHERE u.username = 'sam_volunteer' LIMIT 1;

-- 5 dummy professional blog posts
INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Professional Insights', 'A practical grounding routine in 60 seconds',
'Try the 5-4-3-2-1 method: name 5 things you see, 4 you feel, 3 you hear, 2 you smell, and 1 you taste. Pair it with slow exhales to downshift your nervous system.',
TRUE, 'published', (NOW() - INTERVAL 5 DAY)
FROM users u WHERE u.username = 'dr_faye' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Professional Insights', 'Thoughts vs facts: a CBT mini-exercise',
'Write one anxious thought, then write the evidence for and against it. End with a balanced statement that is realistic, not overly positive. Repeat daily to build the skill.',
TRUE, 'published', (NOW() - INTERVAL 4 DAY)
FROM users u WHERE u.username = 'dr_faye' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Professional Insights', 'Sleep basics that actually matter',
'If you only change two things: keep a consistent wake time and get morning light within an hour of waking. These anchor your body clock and often improve sleep quality.',
TRUE, 'published', (NOW() - INTERVAL 3 DAY)
FROM users u WHERE u.username = 'dr_faye' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Professional Insights', 'Behavioral activation: starting when motivation is low',
'Motivation often follows action. Pick a 2-minute task that matches your values (tidy one surface, step outside, text a friend). Track the action, not the mood.',
TRUE, 'published', (NOW() - INTERVAL 2 DAY)
FROM users u WHERE u.username = 'dr_faye' LIMIT 1;

INSERT INTO blog_posts (user_id, category, title, content, is_professional_post, status, created_at)
SELECT u.user_id, 'Professional Insights', 'A calmer way to set boundaries',
'Use a clear, respectful boundary plus a brief reason: “I can’t take calls after 9pm because I’m protecting sleep.” Then offer an alternative time when possible.',
TRUE, 'published', (NOW() - INTERVAL 1 DAY)
FROM users u WHERE u.username = 'dr_faye' LIMIT 1;


