-- Rate-limit login alert emails: track the last time a login-success
-- notification was sent so the PIN-verify handler can skip subsequent
-- fires within the same 10-minute window.
--
-- NULL means no email has ever been sent for this account, which the
-- gate treats the same as "10+ minutes ago" and allows the first send.

START TRANSACTION;

ALTER TABLE `users`
  ADD COLUMN `last_login_email_at` DATETIME NULL DEFAULT NULL
  AFTER `sessions_invalidated_at`;

COMMIT;
