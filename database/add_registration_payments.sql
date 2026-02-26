-- ============================================================
-- EgiPay Migration: Registration Payments
-- Run this once against the `egipay` database
-- ============================================================

USE `egipay`;

CREATE TABLE IF NOT EXISTS `registration_payments` (
  `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `inv_no`        VARCHAR(30)      NOT NULL UNIQUE,
  `token`         VARCHAR(64)      NOT NULL UNIQUE,
  `name`          VARCHAR(100)     NOT NULL,
  `email`         VARCHAR(150)     NOT NULL,
  `phone`         VARCHAR(20)      DEFAULT NULL,
  `password_hash` VARCHAR(255)     NOT NULL,
  `plan`          ENUM('starter','business','enterprise') NOT NULL DEFAULT 'starter',
  `amount`        DECIMAL(10,2)    NOT NULL DEFAULT 12000.00,
  `status`        ENUM('pending','paid','expired') NOT NULL DEFAULT 'pending',
  `payment_method` VARCHAR(50)     DEFAULT NULL,
  `expires_at`    DATETIME         NOT NULL,
  `paid_at`       DATETIME         DEFAULT NULL,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_reg_email`  (`email`),
  INDEX `idx_reg_token`  (`token`),
  INDEX `idx_reg_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
