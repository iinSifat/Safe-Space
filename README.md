# Safe Space - Setup Instructions

## 🚀 Quick Start Guide

### Prerequisites
- **XAMPP** or **WAMP** (includes Apache, MySQL, PHP)
- Web browser (Chrome, Firefox, Edge)
- Text editor (VS Code, Sublime, etc.)

---

## 📋 Installation Steps

### 1. Install XAMPP/WAMP
1. Download XAMPP from: https://www.apachefriends.org/
2. Install XAMPP to `C:\xampp` (default location)
3. Start **Apache** and **MySQL** from XAMPP Control Panel

### 2. Setup Database
1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Create a new database OR use the SQL file:
   - Click "New" to create database
   - Name it: `safe_space_db`
   - Click "Create"
3. Import the schema:
   - Click on `safe_space_db` database
   - Go to "SQL" tab
   - Copy and paste content from `database/schema.sql`
   - Click "Go" to execute

**OR** run SQL file directly:
- Click "Import" tab
- Choose file: `database/schema.sql`
- Click "Go"

### 3. Configure Database Connection
1. Open `config/config.php`
2. Update these lines if needed (default should work):
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Default XAMPP username
define('DB_PASS', '');              // Default XAMPP password (empty)
define('DB_NAME', 'safe_space_db');
```

### 4. Move Project to Web Server
1. Copy the project folder to:
   - **XAMPP**: `C:\xampp\htdocs\`
   - **WAMP**: `C:\wamp64\www\`
2. Rename folder to: `safe-space` (or keep current name)

### 5. Access the Application
Open your browser and go to:
```
http://localhost/DBMS PROJECŢVIBE CODING/
```

Or if you renamed the folder:
```
http://localhost/safe-space/
```

---

## 🔐 Default Admin Credentials

**Username:** `admin`  
**Password:** `Admin@123`

**Access Admin Panel:**
```
http://localhost/DBMS PROJECŢVIBE CODING/auth/admin_login.php
```

---

## 📁 Project Structure

```
DBMS PROJECŢVIBE CODING/
├── index.php                  # Landing page
├── config/
│   └── config.php            # Database configuration
├── database/
│   └── schema.sql            # Database structure
├── auth/
│   ├── registration.php      # User registration
│   ├── login.php            # User login
│   └── admin_login.php      # Admin login
├── assets/
│   └── css/
│       └── styles.css       # Beautiful mental health theme
├── dashboard/               # User dashboards (to be created)
└── admin/                   # Admin panel (to be created)
```

---

## 🎨 Features Implemented

### ✅ Phase 1 - Authentication System
- [x] Beautiful, calming mental health-themed UI
- [x] User registration with role selection
  - Patient
  - Mental Health Professional
  - Volunteer/Trainee Psychologist
- [x] User login with session management
- [x] Admin login (separate authentication)
- [x] Password encryption (bcrypt)
- [x] Database schema with proper relationships
- [x] Activity logging
- [x] Gamification system (points & badges)

---

## 👥 User Roles

### 1. **Patient**
- Seeking mental health support
- Can access forums, consultations, resources
- Earn points and badges for engagement

### 2. **Professional**
- Licensed mental health experts
- Must verify credentials
- Can offer paid/free consultations
- Pending verification after registration

### 3. **Volunteer**
- Trainee psychologists or peer supporters
- Must complete training modules
- Provide peer support after approval
- Pending approval after registration

### 4. **Admin**
- System administrators and moderators
- Manage users, verify professionals
- Moderate content and forums
- Access analytics and reports

---

## 🧪 Testing the Application

### Test User Registration
1. Go to: http://localhost/DBMS PROJECŢVIBE CODING/auth/registration.php
2. Select a role (Patient, Professional, or Volunteer)
3. Fill in the form
4. Submit and verify success

### Test User Login
1. Go to: http://localhost/DBMS PROJECŢVIBE CODING/auth/login.php
2. Enter credentials
3. Login and verify redirection

### Test Admin Login
1. Go to: http://localhost/DBMS PROJECŢVIBE CODING/auth/admin_login.php
2. Username: `admin`
3. Password: `Admin@123`
4. Login and verify admin access

---

## 🔧 Troubleshooting

### Database Connection Error
**Error:** "Connection failed"
- Ensure MySQL is running in XAMPP
- Check database credentials in `config/config.php`
- Verify database `safe_space_db` exists

### Page Not Found (404)
- Check project folder name matches URL
- Ensure Apache is running
- Clear browser cache

### Password Hash Error
- PHP version must be 5.5 or higher
- Check PHP version: Create `info.php` with `<?php phpinfo(); ?>`
- Access: http://localhost/info.php

### Session Issues
- Check if session.save_path is writable
- Verify PHP sessions are enabled
- Clear browser cookies

---

## 📊 Database Tables

The system includes these tables:
1. **admins** - Admin accounts
2. **users** - All user accounts
3. **professionals** - Professional details
4. **volunteers** - Volunteer details
5. **user_points** - Gamification points
6. **user_badges** - Achievement badges
7. **activity_log** - System activity tracking

---

## 🎯 Next Development Phases

### Phase 2 - Dashboard & Profile
- [ ] User dashboard
- [ ] Profile management
- [ ] Settings page

### Phase 3 - Forums & Community
- [ ] Anonymous forums
- [ ] Post creation and moderation
- [ ] Comment system

### Phase 4 - Professional Services
- [ ] Professional verification system
- [ ] Consultation booking
- [ ] Video/chat consultations

### Phase 5 - Gamification
- [ ] Points system implementation
- [ ] Badge awards
- [ ] Tier progression

---

## 🛡️ Security Features

- ✅ Password hashing (bcrypt)
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ CSRF protection ready
- ✅ Session security
- ✅ Activity logging
- ✅ Input validation & sanitization

---

## 📧 Support

For issues or questions:
- Email: support@safespace.com
- Admin Email: admin@safespace.com

---

## 📝 Notes

- All passwords are hashed using bcrypt
- Default admin password should be changed in production
- The system uses UTF-8 encoding
- Session timeout is set to 1 hour
- File uploads limited to 5MB

---

## 🎨 Design Philosophy

The interface uses:
- **Calming colors** (soft blues, purples, teals)
- **Smooth animations** for engagement
- **Accessible design** for all users
- **Responsive layout** for mobile devices
- **Mental health-focused** messaging

---

**Safe Space** - *Your journey to wellness starts here* 💙
