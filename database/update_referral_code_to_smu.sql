-- ============================================================
-- Update Referral Code to SMU-XXXXXX format (6 random chars)
-- ============================================================

-- Update existing referral codes to new format
UPDATE users 
SET referral_code = CONCAT('SMU-', UPPER(SUBSTRING(MD5(CONCAT(id, referral_code, RAND())), 1, 6)))
WHERE referral_code IS NOT NULL;

-- Verify the update
SELECT id, name, email, member_code, referral_code 
FROM users 
WHERE referral_code IS NOT NULL 
ORDER BY id;
