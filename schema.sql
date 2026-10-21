-- schema.sql  (import in phpMyAdmin > Import, or: mysql -u root < schema.sql)
CREATE DATABASE IF NOT EXISTS hm_lab_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hm_lab_db;

DROP TABLE IF EXISTS reservation_equipment;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS equipment;
DROP TABLE IF EXISTS laboratories;

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
  status ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Only one CONFIRMED booking per lab/date/shift; cancelled rows are ignored (NULLs are not unique-checked)
  active_slot VARCHAR(60) GENERATED ALWAYS AS (
    IF(status = 'confirmed', CONCAT(lab_id, '|', reservation_date, '|', time_shift), NULL)
  ) STORED,
  UNIQUE KEY uq_active_slot (active_slot),
  KEY idx_date (reservation_date),
  CONSTRAINT fk_res_lab FOREIGN KEY (lab_id) REFERENCES laboratories(id)
) ENGINE=InnoDB;

CREATE TABLE reservation_equipment (
  reservation_id INT UNSIGNED NOT NULL,
  equipment_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (reservation_id, equipment_id),
  CONSTRAINT fk_re_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
  CONSTRAINT fk_re_eq FOREIGN KEY (equipment_id) REFERENCES equipment(id)
) ENGINE=InnoDB;

-- IDs 1-3 match the checkboxes already in index.html
INSERT INTO equipment (id, name) VALUES
 (1,'Sous Vide Precision Cooker'),
 (2,'Commercial Sous Chef Mixer'),
 (3,'Espresso Grinder & Bar Tools'),
 (4,'Flambé Service Cart'),
 (5,'Housekeeping Linens Set');

-- category values must be: kitchen | bar | dining | hotel (the filter buttons use these)
INSERT INTO laboratories (code, name, category, description, capacity) VALUES
 ('CK-01','Culinary Station A','kitchen','Full-range cooking station with prep counter and induction burners.',4),
 ('CK-02','Pastry & Bakeshop Station','kitchen','Marble bench with deck oven for breads, doughs and desserts.',6),
 ('MB-01','Bartending Counter 1','bar','Speed rail bar with back bar for cocktail and coffee service practice.',5),
 ('MB-02','Barista Lab Bench','bar','Espresso bench for latte art and extraction drills.',3),
 ('FD-01','Banquet Service Hall','dining','Table service training with banquet seating and buffet line.',12),
 ('FD-02','Private Dining Room','dining','Intimate room for plated and wine service drills.',8),
 ('MH-01','Front Office Desk','hotel','Check-in desk with reservation software and concierge corner.',4),
 ('MH-02','Guest Room Suite','hotel','Furnished room for housekeeping standards and turn-down practice.',6);
