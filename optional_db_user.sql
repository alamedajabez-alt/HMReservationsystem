-- OPTIONAL (recommended for production): run as root, then set db_user/db_pass in config.php.
CREATE USER IF NOT EXISTS 'hm_app'@'localhost' IDENTIFIED BY 'PUT-A-STRONG-PASSWORD-HERE';
GRANT SELECT, INSERT, UPDATE ON hm_lab_db.* TO 'hm_app'@'localhost';
FLUSH PRIVILEGES;
