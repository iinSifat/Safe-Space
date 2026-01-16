# Safe Space - Complete File Structure

```
E:\DBMS PROJECŢVIBE CODING\
│
├── 📄 index.php                          ← 🏠 Landing Page (START HERE)
│   ├── Beautiful gradient background
│   ├── Safe Space logo and branding
│   ├── Feature showcase cards
│   ├── Stats section (24/7, Free, etc.)
│   └── Get Started / Sign In buttons
│
├── 📄 .htaccess                          ← 🔒 Security & Apache config
│   ├── Prevents directory listing
│   ├── Protects config files
│   └── Security headers
│
├── 📄 README.md                          ← 📚 Main documentation
├── 📄 TESTING.md                         ← 🧪 Testing guide
├── 📄 DESIGN.md                          ← 🎨 Design system docs
├── 📄 PROJECT_SUMMARY.md                 ← 📊 Complete overview
└── 📄 QUICKSTART.txt                     ← ⚡ Quick start guide
│
├── 📁 config/                            ← ⚙️ Configuration
│   └── 📄 config.php                     ← Database connection & helpers
│       ├── Database class (singleton)
│       ├── Helper functions
│       ├── Security functions
│       ├── Session management
│       └── Constants & settings
│
├── 📁 database/                          ← 🗄️ Database files
│   └── 📄 schema.sql                     ← Complete DB structure
│       ├── 7 tables with relationships
│       ├── Indexes for performance
│       ├── Triggers for automation
│       ├── Default admin accounts
│       └── Security constraints
│
├── 📁 auth/                              ← 🔐 Authentication pages
│   │
│   ├── 📄 registration.php               ← ✍️ User Registration
│   │   ├── Role selection (3 cards)
│   │   │   ├── Patient
│   │   │   ├── Professional
│   │   │   └── Volunteer
│   │   ├── Form validation
│   │   ├── Password strength check
│   │   ├── Duplicate detection
│   │   ├── Bcrypt password hashing
│   │   ├── Auto-creates related tables
│   │   └── Redirects to login on success
│   │
│   ├── 📄 login.php                      ← 🔑 User Login
│   │   ├── Username or email login
│   │   ├── Password verification
│   │   ├── Remember me option
│   │   ├── Session creation
│   │   ├── Activity logging
│   │   ├── Last login update
│   │   └── Role-based redirect
│   │
│   └── 📄 admin_login.php                ← 🛡️ Admin Login
│       ├── Separate admin authentication
│       ├── Orange/red theme
│       ├── Security warnings
│       ├── Activity logging
│       └── Admin dashboard redirect
│
├── 📁 dashboard/                         ← 📊 User area
│   │
│   ├── 📄 index.php                      ← 🏡 User Dashboard
│   │   ├── Welcome message
│   │   ├── User info display
│   │   │   ├── Role
│   │   │   ├── Points
│   │   │   └── Tier
│   │   ├── Feature cards (Coming Soon)
│   │   │   ├── My Profile
│   │   │   ├── Community Forums
│   │   │   ├── Book Consultation
│   │   │   ├── Learning Resources
│   │   │   ├── My Rewards
│   │   │   └── Emergency Support
│   │   ├── Logout button
│   │   └── Protected (login required)
│   │
│   └── 📄 logout.php                     ← 👋 Logout Handler
│       ├── Logs activity
│       ├── Destroys session
│       ├── Clears cookies
│       └── Redirects to login
│
├── 📁 admin/                             ← 🛡️ Admin area
│   │
│   └── 📄 dashboard.php                  ← 📈 Admin Dashboard
│       ├── Admin header (orange theme)
│       ├── Statistics cards
│       │   ├── Total Users
│       │   ├── Professionals
│       │   ├── Volunteers
│       │   └── Forum Posts
│       ├── Management sections
│       │   ├── User Management
│       │   ├── Content Moderation
│       │   ├── Analytics & Reports
│       │   └── System Settings
│       ├── Recent activity log
│       └── Protected (admin only)
│
└── 📁 assets/                            ← 🎨 Static resources
    │
    └── 📁 css/
        │
        └── 📄 styles.css                 ← 💅 Complete styling (900+ lines)
            ├── CSS Variables (colors, spacing)
            ├── Global reset & base styles
            ├── Animated gradient background
            ├── Container & layout
            ├── Auth card components
            ├── Logo & branding
            ├── Form elements
            │   ├── Inputs
            │   ├── Selects
            │   ├── Textareas
            │   └── Password toggle
            ├── Buttons (primary, secondary, admin)
            ├── Role selection cards
            ├── Alerts & messages
            ├── Links & text utilities
            ├── Checkboxes & radios
            ├── Responsive breakpoints
            ├── Utility classes
            ├── Loading spinner
            ├── Accessibility styles
            └── Print styles
```

