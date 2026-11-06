CREATE DATABASE IF NOT EXISTS student_org_system;
USE student_org_system;

CREATE TABLE users (
  user_id INT PRIMARY KEY AUTO_INCREMENT,
  student_id VARCHAR(20) UNIQUE NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  verification_code VARCHAR(255) NULL,
  is_verified TINYINT(1) DEFAULT 0,
  role ENUM('student','officer','admin') DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE organizations (
  org_id INT PRIMARY KEY AUTO_INCREMENT,
  org_name VARCHAR(100) NOT NULL,
  org_description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE events (
  event_id INT PRIMARY KEY AUTO_INCREMENT,
  org_id INT NOT NULL,
  event_title VARCHAR(150) NOT NULL,
  event_description TEXT,
  event_date DATE NOT NULL,
  event_venue VARCHAR(100) NOT NULL,
  registration_open TINYINT(1) DEFAULT 1,
  max_participants INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (org_id) REFERENCES organizations(org_id) ON DELETE CASCADE
);

CREATE TABLE registrations (
  reg_id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  event_id INT NOT NULL,
  registration_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
  UNIQUE KEY unique_reg (user_id, event_id)
);

INSERT INTO organizations (org_name, org_description) VALUES 
('Computer Society', 'Official student organization for IT/CS students');

INSERT INTO events (org_id, event_title, event_description, event_date, event_venue, registration_open) VALUES 
(1, 'Tech Career Summit 2026', 'Connect with industry professionals.', '2026-11-15', 'Main Auditorium', 1);
