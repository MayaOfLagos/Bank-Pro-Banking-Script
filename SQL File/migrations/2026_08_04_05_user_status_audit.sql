-- Migration: audit trail for admin status changes on user accounts.
--   * users.acct_status_reason      — free-form note the admin enters when
--                                     changing acct_status (e.g. "flagged
--                                     for fraudulent chargebacks 2026-08-04").
--   * users.acct_status_changed_at  — timestamp of the most recent
--                                     acct_status update. Written by the
--                                     admin panel whenever status is saved.
--
-- Both nullable so existing rows migrate cleanly. Future work: convert into
-- a full history table if audit requirements grow (each status change gets
-- its own row); for now a single "last change" pair covers the common
-- "why is this account suspended?" question support ops needs to answer.

START TRANSACTION;

ALTER TABLE `users`
  ADD COLUMN `acct_status_reason`     TEXT     NULL AFTER `acct_status`,
  ADD COLUMN `acct_status_changed_at` DATETIME NULL AFTER `acct_status_reason`;

COMMIT;