---

## 📊 Database Structure (Inside phpMyAdmin)

```
safe_space_db/
│
├── 📋 admins                             ← Admin accounts
│   ├── admin_id (PK)
│   ├── username (UNIQUE)
│   ├── email (UNIQUE)
│   ├── password_hash
│   ├── full_name
│   ├── role (super_admin, moderator, content_manager)
│   ├── created_at
│   ├── last_login
│   └── is_active
│
├── 📋 users                              ← Main user accounts
│   ├── user_id (PK)
│   ├── username (UNIQUE)
│   ├── email (UNIQUE)
│   ├── password_hash
│   ├── user_type (patient, professional, volunteer, supporter)
│   ├── is_anonymous
│   ├── is_verified
│   ├── is_active
│   ├── profile_picture
│   ├── bio
│   ├── date_of_birth
│   ├── gender
│   ├── country
│   ├── timezone
│   ├── created_at
│   ├── last_login
│   ├── email_verified_at
│   ├── verification_token
│   ├── reset_token
│   └── reset_token_expiry
│
├── 📋 professionals                      ← Mental health professionals
│   ├── professional_id (PK)
│   ├── user_id (FK → users) (UNIQUE)
│   ├── full_name
│   ├── specialization
│   ├── license_number
│   ├── license_state
│   ├── license_country
│   ├── degree
│   ├── years_of_experience
│   ├── credentials (JSON)
│   ├── consultation_fee
│   ├── bio_detailed
│   ├── languages_spoken
│   ├── availability_schedule (JSON)
│   ├── is_accepting_patients
│   ├── verification_status (pending, verified, rejected)
│   ├── verification_documents (JSON)
│   ├── verified_at
│   ├── verified_by
│   ├── created_at
│   └── updated_at
│
├── 📋 volunteers                         ← Peer support volunteers
│   ├── volunteer_id (PK)
│   ├── user_id (FK → users) (UNIQUE)
│   ├── full_name
│   ├── age
│   ├── education_level
│   ├── motivation
│   ├── lived_experience
│   ├── availability_hours_per_week
│   ├── preferred_support_areas
│   ├── training_completed
│   ├── training_completion_date
│   ├── is_active_volunteer
│   ├── approval_status (pending, approved, rejected, suspended)
│   ├── approved_at
│   ├── approved_by
│   ├── background_check_status
│   ├── total_support_hours
│   ├── created_at
│   └── updated_at
│
├── 📋 user_points                        ← Gamification points
│   ├── point_id (PK)
│   ├── user_id (FK → users)
│   ├── total_points
│   ├── tier_level (bronze, silver, gold)
│   ├── points_spent
│   ├── last_activity_date
│   ├── streak_days
│   ├── created_at
│   └── updated_at
│   └── Auto-created on user registration (Trigger)
│
├── 📋 user_badges                        ← Achievement badges
│   ├── badge_id (PK)
│   ├── user_id (FK → users)
│   ├── badge_name
│   ├── badge_description
│   ├── earned_at
│   └── badge_icon
│
└── 📋 activity_log                       ← Security & audit log
    ├── log_id (PK)
    ├── user_id (FK → users) (nullable)
    ├── activity_type
    ├── activity_description
    ├── ip_address
    ├── user_agent
    └── created_at
    └── Logs: registration, login, logout, admin actions
```

---

## 🔗 Relationships & Foreign Keys

```
users (1) ←─────→ (1) user_points
      ↓
      └─ professionals (optional, if role=professional)
      └─ volunteers (optional, if role=volunteer)
      
users (1) ←─────→ (many) user_badges
users (1) ←─────→ (many) activity_log

ON DELETE CASCADE: Deleting user removes all related data
```

---

