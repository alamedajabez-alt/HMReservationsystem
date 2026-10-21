-- schema.sql  (phpMyAdmin > Import, or: mysql -u root < schema.sql)
-- Re-importing resets laboratories/equipment/reservations. Custodian accounts (admins) are KEPT.
CREATE DATABASE IF NOT EXISTS hm_lab_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hm_lab_db;

DROP TABLE IF EXISTS reservation_equipment;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS equipment;
DROP TABLE IF EXISTS laboratories;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE laboratories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  category ENUM('kitchen','bar','dining','hotel') NOT NULL,
  description TEXT,
  capacity TINYINT UNSIGNED NOT NULL DEFAULT 4,
  status ENUM('available','maintenance') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB;

CREATE TABLE equipment (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref_code VARCHAR(20) NOT NULL UNIQUE,
  lab_id INT UNSIGNED NOT NULL,
  student_name VARCHAR(100) NOT NULL,
  student_id VARCHAR(30) NOT NULL,
  course_code VARCHAR(100) NOT NULL,
  reservation_date DATE NOT NULL,
  time_shift VARCHAR(30) NOT NULL,
  -- pending -> (custodian) approved | rejected ; student may cancel pending/approved
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  admin_note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- DOUBLE-BOOKING GUARD #1: one pending/approved booking per lab + date + shift.
  active_slot VARCHAR(60) GENERATED ALWAYS AS (
    IF(status IN ('pending','approved'), CONCAT(lab_id, '|', reservation_date, '|', time_shift), NULL)
  ) STORED,
  -- DOUBLE-BOOKING GUARD #2: one person can't hold two stations at the same date + shift.
  student_slot VARCHAR(100) GENERATED ALWAYS AS (
    IF(status IN ('pending','approved'), CONCAT(student_id, '|', reservation_date, '|', time_shift), NULL)
  ) STORED,
  UNIQUE KEY uq_active_slot (active_slot),
  UNIQUE KEY uq_student_slot (student_slot),
  KEY idx_date (reservation_date),
  KEY idx_status (status, reservation_date),
  KEY idx_student (student_id, status, reservation_date),
  CONSTRAINT fk_res_lab FOREIGN KEY (lab_id) REFERENCES laboratories(id),
  CONSTRAINT fk_res_admin FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE reservation_equipment (
  reservation_id INT UNSIGNED NOT NULL,
  equipment_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (reservation_id, equipment_id),
  CONSTRAINT fk_re_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
  CONSTRAINT fk_re_eq FOREIGN KEY (equipment_id) REFERENCES equipment(id)
) ENGINE=InnoDB;

INSERT INTO equipment (id, name) VALUES
 (1,'Sous Vide Precision Cooker'),
 (2,'Commercial Sous Chef Mixer'),
 (3,'Espresso Grinder & Bar Tools'),
 (4,'Flambé Service Cart'),
 (5,'Housekeeping Linens Set');

INSERT INTO laboratories (code, name, category, description, capacity) VALUES
 ('CK-01','Culinary Station A','kitchen','Full-range cooking station with prep counter and induction burners.',4),
 ('CK-02','Pastry & Bakeshop Station','kitchen','Marble bench with deck oven for breads, doughs and desserts.',6),
 ('MB-01','Bartending Counter 1','bar','Speed rail bar with back bar for cocktail and coffee service practice.',5),
 ('MB-02','Barista Lab Bench','bar','Espresso bench for latte art and extraction drills.',3),
 ('FD-01','Banquet Service Hall','dining','Table service training with banquet seating and buffet line.',12),
 ('FD-02','Private Dining Room','dining','Intimate room for plated and wine service drills.',8),
 ('MH-01','Front Office Desk','hotel','Check-in desk with reservation software and concierge corner.',4),
 ('MH-02','Guest Room Suite','hotel','Furnished room for housekeeping standards and turn-down practice.',6);
