-- Migration: change domestic_transfer.acct_number from int(15) to varchar(50)
-- so leading zeros in account numbers are preserved.
-- Safe to run once; MODIFY COLUMN is idempotent when the target type matches.
-- Note: MySQL will convert existing integer values to their string form (no leading
-- zeros can be recovered for historical rows — this only preserves them going forward).

START TRANSACTION;

ALTER TABLE `domestic_transfer`
  MODIFY `acct_number` varchar(50) COLLATE utf8_unicode_ci NOT NULL;

COMMIT;