## 🎯 Page Flow Diagram

```
                    ┌─────────────┐
                    │  index.php  │ ← Landing Page
                    │   (Public)  │
                    └──────┬──────┘
                           │
              ┌────────────┴────────────┐
              ↓                         ↓
    ┌──────────────────┐      ┌──────────────────┐
    │ registration.php │      │    login.php     │
    │  (Public)        │      │    (Public)      │
    └────────┬─────────┘      └─────────┬────────┘
             │                          │
             │ Register                 │ Login
             │                          │
             └──────────┬───────────────┘
                        ↓
              ┌──────────────────┐
              │ dashboard/       │
              │   index.php      │ ← User Dashboard
              │  (Protected)     │
              └──────────────────┘
                        │
                        ↓ Logout
              ┌──────────────────┐
              │ dashboard/       │
              │   logout.php     │
              └──────────────────┘
                        │
                        ↓ Redirect
              ┌──────────────────┐
              │    login.php     │
              └──────────────────┘


    ADMIN FLOW:
    
    ┌─────────────────┐
    │ admin_login.php │ ← Admin Portal Entry
    │   (Public)      │
    └────────┬────────┘
             │ Admin Login
             ↓
    ┌─────────────────┐
    │ admin/          │
    │ dashboard.php   │ ← Admin Dashboard
    │  (Admin Only)   │
    └─────────────────┘
```

---

## 🔒 Security Layer Map

```
┌──────────────────────────────────────────────┐
│              User Input                      │
└──────────────┬───────────────────────────────┘
               ↓
┌──────────────────────────────────────────────┐
│  1. Input Sanitization (sanitize_input)     │
│     - trim(), stripslashes()                 │
│     - htmlspecialchars()                     │
└──────────────┬───────────────────────────────┘
               ↓
┌──────────────────────────────────────────────┐
│  2. Validation                               │
│     - Email format                           │
│     - Password strength                      │
│     - Required fields                        │
└──────────────┬───────────────────────────────┘
               ↓
┌──────────────────────────────────────────────┐
│  3. Database Security                        │
│     - Prepared Statements                    │
│     - Parameter binding                      │
│     - SQL Injection Prevention               │
└──────────────┬───────────────────────────────┘
               ↓
┌──────────────────────────────────────────────┐
│  4. Password Hashing                         │
│     - Bcrypt (cost: 12)                      │
│     - Salt included                          │
└──────────────┬───────────────────────────────┘
               ↓
┌──────────────────────────────────────────────┐
│  5. Session Management                       │
│     - Secure session cookies                 │
│     - Session regeneration                   │
│     - Timeout (1 hour)                       │
└──────────────┬───────────────────────────────┘
               ↓
┌──────────────────────────────────────────────┐
│  6. Activity Logging                         │
│     - All actions logged                     │
│     - IP & User Agent captured               │
└──────────────────────────────────────────────┘
```

---

## 📂 Folder Permissions (For Production)

```
E:\DBMS PROJECŢVIBE CODING\
├── config/           → 755 (protect config.php)
├── database/         → 700 (block public access)
├── auth/             → 755
├── dashboard/        → 755
├── admin/            → 755
├── assets/           → 755
│   └── css/          → 755
└── uploads/ (create) → 777 (for file uploads)
```

---

## 🎨 Asset Organization (Future)

```
assets/
├── css/
│   ├── styles.css          (Main stylesheet - current)
│   ├── admin.css           (Admin-specific styles - future)
│   └── print.css           (Print styles - future)
│
├── js/                     (Future JavaScript)
│   ├── main.js
│   ├── validation.js
│   └── dashboard.js
│
├── images/                 (Future images)
│   ├── logo.png
│   ├── favicon.ico
│   └── backgrounds/
│
└── icons/                  (Future icon files)
    └── badges/
```

---

## ✅ File Status Legend

📄 **Created & Complete**
📁 **Directory (exists)**
🔒 **Security file**
🏠 **Entry point**
⚙️ **Configuration**
🗄️ **Database**
🔐 **Authentication**
📊 **Dashboard**
🛡️ **Admin**
🎨 **Styling**
📚 **Documentation**

---

**Total Files Created:** 15  
**Total Lines of Code:** ~3,550  
**Status:** ✅ Phase 1 Complete
