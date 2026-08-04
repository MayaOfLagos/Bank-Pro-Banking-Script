-- Migration: admin-editable legal documents on the singleton settings row,
-- plus a ledger table for on-disk database backups produced from the admin
-- panel. Both features are additive — leaving the columns / table absent
-- degrades gracefully to "no legal copy yet" and "no backups recorded".

START TRANSACTION;

ALTER TABLE `settings`
  ADD COLUMN `terms_of_service_html` TEXT NULL DEFAULT NULL AFTER `signup_default_status`,
  ADD COLUMN `privacy_policy_html`   TEXT NULL DEFAULT NULL AFTER `terms_of_service_html`;

CREATE TABLE IF NOT EXISTS `db_backups` (
  `id`         int NOT NULL AUTO_INCREMENT,
  `filename`   varchar(120) NOT NULL,
  `size_bytes` bigint NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filename` (`filename`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
