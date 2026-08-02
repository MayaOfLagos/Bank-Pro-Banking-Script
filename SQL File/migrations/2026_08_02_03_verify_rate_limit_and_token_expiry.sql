-- Migration: DATETIME-based reset token expiry + per-user verification rate limiting.
--   * resettokenexp becomes DATETIME so we can enforce a 30-minute window instead of the
--     day-granularity string previously stored.
--   * verify_attempts / verify_locked_until back a shared attempt counter that locks
--     account verification (login PIN + transfer OTP/COT/IMF/TAX) after N failures.
--
-- Safe to run once. Existing resettokenexp values (Y-m-d strings) get converted to
-- DATETIME by MySQL (parsed at midnight of that day); already-issued reset links
-- will simply expire at midnight, which matches the previous behaviour.

START TRANSACTION;

ALTER TABLE `users`
  MODIFY `resettokenexp` datetime DEFAULT NULL,
  ADD COLUMN `verify_attempts` int(11) NOT NULL DEFAULT 0 AFTER `acct_tax`,
  ADD COLUMN `verify_locked_until` datetime DEFAULT NULL AFTER `verify_attempts`;

COMMIT;
