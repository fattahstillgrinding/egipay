-- ============================================================
-- EgiPay Database Schema
-- Version: 1.0.0
-- Engine: InnoDB | Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS `egipay`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `egipay`;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100)     NOT NULL,
  `email`        VARCHAR(150)     NOT NULL UNIQUE,
  `password`     VARCHAR(255)     NOT NULL,
  `phone`        VARCHAR(20)      DEFAULT NULL,
  `role`         ENUM('admin','merchant','customer') NOT NULL DEFAULT 'merchant',
  `plan`         ENUM('starter','business','enterprise') NOT NULL DEFAULT 'starter',
  `status`       ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `avatar`       VARCHAR(10)      DEFAULT NULL,
  `email_verified_at` DATETIME   DEFAULT NULL,
  `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: wallets
-- ============================================================
CREATE TABLE IF NOT EXISTS `wallets` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED     NOT NULL,
  `balance`      DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `locked`       DECIMAL(15,2)    NOT NULL DEFAULT 0.00  COMMENT 'Pending/held amount',
  `total_in`     DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `total_out`    DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wallet_user` (`user_id`),
  CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: payment_methods
-- ============================================================
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(60)   NOT NULL,
  `type`            ENUM('ewallet','bank_transfer','qris','credit_card','minimarket','paylater') NOT NULL,
  `icon_class`      VARCHAR(50)   DEFAULT 'bi bi-credit-card',
  `color`           VARCHAR(10)   DEFAULT '#6c63ff',
  `fee_percent`     DECIMAL(5,2)  NOT NULL DEFAULT 1.90,
  `fee_flat`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_amount`      DECIMAL(12,2) NOT NULL DEFAULT 1000.00,
  `max_amount`      DECIMAL(12,2) NOT NULL DEFAULT 50000000.00,
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order`      INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: transactions
-- ============================================================
CREATE TABLE IF NOT EXISTS `transactions` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `tx_id`           VARCHAR(30)      NOT NULL UNIQUE,
  `user_id`         INT UNSIGNED     NOT NULL,
  `payment_method_id` INT UNSIGNED   DEFAULT NULL,
  `type`            ENUM('payment','topup','withdrawal','refund') NOT NULL DEFAULT 'payment',
  `amount`          DECIMAL(15,2)    NOT NULL,
  `fee`             DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  `total`           DECIMAL(15,2)    NOT NULL,
  `currency`        CHAR(3)          NOT NULL DEFAULT 'IDR',
  `recipient`       VARCHAR(150)     DEFAULT NULL,
  `recipient_bank`  VARCHAR(100)     DEFAULT NULL,
  `note`            TEXT             DEFAULT NULL,
  `status`          ENUM('pending','success','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `snap_token`      VARCHAR(255)     DEFAULT NULL  COMMENT 'Midtrans snap token if used',
  `paid_at`         DATETIME         DEFAULT NULL,
  `expired_at`      DATETIME         DEFAULT NULL,
  `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tx_id`   (`tx_id`),
  INDEX `idx_user_tx` (`user_id`),
  INDEX `idx_status`  (`status`),
  INDEX `idx_created` (`created_at`),
  CONSTRAINT `fk_tx_user`   FOREIGN KEY (`user_id`)          REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tx_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: api_keys
-- ============================================================
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED  NOT NULL,
  `name`         VARCHAR(100)  NOT NULL DEFAULT 'Default Key',
  `key_type`     ENUM('live','sandbox') NOT NULL DEFAULT 'sandbox',
  `client_key`   VARCHAR(100)  NOT NULL UNIQUE,
  `server_key`   VARCHAR(100)  NOT NULL UNIQUE,
  `last_used_at` DATETIME      DEFAULT NULL,
  `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_apikeys_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: audit_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED     DEFAULT NULL,
  `action`     VARCHAR(100)     NOT NULL,
  `description` TEXT            DEFAULT NULL,
  `ip_address` VARCHAR(45)      DEFAULT NULL,
  `user_agent` VARCHAR(300)     DEFAULT NULL,
  `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_audit_user`   (`user_id`),
  INDEX `idx_audit_action` (`action`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED   NOT NULL,
  `type`       VARCHAR(50)    NOT NULL DEFAULT 'info',
  `title`      VARCHAR(150)   NOT NULL,
  `message`    TEXT           NOT NULL,
  `is_read`    TINYINT(1)     NOT NULL DEFAULT 0,
  `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Payment Methods
INSERT INTO `payment_methods` (`name`, `type`, `icon_class`, `color`, `fee_percent`, `fee_flat`, `min_amount`, `max_amount`, `sort_order`) VALUES
  ('QRIS',          'qris',          'bi bi-qr-code',           '#6c63ff', 0.70,  0,     1000,   10000000, 1),
  ('GoPay',         'ewallet',       'bi bi-phone',             '#00d4ff', 1.50,  0,     1000,   10000000, 2),
  ('OVO',           'ewallet',       'bi bi-phone-fill',        '#a78bfa', 1.50,  0,     1000,   10000000, 3),
  ('DANA',          'ewallet',       'bi bi-phone-vibrate',     '#10b981', 1.50,  0,     1000,   10000000, 4),
  ('ShopeePay',     'ewallet',       'bi bi-bag',               '#f59e0b', 1.50,  0,     1000,   10000000, 5),
  ('BCA',           'bank_transfer', 'bi bi-bank',              '#f59e0b', 0.00,  4000,  10000,  50000000, 6),
  ('Mandiri',       'bank_transfer', 'bi bi-bank2',             '#ef4444', 0.00,  4000,  10000,  50000000, 7),
  ('BNI',           'bank_transfer', 'bi bi-building',          '#3b82f6', 0.00,  4000,  10000,  50000000, 8),
  ('BRI',           'bank_transfer', 'bi bi-bank',              '#60a5fa', 0.00,  4000,  10000,  50000000, 9),
  ('Visa/Mastercard','credit_card',  'bi bi-credit-card',       '#f72585', 2.90,  0,     10000,  50000000, 10),
  ('Indomaret',     'minimarket',    'bi bi-shop',              '#6c63ff', 0.00,  2500,  10000,  5000000,  11),
  ('Alfamart',      'minimarket',    'bi bi-shop-window',       '#00d4ff', 0.00,  2500,  10000,  5000000,  12),
  ('Akulaku',       'paylater',      'bi bi-shield-check',      '#a78bfa', 2.50,  0,     50000,  20000000, 13),
  ('Kredivo',       'paylater',      'bi bi-credit-card-2-front','#f72585',2.50,  0,     50000,  20000000, 14);

-- Default Admin User (password: Admin@123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `plan`, `status`, `avatar`, `email_verified_at`) VALUES
  ('Admin EgiPay', 'admin@egipay.com', '$2y$10$C4SxKih5n1/8DWhSkohzg.j/S7oStOWkTPMQ8Gb2UimmqIWZRIilO', '+62811000001', 'admin', 'enterprise', 'active', 'AE', NOW()),
  ('Demo Merchant', 'merchant@demo.com', '$2y$10$C4SxKih5n1/8DWhSkohzg.j/S7oStOWkTPMQ8Gb2UimmqIWZRIilO', '+62812000002', 'merchant', 'business', 'active', 'DM', NOW());
-- Note: password for all default accounts is "Admin@123"

-- Wallets for seeded users
INSERT INTO `wallets` (`user_id`, `balance`, `total_in`, `total_out`) VALUES
  (1, 100000000.00, 100000000.00, 0.00),
  (2, 24821500.00,  120000000.00, 95178500.00);

-- Sample API Keys for demo merchant
INSERT INTO `api_keys` (`user_id`, `name`, `key_type`, `client_key`, `server_key`) VALUES
  (2, 'Sandbox Key',  'sandbox', 'SB-Mid-client-xxxxxxxxxxxxxxxx', 'SB-Mid-server-xxxxxxxxxxxxxxxx'),
  (2, 'Production Key','live',   'Mid-client-xxxxxxxxxxxxxxxx',    'Mid-server-xxxxxxxxxxxxxxxx');

-- Sample Transactions for demo merchant
INSERT INTO `transactions` (`tx_id`,`user_id`,`payment_method_id`,`type`,`amount`,`fee`,`total`,`recipient`,`note`,`status`,`paid_at`,`created_at`) VALUES
  ('TXN-8821AA', 2, 2, 'payment', 250000.00,  3750.00,  253750.00,  'GoPay: 08123456789',   'Belanja online',       'success', NOW() - INTERVAL 1 HOUR,  NOW() - INTERVAL 1 HOUR),
  ('TXN-8820BB', 2, 1, 'payment', 1500000.00, 10500.00, 1510500.00, 'Toko ABC',              'Pembelian produk',     'success', NOW() - INTERVAL 3 HOUR,  NOW() - INTERVAL 3 HOUR),
  ('TXN-8819CC', 2, 7, 'payment', 750000.00,  4000.00,  754000.00,  'Acc: 1234567890',       'Pembayaran jasa',      'pending', NULL,                      NOW() - INTERVAL 1 DAY),
  ('TXN-8818DD', 2, 10,'payment', 3200000.00, 92800.00, 3292800.00, 'PT Mitra Jaya',         'Invoice #INV-001',     'success', NOW() - INTERVAL 1 DAY,   NOW() - INTERVAL 1 DAY),
  ('TXN-8817EE', 2, 3, 'payment', 85000.00,   1275.00,  86275.00,   'OVO: 08987654321',      'Bayar tagihan',        'failed',  NULL,                      NOW() - INTERVAL 2 DAY),
  ('TXN-8816FF', 2, 4, 'payment', 420000.00,  6300.00,  426300.00,  'Ahmad Shop',            'Pembelian bahan baku', 'success', NOW() - INTERVAL 2 DAY,   NOW() - INTERVAL 2 DAY),
  ('TXN-8815GG', 2, 6, 'payment', 1100000.00, 4000.00,  1104000.00, 'Acc: 0987654321',       'Gaji freelancer',      'success', NOW() - INTERVAL 3 DAY,   NOW() - INTERVAL 3 DAY),
  ('TXN-8814HH', 2, 11,'payment', 200000.00,  2500.00,  202500.00,  'Kode: 123456789',       'Tagihan listrik',      'pending', NULL,                      NOW() - INTERVAL 3 DAY),
  ('TXN-8813II', 2, 1, 'payment', 500000.00,  3500.00,  503500.00,  'Toko Online',           'Flash sale',           'success', NOW() - INTERVAL 5 DAY,   NOW() - INTERVAL 5 DAY),
  ('TXN-8812JJ', 2, 2, 'topup',   2000000.00, 0.00,     2000000.00, 'EgiPay Topup',          'Top up saldo',         'success', NOW() - INTERVAL 6 DAY,   NOW() - INTERVAL 6 DAY);

-- Sample notifications
INSERT INTO `notifications` (`user_id`, `type`, `title`, `message`, `is_read`) VALUES
  (2, 'success', 'Pembayaran Berhasil', 'Transaksi TXN-8821AA sebesar Rp 250.000 berhasil diproses.', 0),
  (2, 'info',    'API Key Baru',        'API Key sandbox Anda telah diaktifkan. Mulai testing sekarang!', 0),
  (2, 'warning', 'Verifikasi KYC',      'Lengkapi verifikasi KYC Anda untuk meningkatkan limit transaksi.', 1);
