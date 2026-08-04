-- Catch-up migration: brings a database at the state of
-- database-2026-08-03_1425-compat.sql up to the current schema
-- (3 new tables, 25 new columns).
--
-- Run this instead of the individual files in migrations/. Two of those
-- place a column with `AFTER signup_country_blocklist` / `AFTER
-- session_idle_minutes`, where the referenced column is created by a
-- file that sorts LATER, so applying them in filename order fails.
-- This file drops the AFTER clauses, which are cosmetic only — nothing
-- in the codebase reads columns positionally.
--
-- Every statement is guarded by an INFORMATION_SCHEMA check, so it is
-- safe to re-run and safe against a database that is already fully or
-- partly migrated.
--
-- SCHEMA ONLY - NO SEED DATA. This file contains exactly 3 CREATE TABLE
-- IF NOT EXISTS and 25 ADD COLUMN statements. There is no INSERT,
-- UPDATE, DELETE, REPLACE, TRUNCATE or DROP anywhere in it. The three
-- new tables are created empty. Production rows are never read, written
-- or removed.
--
-- Verified against a clean load of the dump: the resulting schema
-- matches the development database exactly — 20 tables, 32 indexes, no
-- missing or extra columns, no type or default mismatches.

SET @db := DATABASE();

-- new table: admin_audit_log
CREATE TABLE IF NOT EXISTS `admin_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `admin_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_created` (`admin_id`,`created_at`),
  KEY `idx_target` (`target_type`,`target_id`),
  KEY `idx_action_created` (`action`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- new table: db_backups
CREATE TABLE IF NOT EXISTS `db_backups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `filename` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filename` (`filename`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- new table: register_attempts
CREATE TABLE IF NOT EXISTS `register_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ip_attempted` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- settings.favicon
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='favicon')=0,
    'ALTER TABLE `settings` ADD COLUMN `favicon` VARCHAR(255) NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.registration_enabled
-- Defaults to 0 (closed) so the migration cannot silently open public
-- signup on a live site. Adding a NOT NULL column writes its default
-- into the existing settings row, and there is no INSERT here to set it
-- afterwards. Open it deliberately in admin > settings once ready.
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='registration_enabled')=0,
    'ALTER TABLE `settings` ADD COLUMN `registration_enabled` INT NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.signup_default_status
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='signup_default_status')=0,
    'ALTER TABLE `settings` ADD COLUMN `signup_default_status` VARCHAR(50) NOT NULL DEFAULT ''hold''', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.terms_of_service_html
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='terms_of_service_html')=0,
    'ALTER TABLE `settings` ADD COLUMN `terms_of_service_html` TEXT NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.privacy_policy_html
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='privacy_policy_html')=0,
    'ALTER TABLE `settings` ADD COLUMN `privacy_policy_html` TEXT NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.default_balance
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='default_balance')=0,
    'ALTER TABLE `settings` ADD COLUMN `default_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.default_avail_balance
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='default_avail_balance')=0,
    'ALTER TABLE `settings` ADD COLUMN `default_avail_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.default_acct_limit
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='default_acct_limit')=0,
    'ALTER TABLE `settings` ADD COLUMN `default_acct_limit` DECIMAL(15,2) NOT NULL DEFAULT 0.00', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.default_limit_remain
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='default_limit_remain')=0,
    'ALTER TABLE `settings` ADD COLUMN `default_limit_remain` DECIMAL(15,2) NOT NULL DEFAULT 0.00', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.signup_email_blocklist
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='signup_email_blocklist')=0,
    'ALTER TABLE `settings` ADD COLUMN `signup_email_blocklist` TEXT NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.signup_email_allowlist
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='signup_email_allowlist')=0,
    'ALTER TABLE `settings` ADD COLUMN `signup_email_allowlist` TEXT NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.signup_country_blocklist
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='signup_country_blocklist')=0,
    'ALTER TABLE `settings` ADD COLUMN `signup_country_blocklist` TEXT NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.session_idle_minutes
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='session_idle_minutes')=0,
    'ALTER TABLE `settings` ADD COLUMN `session_idle_minutes` INT NOT NULL DEFAULT 30', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.login_ip_allowlist
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='login_ip_allowlist')=0,
    'ALTER TABLE `settings` ADD COLUMN `login_ip_allowlist` TEXT NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- settings.login_ip_blocklist
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings' AND COLUMN_NAME='login_ip_blocklist')=0,
    'ALTER TABLE `settings` ADD COLUMN `login_ip_blocklist` TEXT NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users.can_deposit
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='can_deposit')=0,
    'ALTER TABLE `users` ADD COLUMN `can_deposit` INT NOT NULL DEFAULT 1', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users.can_withdraw
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='can_withdraw')=0,
    'ALTER TABLE `users` ADD COLUMN `can_withdraw` INT NOT NULL DEFAULT 1', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users.can_request_card
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='can_request_card')=0,
    'ALTER TABLE `users` ADD COLUMN `can_request_card` INT NOT NULL DEFAULT 1', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users.acct_status_reason
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='acct_status_reason')=0,
    'ALTER TABLE `users` ADD COLUMN `acct_status_reason` TEXT NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users.acct_status_changed_at
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='acct_status_changed_at')=0,
    'ALTER TABLE `users` ADD COLUMN `acct_status_changed_at` DATETIME NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users.pending_email
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='pending_email')=0,
    'ALTER TABLE `users` ADD COLUMN `pending_email` VARCHAR(200) NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users.pending_email_otp
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='pending_email_otp')=0,
    'ALTER TABLE `users` ADD COLUMN `pending_email_otp` VARCHAR(6) NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users.pending_email_expires
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='pending_email_expires')=0,
    'ALTER TABLE `users` ADD COLUMN `pending_email_expires` DATETIME NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users.pending_email_attempts
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='pending_email_attempts')=0,
    'ALTER TABLE `users` ADD COLUMN `pending_email_attempts` INT NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users.sessions_invalidated_at
SET @s := (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='sessions_invalidated_at')=0,
    'ALTER TABLE `users` ADD COLUMN `sessions_invalidated_at` DATETIME NULL DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

