-- ============================================================
-- EgiPay – Add Superadmin Role
-- Run after all previous migrations
-- ============================================================

USE `egipay`;

-- 1. Extend role ENUM to include superadmin
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('superadmin','admin','merchant','customer')
    NOT NULL DEFAULT 'merchant';

-- 2. Seed default superadmin account (password: SuperAdmin@123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `plan`, `status`, `avatar`, `email_verified_at`)
VALUES (
  'Super Admin EgiPay',
  'superadmin@egipay.com',
  '$2y$10$Xah3UIuf.RGGOdmP2MClBO2v1Bt.3JQwwv8kvGbUQBxEjiOmZgeoK',
  '+62800000001',
  'superadmin',
  'enterprise',
  'active',
  'SA',
  NOW()
);

-- 3. Seed wallet for superadmin
INSERT INTO `wallets` (`user_id`, `balance`, `total_in`, `total_out`)
SELECT id, 0.00, 0.00, 0.00 FROM `users` WHERE email = 'superadmin@egipay.com';

-- NOTE: The password hash above is for 'SuperAdmin@123'
-- If it doesn't work, run: SELECT password_hash('SuperAdmin@123', PASSWORD_BCRYPT);
-- and update manually.
