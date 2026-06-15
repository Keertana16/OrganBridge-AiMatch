CREATE DATABASE IF NOT EXISTS organ_db;
USE organ_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS transfer_events;
DROP TABLE IF EXISTS transfer_history;
DROP TABLE IF EXISTS transfer_requests;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS transfers;
DROP TABLE IF EXISTS ai_matches;
DROP TABLE IF EXISTS organ_receivers;
DROP TABLE IF EXISTS organ_requests;
DROP TABLE IF EXISTS recipients;
DROP TABLE IF EXISTS organ_listings;
DROP TABLE IF EXISTS organs;
DROP TABLE IF EXISTS coordinators;
DROP TABLE IF EXISTS hospitals;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  full_name VARCHAR(150),
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('hospital','coordinator') NOT NULL,
  city VARCHAR(100),
  phone VARCHAR(20),
  contact_phone VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hospitals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  city VARCHAR(100),
  contact_phone VARCHAR(20),
  registration_number VARCHAR(100),
  verified TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE coordinators (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  employee_id VARCHAR(100),
  department VARCHAR(150) DEFAULT 'Transplant Coordination',
  permission_level VARCHAR(50) DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE organs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT NOT NULL,
  organ_type VARCHAR(50) NOT NULL,
  organ_name VARCHAR(50),
  blood_group VARCHAR(5) NOT NULL,
  donor_age INT,
  donor_city VARCHAR(100),
  hospital_name VARCHAR(150),
  organ_condition ENUM('Excellent','Good','Marginal') DEFAULT 'Good',
  availability_status ENUM('available','matched','expired','transferred') DEFAULT 'available',
  available_until DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE organ_listings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT NOT NULL,
  organ_type VARCHAR(50) NOT NULL,
  blood_group VARCHAR(5) NOT NULL,
  donor_age INT,
  city VARCHAR(100),
  `condition` ENUM('Excellent','Good','Marginal') DEFAULT 'Good',
  organ_function_pct DECIMAL(5,2) DEFAULT 85.00,
  viable_until DATETIME,
  status ENUM('Available','Matched','Expired','Transferred') DEFAULT 'Available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE recipients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT,
  user_id INT NULL,
  patient_name VARCHAR(150) NOT NULL,
  organ_needed VARCHAR(50) NOT NULL,
  email VARCHAR(150) NULL,
  age INT,
  blood_group VARCHAR(5) NOT NULL,
  city VARCHAR(100),
  urgency_level INT DEFAULT 1,
  waiting_days INT DEFAULT 0,
  systolic_bp INT,
  diastolic_bp INT,
  hla_match_score INT DEFAULT 70,
  gfr_score INT,
  creatinine DECIMAL(5,2),
  diabetes TINYINT DEFAULT 0,
  hypertension TINYINT DEFAULT 0,
  cardiac_stable TINYINT DEFAULT 1,
  infection TINYINT DEFAULT 0,
  prev_transplants TINYINT DEFAULT 0,
  status ENUM('Waiting','Matched','Fulfilled','Cancelled') DEFAULT 'Waiting',
  medical_history TEXT,
  medical_notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE ai_matches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  organ_id INT NOT NULL,
  request_id INT,
  recipient_id INT NOT NULL,
  match_score DECIMAL(5,2),
  donor_age INT,
  donor_blood_group VARCHAR(5),
  donor_city VARCHAR(100),
  organ_type VARCHAR(50),
  recipient_age INT,
  recipient_blood_group VARCHAR(5),
  recipient_city VARCHAR(100),
  waiting_days INT,
  priority_level VARCHAR(20),
  distance_km DECIMAL(8,2),
  organ_type_match TINYINT DEFAULT 1,
  blood_group_compatible TINYINT DEFAULT 1,
  city_match TINYINT DEFAULT 0,
  coordinator_id INT,
  decision ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  match_status VARCHAR(30) DEFAULT 'pending',
  rejection_reason VARCHAR(255),
  score_factors TEXT,
  matched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  evaluated_at DATETIME NULL
);

