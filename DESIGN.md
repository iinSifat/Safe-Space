# 🎨 Safe Space - Visual Design Documentation

## Color Palette

### Primary Colors
```
Soft Blue:        #6B9BD1  ███████
Deeper Blue:      #4A7BA7  ███████
Light Sky Blue:   #A8C9E8  ███████
```

### Secondary Colors
```
Soft Purple:      #B8A6D9  ███████
Calming Teal:     #8FD4C1  ███████
Warm Peach:       #F4C4A7  ███████
```

### Status Colors
```
Success:          #6FCF97  ███████
Warning:          #F2C94C  ███████
Error:            #EB5757  ███████
Info:             #56CCF2  ███████
```

---

## Typography

### Font Family
```css
font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
```

### Sizes
- **Hero Title**: 3rem (48px)
- **Page Title**: 2rem (32px)
- **Section Title**: 1.5rem (24px)
- **Body**: 1rem (16px)
- **Small**: 0.85rem (13.6px)

---

## Component Showcase

### 1. Logo & Branding
```
┌─────────────────────────────┐
│     ╔═══╗                   │
│     ║ ♥ ║  Gradient Circle  │
│     ╚═══╝                   │
│                             │
│    Safe Space               │
│    (Gradient Text)          │
│                             │
│ Your journey to wellness... │
└─────────────────────────────┘
```

### 2. Auth Card
```
┌─────────────────────────────────────┐
│ ━━━━━ Gradient Top Bar ━━━━━━━━━━━ │ (6px)
│                                     │
│         [Logo Circle]               │
│        Safe Space                   │
│    Your tagline here                │
│                                     │
│  ┌───────────────────────────────┐ │
│  │ Input Field                   │ │
│  └───────────────────────────────┘ │
│                                     │
│  [━━━━━━━ Button ━━━━━━━━━━━━━━] │
│                                     │
└─────────────────────────────────────┘
```

### 3. Role Selection Cards
```
┌──────────┐  ┌──────────┐  ┌──────────┐
│   (👤)   │  │   (📊)   │  │   (👥)   │
│          │  │          │  │          │
│ Patient  │  │ Profess. │  │ Volunteer│
│          │  │          │  │          │
│ Seeking  │  │ Licensed │  │ Trained  │
│ support  │  │ expert   │  │ peer     │
└──────────┘  └──────────┘  └──────────┘
  Normal       Hover (↑)     Active (✨)
```

### 4. Alert Messages
```
┌─────────────────────────────────────┐
│ ║ ✓ Success message appears here   │ Green
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ ║ ⚠ Warning message appears here   │ Yellow
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ ║ ✕ Error message appears here     │ Red
└─────────────────────────────────────┘
```

### 5. Buttons

**Primary Button**
```
┌─────────────────────────┐
│   Sign In to Safe Space │  Gradient Blue → Purple
└─────────────────────────┘
    Hover: ↑ (lifts up)
```

**Secondary Button**
```
┌─────────────────────────┐
│   Cancel or Go Back     │  Light Gray
└─────────────────────────┘
```

**Admin Button**
```
┌─────────────────────────┐
│  Access Admin Portal    │  Gradient Orange → Red
└─────────────────────────┘
```

---

## Page Layouts

### Landing Page (index.php)
```
╔═══════════════════════════════════════╗
║         [Logo] Safe Space             ║
║                                       ║
║    Welcome to Safe Space              ║
║    Your mental health community...    ║
║                                       ║
║  ┌────┐  ┌────┐  ┌────┐  ┌────┐    ║
║  │24/7│  │100%│  │Free│  │ ∞ │    ║
║  └────┘  └────┘  └────┘  └────┘    ║
║                                       ║
║  ┌──────┐  ┌──────┐  ┌──────┐      ║
║  │Anonym│  │Profes│  │Peer  │      ║
║  │Support│  │Help  │  │Supprt│      ║
║  └──────┘  └──────┘  └──────┘      ║
║                                       ║
║  [Get Started Free] [Sign In]        ║
╚═══════════════════════════════════════╝
```

