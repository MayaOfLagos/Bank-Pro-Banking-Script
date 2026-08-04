-- Migration: admin-controlled starting balances/limits for new signups.
--   * settings.default_balance       — value written into users.acct_balance
--                                       for signups that come through the
--                                       public /register endpoint.
--   * settings.default_avail_balance — value written into users.avail_balance.
--   * settings.default_acct_limit    — value written into users.acct_limit.
--   * settings.default_limit_remain  — value written into users.limit_remain.
--
-- All four default to 0.00 to preserve the current hardcoded behaviour for
-- any deployment that upgrades but doesn't touch the admin panel. Capped
-- at 10,000,000 in the admin form (and rechecked on the API side) to stop
-- typo disasters (an admin accidentally shipping every new user a million
-- dollars).

START TRANSACTION;

ALTER TABLE `settings`
  ADD COLUMN `default_balance`       DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `signup_default_status`,
  ADD COLUMN `default_avail_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `default_balance`,
  ADD COLUMN `default_acct_limit`    DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `default_avail_balance`,
  ADD COLUMN `default_limit_remain`  DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `default_acct_limit`;

COMMIT;
