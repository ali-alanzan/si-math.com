-- جدول محاولات الموديلات
CREATE TABLE IF NOT EXISTS `wp_aqs_module_attempts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `module_id` bigint(20) NOT NULL,
  `course_id` bigint(20) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `correct_answers` int(11) NOT NULL,
  `time_taken` int(11) DEFAULT NULL COMMENT 'بالثواني',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `module_id` (`module_id`),
  KEY `course_id` (`course_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ملاحظة: إذا كان عندك جدول الشات من قبل، خليه زي ما هو
-- لكن لو مش موجود، استخدم الكود ده:

CREATE TABLE IF NOT EXISTS `wp_aqs_chat_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sender_id` bigint(20) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
