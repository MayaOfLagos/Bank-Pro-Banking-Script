-- Migration: admin-triggered "sign this user out of everything" hook.
--   * users.sessions_invalidated_at — when set, every session whose
--                                     session_started_at is earlier
--                                     than this timestamp gets torn
--                                     down on the next authenticated
--                                     API call (api/user/_bootstrap.php
--                                     enforces this alongside the
--                                     existing pw_snapshot check).
--                                     Never rewritten to NULL — a fresh
--                                     login just stamps a newer
--                                     session_started_at that outranks
--                                     the invalidation cutoff.

START TRANSACTION;

ALTER TABLE `users`
  ADD COLUMN `sessions_invalidated_at` DATETIME NULL DEFAULT NULL AFTER `password_changed_at`;

COMMIT;
