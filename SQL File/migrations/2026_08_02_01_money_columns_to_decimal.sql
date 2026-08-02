-- Migration: convert float/double money columns to decimal(15,2)
-- Applies to existing installs. New installs get decimal via database.sql.
-- Safe to run once; MODIFY COLUMN is idempotent when the target type matches.
--
-- Note: MySQL converts existing float/double values to decimal(15,2) by rounding
-- to 2 decimal places. Values outside +/- 9,999,999,999,999.99 will fail.
-- Back up before running.

START TRANSACTION;

ALTER TABLE `card`
  MODIFY `card_limit`        decimal(15,2) NOT NULL DEFAULT 5000.00,
  MODIFY `card_limit_remain` decimal(15,2) NOT NULL DEFAULT 5000.00;

ALTER TABLE `deposit`
  MODIFY `amount` decimal(15,2) NOT NULL;

ALTER TABLE `domestic_transfer`
  MODIFY `amount` decimal(15,2) NOT NULL DEFAULT 0.00;

ALTER TABLE `loan`
  MODIFY `amount` decimal(15,2) DEFAULT 0.00;

ALTER TABLE `settings`
  MODIFY `trans_limit_min` decimal(15,2) DEFAULT NULL,
  MODIFY `trans_limit_max` decimal(15,2) DEFAULT NULL;

ALTER TABLE `temp_trans`
  MODIFY `amount` decimal(15,2) NOT NULL DEFAULT 0.00;

ALTER TABLE `transactions`
  MODIFY `amount` decimal(15,2) NOT NULL;

ALTER TABLE `users`
  MODIFY `acct_balance` decimal(15,2) DEFAULT 0.00,
  MODIFY `avail_balance` decimal(15,2) DEFAULT 0.00,
  MODIFY `loan_balance` decimal(15,2) DEFAULT 0.00,
  MODIFY `acct_limit`   decimal(15,2) DEFAULT NULL,
  MODIFY `limit_remain` decimal(15,2) DEFAULT NULL;

ALTER TABLE `wire_transfer`
  MODIFY `amount` decimal(15,2) NOT NULL DEFAULT 0.00;

ALTER TABLE `withdrawal`
  MODIFY `amount` decimal(15,2) NOT NULL;

COMMIT;