### Registration Page (auth/registration.php)
```
╔═══════════════════════════════════════╗
║  ━━━━━ Gradient Bar ━━━━━━━━━━━━━━━ ║
║         [Logo] Safe Space             ║
║                                       ║
║ I am joining as: *                    ║
║  [Patient] [Professional] [Volunteer] ║
║                                       ║
║ Username: * [____________]            ║
║ Email: *    [____________]            ║
║ Password: * [____________]            ║
║ Confirm: *  [____________]            ║
║                                       ║
║ [✓] I agree to terms...               ║
║                                       ║
║ [━━━━━ Create Account ━━━━━━━]       ║
║                                       ║
║ ─────── Already have account? ─────── ║
║        Sign in to Safe Space          ║
╚═══════════════════════════════════════╝
```

### Login Page (auth/login.php)
```
╔═══════════════════════════════════════╗
║  ━━━━━ Gradient Bar ━━━━━━━━━━━━━━━ ║
║         [Logo] Safe Space             ║
║    Welcome back. We're here for you.  ║
║                                       ║
║ Username or Email: * [____________]   ║
║ Password: *          [____________]   ║
║                                       ║
║ [✓] Remember me     Forgot password?  ║
║                                       ║
║ [━━━━━━━━ Sign In ━━━━━━━━━━]       ║
║                                       ║
║ ─────── New to Safe Space? ────────── ║
║        Create a free account          ║
║                                       ║
║            Admin Login                ║
╚═══════════════════════════════════════╝
```

### Admin Login (auth/admin_login.php)
```
╔═══════════════════════════════════════╗
║  ━━━━━ Orange/Red Gradient ━━━━━━━━━ ║
║      [🛡️] 🛡️ ADMIN PORTAL           ║
║         Safe Space                    ║
║     Administrative Access             ║
║                                       ║
║ ⚠ Authorized Personnel Only           ║
║   All access attempts are logged      ║
║                                       ║
║ Admin Username: * [____________]      ║
║ Password: *       [____________]      ║
║                                       ║
║ [🛡️ Access Admin Portal]             ║
║                                       ║
║ ─────── Not an admin? ────────────── ║
║        ← Back to User Login           ║
║                                       ║
║ 🔒 All activities monitored           ║
╚═══════════════════════════════════════╝
```

### User Dashboard (dashboard/index.php)
```
╔═══════════════════════════════════════╗
║ Welcome back, username! 👋  [Logout]  ║
║ Role: Patient | Points: 0 | Tier: Bronze
║                                       ║
║ ┌──────┐  ┌──────┐  ┌──────┐        ║
║ │ 👤   │  │ 💬   │  │ 📊   │        ║
║ │Profile│ │Forums│  │Consul│        ║
║ │SOON  │  │SOON  │  │SOON  │        ║
║ └──────┘  └──────┘  └──────┘        ║
║                                       ║
║ ┌──────┐  ┌──────┐  ┌──────┐        ║
║ │ 📚   │  │ ⭐   │  │ 🚨   │        ║
║ │Learn │  │Rewards│ │Emergency│     ║
║ │SOON  │  │SOON  │  │988   │        ║
║ └──────┘  └──────┘  └──────┘        ║
║                                       ║
║ 🎉 Getting Started                    ║
║ • Complete your profile               ║
║ • Explore forums                      ║
║ • Take assessments                    ║
╚═══════════════════════════════════════╝
```

