-- Migration: login-time IP allowlist / blocklist.
--   * settings.login_ip_allowlist — comma-separated list of IPv4/IPv6
--                                   addresses. When non-empty, only
--                                   these IPs may reach the login
--                                   endpoints (api/auth/login.php and
--                                   admin/login.php). Enforced BEFORE
--                                   credentials are checked so a banned
--                                   IP gets no signal about whether an
--                                   account exists.
--   * settings.login_ip_blocklist — comma-separated list of IPs that
--                                   are always rejected. Allowlist wins
--                                   if both are populated (an allowlisted
--                                   IP that also appears on the block
--                                   list is denied — allowlist entries
--                                   are a "who may enter", blocklist
--                                   entries are "who is denied".)
-- Both lists are stored as free text; the PHP layer parses and
-- validates each entry with filter_var(..., FILTER_VALIDATE_IP) so
-- garbage rows quietly no-op instead of ever matching a real client.
--
-- The first column used to be placed `AFTER session_idle_minutes`, which
-- chained this file onto a placement that itself failed. Dropped for the
-- same reason: column position is cosmetic. The second AFTER is kept
-- because it references a column added earlier in this same statement,
-- which is always valid, and it keeps the two lists adjacent.

START TRANSACTION;

ALTER TABLE `settings`
  ADD COLUMN `login_ip_allowlist` TEXT NULL DEFAULT NULL,
  ADD COLUMN `login_ip_blocklist` TEXT NULL DEFAULT NULL AFTER `login_ip_allowlist`;

COMMIT;
