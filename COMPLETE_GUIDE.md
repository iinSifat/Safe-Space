# Safe Space - Complete Deployment & Usage Guide

## 🎯 Getting Started

Safe Space is now a **fully functional mental health support platform** with a complete **mood tracking system**, **community forum**, **gamification**, and more!

---

## ⚡ Quick Start (5 Minutes)

### 1. Database Setup
```bash
# Open phpMyAdmin
http://localhost/phpmyadmin

# Create database
CREATE DATABASE safe_space_db;

# Import schema
# Click Import → Choose database/schema.sql → Execute
```

### 2. Configuration
Edit `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Your MySQL password
define('DB_NAME', 'safe_space_db');
```

### 3. Start Using!
```
http://localhost/safe-space/
```

---

## 📖 Full User Guide

### **Default Accounts**

#### 👤 User Account (Demo)
- **Username**: `testuser`
- **Password**: `Test@12345`
- (Or register new account)

#### 🔐 Admin Account
- **Username**: `admin`
- **Password**: `Admin@123`
- **Access**: http://localhost/safe-space/auth/admin_login.php

#### 👥 Moderator Account
- **Username**: `moderator1`
- **Password**: `Admin@123`

---

## 🎯 Core Features - How to Use

### 1. **MOOD TRACKER** 📊 ⭐ NEW FEATURE!

#### Accessing Mood Tracker
1. Log in to your account
2. Click **"Dashboard"** → **"Mood Tracker"**
3. Or go to: `http://localhost/safe-space/dashboard/mood_tracker.php`

#### How to Log Mood
```
Step 1: Select Your Mood (1-10 scale)
   😭 😢 😟 😕 😐 🙂 😊 😄 😃 😍
   
Step 2: Adjust Sliders
   - Energy Level: 1-5 (how energetic you feel)
   - Stress Level: 1-10 (how stressed you are)

Step 3: Optional Details
   - Add notes (what happened today)
   - Select activities: 🏃🧘👥💬🎨
   - Check if took medication 💊

Step 4: Click "Save Mood Entry ✓"
   - Earn +5 points
   - See your streak grow
```

#### Viewing Mood History
- See last 7 days of entries
- Interactive mood trend chart
- Energy & stress levels
- Notes and activities logged

#### What You Earn
- **+5 points** for each mood entry
- **Streak bonus**: Keep logging daily!
- **Mood Master badge**: Log 30 consecutive days

#### Understanding Your Data
```
Mood Level: Your overall emotional state
Energy: Physical & mental energy (1=exhausted, 5=energetic)
Stress: Anxiety/stress level (1=calm, 10=extreme stress)

Example: 😊 Mood 7 | Energy 4/5 | Stress 3/10 = Good day!
```

---

### 2. **COMMUNITY FORUM** 💬

#### Create a Post
1. Go to **Dashboard** → **Forum**
2. Click **"+ New Post"**
3. Fill in:
   - **Title**: What's on your mind?
   - **Category**: Anxiety, Depression, Sleep, etc.
   - **Message**: Your thoughts (anonymous)
4. Click **Post** → Earn **+20 points**

#### Browse & Reply
1. View posts by category
2. Click any post to open
3. Read others' replies
4. Add your own support/response
5. Earn **+10 points** per helpful reply

#### Example Categories
- **Anxiety**: Social anxiety, panic attacks, generalized anxiety
- **Depression**: Sadness, motivation, self-harm thoughts
- **Stress**: Work stress, academic pressure, life changes
- **Relationships**: Dating, family, friendships
- **Sleep**: Insomnia, nightmares, sleep quality
- **Work/School**: Burnout, pressure, deadlines
- **Self-Care**: Wellness, exercise, nutrition
- **General Support**: Any mental health topic

---

### 3. **USER DASHBOARD** 🏠

Your personal hub showing:

```
┌─────────────────────────────────┐
│  Welcome, [Your Username]! 👋   │
│  Today's Mood: 😊 (if logged)   │
├─────────────────────────────────┤
│  📊 Points: 250                 │
│  🔥 Streak: 5 days              │
│  🏆 Badges: 3 earned            │
│  ⭐ Tier: Bronze → Silver        │
├─────────────────────────────────┤
│  Quick Actions:                 │
│  [Log Mood] [Forum] [Profile]   │
│  [Professionals] [Settings]     │
└─────────────────────────────────┘
```

#### Dashboard Stats
- **Points**: Lifetime points earned
- **Tier**: Bronze, Silver, Gold (based on points)
- **Streak**: Consecutive days of mood logging
- **Badges**: Achievements unlocked

---

### 4. **USER PROFILE** 👤

Access: Dashboard → Profile

#### What You Can Do
- View all your statistics
- Edit bio & country
- See all earned badges
- Track tier progress
- View badge descriptions

#### Example Badge Collection
- 🏆 Community Helper (helped 10+ people)
- 🎖️ Mood Logger (logged 10+ moods)
- 📖 Story Sharer (shared personal story)
- 🎯 Consistent Engager (30+ days active)

