-- Migration: admin audit log.
--   * admin_audit_log — append-only trail of every admin write action taken
--                       through the admin panel (settings updates, user
--                       status/billing/transfer flips, card activate/hold,
--                       account creation, etc.). Deliberately no hard FK to
--                       admin.id so historical rows survive if an admin row
--                       is ever removed. admin_name is snapshotted at write
--                       time for the same reason — admins can be renamed.
--   * details is a JSON blob (TEXT) — MySQL 5.7's JSON type is fine but we
--                       stick with TEXT for maximum shared-hosting parity.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `admin_audit_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `admin_id` INT NOT NULL,
  `admin_name` VARCHAR(200) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(50) DEFAULT NULL,
  `target_id` VARCHAR(50) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_created` (`admin_id`, `created_at`),
  KEY `idx_target` (`target_type`, `target_id`),
  KEY `idx_action_created` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
