-- Migration: audit table for password-reset request throttling.
-- Records every reset request (whether or not the email is registered) so we
-- can rate-limit without leaking account existence via response timing.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `password_reset_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_time` (`email`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

COMMIT;
