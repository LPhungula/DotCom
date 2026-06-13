-- ══════════════════════════════════════════════════
--  DOT COM DRIVING SCHOOL — Database Setup
--  Import this file into phpMyAdmin
--  Database: dotcom_driving_school
-- ══════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS dotcom_driving_school
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE dotcom_driving_school;

-- ── USERS (authentication) ──────────────────────
CREATE TABLE IF NOT EXISTS users (
  UserID   INT AUTO_INCREMENT PRIMARY KEY,
  Username VARCHAR(60)  NOT NULL UNIQUE,
  Password VARCHAR(255) NOT NULL,
  Role     ENUM('admin','student') NOT NULL DEFAULT 'student',
  CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── STUDENTS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS students (
  StudentID   INT AUTO_INCREMENT PRIMARY KEY,
  FirstName   VARCHAR(60)  NOT NULL,
  LastName    VARCHAR(60)  NOT NULL,
  Phone       VARCHAR(20)  NOT NULL,
  Email       VARCHAR(100) NOT NULL UNIQUE,
  IDNumber    VARCHAR(20)  DEFAULT NULL,
  CourseType  VARCHAR(80)  NOT NULL,
  UserID      INT          NOT NULL,
  EnrolledAt  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (UserID) REFERENCES users(UserID) ON DELETE CASCADE
);

-- ── QR CODES ────────────────────────────────────
CREATE TABLE IF NOT EXISTS qr_codes (
  QRID      INT AUTO_INCREMENT PRIMARY KEY,
  StudentID INT NOT NULL UNIQUE,
  QRToken   VARCHAR(64) NOT NULL UNIQUE,
  CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (StudentID) REFERENCES students(StudentID) ON DELETE CASCADE
);

-- ── ATTENDANCE ──────────────────────────────────
CREATE TABLE IF NOT EXISTS attendance (
  AttendanceID INT AUTO_INCREMENT PRIMARY KEY,
  StudentID    INT      NOT NULL,
  ScanTime     DATETIME DEFAULT CURRENT_TIMESTAMP,
  Status       ENUM('Present','Absent','Late') DEFAULT 'Present',
  Notes        VARCHAR(200) DEFAULT NULL,
  FOREIGN KEY (StudentID) REFERENCES students(StudentID) ON DELETE CASCADE
);

-- ── COURSES ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS courses (
  CourseID    INT AUTO_INCREMENT PRIMARY KEY,
  CourseName  VARCHAR(80)  NOT NULL,
  Description TEXT,
  Price       DECIMAL(8,2) DEFAULT 0.00,
  Duration    VARCHAR(40)  DEFAULT NULL
);

-- ══ SEED DATA ════════════════════════════════════

-- Admin account  (password: admin123)
INSERT IGNORE INTO users (Username, Password, Role)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Demo student accounts (password: student123)
INSERT IGNORE INTO users (Username, Password, Role) VALUES
('johnsmith',  '$2y$10$TKh8H1.PFJ0Z6AiE6IiJNu1bZ.Wr5AQNKV5YiEsxVU8LVJuRPJqW', 'student'),
('janedoe',    '$2y$10$TKh8H1.PFJ0Z6AiE6IiJNu1bZ.Wr5AQNKV5YiEsxVU8LVJuRPJqW', 'student'),
('markbrown',  '$2y$10$TKh8H1.PFJ0Z6AiE6IiJNu1bZ.Wr5AQNKV5YiEsxVU8LVJuRPJqW', 'student'),
('emilydavis', '$2y$10$TKh8H1.PFJ0Z6AiE6IiJNu1bZ.Wr5AQNKV5YiEsxVU8LVJuRPJqW', 'student'),
('michaellee', '$2y$10$TKh8H1.PFJ0Z6AiE6IiJNu1bZ.Wr5AQNKV5YiEsxVU8LVJuRPJqW', 'student');

-- Demo students
INSERT IGNORE INTO students (FirstName, LastName, Phone, Email, IDNumber, CourseType, UserID) VALUES
('John',    'Smith',  '0712345678', 'johnsmith@gmail.com',  '9501015800082', 'Manual Driving Course', 2),
('Jane',    'Doe',    '0823456789', 'janedoe@gmail.com',    '9602025800083', 'Code 8 - Light Motor',  3),
('Mark',    'Brown',  '0734567890', 'markbrown@gmail.com',  '9703035800084', 'Learners Licence Prep', 4),
('Emily',   'Davis',  '0645678901', 'emilydavis@gmail.com', '9804045800085', 'Manual Driving Course', 5),
('Michael', 'Lee',    '0756789012', 'michaellee@gmail.com', '9905055800086', 'Code 10 - Heavy Motor', 6);

-- QR tokens for demo students
INSERT IGNORE INTO qr_codes (StudentID, QRToken) VALUES
(1, 'QR-STU-001-ABCDEF1234567890'),
(2, 'QR-STU-002-BCDEFG2345678901'),
(3, 'QR-STU-003-CDEFGH3456789012'),
(4, 'QR-STU-004-DEFGHI4567890123'),
(5, 'QR-STU-005-EFGHIJ5678901234');

-- Demo attendance records
INSERT IGNORE INTO attendance (StudentID, ScanTime, Status) VALUES
(1, '2025-05-20 10:15:32', 'Present'),
(2, '2025-05-20 10:12:10', 'Present'),
(3, '2025-05-20 10:08:45', 'Present'),
(4, '2025-05-20 10:05:22', 'Present'),
(5, '2025-05-20 10:02:18', 'Absent'),
(1, '2025-05-18 09:58:44', 'Present'),
(2, '2025-05-18 10:01:12', 'Late'),
(3, '2025-05-18 09:55:00', 'Present'),
(1, '2025-05-16 10:05:11', 'Present'),
(4, '2025-05-16 10:07:45', 'Present');

-- Courses
INSERT IGNORE INTO courses (CourseName, Description, Price, Duration) VALUES
('Learners Licence Prep',   'Pass your K53 test first time. Theory + mock tests.', 650.00,  '1 week'),
('Manual Driving Course',   'Full Code 8 manual licence training. K53 manoeuvres.', 2800.00, '10 lessons'),
('Code 8 - Light Motor',    'Light motor vehicle licence. Road rules + yard test.', 2800.00, '10 lessons'),
('Code 10 - Heavy Motor',   'Professional Code 10 training for trucks.', 4500.00, '15 lessons'),
('Refresher Course',        'Already licenced? Build confidence back on the road.', 1200.00, '5 lessons');
