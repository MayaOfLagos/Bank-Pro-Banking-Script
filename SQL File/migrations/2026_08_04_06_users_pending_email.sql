-- Pending email change workflow: user requests a new address, an OTP is
-- sent to that new address, and only on successful verification does
-- acct_email get promoted. The four columns below hold the pending state.
--
--   pending_email          — normalised new address (lowercased, trimmed)
--   pending_email_otp      — 6-digit code, plain text (short-lived, single use)
--   pending_email_expires  — MySQL NOW() + 15 min; requests past this die
--   pending_email_attempts — verification tries against the current OTP;
--                            5 fails invalidate the request and force a resend
--
-- Written with a PREPARE/EXECUTE guard around each ADD COLUMN so this
-- file is safe to re-run and portable across MySQL 5.7 / 8+ and MariaDB
-- (older MySQL does not support `ADD COLUMN IF NOT EXISTS`).

SET @db := DATABASE();

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'pending_email') = 0,
    'ALTER TABLE `users` ADD COLUMN `pending_email` VARCHAR(200) DEFAULT NULL AFTER `acct_email`',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'pending_email_otp') = 0,
    'ALTER TABLE `users` ADD COLUMN `pending_email_otp` VARCHAR(6) DEFAULT NULL AFTER `pending_email`',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'pending_email_expires') = 0,
    'ALTER TABLE `users` ADD COLUMN `pending_email_expires` DATETIME DEFAULT NULL AFTER `pending_email_otp`',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'pending_email_attempts') = 0,
    'ALTER TABLE `users` ADD COLUMN `pending_email_attempts` INT NOT NULL DEFAULT 0 AFTER `pending_email_expires`',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
