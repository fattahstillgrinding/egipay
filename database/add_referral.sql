-- ============================================================
-- Referral Code System
-- ============================================================

-- 1. Add referral_code column to users
ALTER TABLE users
  ADD COLUMN referral_code VARCHAR(30) NULL UNIQUE AFTER member_code,
  ADD COLUMN referred_by   INT UNSIGNED NULL AFTER referral_code,
  ADD INDEX idx_referral_code (referral_code),
  ADD CONSTRAINT fk_referred_by FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL;

-- 2. Add referred_by to registration_payments (track at signup time)
ALTER TABLE registration_payments
  ADD COLUMN referral_code VARCHAR(30) NULL AFTER plan,
  ADD COLUMN referred_by   INT UNSIGNED NULL AFTER referral_code;

-- 3. Referral stats table (counts & rewards)
CREATE TABLE IF NOT EXISTS referrals (
  id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  referrer_id  INT UNSIGNED    NOT NULL,
  referred_id  INT UNSIGNED    NOT NULL,
  referral_code VARCHAR(30)    NOT NULL,
  status       ENUM('pending','rewarded') NOT NULL DEFAULT 'pending',
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_referred (referred_id),
  KEY idx_referrer (referrer_id),
  CONSTRAINT fk_ref_referrer FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ref_referred FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
