-- Migration: admin-controlled email domain + country filters for signup.
--   * settings.signup_email_blocklist    — comma-separated email domains that
--                                          get rejected outright.
--   * settings.signup_email_allowlist    — comma-separated email domains;
--                                          when non-empty, ONLY these domains
--                                          are accepted (allowlist wins over
--                                          blocklist).
--   * settings.signup_country_blocklist  — comma-separated two-letter ISO
--                                          country codes to reject.
--
-- All three default to NULL (= no restriction) so upgrading deployments keep
-- the current permissive behaviour until an admin opts in. Kept as TEXT
-- rather than a joined table because the values are edited as free-form
-- comma-separated lists in the admin panel and re-parsed on each check —
-- avoids a schema migration per new domain / country.

START TRANSACTION;

ALTER TABLE `settings`
  ADD COLUMN `signup_email_blocklist`   TEXT NULL AFTER `default_limit_remain`,
  ADD COLUMN `signup_email_allowlist`   TEXT NULL AFTER `signup_email_blocklist`,
  ADD COLUMN `signup_country_blocklist` TEXT NULL AFTER `signup_email_allowlist`;

COMMIT;
