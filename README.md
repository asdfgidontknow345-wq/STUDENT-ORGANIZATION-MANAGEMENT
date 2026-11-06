# Student Organization Management and Event Registration System (CampusSync)

A full-stack, enterprise-grade PHP & SQL university event and student organization management platform. Built with strict Role-Based Access Control (RBAC), PDO prepared statements, and support for both SQLite and MySQL.

---

## 🚀 Quick Start

### Option 1: One-Click Startup (Recommended)
Double-click `start-server.bat` in this directory, or run from terminal:
```powershell
php -c php.ini -S 0.0.0.0:8000
```
Then navigate to: **[http://localhost:8000](http://localhost:8000)**

### Option 2: Running via XAMPP
1. Move or clone this folder into `C:\xampp\htdocs\student-org-system`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Import `database.sql` into phpMyAdmin (or let the app auto-provision upon launch).
4. Navigate to **[http://localhost/student-org-system](http://localhost/student-org-system)**.

---

## 🔑 Demo Credentials

| Account Role | University Email | Password | Access Rights |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@university.edu` | `password123` | Full control over users, organizations, events, attendees, CSV exports & rosters |
| **Student** | `student@university.edu` | `password123` | Campus event registration, pass management, profile editing & password changes |
| **Student 2** | `maria@university.edu` | `password123` | Independent student account for multi-user registration testing |

---

## 🛡️ Role-Based Access Control (RBAC) & Security

- **Server-Side Enforcement**: All administrative routes are protected with `require_admin()` which terminates unauthorized access with **HTTP 403 Forbidden**.
- **CSRF Token Protection**: All state-modifying actions (registrations, cancellations, user updates, event modifications) are guarded with cryptographically secure CSRF tokens.
- **Session Security**: Session IDs are regenerated upon login (`session_regenerate_id(true)`) to mitigate session fixation.
- **Data Integrity**: Concurrency-safe transactions guarantee event capacity limits and eliminate race-condition overbooking.
- **XSS Sanitization**: User-supplied values are escaped via `e()` (`htmlspecialchars`) before output.

---

## 💻 System Functionalities

### Part 1: Admin Functionalities
- **Admin Dashboard (`pages/admin_dashboard.php`)**: Live KPI metrics (registered students, organizations, events, total registrations, pending accounts), upcoming event timeline, and recent activity logs.
- **User Management (`pages/admin_users.php`)**: Directory with keyword search, role filters, verification status filters, account activation/deactivation, in-place edit modal, and safe user deletion.
- **Organization Management (`pages/admin_organizations.php`)**: Manage student organizations, assign student officers, track associated events, add/edit/delete organizations.
- **Event Management (`pages/admin_events.php` & `pages/edit_event.php`)**: Create, edit, toggle registration status (open/closed), and delete campus events.
- **Registration Master Log (`pages/admin_registrations.php`)**: Live log of all student registrations with event filtering and cancellation control.
- **Reporting & Monitoring**:
  - **CSV Export (`pages/export_attendees.php`)**: One-click Excel-compatible CSV export for any specific event or all platform registrations.
  - **Printable Attendee Roster (`pages/print_attendees.php`)**: Formal, print-formatted attendee check-in sheet with signature column and event metadata.

### Part 2: Student Functionalities
- **Campus Event Directory (`pages/events.php`)**: Search events by title, description, or venue; filter by organization and availability status (Open, Full, Closed); real-time slot counter.
- **Event Registration (`pages/event_details.php`)**: Reserve seats, view logistics, inspect organization charters, and cancel registrations with instant slot recovery.
- **My Registrations (`pages/my_registrations.php`)**: Personal event pass schedule, venue information, and self-service cancellation with confirmation alerts.
- **Student Profile (`pages/profile.php`)**: View official Student ID, update personal contact details, and securely change passwords with hash verification.

---

## 📁 Project Directory Structure

```
student-org-system/
├── config/
│   ├── db_connect.php            # Dual SQLite / MySQL connection & auto-migration
│   ├── security.php              # RBAC helpers, CSRF engine, flash messaging & XSS filter
│   └── database.sqlite           # Portable SQLite local database
├── auth/
│   ├── login.php                 # Role-based login with session regeneration
│   ├── logout.php                # Session destruction and cleanup
│   ├── register.php              # Student onboarding with verification token
│   ├── verify.php                # Account activation handler
│   └── resend_email.php          # Resend verification token
├── pages/
│   ├── index.php                 # University portal homepage with personalized greeting
│   ├── events.php                # Event browsing, searching, and filtering
│   ├── event_details.php         # Event details & concurrent registration engine
│   ├── my_registrations.php      # Student registration passes & cancellation
│   ├── profile.php               # Student profile and password management
│   ├── create_event.php          # Admin/Officer event publishing
│   ├── edit_event.php            # Admin event editor & capacity control
│   ├── admin_dashboard.php       # Admin control center and activity feed
│   ├── admin_users.php           # User & student management directory
│   ├── admin_organizations.php   # Organization accreditation & officer assignment
│   ├── admin_registrations.php   # Master attendance logs & registration manager
│   ├── export_attendees.php      # CSV export engine for event attendees
│   └── print_attendees.php       # Printable official event check-in roster
├── assets/
│   └── css/
│       └── styles.css            # Academic navy/blue aesthetic stylesheet
├── includes/
│   ├── header.php                # Global HTML head and typography
│   ├── navbar.php                # Dynamic, responsive, role-based navigation bar
│   └── footer.php                # Global footer
├── test_suite.php                # Automated 30-point regression & security test suite
├── start-server.bat              # One-click Windows development server launcher
├── php.ini                       # Custom PHP configuration with extensions enabled
└── README.md                     # Comprehensive documentation
```

---

## 🧪 Automated Testing

An automated 30-point integration test suite is included in `test_suite.php`. To execute:
```powershell
php -c php.ini test_suite.php
```
All 30 tests cover:
- Guest redirection & boundary protection
- Admin authentication & administrative access control
- Student authentication & strict HTTP 403 enforcement on admin endpoints
- Full registration, duplicate-blocking, and cancellation lifecycle
- Profile updates & privilege escalation immunity
