-- ============================================================
-- Update Member Code from SMU-xxxx to MU-000000xxx (9 digits)
-- ============================================================

-- Update existing member codes
UPDATE users 
SET member_code = CONCAT('MU-', LPAD(SUBSTRING(member_code, 5), 9, '0'))
WHERE member_code LIKE 'SMU-%' AND member_code IS NOT NULL;

-- Verify the update
SELECT id, name, email, member_code 
FROM users 
WHERE member_code IS NOT NULL 
ORDER BY id;
