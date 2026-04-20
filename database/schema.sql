CREATE DATABASE IF NOT EXISTS hostel_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hostel_management;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS leave_approval_log;
DROP TABLE IF EXISTS leave_requests;
DROP TABLE IF EXISTS complaints;
DROP TABLE IF EXISTS visitors;
DROP TABLE IF EXISTS allotments;
DROP TABLE IF EXISTS beds;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS floors;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS notices;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS hostels;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE hostels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  type ENUM('boys','girls'),
  warden_name VARCHAR(100),
  warden_phone VARCHAR(15),
  total_floors INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  phone VARCHAR(15) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('super_admin','warden','class_coordinator','staff') NOT NULL,
  hostel_id INT NULL,
  department VARCHAR(100) NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  last_login DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

CREATE TABLE floors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hostel_id INT,
  floor_number INT,
  floor_label VARCHAR(50),
  FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

CREATE TABLE rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  floor_id INT,
  room_number VARCHAR(10),
  room_type ENUM('single','double','triple','quad'),
  capacity INT,
  amenities TEXT,
  status ENUM('active','maintenance','closed') DEFAULT 'active',
  FOREIGN KEY (floor_id) REFERENCES floors(id)
);

CREATE TABLE beds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT,
  bed_number INT,
  status ENUM('vacant','occupied','reserved','maintenance') DEFAULT 'vacant',
  FOREIGN KEY (room_id) REFERENCES rooms(id)
);

CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  roll_number VARCHAR(30) NOT NULL UNIQUE,
  gender ENUM('male','female') NOT NULL,
  course VARCHAR(50) NOT NULL,
  branch VARCHAR(50) NOT NULL,
  year INT NOT NULL,
  phone VARCHAR(15) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  parent_name VARCHAR(100) NOT NULL,
  parent_phone VARCHAR(15) NOT NULL UNIQUE,
  parent_email VARCHAR(100) UNIQUE,
  address TEXT NOT NULL,
  id_proof_type VARCHAR(50),
  id_proof_number VARCHAR(50) UNIQUE,
  photo VARCHAR(200),
  id_proof_file VARCHAR(200),
  joining_date DATE,
  status ENUM('active','alumni','suspended') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_phone CHECK (phone REGEXP '^[6-9][0-9]{9}$'),
  CONSTRAINT chk_email CHECK (email LIKE '%@%.%')
);

CREATE TABLE allotments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT,
  bed_id INT,
  hostel_id INT,
  allotment_date DATE,
  vacate_date DATE NULL,
  status ENUM('active','vacated','transferred'),
  remarks TEXT,
  allotted_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id),
  FOREIGN KEY (bed_id) REFERENCES beds(id),
  FOREIGN KEY (hostel_id) REFERENCES hostels(id),
  FOREIGN KEY (allotted_by) REFERENCES users(id)
);

CREATE TABLE leave_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  from_date DATE NOT NULL,
  to_date DATE NOT NULL,
  total_days INT GENERATED ALWAYS AS (DATEDIFF(to_date, from_date) + 1) STORED,
  reason TEXT NOT NULL,
  leave_type ENUM('home','medical','emergency','event','other') NOT NULL,
  destination VARCHAR(200) NOT NULL,
  contact_during_leave VARCHAR(15) NOT NULL,
  cc_id INT NULL,
  cc_status ENUM('pending','approved','rejected') DEFAULT 'pending',
  cc_remarks TEXT NULL,
  cc_action_at DATETIME NULL,
  warden_id INT NULL,
  warden_status ENUM('pending','approved','rejected') DEFAULT 'pending',
  warden_remarks TEXT NULL,
  warden_action_at DATETIME NULL,
  final_status ENUM('pending','cc_approved','cc_rejected','warden_approved','warden_rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id),
  FOREIGN KEY (cc_id) REFERENCES users(id),
  FOREIGN KEY (warden_id) REFERENCES users(id)
);

CREATE TABLE leave_approval_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  leave_id INT NOT NULL,
  approver_id INT NOT NULL,
  approver_role ENUM('class_coordinator','warden') NOT NULL,
  action ENUM('approved','rejected') NOT NULL,
  remarks TEXT,
  action_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (leave_id) REFERENCES leave_requests(id),
  FOREIGN KEY (approver_id) REFERENCES users(id)
);

CREATE TABLE visitors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT,
  visitor_name VARCHAR(100) NOT NULL,
  visitor_phone VARCHAR(15) NOT NULL,
  relation VARCHAR(50) NOT NULL,
  purpose TEXT,
  id_proof VARCHAR(100),
  check_in DATETIME NOT NULL,
  check_out DATETIME NULL,
  approved_by INT,
  status ENUM('checked_in','checked_out') DEFAULT 'checked_in',
  FOREIGN KEY (student_id) REFERENCES students(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
);

CREATE TABLE staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  role ENUM('warden','caretaker','security','cleaner','admin'),
  hostel_id INT,
  phone VARCHAR(15) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  shift ENUM('morning','evening','night','full_day'),
  joining_date DATE,
  status ENUM('active','inactive') DEFAULT 'active',
  FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

CREATE TABLE complaints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT,
  hostel_id INT,
  category ENUM('maintenance','hygiene','security','electrical','plumbing','other'),
  subject VARCHAR(200),
  description TEXT,
  priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
  status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
  assigned_to INT NULL,
  resolved_date DATE NULL,
  resolution_note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id),
  FOREIGN KEY (hostel_id) REFERENCES hostels(id),
  FOREIGN KEY (assigned_to) REFERENCES staff(id)
);

CREATE TABLE notices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hostel_id INT,
  title VARCHAR(200),
  content TEXT,
  target ENUM('all','boys','girls'),
  posted_by INT,
  expiry_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (hostel_id) REFERENCES hostels(id),
  FOREIGN KEY (posted_by) REFERENCES users(id)
);

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(200),
  module VARCHAR(100),
  description TEXT,
  ip_address VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX idx_students_branch ON students(branch);
CREATE INDEX idx_students_year ON students(year);
CREATE INDEX idx_allotments_status ON allotments(status);
CREATE INDEX idx_leave_final_status ON leave_requests(final_status);
CREATE INDEX idx_complaints_status ON complaints(status);
CREATE INDEX idx_visitors_status ON visitors(status);

INSERT INTO hostels (name, type, warden_name, warden_phone, total_floors)
VALUES
('Boys Hostel A', 'boys', 'Mr. Singh', '9876543210', 4),
('Girls Hostel B', 'girls', 'Ms. Rao', '9123456789', 4);

