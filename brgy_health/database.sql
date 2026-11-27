-- BRGY. HEALTH CENTER RECORD KEEPING: a single SQL file (fresh install)
CREATE DATABASE IF NOT EXISTS brgy_health CHARACTER SET utf8mb4;
USE brgy_health;

CREATE TABLE users(
 id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL,
 full_name VARCHAR(120) NOT NULL,
 role ENUM('admin','doctor','nurse','midwife','bhw','resident') NOT NULL,
 status ENUM('active','pending','rejected') NOT NULL DEFAULT 'active',
 license_no VARCHAR(30) NULL, purok VARCHAR(60) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE patients(
 id INT AUTO_INCREMENT PRIMARY KEY,
 token CHAR(32) UNIQUE NOT NULL,
 full_name VARCHAR(120) NOT NULL,
 birthdate DATE NOT NULL,
 sex ENUM('M','F') NOT NULL,
 purok VARCHAR(60), contact VARCHAR(30), guardian VARCHAR(120), address VARCHAR(200) NULL,
 has_hpn TINYINT(1) DEFAULT 0, has_dm TINYINT(1) DEFAULT 0,
 sms_optin TINYINT(1) NOT NULL DEFAULT 1,
 contact_locked TINYINT(1) NOT NULL DEFAULT 0, address_locked TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_person(full_name,birthdate)
);
CREATE TABLE patient_allergies(
 id INT AUTO_INCREMENT PRIMARY KEY, patient_id INT NOT NULL,
 allergen VARCHAR(100) NOT NULL, reaction VARCHAR(150),
 severity ENUM('Mild','Moderate','Severe') NOT NULL DEFAULT 'Mild', noted_date DATE,
 FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE
);
CREATE TABLE vaccinations(
 id INT AUTO_INCREMENT PRIMARY KEY, patient_id INT NOT NULL, given_by INT NULL,
 vaccine VARCHAR(40) NOT NULL, dose_no TINYINT NOT NULL, date_given DATE NOT NULL,
 UNIQUE KEY uq_vax(patient_id,vaccine,dose_no),
 FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE,
 FOREIGN KEY(given_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE vitals(
 id INT AUTO_INCREMENT PRIMARY KEY, patient_id INT NOT NULL, taken_by INT NULL, date_taken DATE NOT NULL,
 systolic INT, diastolic INT, sugar DECIMAL(5,1) NULL,
 FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE,
 FOREIGN KEY(taken_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE prenatal(
 id INT AUTO_INCREMENT PRIMARY KEY, patient_id INT NOT NULL, lmp DATE NOT NULL, active TINYINT(1) DEFAULT 1,
 FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE
);
CREATE TABLE prenatal_visits(
 id INT AUTO_INCREMENT PRIMARY KEY, prenatal_id INT NOT NULL, attended_by INT NULL, visit_date DATE NOT NULL,
 weight DECIMAL(5,1) NULL, bp VARCHAR(10), notes VARCHAR(255),
 FOREIGN KEY(prenatal_id) REFERENCES prenatal(id) ON DELETE CASCADE,
 FOREIGN KEY(attended_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE appointments(
 id INT AUTO_INCREMENT PRIMARY KEY, patient_id INT NOT NULL, assigned_to INT NULL, appt_date DATE NOT NULL, purpose VARCHAR(120), notes VARCHAR(255) NULL,
 status ENUM('Requested','Confirmed','Cancelled','Done') DEFAULT 'Requested',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE,
 FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE notifications(
 id INT AUTO_INCREMENT PRIMARY KEY, patient_id INT NOT NULL, message VARCHAR(255) NOT NULL,
 dedupe_key VARCHAR(80) NULL, is_read TINYINT(1) DEFAULT 0,
 sms_status ENUM('none','pending','sent','failed') NOT NULL DEFAULT 'none',
 sms_sent_at DATETIME NULL, sms_error VARCHAR(200) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_n(patient_id,dedupe_key),
 FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE
);
CREATE TABLE user_patients(
 user_id INT NOT NULL, patient_id INT NOT NULL, PRIMARY KEY(user_id,patient_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE
);
CREATE TABLE resident_ids(
 id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, id_type VARCHAR(40) NOT NULL,
 file_front VARCHAR(80) NOT NULL, file_back VARCHAR(80) NULL,
 status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending', remarks VARCHAR(150) NULL,
 uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE audit_log(
 id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, action VARCHAR(150), patient_id INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
);
-- patient_id in audit_log has no foreign key so the history is kept even if the patient is deleted
