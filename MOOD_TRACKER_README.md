# Safe Space - Complete Mental Health Support Platform

## 🎉 What's New - January 2026

### **Fully Functional Website with Mood Meter!**

Safe Space is now a complete, workable mental health support platform with:

## ✨ Key Features

### 1. **Mood Tracking System** 📊
- **Interactive Mood Meter** with 10-point emoji scale
- **Daily Mood Logging** with multiple data points:
  - Mood emoji selection (😭 to 😍)
  - Energy level (1-5)
  - Stress level (1-10)
  - Activity tracking (exercise, meditation, sleep, etc.)
  - Optional notes and mood triggers
  - Medication tracking
  
- **Mood History & Analytics**:
  - 7-day mood trend chart
  - Historical mood entries
  - Statistics dashboard
  - Streak tracking (maintain daily logging)

### 2. **User Dashboard** 🏠
- **Welcome Section** with today's mood display
- **Statistics Overview**:
  - Total points earned
  - Tier progress (Bronze → Silver → Gold)
  - Day streak
  - Badges earned
- **Quick Actions** for easy navigation
- **Recent Activity Feed**

### 3. **Community Forum** 💬
- **Anonymous Discussions** organized by categories:
  - Anxiety
  - Depression
  - Stress
  - Relationships
  - Sleep
  - Work/School
  - Self-Care
  - General Support

- **Features**:
  - Create posts with title and content
  - Reply to posts and provide support
  - View count and reply tracking
  - Earn points for participation (+20 for posts, +10 for replies)
  - Fully encrypted and moderated

### 4. **User Profile** 👤
- View all achievements and statistics
- Edit bio and country information
- Display all earned badges
- Tier and point progression

### 5. **Mental Health Professionals** 👨‍⚕️
- Browse verified professionals
- Filter by specialization
- View ratings and credentials
- Direct booking interface (expandable)

### 6. **User Settings** ⚙️
- Privacy & anonymity controls
- Anonymous posting toggle
- Password management
- Account information

### 7. **Gamification System** 🎮
- **Points System**:
  - Log mood: +5 points
  - Create forum post: +20 points
  - Reply to post: +10 points
  - Like helpful response: +2 points
  - Complete training: +25 points
  - Daily check-in: +5 points

- **Tier System**:
  - Bronze (0-499 points)
  - Silver (500-1,499 points)
  - Gold (1,500+ points)

- **Badges** for achievements:
  - Community Helper
  - Mood Logger
  - Story Sharer
  - Consistent Engager
  - And more!

### 8. **Database Schema** 🗄️
Complete MySQL database with 11 tables:
1. `admins` - Administrator accounts
2. `users` - Main user accounts
3. `professionals` - Mental health professionals
4. `volunteers` - Peer support volunteers
5. `user_points` - Gamification points & tiers
6. `user_badges` - Achievement badges
7. `activity_log` - Security & audit trail
8. **`mood_logs`** - Mood tracking entries (NEW)
9. **`community_healers`** - Peer helpers who assist others (NEW)
10. `forum_posts` - Community discussions
11. `forum_replies` - Post responses

## 📁 Project Structure

```
safe-space/
├── index.php                 # Landing page
├── config/
│   └── config.php           # Database & app configuration
├── database/
│   └── schema.sql           # MySQL schema with mood & community healer tables
├── auth/
│   ├── login.php            # User login
│   ├── registration.php     # New user registration
│   ├── admin_login.php      # Admin login
│   └── logout.php           # User logout
├── dashboard/
│   ├── index.php            # Main dashboard
│   ├── mood_tracker.php     # Mood logging & tracking (NEW)
│   ├── forum.php            # Forum listing & creation
│   ├── forum_view.php       # Forum post view & replies
│   ├── profile.php          # User profile
│   ├── professionals.php    # Professional directory
│   ├── settings.php         # User settings
│   └── logout.php           # Logout handler
├── admin/
│   └── dashboard.php        # Admin panel (expandable)
├── assets/
│   ├── css/
│   │   └── styles.css       # Complete styling including mood meter
│   └── js/
│       └── main.js          # Interactive scripts
└── MOOD_TRACKER_README.md   # This file
```

## 🚀 Installation & Setup

