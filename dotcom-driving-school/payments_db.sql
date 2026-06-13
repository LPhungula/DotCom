-- ══════════════════════════════════════════════════
--  PAYMENTS FEATURE — Additional Tables
--  Run this in phpMyAdmin SQL tab on dotcom_driving_school
-- ══════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS payments (
  PaymentID    INT AUTO_INCREMENT PRIMARY KEY,
  StudentID    INT NOT NULL,
  CourseID     INT DEFAULT NULL,
  Description  VARCHAR(150) NOT NULL,
  Amount       DECIMAL(10,2) NOT NULL,
  Method       ENUM('Cash','EFT','Card','Mobile Money') NOT NULL DEFAULT 'Cash',
  Reference    VARCHAR(100) DEFAULT NULL,
  ProofFile    VARCHAR(255) DEFAULT NULL,
  Status       ENUM('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending',
  PaymentDate  DATE NOT NULL,
  CreatedAt    DATETIME DEFAULT CURRENT_TIMESTAMP,
  ConfirmedBy  INT DEFAULT NULL,
  ConfirmedAt  DATETIME DEFAULT NULL,
  FOREIGN KEY (StudentID) REFERENCES students(StudentID) ON DELETE CASCADE,
  FOREIGN KEY (CourseID)  REFERENCES courses(CourseID)   ON DELETE SET NULL,
  FOREIGN KEY (ConfirmedBy) REFERENCES users(UserID)     ON DELETE SET NULL
);

-- Seed a few demo payments
INSERT INTO payments (StudentID, CourseID, Description, Amount, Method, Reference, Status, PaymentDate) VALUES
(1, 2, 'Manual Driving Course - Full Package', 2800.00, 'EFT', 'EFT-REF-88213', 'Confirmed', '2025-05-15'),
(2, 3, 'Code 8 - Light Motor - Deposit', 1000.00, 'Cash', NULL, 'Confirmed', '2025-05-16'),
(3, 1, 'Learners Licence Prep', 650.00, 'Mobile Money', 'SS-2025-9981', 'Pending', '2025-05-18'),
(4, 2, 'Manual Driving Course - Full Package', 2800.00, 'Card', 'CARD-AUTH-3321', 'Confirmed', '2025-05-12'),
(5, 4, 'Code 10 - Heavy Motor - Deposit', 1500.00, 'EFT', 'EFT-REF-90011', 'Pending', '2025-05-19');
