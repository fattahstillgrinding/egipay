-- ============================================================
-- EgiPay Migration: Ebook Orders
-- Jalankan sekali terhadap database `egipay`
-- ============================================================

USE `egipay`;

CREATE TABLE IF NOT EXISTS `ebook_orders` (
  `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `inv_no`         VARCHAR(40)      NOT NULL UNIQUE,
  `token`          VARCHAR(64)      NOT NULL UNIQUE,
  `user_id`        INT UNSIGNED     NOT NULL,
  `ebook_id`       INT UNSIGNED     NOT NULL,
  `ebook_title`    VARCHAR(200)     NOT NULL,
  `amount`         DECIMAL(10,2)    NOT NULL,
  `status`         ENUM('pending','paid','expired') NOT NULL DEFAULT 'pending',
  `payment_method` VARCHAR(50)      DEFAULT NULL,
  `expires_at`     DATETIME         NOT NULL,
  `paid_at`        DATETIME         DEFAULT NULL,
  `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ebook_order_user`   (`user_id`),
  INDEX `idx_ebook_order_token`  (`token`),
  INDEX `idx_ebook_order_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