### Admin Dashboard (admin/dashboard.php)
```
╔═══════════════════════════════════════╗
║ 🛡️ Admin Dashboard          [Logout] ║
║ Welcome, Admin Name (Super Admin)     ║
║                                       ║
║ ┌──────┐  ┌──────┐  ┌──────┐  ┌────┐║
║ │  -   │  │  -   │  │  -   │  │ -  │║
║ │Users │  │Profs │  │Vols  │  │Posts║
║ └──────┘  └──────┘  └──────┘  └────┘║
║                                       ║
║ ┌───────────────┐  ┌───────────────┐ ║
║ │User Management│  │Content Moder. │ ║
║ │Coming Soon    │  │Coming Soon    │ ║
║ └───────────────┘  └───────────────┘ ║
║                                       ║
║ ┌───────────────┐  ┌───────────────┐ ║
║ │Analytics      │  │System Settings│ ║
║ │Coming Soon    │  │Coming Soon    │ ║
║ └───────────────┘  └───────────────┘ ║
╚═══════════════════════════════════════╝
```

---

## Animation Effects

### 1. Fade In Up (Page Load)
```
0%   : opacity: 0, translateY(30px)
100% : opacity: 1, translateY(0)
Duration: 0.6s
```

### 2. Pulse (Logo)
```
0%, 100% : scale(1)
50%      : scale(1.05)
Duration: 2s infinite
```

### 3. Wave Animation (Background)
```
Animated wave SVG pattern
Moving left to right
Duration: 20s infinite
```

### 4. Slide Down (Alerts)
```
0%   : opacity: 0, translateY(-10px)
100% : opacity: 1, translateY(0)
Duration: 0.3s
```

### 5. Button Hover
```
Normal : translateY(0)
Hover  : translateY(-2px) + shadow
Duration: 0.3s
```

---

## Responsive Breakpoints

```css
Desktop:  > 768px   (Normal view)
Tablet:   ≤ 768px   (Adjusted grid)
Mobile:   ≤ 480px   (Single column)
```

### Mobile Adjustments
- Font sizes reduced by ~15%
- Single column layouts
- Larger touch targets
- Simplified navigation
- Stacked buttons

---

## Accessibility Features

### 1. Color Contrast
- All text meets WCAG AA standards
- Minimum contrast ratio: 4.5:1

### 2. Keyboard Navigation
- All interactive elements focusable
- Clear focus indicators
- Logical tab order

### 3. Screen Reader Support
- Semantic HTML5 elements
- ARIA labels where needed
- Alt text for images
- Form labels properly associated

### 4. Focus Styles
```css
*:focus-visible {
    outline: 2px solid #6B9BD1;
    outline-offset: 2px;
}
```

---

## Icons Used

All icons are Material Design style SVGs:

- 👤 User (Profile, Patient)
- 📊 Bar Chart (Professional, Analytics)
- 👥 People (Volunteers, Community)
- 💬 Chat (Forums, Messages)
- ⭐ Star (Rewards, Achievements)
- 🚨 Warning (Emergency, Alerts)
- 🛡️ Shield (Admin, Security)
- ❤️ Heart (Logo, Love, Support)
- 🔒 Lock (Password, Security)
- ✓ Check (Success, Complete)
- ✕ Close (Error, Delete)
- ⚠ Warning Triangle (Alerts)

---

## Special Effects

### 1. Gradient Backgrounds
```css
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

### 2. Glass Morphism (Subtle)
```css
background: rgba(255, 255, 255, 0.95);
backdrop-filter: blur(10px);
```

### 3. Box Shadows
```css
Small:  0 2px 8px rgba(0, 0, 0, 0.08)
Medium: 0 4px 16px rgba(0, 0, 0, 0.12)
Large:  0 8px 32px rgba(0, 0, 0, 0.16)
Glow:   0 0 20px rgba(107, 155, 209, 0.3)
```

### 4. Border Radius
```css
Small:  8px   (Inputs, buttons)
Medium: 12px  (Cards)
Large:  20px  (Major containers)
Circle: 50%   (Icons, avatars)
```

---

## Print Styles

For documentation/reports:
- Remove backgrounds
- Black text on white
- Show links as text
- Hide navigation elements

---

**Safe Space Design System** - Version 1.0
*Calming, Supportive, Accessible*
