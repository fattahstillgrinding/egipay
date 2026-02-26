-- ============================================================
-- EgiPay – Add Withdrawals System
-- Run this after egipay.sql has been imported
-- ============================================================

USE `egipay`;

-- ============================================================
-- Table: withdrawals
-- ============================================================
CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `wdr_no`            VARCHAR(25)      NOT NULL UNIQUE,
  `user_id`           INT UNSIGNED     NOT NULL,
  `amount`            DECIMAL(15,2)    NOT NULL,
  `fee`               DECIMAL(10,2)    NOT NULL DEFAULT 0.00  COMMENT 'Admin fee deducted',
  `net_amount`        DECIMAL(15,2)    NOT NULL               COMMENT 'amount - fee',
  `bank_name`         VARCHAR(60)      NOT NULL,
  `bank_account_no`   VARCHAR(40)      NOT NULL,
  `bank_account_name` VARCHAR(100)     NOT NULL,
  `note`              TEXT             DEFAULT NULL            COMMENT 'Member note',
  `status`            ENUM('pending','processing','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_id`          INT UNSIGNED     DEFAULT NULL,
  `admin_note`        TEXT             DEFAULT NULL            COMMENT 'Admin reason / note',
  `processed_at`      DATETIME         DEFAULT NULL,
  `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_wdr_user`   (`user_id`),
  INDEX `idx_wdr_status` (`status`),
  INDEX `idx_wdr_no`     (`wdr_no`),
  CONSTRAINT `fk_wdr_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wdr_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Sample withdrawal requests (for demo merchant user id=2)
-- ============================================================
INSERT INTO `withdrawals` (`wdr_no`,`user_id`,`amount`,`fee`,`net_amount`,`bank_name`,`bank_account_no`,`bank_account_name`,`note`,`status`,`admin_id`,`processed_at`,`created_at`) VALUES
  ('WDR-A1B2C3D4', 2, 500000.00,  6500.00, 493500.00, 'BCA',   '1234567890', 'Demo Merchant', 'Penarikan pertama',    'approved', 1, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 3 DAY),
  ('WDR-E5F6G7H8', 2, 1000000.00, 6500.00, 993500.00, 'BNI',   '9876543210', 'Demo Merchant', NULL,                   'pending',  NULL, NULL,                 NOW() - INTERVAL 1 HOUR),
  ('WDR-I9J0K1L2', 2, 250000.00,  3750.00, 246250.00, 'GoPay', '081234567890','Demo Merchant', 'Tarik ke GoPay',     'rejected', 1, NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 6 DAY);

-- Update wallets locked amount to match pending sample data
UPDATE `wallets` SET `locked` = 1000000.00 WHERE `user_id` = 2;
-- Also update balance to reflect locked amount is held
UPDATE `wallets` SET `balance` = `balance` - 1000000.00 WHERE `user_id` = 2;
