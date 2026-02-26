-- ============================================================
-- EgiPay – Dompet Insentif (Incentive Wallet)
-- Run this after egipay.sql has been imported
-- ============================================================

USE `egipay`;

-- ============================================================
-- Table: incentive_wallets  (saldo insentif per user)
-- ============================================================
CREATE TABLE IF NOT EXISTS `incentive_wallets` (
  `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED     NOT NULL,
  `balance`           DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `locked`            DECIMAL(15,2)    NOT NULL DEFAULT 0.00  COMMENT 'Held for pending withdrawal',
  `total_received`    DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `total_transferred` DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `total_withdrawn`   DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_incwallet_user` (`user_id`),
  CONSTRAINT `fk_incwallet_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: incentive_transfers  (transfer P2P antar username – GRATIS)
-- ============================================================
CREATE TABLE IF NOT EXISTS `incentive_transfers` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `ref_no`       VARCHAR(30)      NOT NULL UNIQUE,
  `from_user_id` INT UNSIGNED     NOT NULL,
  `to_user_id`   INT UNSIGNED     NOT NULL,
  `amount`       DECIMAL(15,2)    NOT NULL,
  `fee`          DECIMAL(10,2)    NOT NULL DEFAULT 0.00  COMMENT 'Selalu 0 – transfer gratis',
  `note`         TEXT             DEFAULT NULL,
  `status`       ENUM('success','failed') NOT NULL DEFAULT 'success',
  `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_inctrf_from`   (`from_user_id`),
  INDEX `idx_inctrf_to`     (`to_user_id`),
  INDEX `idx_inctrf_ref`    (`ref_no`),
  CONSTRAINT `fk_inctrf_from` FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inctrf_to`   FOREIGN KEY (`to_user_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: incentive_withdrawals  (cairkan insentif ke bank/ewallet)
-- ============================================================
CREATE TABLE IF NOT EXISTS `incentive_withdrawals` (
  `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `wdr_no`            VARCHAR(25)      NOT NULL UNIQUE,
  `user_id`           INT UNSIGNED     NOT NULL,
  `amount`            DECIMAL(15,2)    NOT NULL,
  `fee`               DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  `net_amount`        DECIMAL(15,2)    NOT NULL,
  `bank_name`         VARCHAR(60)      NOT NULL,
  `bank_account_no`   VARCHAR(40)      NOT NULL,
  `bank_account_name` VARCHAR(100)     NOT NULL,
  `note`              TEXT             DEFAULT NULL,
  `scheduled_date`    DATE             NOT NULL               COMMENT 'H (sebelum jam 12) atau H+1 (setelah jam 12)',
  `transfer_info`     VARCHAR(50)      DEFAULT NULL           COMMENT 'Hari ini / Besok',
  `status`            ENUM('pending','processing','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_id`          INT UNSIGNED     DEFAULT NULL,
  `admin_note`        TEXT             DEFAULT NULL,
  `processed_at`      DATETIME         DEFAULT NULL,
  `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_incwdr_user`   (`user_id`),
  INDEX `idx_incwdr_status` (`status`),
  INDEX `idx_incwdr_sched`  (`scheduled_date`),
  CONSTRAINT `fk_incwdr_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_incwdr_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Buat incentive_wallet otomatis untuk semua member aktif
-- ============================================================
INSERT IGNORE INTO `incentive_wallets` (`user_id`)
  SELECT `id` FROM `users` WHERE `role` = 'merchant' AND `status` = 'active';

-- ============================================================
-- Contoh data demo (opsional – untuk user id=2)
-- ============================================================
UPDATE `incentive_wallets` SET `balance` = 150000.00, `total_received` = 150000.00 WHERE `user_id` = 2;
