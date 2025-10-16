-- Create password_resets table for secure token storage
CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `token` (`token`),
  KEY `email` (`email`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Optional: Clean up expired tokens (run this periodically)
-- DELETE FROM password_resets WHERE expires_at < NOW();
