-- Migration: per-user feature toggles surfaced in the admin panel.
--   * users.can_deposit       — allow crypto/bank deposit submissions.
--   * users.can_withdraw      — allow bank + crypto withdrawal submissions.
--   * users.can_request_card  — allow the customer to request a new card.
--
-- All three default to 1 (allowed) so existing users keep every feature they
-- had before. Columns are NOT NULL so PHP never sees an ambiguous NULL when
-- comparing against '1' — matches the existing `transfer` column convention.

START TRANSACTION;

ALTER TABLE `users`
  ADD COLUMN `can_deposit` int NOT NULL DEFAULT 1 AFTER `transfer`,
  ADD COLUMN `can_withdraw` int NOT NULL DEFAULT 1 AFTER `can_deposit`,
  ADD COLUMN `can_request_card` int NOT NULL DEFAULT 1 AFTER `can_withdraw`;

COMMIT;
