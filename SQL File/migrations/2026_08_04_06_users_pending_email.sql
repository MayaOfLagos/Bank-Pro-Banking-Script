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
-- Runs cleanly against MySQL 5.7 / MariaDB 10.3+ (cPanel-friendly). Uses
-- IF NOT EXISTS on ALTER so re-running is a no-op on hosts that were
-- migrated manually.
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `pending_email` VARCHAR(200) DEFAULT NULL AFTER `acct_email`,
    ADD COLUMN IF NOT EXISTS `pending_email_otp` VARCHAR(6) DEFAULT NULL AFTER `pending_email`,
    ADD COLUMN IF NOT EXISTS `pending_email_expires` DATETIME DEFAULT NULL AFTER `pending_email_otp`,
    ADD COLUMN IF NOT EXISTS `pending_email_attempts` INT NOT NULL DEFAULT 0 AFTER `pending_email_expires`;
