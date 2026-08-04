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

START TRANSACTION;

ALTER TABLE `settings`
  ADD COLUMN `session_idle_minutes` INT NOT NULL DEFAULT 30 AFTER `signup_country_blocklist`;

COMMIT;
