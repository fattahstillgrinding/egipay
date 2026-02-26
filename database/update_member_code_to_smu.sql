-- ============================================================
-- EgiPay Migration: Rename member_code prefix to SMU-
-- Converts existing EGP- and EGI- codes to SMU-
-- Run once against the `egipay` database
-- ============================================================

USE `egipay`;

-- Update EGP-XXXX → SMU-XXXX
UPDATE `users`
SET `member_code` = CONCAT('SMU-', SUBSTR(`member_code`, 5))
WHERE `member_code` LIKE 'EGP-%';

-- Update EGI-XXXX → SMU-XXXX
UPDATE `users`
SET `member_code` = CONCAT('SMU-', SUBSTR(`member_code`, 5))
WHERE `member_code` LIKE 'EGI-%';

-- Verify result
SELECT id, name, member_code FROM `users` ORDER BY id;
