# Safe Space - Testing Guide

## 🧪 Quick Testing Checklist

### Setup Verification

#### 1. Check Database Connection
- [ ] Open: http://localhost/phpmyadmin
- [ ] Verify database `safe_space_db` exists
- [ ] Check all 7 tables are created
- [ ] Verify admin account exists in `admins` table

#### 2. Check File Structure
```
✓ index.php (landing page)
✓ config/config.php (database config)
✓ database/schema.sql (database structure)
✓ auth/registration.php (user registration)
✓ auth/login.php (user login)
✓ auth/admin_login.php (admin login)
✓ assets/css/styles.css (styling)
✓ dashboard/index.php (user dashboard)
✓ dashboard/logout.php (logout)
✓ admin/dashboard.php (admin panel)
```

---

## 🎯 Test Scenarios

### Test 1: Landing Page
**URL:** http://localhost/DBMS PROJECŢVIBE CODING/

**Expected:**
- ✅ Beautiful gradient background
- ✅ Safe Space logo and branding
- ✅ Feature cards displayed
- ✅ "Get Started Free" and "Sign In" buttons work
- ✅ Stats section shows (24/7, 100%, Free, ∞)

---

### Test 2: User Registration

**URL:** http://localhost/DBMS PROJECŢVIBE CODING/auth/registration.php

#### Test Case 2.1: Register as Patient
1. Click on "Patient" role card
2. Fill in:
   - Username: `test_patient`
   - Email: `patient@test.com`
   - Password: `Password@123`
   - Confirm Password: `Password@123`
3. Check "I agree to terms"
4. Click "Create Account"

**Expected:**
- ✅ Success message: "Registration successful!"
- ✅ Redirected to login page
- ✅ User exists in database `users` table
- ✅ Entry created in `user_points` table

#### Test Case 2.2: Register as Professional
1. Click on "Professional" role card
2. Fill in form with professional credentials
3. Submit

**Expected:**
- ✅ Success message
- ✅ Entry in `users` and `professionals` tables
- ✅ Professional status: "pending" verification

#### Test Case 2.3: Register as Volunteer
1. Click on "Volunteer" role card
2. Fill in form
3. Submit

**Expected:**
- ✅ Success message
- ✅ Entry in `users` and `volunteers` tables
- ✅ Volunteer status: "pending" approval

#### Test Case 2.4: Validation Errors
Test with:
- Empty fields → Should show error
- Short username (< 3 chars) → Error
- Invalid email → Error
- Weak password → Error
- Passwords don't match → Error
- Duplicate username → Error
- Duplicate email → Error
- No role selected → Error

---

### Test 3: User Login

**URL:** http://localhost/DBMS PROJECŢVIBE CODING/auth/login.php

#### Test Case 3.1: Successful Login
1. Username/Email: `test_patient`
2. Password: `Password@123`
3. Click "Sign In"

**Expected:**
- ✅ Redirected to `/dashboard/index.php`
- ✅ Welcome message shows username
- ✅ User info displays role and points
- ✅ Last login updated in database

#### Test Case 3.2: Failed Login
1. Wrong username
2. Wrong password
3. Click "Sign In"

**Expected:**
- ✅ Error: "Invalid username/email or password"
- ✅ Failed attempt logged in `activity_log` table

#### Test Case 3.3: Remember Me
1. Check "Remember me"
2. Login successfully
3. Close browser
4. Reopen

**Expected:**
- ✅ Cookie set for 30 days
- ✅ User remains logged in

---

### Test 4: Admin Login

**URL:** http://localhost/DBMS PROJECŢVIBE CODING/auth/admin_login.php

#### Test Case 4.1: Admin Login Success
1. Username: `admin`
2. Password: `Admin@123`
3. Click "Access Admin Portal"

**Expected:**
- ✅ Redirected to `/admin/dashboard.php`
- ✅ Admin dashboard displays
- ✅ Welcome message shows admin name
- ✅ Statistics cards visible (even if empty)
- ✅ Last login updated

#### Test Case 4.2: Failed Admin Login
1. Wrong credentials
2. Click login

**Expected:**
- ✅ Error message displayed
- ✅ Failed attempt logged

---

### Test 5: User Dashboard

**URL:** http://localhost/DBMS PROJECŢVIBE CODING/dashboard/index.php

#### Test Case 5.1: Access While Logged In
**Expected:**
- ✅ Welcome message with username
- ✅ User role displayed
- ✅ Points and tier shown
- ✅ All feature cards visible with "Coming Soon" badges
- ✅ Emergency support card highlighted
- ✅ Logout button works

#### Test Case 5.2: Access Without Login
1. Clear session/logout
2. Try to access dashboard directly

**Expected:**
- ✅ Redirected to login page
- ✅ Message: "Please login to continue"

---

### Test 6: Logout

**URL:** http://localhost/DBMS PROJECŢVIBE CODING/dashboard/logout.php

#### Test Case 6.1: User Logout
1. Login as user
2. Click logout

