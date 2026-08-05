-- Migration: in-app notifications (customer drawer + admin team inbox).
--   * notifications — one row per delivered message. `audience` splits the two
--                     inboxes: 'user' rows carry a user_id and are only ever
--                     read by that customer; 'admin' rows have user_id NULL and
--                     are read by the whole admin team.
--   * Deliberately no foreign key to users.id — same reasoning as
--                     admin_audit_log: the historical record must survive if a
--                     user row is ever deleted, and shared hosting gives us no
--                     good story for FK-cascade surprises. Scoping is enforced
--                     in the query layer (every read/write binds :uid).
--   * body is markdown SOURCE, never HTML. The client sanitises and renders;
--                     storing HTML here would make the table an XSS vector the
--                     moment an admin composes a broadcast.
--   * meta is a JSON blob (TEXT) — MySQL 5.7's JSON type is fine but we stick
--                     with TEXT for maximum shared-hosting parity.
--
-- Scheduling needs no cron. This is the whole trick, and it is why admin
-- "schedule for later" works on cPanel where no reliable cron exists: a row
-- whose scheduled_at is in the future simply is not visible yet. Every read
-- path — feed, pagination, unread counts, mark-as-read — filters on
-- (scheduled_at IS NULL OR scheduled_at <= NOW()), so the row materialises on
-- its own the first time someone loads the feed after it comes due. Nothing
-- has to run on a timer. That predicate lives in exactly one place in PHP:
-- notify_visible_sql() in include/notifications.php. Do not inline it.
--
-- read_at on an audience='admin' row is SHARED ACROSS ALL ADMINS. It is a team
-- inbox, not per-admin state: when one admin reads an alert it is read for
-- everybody. Do not later assume per-admin read tracking from this column —
-- that would need a separate notification_reads(notification_id, admin_id)
-- join table, which is a deliberate non-goal here.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `audience` VARCHAR(10) NOT NULL DEFAULT 'user',
  `user_id` INT DEFAULT NULL,
  `event` VARCHAR(64) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT DEFAULT NULL,
  `severity` VARCHAR(10) NOT NULL DEFAULT 'info',
  `link` VARCHAR(255) DEFAULT NULL,
  `meta` TEXT DEFAULT NULL,
  `source` VARCHAR(10) NOT NULL DEFAULT 'system',
  `created_by_admin_id` INT DEFAULT NULL,
  `scheduled_at` DATETIME DEFAULT NULL,
  `read_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_feed` (`audience`, `user_id`, `scheduled_at`, `id`),
  KEY `idx_user_unread` (`audience`, `user_id`, `read_at`),
  KEY `idx_event_created` (`event`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