---

### 5. **PROFESSIONALS** 👨‍⚕️

Access: Dashboard → Professionals

#### Browse Professionals
- View licensed mental health experts
- See specializations:
  - Depression & Anxiety
  - Trauma & PTSD
  - Relationship Issues
  - Work Stress & Burnout
- Check ratings (⭐⭐⭐⭐⭐)
- See consultation fees
- Look for ✓ Verified badge

#### Booking (Coming Soon)
- Schedule consultations
- Secure payment
- Session transcripts
- Follow-up resources

---

### 6. **SETTINGS** ⚙️

Access: Dashboard → Settings

#### Privacy Options
```
☐ Post Anonymously
   When checked: Your username hidden
   in forum posts
   
☐ Show Profile to Others
   Control who can see your profile
   
☐ Email Notifications
   Get updates on replies to your posts
```

#### Security
- Change password
- View login history
- Manage sessions
- Two-factor authentication (coming soon)

#### Account Management
- Update email
- View account created date
- Download your data
- Delete account (permanent)

---

## 🎮 Gamification System

### Points Earning Guide

| Action | Points | How |
|--------|--------|-----|
| Log Mood | +5 | Daily mood_tracker.php |
| Forum Post | +20 | Create new post |
| Forum Reply | +10 | Reply to a post |
| Helpful Vote | +2 | Like a reply |
| Training Complete | +25 | Finish a course |
| Story Share | +20 | Share your journey |
| Crisis Help | +15 | Flag urgent content |
| First Login | +10 | One-time bonus |

### Tier System

```
BRONZE (0-499 points) - Entry Level
├─ Access: Forums, Mood Tracking
├─ Perks: Basic features
└─ Next: 499 more points to Silver

SILVER (500-1,499 points) - Committed Member
├─ Access: All features
├─ Perks: Priority consultation booking
├─ Bonus: 1 free consultation/month
└─ Next: 1,000 more points to Gold

GOLD (1,500+ points) - Premium Member
├─ Access: All features
├─ Perks: Premium priority booking
├─ Bonus: 2 free consultations/month
├─ Exclusive: Gold-only events
└─ Status: VIP community member
```

### Badges to Earn

```
🏆 Community Helper
   Requirement: Help 10+ different users
   
🎖️ Mood Logger
   Requirement: Log mood 10+ times
   
📖 Story Sharer
   Requirement: Share personal story
   
🎯 Consistent Engager
   Requirement: 30+ consecutive days active
   
🚨 Crisis Responder
   Requirement: Flag crisis content
   
💬 Helpful Soul
   Requirement: Get 50+ helpful votes
   
🌟 Wellness Advocate
   Requirement: Complete 5+ training modules
```

---

## 🔐 Security & Privacy

### Your Data is Safe
✓ All passwords hashed with bcrypt  
✓ Encrypted connections (HTTPS ready)  
✓ Anonymous options available  
✓ No sharing with third parties  
✓ Regular security audits  
✓ Activity logging for your safety  

### Privacy Controls
- Choose anonymous posting
- Hide profile from others
- Control mood data visibility
- Manage communication preferences

---

## 📱 Navigation Guide

### Main Menu Links (After Login)

```
Safe Space Header:
├─ Dashboard (home page with stats)
├─ Mood Tracker (log & view moods)
├─ Forum (community discussions)
├─ Professionals (therapist directory)
├─ Profile (your profile & achievements)
├─ Settings (preferences & security)
└─ Logout (exit account)
```

### Quick Actions on Dashboard

```
⚡ Quick Actions (6 buttons):
├─ 📊 Log Mood (go to mood tracker)
├─ 💬 Forum (view discussions)
├─ 👨‍⚕️ Professionals (find therapist)
├─ 👤 Profile (your profile)
├─ ℹ️ Resources (home page info)
└─ ⚙️ Settings (account settings)
```

---

## 💡 Tips & Tricks

### Maximizing Your Points
1. **Log mood every day** (+5 × 365 = 1,825 points/year!)
2. **Be active in forums** (+10-20 per post)
3. **Help others regularly** (earn Community Helper badge)
4. **Complete training modules** (+25 points each)
5. **Share your story** (+20 bonus points)

### Building Your Streak
- Log at least once per day
- Same time each day works best
- Notifications remind you (coming soon)
- 🔥 Streaks earn bonus badges!

### Effective Mood Tracking
```
Best Practices:
✓ Log at consistent time (e.g., bedtime)
✓ Be honest about your feelings
✓ Add context (what happened today)
✓ Note activities that helped
✓ Track patterns over weeks
✓ Share insights with therapist
```

### Getting Most Support
1. Join relevant forum categories
2. Share experiences (others relate!)
3. Provide support to others
4. Save helpful posts for later
5. Connect with professionals

---

## 🆘 Crisis Support

### If You're in Crisis RIGHT NOW:
```
📞 CALL: 988 Suicide & Crisis Lifeline (US)
💬 TEXT: "HELLO" to 741741 (Crisis Text Line)
🌍 International: findahelpline.com
```