**Expected:**
- ✅ Redirected to login page
- ✅ Success message: "Logged out successfully"
- ✅ Session destroyed
- ✅ Cannot access dashboard without re-login
- ✅ Logout logged in `activity_log`

---

### Test 7: Session Management

#### Test Case 7.1: Session Timeout
1. Login
2. Wait 1+ hour (or modify SESSION_LIFETIME in config)
3. Try to access dashboard

**Expected:**
- ✅ Redirected to login
- ✅ Message: "Session expired"

#### Test Case 7.2: Simultaneous Access
1. Login on Chrome
2. Try to access from Firefox (same account)

**Expected:**
- ✅ Both sessions work independently
- ✅ Session IDs are different

---

### Test 8: Security Tests

#### Test Case 8.1: SQL Injection Prevention
Try login with:
- Username: `admin' OR '1'='1`
- Password: `anything`

**Expected:**
- ✅ Login fails
- ✅ No database error
- ✅ Protected by prepared statements

#### Test Case 8.2: XSS Prevention
Register with:
- Username: `<script>alert('XSS')</script>`

**Expected:**
- ✅ Script not executed
- ✅ Displayed as plain text
- ✅ htmlspecialchars() working

#### Test Case 8.3: Direct File Access
Try accessing:
- http://localhost/.../config/config.php

**Expected:**
- ✅ Access denied (403)
- ✅ .htaccess protection working

---

### Test 9: Password Security

#### Test Case 9.1: Password Hashing
1. Register new user
2. Check database `users` table
3. Look at `password_hash` column

**Expected:**
- ✅ Password is hashed (not plain text)
- ✅ Hash starts with `$2y$` (bcrypt)
- ✅ Different users have different hashes

---

### Test 10: Database Triggers

#### Test Case 10.1: Auto Point Creation
1. Register new user
2. Check `user_points` table

**Expected:**
- ✅ Entry automatically created
- ✅ Total points = 0
- ✅ Tier = 'bronze'

#### Test Case 10.2: Activity Logging
1. Register user
2. Check `activity_log` table

**Expected:**
- ✅ Registration logged
- ✅ User ID, type, description recorded
- ✅ IP address and user agent captured

---

## 📊 Database Verification Queries

Run these in phpMyAdmin SQL tab:

### Check Users
```sql
SELECT user_id, username, email, user_type, is_verified, created_at 
FROM users 
ORDER BY created_at DESC;
```

### Check Points
```sql
SELECT u.username, up.total_points, up.tier_level, up.streak_days
FROM users u
JOIN user_points up ON u.user_id = up.user_id;
```

### Check Activity Log
```sql
SELECT al.*, u.username 
FROM activity_log al
LEFT JOIN users u ON al.user_id = u.user_id
ORDER BY al.created_at DESC
LIMIT 20;
```

### Check Professionals
```sql
SELECT p.*, u.username 
FROM professionals p
JOIN users u ON p.user_id = u.user_id;
```

### Check Volunteers
```sql
SELECT v.*, u.username 
FROM volunteers v
JOIN users u ON v.user_id = u.user_id;
```

---

## 🐛 Common Issues & Solutions

### Issue 1: "Database Connection Error"
**Solution:**
- Start MySQL in XAMPP
- Check credentials in `config/config.php`
- Verify database exists

### Issue 2: "404 Not Found"
**Solution:**
- Check folder name matches URL
- Verify Apache is running
- Check file exists in correct location

### Issue 3: "Headers Already Sent"
**Solution:**
- Check for whitespace before `<?php`
- Verify no output before `header()` calls
- Check file encoding (UTF-8 without BOM)

### Issue 4: "Call to Undefined Function"
**Solution:**
- Check PHP extensions enabled
- Verify `config.php` is included
- Check PHP version (5.5+)

### Issue 5: Password Login Fails
**Solution:**
- Verify password meets requirements
- Check bcrypt hashing working
- Try re-registering account

---

## ✅ Success Criteria

All tests pass when:
- [x] Landing page loads beautifully
- [x] Registration works for all 3 roles
- [x] Login authentication successful
- [x] Admin login separate and secure
- [x] Dashboard displays correctly
- [x] Logout clears session
- [x] Database entries created properly
- [x] Security measures working
- [x] No errors in browser console
- [x] Responsive design works on mobile

---

## 📝 Test Results Log

| Test | Status | Notes |
|------|--------|-------|
| Landing Page | ⬜ | |
| Patient Registration | ⬜ | |
| Professional Registration | ⬜ | |
| Volunteer Registration | ⬜ | |
| User Login | ⬜ | |
| Admin Login | ⬜ | |
| Dashboard Access | ⬜ | |
| Logout | ⬜ | |
| Session Management | ⬜ | |
| SQL Injection Protection | ⬜ | |

Legend: ✅ Pass | ❌ Fail | ⬜ Not Tested

---

**Safe Space Testing** - Version 1.0
*Last Updated: January 2026*
