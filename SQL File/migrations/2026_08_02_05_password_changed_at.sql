-- Migration: users.password_changed_at column for session invalidation on
-- password change. Sessions store a snapshot of the current time at login;
-- if password_changed_at later exceeds that snapshot, the session is killed.

START TRANSACTION;

ALTER TABLE `users`
  ADD COLUMN `password_changed_at` datetime DEFAULT NULL AFTER `acct_password`;

COMMIT;