**Available 24/7, free, confidential**

### In-App Support
- Click Emergency Support section
- Access crisis resources
- Get immediate helpline numbers
- Professional on-call option

---

## 🐛 Troubleshooting

### Can't Log In?
```
❌ "Invalid username or password"
✓ Check username/password exact spelling
✓ Try resetting password (link on login page)
✓ Clear browser cache
✓ Try different browser
```

### Mood Tracker Not Loading?
```
❌ Page doesn't load
✓ Refresh page (F5)
✓ Check internet connection
✓ Log out and back in
✓ Clear cookies
✓ Try incognito/private window
```

### Can't See Previous Moods?
```
❌ No mood entries showing
✓ Make sure you're logged in as same user
✓ Check if entries are older than 7 days
✓ Try different date filter (if available)
✓ Contact support if lost data
```

### Forums Not Posting?
```
❌ Post won't submit
✓ Check internet connection
✓ Ensure title/content are not empty
✓ Refresh and try again
✓ Check if account active
```

---

## 📊 Understanding Your Mood Data

### Mood Level Interpretation
```
1-2: 😭😢 Very Low (seek help immediately)
3-4: 😟😕 Below Average (concerning)
5-6: 😐🙂 Neutral (okay)
7-8: 😊😄 Good (positive)
9-10: 😃😍 Excellent (great day!)
```

### Energy Levels
```
1 = Exhausted, can't get out of bed
2 = Very low energy, struggling
3 = Moderate, normal
4 = Good energy, motivated
5 = High energy, very motivated
```

### Stress Levels
```
1-2 = Calm, relaxed, peaceful
3-4 = Slightly stressed, manageable
5-6 = Moderate stress, manageable with effort
7-8 = High stress, difficult to manage
9-10 = Extreme stress, crisis level
```

### Pattern Recognition
Track over time:
- Are Mondays worse than weekends?
- Does exercise improve mood?
- Do social activities help?
- What triggers anxiety?

Share patterns with therapist for better care!

---

## 🎓 For Mental Health Professionals

If you're a therapist/counselor:

1. **Register as Professional**
   - Go to registration
   - Select "Professional" role
   - Submit credentials for verification

2. **Your Profile**
   - Display specializations
   - Set consultation rates
   - Manage availability
   - View client reviews

3. **Professional Dashboard**
   - Consultation schedule
   - Client histories
   - Secure messaging
   - Treatment notes

---

## 👥 For Admin/Moderators

Admin Features:

```
Access: http://localhost/safe-space/auth/admin_login.php

Username: admin (or moderator1)
Password: Admin@123

Admin Panel:
├─ User Management
│  ├─ View all users
│  ├─ Deactivate accounts
│  └─ Reset passwords
├─ Content Moderation
│  ├─ Review flagged posts
│  ├─ Delete inappropriate content
│  └─ Manage categories
├─ Professional Verification
│  ├─ Review credentials
│  ├─ Approve/reject profiles
│  └─ Monitor ratings
└─ Analytics
   ├─ User statistics
   ├─ Activity trends
   └─ Crisis alerts
```

---

## 📚 Additional Resources

### In-App Learning
- Mental health blogs
- Coping strategy guides
- Meditation resources
- Crisis first aid training

### External Resources
- NAMI: https://www.nami.org
- SAMHSA: https://www.samhsa.gov
- Psychology Today: https://www.psychologytoday.com

---

## 🎉 Next Steps

1. **Create Your Account** → Registration page
2. **Complete Profile** → Profile section
3. **Log Your First Mood** → Mood Tracker
4. **Join Forum** → Introduce yourself
5. **Earn Your First Badge** 🏆
6. **Connect with Community** 💬
7. **Book Professional** 👨‍⚕️ (coming soon)

---

## 📞 Support & Feedback

**Need Help?**
- Email: support@safespace.com
- In-app: Dashboard → Help
- Forum: Ask in General Support category

**Have Feedback?**
- We'd love to hear from you!
- Use the feedback form
- Help us improve Safe Space

---

## ✅ Feature Checklist

Fully Implemented:
- ✅ Mood tracking with 10-point scale
- ✅ Mood history & analytics
- ✅ Interactive mood chart
- ✅ Daily streak tracking
- ✅ Community forum
- ✅ Anonymous posting
- ✅ User profiles
- ✅ Points & badges
- ✅ Tier system
- ✅ Professional directory
- ✅ User settings
- ✅ Emergency support
- ✅ Admin dashboard
- ✅ Activity logging

Coming Soon:
- 🔜 Video consultations
- 🔜 Meditation audio
- 🔜 Mobile app
- 🔜 AI mood analysis
- 🔜 Wearable integration
- 🔜 Advanced analytics

---

**Version**: 1.0.0 Complete Release
**Last Updated**: January 15, 2026
**Status**: ✅ Production Ready

---

**Safe Space: Your Mental Health, Your Safe Space** ❤️

*Remember: You're not alone. Help is always available.*
