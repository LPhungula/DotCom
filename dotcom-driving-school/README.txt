╔══════════════════════════════════════════════════════════╗
║     DOT COM DRIVING SCHOOL — Setup Instructions          ║
║     IS3 Digital Transformation Project                   ║
╚══════════════════════════════════════════════════════════╝

REQUIREMENTS
────────────
• XAMPP (Apache + MySQL running)
• Browser (Chrome/Firefox/Edge)

STEP 1 — COPY PROJECT FOLDER
─────────────────────────────
Copy the entire "dotcom-driving-school" folder into:
  C:\xampp\htdocs\dotcom-driving-school\

STEP 2 — IMPORT THE DATABASE
──────────────────────────────
1. Open your browser → go to: http://localhost/phpmyadmin
2. Click "New" in the left sidebar
3. Create database named:  dotcom_driving_school
4. Click the new database → go to the "Import" tab
5. Choose file: dotcom_db.sql  (inside this folder)
6. Click "Go" to import

STEP 3 — RUN THE WEBSITE
──────────────────────────
Open your browser → go to:
  http://localhost/dotcom-driving-school/

DEMO LOGIN CREDENTIALS
───────────────────────
  Admin:
    Username: admin
    Password: admin123
    URL: http://localhost/dotcom-driving-school/admin_login.php

  Student:
    Username: johnsmith
    Password: student123
    URL: http://localhost/dotcom-driving-school/login.php

SYSTEM PAGES
────────────
  index.php              → Landing page (Home)
  register.php           → Student registration
  login.php              → Student login
  student_dashboard.php  → Student dashboard + QR code
  admin_login.php        → Admin login panel
  admin_dashboard.php    → Admin dashboard (KPIs + students)
  students.php           → All students management
  generate_qr.php        → Generate/view QR codes
  scan.php               → Scan QR for attendance
  attendance.php         → Attendance records + CSV export
  courses.php            → Manage courses

TECHNOLOGIES
────────────
  Frontend : HTML, CSS, JavaScript
  Backend  : PHP
  Database : MySQL (phpMyAdmin)
  Server   : XAMPP (Apache)
  QR Code  : SVG-generated (no library needed)

PROJECT STRUCTURE
──────────────────
  dotcom-driving-school/
  ├── index.php
  ├── register.php
  ├── login.php
  ├── admin_login.php
  ├── admin_dashboard.php
  ├── student_dashboard.php
  ├── students.php
  ├── generate_qr.php
  ├── scan.php
  ├── attendance.php
  ├── courses.php
  ├── change_password.php
  ├── logout.php
  ├── dotcom_db.sql
  ├── css/
  │   └── style.css
  └── includes/
      ├── db.php
      ├── navbar.php
      └── admin_sidebar.php
