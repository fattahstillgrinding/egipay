-- ============================================================
-- EgiPay Migration: Add member_code to users
-- Run once against the `egipay` database
-- ============================================================

USE `egipay`;

ALTER TABLE `users`
  ADD COLUMN `member_code` VARCHAR(20) DEFAULT NULL UNIQUE
  AFTER `id`;

ALTER TABLE `users`
  ADD INDEX `idx_member_code` (`member_code`);

-- Backfill existing users: SMU-0001, SMU-0002, ...
UPDATE `users` SET `member_code` = CONCAT('SMU-', LPAD(id, 4, '0'))
WHERE `member_code` IS NULL;
