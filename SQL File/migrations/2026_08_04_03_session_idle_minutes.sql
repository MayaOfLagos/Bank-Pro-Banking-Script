-- Migration: admin-configurable idle-session timeout.
--   * settings.session_idle_minutes — how long a session may sit idle
--                                     before session.php tears it down
--                                     and forces re-auth. Applies to
--                                     BOTH the customer Vue portal
--                                     (which shares session.php via
--                                     the /api/* endpoints) and the
--                                     admin panel. Default 30 minutes
--                                     preserves prior behaviour close
--                                     enough (old session.php was 600s
--                                     for /api/*, 1100s for admin).
--
-- No AFTER clause: this used to read `AFTER signup_country_blocklist`,
-- but that column is created by 2026_08_04_04_signup_email_country_filters,
-- which sorts later — so applying these files in filename order failed
-- here. Column position is cosmetic and nothing reads columns
-- positionally, so the placement is simply dropped. Do not re-add it.

START TRANSACTION;

ALTER TABLE `settings`
  ADD COLUMN `session_idle_minutes` INT NOT NULL DEFAULT 30;

COMMIT;
