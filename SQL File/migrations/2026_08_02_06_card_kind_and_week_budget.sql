-- Migration: extras used by the redesigned customer dashboard.
--   * card.card_kind  — visual hint (debit/credit) so the carousel can pick
--                        the right gradient. Free-form varchar so future
--                        kinds can be added without a schema change.
--   * users.week_budget_limit — per-user weekly spend budget shown by the
--                        BudgetWidget. Default 500 so existing users have a
--                        sensible starting cap. Nullable so a real "no
--                        budget" state can be represented later.

START TRANSACTION;

ALTER TABLE `card`
  ADD COLUMN `card_kind` varchar(20) NOT NULL DEFAULT 'debit' AFTER `card_status`;

ALTER TABLE `users`
  ADD COLUMN `week_budget_limit` decimal(15,2) DEFAULT 500.00 AFTER `limit_remain`;

COMMIT;
