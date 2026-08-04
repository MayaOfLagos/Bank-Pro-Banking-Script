-- Migration: admin-controlled signup behaviour.
--   * settings.registration_enabled    — when 0, the /register page and
--                                        the /api/auth/register.php POST
--                                        both refuse. Defaults to 0 so
--                                        the migration cannot silently
--                                        open signup on a live site;
--                                        open it in admin > settings.
--   * settings.signup_default_status  — value written into users.acct_status
--                                        for new signups. Default 'hold' so
--                                        existing behaviour is preserved
--                                        for anyone who doesn't touch it.
--   * register_attempts table         — per-IP rate limit for the register
--                                        endpoint (mirror of the pattern
--                                        password_reset_attempts uses).

START TRANSACTION;

ALTER TABLE `settings`
  ADD COLUMN `registration_enabled` int NOT NULL DEFAULT 0 AFTER `bank_deposit`,
  ADD COLUMN `signup_default_status` varchar(50) NOT NULL DEFAULT 'hold' AFTER `registration_enabled`;

CREATE TABLE IF NOT EXISTS `register_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ip_attempted` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
