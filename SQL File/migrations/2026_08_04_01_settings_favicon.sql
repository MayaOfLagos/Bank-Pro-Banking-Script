-- Migration: brand favicon field.
--   * settings.favicon — filename of the admin-uploaded favicon in
--                        assets/settings/. Nullable so existing accounts
--                        keep falling back to the legacy hardcoded path
--                        until an admin uploads one.

START TRANSACTION;

ALTER TABLE `settings`
  ADD COLUMN `favicon` varchar(255) DEFAULT NULL AFTER `image`;

COMMIT;