CREATE TABLE transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT NOT NULL,
  status ENUM('Pending','Approved','In Transit','Received','Surgery Scheduled','Completed','Failed','Rejected') DEFAULT 'Pending',
  notes TEXT,
  current_lat DECIMAL(10,7) NULL,
  current_lng DECIMAL(10,7) NULL,
  current_location VARCHAR(180) NULL,
  last_gps_at DATETIME NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE transfer_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_id INT NOT NULL,
  status VARCHAR(50) NOT NULL,
  notes TEXT,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  location_label VARCHAR(180) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  notification_type ENUM('organ_added','recipient_request','match_found','transfer_approved','transfer_rejected','transfer_completed','status_update') DEFAULT 'status_update',
  title VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  related_organ_id INT NULL,
  related_request_id INT NULL,
  is_read TINYINT DEFAULT 0,
  action_url VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL
);

INSERT INTO users (name, full_name, email, password, role, city, phone, contact_phone) VALUES
('Demo Hospital', 'Demo Hospital', 'hospital@example.com', '123456', 'hospital', 'Chennai', '9000000000', '9000000000'),
('Demo Coordinator', 'Demo Coordinator', 'coordinator@example.com', '123456', 'coordinator', 'Chennai', '9000000001', '9000000001'),
('Apollo Care Chennai', 'Apollo Care Chennai', 'apollo@example.com', '123456', 'hospital', 'Chennai', '9000000101', '9000000101'),
('City Heart Institute', 'City Heart Institute', 'cityheart@example.com', '123456', 'hospital', 'Bengaluru', '9000000102', '9000000102'),
('Kaveri Medical Center', 'Kaveri Medical Center', 'kaveri@example.com', '123456', 'hospital', 'Hyderabad', '9000000103', '9000000103'),
('Lakeside Transplant Unit', 'Lakeside Transplant Unit', 'lakeside@example.com', '123456', 'hospital', 'Kochi', '9000000104', '9000000104'),
('National Organ Desk', 'National Organ Desk', 'desk@example.com', '123456', 'coordinator', 'Chennai', '9000000201', '9000000201');

INSERT INTO hospitals (user_id, name, email, password, city, contact_phone, registration_number, verified) VALUES
(1, 'Demo Hospital', 'hospital@example.com', '123456', 'Chennai', '9000000000', 'HOSP-DEMO-001', 1),
(3, 'Apollo Care Chennai', 'apollo@example.com', '123456', 'Chennai', '9000000101', 'HOSP-CHN-101', 1),
(4, 'City Heart Institute', 'cityheart@example.com', '123456', 'Bengaluru', '9000000102', 'HOSP-BLR-102', 1),
(5, 'Kaveri Medical Center', 'kaveri@example.com', '123456', 'Hyderabad', '9000000103', 'HOSP-HYD-103', 1),
(6, 'Lakeside Transplant Unit', 'lakeside@example.com', '123456', 'Kochi', '9000000104', 'HOSP-KOC-104', 1);

INSERT INTO coordinators (user_id, name, email, employee_id, department, permission_level) VALUES
(2, 'Demo Coordinator', 'coordinator@example.com', 'COORD-DEMO-001', 'Transplant Coordination', 'admin'),
(7, 'National Organ Desk', 'desk@example.com', 'COORD-NOD-201', 'Regional Allocation', 'admin');

