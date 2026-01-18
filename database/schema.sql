-- Safe Space: Mental Health Care System Database Schema
-- Created: January 2026
-- Database: safe_space_db

-- Drop existing database if exists and create fresh
DROP DATABASE IF EXISTS safe_space_db;
CREATE DATABASE safe_space_db;
USE safe_space_db;

-- ==============================================
-- 1. ADMINS TABLE
-- ==============================================
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

-- ==============================================
-- 2. USERS TABLE (Main user account)
-- ==============================================
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

-- ==============================================
-- 3. PROFESSIONALS TABLE (Mental Health Professionals)
-- ==============================================
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

-- ==============================================
-- 4. VOLUNTEERS TABLE (Peer Support Volunteers)
-- ==============================================
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

-- ==============================================
-- 4b. VOLUNTEER APPLICATIONS TABLE (Post-login applications)
-- ==============================================
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

-- ==============================================
-- 4c. NOTIFICATIONS TABLE
-- ==============================================
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

-- ==============================================
-- 5. USER POINTS TABLE (Gamification)
-- ==============================================
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

-- ==============================================
-- 6. USER BADGES TABLE
-- ==============================================
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

-- ==============================================
-- 7. ACTIVITY LOG TABLE
-- ==============================================
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

-- ==============================================
-- 8. MOOD LOGS TABLE (Mood Tracking)
-- ==============================================
CREATE TABLE mood_logs (
    mood_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mood_level INT CHECK (mood_level >= 1 AND mood_level <= 10),
    mood_emoji VARCHAR(10) NULL,
    mood_label VARCHAR(50) NULL,
    notes TEXT NULL,
    activities VARCHAR(255) NULL COMMENT 'JSON array of activities (exercise, meditation, sleep, etc.)',
    energy_level INT CHECK (energy_level >= 1 AND energy_level <= 5) DEFAULT 3,
    stress_level INT CHECK (stress_level >= 1 AND stress_level <= 10) DEFAULT 5,
    triggers VARCHAR(255) NULL COMMENT 'Comma-separated mood triggers',
    medication_taken BOOLEAN DEFAULT FALSE,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_mood (user_id, logged_at),
    INDEX idx_mood_level (mood_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================
-- 9. COMMUNITY HEALERS TABLE
-- ==============================================
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

-- ==============================================
-- 10. FORUM POSTS TABLE
-- ==============================================
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

-- ==============================================
-- 11. FORUM REPLIES TABLE
-- ==============================================
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

-- ==============================================
-- Insert default admin account
-- Password: Admin@123 (hashed with bcrypt)
-- ==============================================
INSERT INTO admins (username, email, password_hash, full_name, role, is_active) 
VALUES 
    ('admin', 'admin@safespace.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin', TRUE),
    ('moderator1', 'moderator@safespace.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Content Moderator', 'moderator', TRUE);

-- ==============================================
-- MENTAL HEALTH TEST SUITE TABLES
-- ==============================================

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

-- ==============================================
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

-- ==============================================
-- END OF SCHEMA
-- ==============================================