### Prerequisites
- XAMPP or WAMP (Apache, MySQL, PHP)
- Web browser
- Text editor

### Step 1: Install XAMPP
1. Download from https://www.apachefriends.org/
2. Install to default location
3. Start Apache and MySQL

### Step 2: Setup Database
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Create database: `safe_space_db`
3. Import `database/schema.sql`

### Step 3: Configure Connection
Edit `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // your MySQL password
define('DB_NAME', 'safe_space_db');
```

### Step 4: Deploy Files
Copy all files to `C:\xampp\htdocs\safe-space\`

### Step 5: Access the Application
- **Homepage**: http://localhost/safe-space/
- **User Login**: http://localhost/safe-space/auth/login.php
- **Admin Login**: http://localhost/safe-space/auth/admin_login.php

## 👤 Default Accounts

### Admin Account
- **Username**: `admin`
- **Password**: `Admin@123`
- **Role**: Super Admin

### Moderator Account
- **Username**: `moderator1`
- **Password**: `Admin@123`
- **Role**: Content Moderator

## 📊 Using the Mood Meter

### Daily Mood Logging
1. Log in to your account
2. Go to **Dashboard** → **Mood Tracker**
3. Select your mood (😭 to 😍)
4. Adjust energy and stress sliders
5. Add optional notes
6. Select activities you engaged in
7. Click **Save Mood Entry**

### Viewing History
- See recent mood entries (last 50)
- View 7-day trend chart
- Track streak days
- Monitor mood patterns

## 🎯 Earn Points & Badges

### Point-Earning Activities
| Activity | Points |
|----------|--------|
| Log Mood | +5 |
| Create Forum Post | +20 |
| Reply to Post | +10 |
| First Post of Day | +5 |
| Help Flag Crisis Content | +15 |
| Complete Training Module | +25 |
| Share Story | +20 |

### Tier Progression
- **Bronze**: 0-499 points (basic access)
- **Silver**: 500-1,499 points (priority scheduling)
- **Gold**: 1,500+ points (all premium features)

## 🔒 Security Features

- **Password Hashing**: bcrypt (cost 12)
- **Session Management**: Automatic timeout after 1 hour
- **SQL Injection Prevention**: Prepared statements
- **Input Validation**: Server-side validation
- **Data Encryption**: AES-256 for sensitive data
- **CSRF Protection**: Session-based tokens
- **Activity Logging**: All user actions tracked

## 📱 Responsive Design

- **Desktop**: Full-featured experience
- **Tablet**: Optimized layout
- **Mobile**: Touch-friendly interface
- **Accessibility**: WCAG AA compliant

## 🔮 Future Enhancements

- [ ] Real-time notifications
- [ ] AI-powered mood analysis
- [ ] Integration with professional booking (Stripe)
- [ ] Video consultation support
- [ ] Mobile app (React Native)
- [ ] Advanced analytics dashboard
- [ ] Machine learning mood predictions
- [ ] Crisis intervention AI
- [ ] Meditation & breathing exercises
- [ ] Integration with wearables
- [ ] Emergency SMS alerts

## 🛠️ Admin Features

### User Management
- View all users
- Deactivate accounts
- View activity logs
- Manage roles

### Analytics
- Total platform stats
- User engagement metrics
- Popular topics
- Crisis alert tracking

### Content Moderation
- Flag inappropriate posts
- Review reported content
- Manage forum categories
- Monitor professional profiles

## 📞 Crisis Support

**If you're in crisis:**
- Click the **Emergency Support** button
- Call **988 Suicide & Crisis Lifeline** (US, 24/7, free)
- Text **"HELLO"** to **741741** (Crisis Text Line)
- Available 24/7 in your language

## 📧 Support & Contact

For issues or questions:
- Email: support@safespace.com
- In-app support: Dashboard → Help
- Live chat: Coming soon

## 📄 License

Safe Space is open-source software licensed under the MIT License.

## 🙏 Acknowledgments

Special thanks to:
- Mental health professionals for guidance
- Open-source community for tools
- All users for feedback and support

---

**Created**: January 2026
**Version**: 1.0.0 - Full Release
**Status**: ✅ Production Ready

Safe Space: *Your Mental Health, Your Safe Space* ❤️