INSERT INTO organs (id, hospital_id, organ_type, organ_name, blood_group, donor_age, donor_city, hospital_name, organ_condition, availability_status, available_until, created_at) VALUES
(1, 1, 'Kidney', 'Kidney', 'O+', 34, 'Chennai', 'Demo Hospital', 'Excellent', 'available', DATE_ADD(NOW(), INTERVAL 18 HOUR), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 2, 'Liver', 'Liver', 'A+', 41, 'Chennai', 'Apollo Care Chennai', 'Good', 'available', DATE_ADD(NOW(), INTERVAL 10 HOUR), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 3, 'Heart', 'Heart', 'B+', 29, 'Bengaluru', 'City Heart Institute', 'Excellent', 'matched', DATE_ADD(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(4, 4, 'Lung', 'Lung', 'AB+', 38, 'Hyderabad', 'Kaveri Medical Center', 'Good', 'available', DATE_ADD(NOW(), INTERVAL 7 HOUR), DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(5, 5, 'Kidney', 'Kidney', 'O-', 25, 'Kochi', 'Lakeside Transplant Unit', 'Excellent', 'available', DATE_ADD(NOW(), INTERVAL 26 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(6, 2, 'Pancreas', 'Pancreas', 'A-', 46, 'Chennai', 'Apollo Care Chennai', 'Marginal', 'available', DATE_ADD(NOW(), INTERVAL 14 HOUR), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(7, 3, 'Cornea', 'Cornea', 'AB-', 52, 'Bengaluru', 'City Heart Institute', 'Good', 'available', DATE_ADD(NOW(), INTERVAL 36 HOUR), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(8, 4, 'Kidney', 'Kidney', 'B-', 31, 'Hyderabad', 'Kaveri Medical Center', 'Good', 'transferred', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(9, 1, 'Liver', 'Liver', 'O+', 44, 'Chennai', 'Demo Hospital', 'Good', 'available', DATE_ADD(NOW(), INTERVAL 4 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(10, 5, 'Heart', 'Heart', 'O-', 36, 'Kochi', 'Lakeside Transplant Unit', 'Excellent', 'available', DATE_ADD(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(11, 3, 'Lung', 'Lung', 'A+', 48, 'Bengaluru', 'City Heart Institute', 'Marginal', 'expired', DATE_SUB(NOW(), INTERVAL 8 HOUR), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(12, 4, 'Kidney', 'Kidney', 'AB+', 27, 'Hyderabad', 'Kaveri Medical Center', 'Excellent', 'available', DATE_ADD(NOW(), INTERVAL 22 HOUR), DATE_SUB(NOW(), INTERVAL 9 HOUR));

INSERT INTO organ_listings (id, hospital_id, organ_type, blood_group, donor_age, city, `condition`, organ_function_pct, viable_until, status, created_at) VALUES
(1, 1, 'Kidney', 'O+', 34, 'Chennai', 'Excellent', 96.00, DATE_ADD(NOW(), INTERVAL 18 HOUR), 'Available', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 2, 'Liver', 'A+', 41, 'Chennai', 'Good', 88.00, DATE_ADD(NOW(), INTERVAL 10 HOUR), 'Available', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 3, 'Heart', 'B+', 29, 'Bengaluru', 'Excellent', 94.00, DATE_ADD(NOW(), INTERVAL 5 HOUR), 'Matched', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(4, 4, 'Lung', 'AB+', 38, 'Hyderabad', 'Good', 82.00, DATE_ADD(NOW(), INTERVAL 7 HOUR), 'Available', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(5, 5, 'Kidney', 'O-', 25, 'Kochi', 'Excellent', 97.00, DATE_ADD(NOW(), INTERVAL 26 HOUR), 'Available', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(6, 2, 'Pancreas', 'A-', 46, 'Chennai', 'Marginal', 72.00, DATE_ADD(NOW(), INTERVAL 14 HOUR), 'Available', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(7, 3, 'Cornea', 'AB-', 52, 'Bengaluru', 'Good', 86.00, DATE_ADD(NOW(), INTERVAL 36 HOUR), 'Available', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(8, 4, 'Kidney', 'B-', 31, 'Hyderabad', 'Good', 84.00, DATE_SUB(NOW(), INTERVAL 2 HOUR), 'Transferred', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(9, 1, 'Liver', 'O+', 44, 'Chennai', 'Good', 81.00, DATE_ADD(NOW(), INTERVAL 4 HOUR), 'Available', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(10, 5, 'Heart', 'O-', 36, 'Kochi', 'Excellent', 95.00, DATE_ADD(NOW(), INTERVAL 3 HOUR), 'Available', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(11, 3, 'Lung', 'A+', 48, 'Bengaluru', 'Marginal', 63.00, DATE_SUB(NOW(), INTERVAL 8 HOUR), 'Expired', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(12, 4, 'Kidney', 'AB+', 27, 'Hyderabad', 'Excellent', 92.00, DATE_ADD(NOW(), INTERVAL 22 HOUR), 'Available', DATE_SUB(NOW(), INTERVAL 9 HOUR));

INSERT INTO recipients (id, hospital_id, patient_name, organ_needed, email, age, blood_group, city, urgency_level, waiting_days, systolic_bp, diastolic_bp, hla_match_score, gfr_score, creatinine, diabetes, hypertension, cardiac_stable, infection, prev_transplants, status, medical_history, medical_notes, created_at) VALUES
(1, 2, 'Arun Kumar', 'Kidney', 'arun.kumar@example.com', 47, 'A+', 'Chennai', 5, 190, 138, 88, 83, 18, 5.60, 1, 1, 1, 0, 0, 'Waiting', 'End-stage renal disease; dialysis three times weekly.', 'High urgency, stable for surgery.', DATE_SUB(NOW(), INTERVAL 190 DAY)),
(2, 3, 'Meera Iyer', 'Liver', 'meera.iyer@example.com', 39, 'A+', 'Bengaluru', 4, 120, 126, 82, 76, 42, 2.10, 0, 0, 1, 0, 0, 'Waiting', 'Chronic liver failure with rising MELD score.', 'Family available for consent.', DATE_SUB(NOW(), INTERVAL 120 DAY)),
(3, 4, 'Ravi Nair', 'Heart', 'ravi.nair@example.com', 55, 'B+', 'Hyderabad', 5, 220, 132, 84, 81, 55, 1.30, 0, 1, 1, 0, 0, 'Matched', 'Dilated cardiomyopathy with low ejection fraction.', 'Approved match pending transfer.', DATE_SUB(NOW(), INTERVAL 220 DAY)),
(4, 5, 'Sara Joseph', 'Lung', 'sara.joseph@example.com', 31, 'AB+', 'Kochi', 4, 95, 118, 76, 88, 64, 0.90, 0, 0, 1, 0, 0, 'Waiting', 'Pulmonary fibrosis; oxygen dependent.', 'Can travel within 4 hours.', DATE_SUB(NOW(), INTERVAL 95 DAY)),
(5, 1, 'Naveen Reddy', 'Kidney', 'naveen.reddy@example.com', 62, 'O+', 'Chennai', 3, 300, 145, 92, 67, 12, 6.20, 1, 1, 1, 0, 1, 'Waiting', 'Long dialysis history; previous transplant 12 years ago.', 'Needs careful cardiac clearance.', DATE_SUB(NOW(), INTERVAL 300 DAY)),
(6, 3, 'Priya Menon', 'Pancreas', 'priya.menon@example.com', 28, 'A-', 'Bengaluru', 4, 80, 116, 74, 79, 70, 0.80, 1, 0, 1, 0, 0, 'Waiting', 'Type 1 diabetes with recurrent hypoglycemia.', 'Suitable for pancreas listing.', DATE_SUB(NOW(), INTERVAL 80 DAY)),
(7, 4, 'Imran Khan', 'Cornea', 'imran.khan@example.com', 44, 'AB+', 'Hyderabad', 2, 45, 122, 80, 70, 80, 0.70, 0, 0, 1, 0, 0, 'Waiting', 'Corneal scarring after injury.', 'Elective transplant candidate.', DATE_SUB(NOW(), INTERVAL 45 DAY)),
(8, 2, 'Latha Devi', 'Kidney', 'latha.devi@example.com', 36, 'B+', 'Chennai', 5, 260, 136, 86, 91, 15, 5.80, 0, 1, 1, 0, 0, 'Fulfilled', 'Renal failure after autoimmune nephritis.', 'Completed transfer case.', DATE_SUB(NOW(), INTERVAL 260 DAY)),
(9, 5, 'George Mathew', 'Heart', 'george.mathew@example.com', 49, 'O+', 'Kochi', 5, 175, 128, 82, 85, 68, 1.10, 0, 0, 1, 0, 0, 'Waiting', 'Advanced heart failure; listed urgent.', 'Near donor center.', DATE_SUB(NOW(), INTERVAL 175 DAY)),
(10, 1, 'Ananya Shah', 'Liver', 'ananya.shah@example.com', 42, 'O+', 'Chennai', 4, 142, 124, 78, 74, 39, 1.90, 0, 0, 1, 0, 0, 'Waiting', 'Acute-on-chronic liver disease.', 'Responding to bridge therapy.', DATE_SUB(NOW(), INTERVAL 142 DAY)),
(11, 4, 'Vikram Das', 'Lung', 'vikram.das@example.com', 58, 'A+', 'Hyderabad', 3, 210, 140, 88, 62, 52, 1.40, 0, 1, 0, 1, 0, 'Waiting', 'COPD with recent infection under review.', 'Temporary infection flag.', DATE_SUB(NOW(), INTERVAL 210 DAY)),
(12, 3, 'Kavya Rao', 'Kidney', 'kavya.rao@example.com', 24, 'AB+', 'Bengaluru', 2, 35, 110, 70, 93, 22, 4.30, 0, 0, 1, 0, 0, 'Waiting', 'Congenital renal disease.', 'Young candidate, excellent HLA score.', DATE_SUB(NOW(), INTERVAL 35 DAY));

INSERT INTO ai_matches (id, organ_id, request_id, recipient_id, match_score, donor_age, donor_blood_group, donor_city, organ_type, recipient_age, recipient_blood_group, recipient_city, waiting_days, priority_level, distance_km, organ_type_match, blood_group_compatible, city_match, coordinator_id, decision, match_status, rejection_reason, score_factors, matched_at, evaluated_at) VALUES
(1, 1, 1, 1, 91.80, 34, 'O+', 'Chennai', 'Kidney', 47, 'A+', 'Chennai', 190, 'critical', 10.00, 1, 1, 1, NULL, 'Pending', 'pending', NULL, '{"blood_compatible":true,"urgency_component":22,"organ_function_component":14.4,"hla_component":9.96,"distance_component":9.8}', DATE_SUB(NOW(), INTERVAL 90 MINUTE), NULL),
(2, 2, 2, 2, 78.60, 41, 'A+', 'Chennai', 'Liver', 39, 'A+', 'Bengaluru', 120, 'high', 250.00, 1, 1, 0, NULL, 'Pending', 'pending', NULL, '{"blood_compatible":true,"urgency_component":17.6,"organ_function_component":13.2,"hla_component":9.12,"distance_component":5}', DATE_SUB(NOW(), INTERVAL 70 MINUTE), NULL),
(3, 3, 3, 3, 87.40, 29, 'B+', 'Bengaluru', 'Heart', 55, 'B+', 'Hyderabad', 220, 'critical', 250.00, 1, 1, 0, 1, 'Approved', 'approved', NULL, '{"blood_compatible":true,"urgency_component":22,"organ_function_component":14.1,"hla_component":9.72,"distance_component":5}', DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(4, 4, 4, 4, 72.10, 38, 'AB+', 'Hyderabad', 'Lung', 31, 'AB+', 'Kochi', 95, 'high', 250.00, 1, 1, 0, NULL, 'Pending', 'pending', NULL, '{"blood_compatible":true,"urgency_component":17.6,"organ_function_component":12.3,"hla_component":10.56,"distance_component":5}', DATE_SUB(NOW(), INTERVAL 50 MINUTE), NULL),
(5, 8, 8, 8, 83.50, 31, 'B-', 'Hyderabad', 'Kidney', 36, 'B+', 'Chennai', 260, 'critical', 250.00, 1, 1, 0, 1, 'Approved', 'approved', NULL, '{"blood_compatible":true,"urgency_component":22,"organ_function_component":12.6,"hla_component":10.92,"distance_component":5}', DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(6, 9, 10, 10, 76.20, 44, 'O+', 'Chennai', 'Liver', 42, 'O+', 'Chennai', 142, 'high', 10.00, 1, 1, 1, NULL, 'Pending', 'pending', NULL, '{"blood_compatible":true,"urgency_component":17.6,"organ_function_component":12.15,"hla_component":8.88,"distance_component":9.8}', DATE_SUB(NOW(), INTERVAL 35 MINUTE), NULL),
(7, 10, 9, 9, 88.90, 36, 'O-', 'Kochi', 'Heart', 49, 'O+', 'Kochi', 175, 'critical', 10.00, 1, 1, 1, NULL, 'Pending', 'pending', NULL, '{"blood_compatible":true,"urgency_component":22,"organ_function_component":14.25,"hla_component":10.2,"distance_component":9.8}', DATE_SUB(NOW(), INTERVAL 20 MINUTE), NULL),
(8, 6, 6, 6, 69.30, 46, 'A-', 'Chennai', 'Pancreas', 28, 'A-', 'Bengaluru', 80, 'high', 250.00, 1, 1, 0, 1, 'Rejected', 'rejected', 'Recipient team requested endocrinology review before allocation.', '{"blood_compatible":true,"urgency_component":17.6,"organ_function_component":10.8,"hla_component":9.48,"distance_component":5}', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 20 HOUR));

INSERT INTO transfers (id, match_id, status, notes, current_lat, current_lng, current_location, last_gps_at, updated_at) VALUES
(1, 3, 'In Transit', 'Organ retrieval completed; transport team departed Bengaluru.', 15.1783, 77.9906, 'Ambulance between Bengaluru and Hyderabad', DATE_SUB(NOW(), INTERVAL 45 MINUTE), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 5, 'Completed', 'Kidney received and transplant completed successfully.', 13.0827, 80.2707, 'Recipient hospital, Chennai', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO transfer_events (transfer_id, status, notes, latitude, longitude, location_label, created_at) VALUES
(1, 'Approved', 'Coordinator approved match and notified both hospitals.', 12.9716, 77.5946, 'City Heart Institute, Bengaluru', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(1, 'In Transit', 'Cold chain container sealed and handed to transport team.', 15.1783, 77.9906, 'Ambulance between Bengaluru and Hyderabad', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 'Approved', 'Transfer approved after crossmatch confirmation.', 17.3850, 78.4867, 'Kaveri Medical Center, Hyderabad', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 'In Transit', 'Ambulance transfer started from donor hospital.', 15.2330, 79.1060, 'Ambulance route to Chennai', DATE_SUB(DATE_SUB(NOW(), INTERVAL 2 DAY), INTERVAL 8 HOUR)),
(2, 'Received', 'Recipient hospital confirmed organ arrival.', 13.0827, 80.2707, 'Recipient hospital, Chennai', DATE_SUB(DATE_SUB(NOW(), INTERVAL 2 DAY), INTERVAL 3 HOUR)),
(2, 'Completed', 'Surgery completed and patient moved to ICU.', 13.0827, 80.2707, 'Recipient hospital ICU, Chennai', DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO notifications (user_id, notification_type, title, message, related_organ_id, related_request_id, is_read, action_url, created_at) VALUES
(2, 'match_found', 'High priority match found', 'Kidney O+ matched with Arun Kumar at 91.8%.', 1, 1, 0, 'review_matches.php', DATE_SUB(NOW(), INTERVAL 90 MINUTE)),
(2, 'match_found', 'Heart match needs review', 'Heart O- matched with George Mathew at 88.9%.', 10, 9, 0, 'review_matches.php', DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(7, 'match_found', 'Liver match ready', 'Liver O+ matched with Ananya Shah at 76.2%.', 9, 10, 0, 'review_matches.php', DATE_SUB(NOW(), INTERVAL 35 MINUTE)),
(1, 'organ_added', 'Organ listed', 'Your Kidney O+ listing is available for allocation.', 1, NULL, 1, 'my_listings.php', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'recipient_request', 'Recipient waiting', 'Arun Kumar is waiting for Kidney allocation.', NULL, 1, 0, 'my_patients.php', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(4, 'transfer_approved', 'Transfer approved', 'Heart transfer for Ravi Nair has been approved.', 3, 3, 0, 'transfers.php', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(5, 'status_update', 'Lung expiring soon', 'Lung AB+ listing expires within 7 hours.', 4, NULL, 0, 'my_listings.php', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(6, 'transfer_completed', 'Transfer completed', 'Kidney transfer for Latha Devi was completed.', 8, 8, 1, 'transfers.php', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'status_update', 'Organ expiring soon', 'Heart O- listing in Kochi expires within 3 hours.', 10, NULL, 0, 'all_organs.php', DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(1, 'match_found', 'Liver match generated', 'Ananya Shah matched with your Liver O+ listing.', 9, 10, 0, 'review_matches.php', DATE_SUB(NOW(), INTERVAL 35 MINUTE));
