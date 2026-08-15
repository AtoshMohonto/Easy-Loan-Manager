-- Easy-Loan-Manager database schema
CREATE DATABASE IF NOT EXISTS `easy_loan_manager` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `easy_loan_manager`;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('Admin','Manager','Viewer') NOT NULL DEFAULT 'Viewer',
  `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `preferred_lang` ENUM('en','bn') NOT NULL DEFAULT 'en',
  `last_login` DATETIME DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- locations (used for the location filter across the app)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `locations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- persons (lenders & borrowers)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `person_type` ENUM('Lender','Borrower') NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `location_id` INT DEFAULT NULL,
  `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `notes` TEXT DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- transactions
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `person_id` INT DEFAULT NULL,
  `transaction_type` ENUM('Amount Given','Amount Received','Interest','Expense','Adjustment') NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `amount_words_en` VARCHAR(500) DEFAULT NULL,
  `amount_words_bn` VARCHAR(500) DEFAULT NULL,
  `transaction_date` DATE NOT NULL,
  `payment_method` ENUM('Cash','Bank','Mobile Banking','Cheque','Other') NOT NULL DEFAULT 'Cash',
  `description` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`person_id`) REFERENCES `persons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- notifications
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL COMMENT 'NULL = broadcast to all users',
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('system','transaction','person','reminder') NOT NULL DEFAULT 'system',
  `related_person_id` INT DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`related_person_id`) REFERENCES `persons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- monthly profit notes (manual annotations/targets per month)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `monthly_profit_notes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `year_month` CHAR(7) NOT NULL UNIQUE COMMENT 'YYYY-MM',
  `note` TEXT DEFAULT NULL,
  `target_profit` DECIMAL(15,2) DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- activity_logs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) DEFAULT NULL,
  `entity_id` INT DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- institution_loans (loans taken from banks / financial institutions)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `institution_loans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `institution_name` VARCHAR(150) NOT NULL,
  `loan_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `interest_rate` DECIMAL(5,2) DEFAULT NULL,
  `taken_date` DATE NOT NULL,
  `due_date` DATE DEFAULT NULL,
  `status` ENUM('Active','Closed') NOT NULL DEFAULT 'Active',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- institution_payments (principal / interest paid back to institutions)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `institution_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `institution_loan_id` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `payment_type` ENUM('Principal','Interest') NOT NULL DEFAULT 'Principal',
  `payment_date` DATE NOT NULL,
  `payment_method` ENUM('Cash','Bank','Mobile Banking','Cheque','Other') NOT NULL DEFAULT 'Cash',
  `description` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`institution_loan_id`) REFERENCES `institution_loans`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- settings (single row)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `company_name` VARCHAR(200) NOT NULL DEFAULT 'EasyLoan',
  `currency_symbol` VARCHAR(5) NOT NULL DEFAULT '৳',
  `default_lang` ENUM('en','bn') NOT NULL DEFAULT 'en',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =======================================================================
-- Seed data
-- =======================================================================

INSERT INTO `settings` (`id`, `company_name`, `currency_symbol`, `default_lang`)
VALUES (1, 'EasyLoan', '৳', 'en')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Passwords: admin123 / manager123 / viewer123 (bcrypt hashes below)
INSERT INTO `users` (`full_name`, `username`, `email`, `password`, `role`, `status`)
VALUES
('System Admin', 'admin', 'admin@easyloanmanager.local', '$2y$10$4p5FjXDtziQgGSi.4GcsYu8CViCacKbsTKwH8hvf8Usn7pwAA2GIK', 'Admin', 'Active'),
('Loan Manager', 'manager', 'manager@easyloanmanager.local', '$2y$10$A9DBRSGAVjUbSju9ssGPXO.OBecJ5e9D48fTrpml4k2HZTTEECey.', 'Manager', 'Active'),
('Report Viewer', 'viewer', 'viewer@easyloanmanager.local', '$2y$10$JOAGg1T8qQAKAiegIVUMN.5UDdLw5w3mA2O6qPOvTgl5KNd1rnliy', 'Viewer', 'Active')
ON DUPLICATE KEY UPDATE `username` = `username`;

INSERT INTO `locations` (`name`) VALUES ('Dhaka'), ('Chattogram'), ('Sylhet')
ON DUPLICATE KEY UPDATE `name` = `name`;
