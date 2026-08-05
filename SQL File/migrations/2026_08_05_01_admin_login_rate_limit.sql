-- Migration: brute-force protection for the admin login.
--
-- The customer login has been rate-limited since
-- 2026_08_02_03_verify_rate_limit_and_token_expiry.sql — five failures then a
-- fifteen-minute lock, driven by users.verify_attempts / verify_locked_until
-- and enforced in api/_security.php. The admin login had no equivalent: the
-- most privileged credential in the application was unlimited-rate guessable,
-- and because nothing logged a failed admin login, the guessing was also
-- invisible.
--
-- These columns mirror the `users` pair exactly, so the same five-attempt /
-- fifteen-minute policy and the same code shape apply on both sides.
--
--   admin.verify_attempts     — consecutive failed password checks. Reset to 0
--                               on any successful login.
--   admin.verify_locked_until — NULL when unlocked; a future DATETIME while
--                               the account is locked out. Compared against
--                               NOW() in PHP, not in SQL, so the semantics
--                               match api/_security.php.
--
-- Both default to the unlocked state, so applying this migration cannot lock
-- an existing admin out. No seed data, consistent with every other migration
-- in this directory.
--
-- Written with the PREPARE/EXECUTE guard used by
-- 2026_08_04_06_users_pending_email.sql so the file is safe to re-run, is
-- detectable by the migration console's INFORMATION_SCHEMA probe, and works
-- on MySQL 5.7 (no `ADD COLUMN IF NOT EXISTS`) as well as MySQL 8 / MariaDB.
--
-- No AFTER clause: the `admin` table has no committed base DDL in this repo,
-- so naming a neighbouring column would make this file fail on installs whose
-- column order differs. Column position is cosmetic.

SET @db := DATABASE();

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'verify_attempts') = 0,
    'ALTER TABLE `admin` ADD COLUMN `verify_attempts` INT NOT NULL DEFAULT 0',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'verify_locked_until') = 0,
    'ALTER TABLE `admin` ADD COLUMN `verify_locked_until` DATETIME DEFAULT NULL',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
