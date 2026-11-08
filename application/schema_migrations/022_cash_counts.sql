-- Tabel riwayat opname dan hitung fisik laci kasir POS
CREATE TABLE IF NOT EXISTS `cashier_cash_counts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `count_date` date NOT NULL,
  `count_time` datetime NOT NULL,
  `user_id` varchar(32) NOT NULL,
  `cashier_name` varchar(128) NOT NULL,
  `initial_float` decimal(15,2) NOT NULL DEFAULT '0.00',
  `system_target` decimal(15,2) NOT NULL DEFAULT '0.00',
  `counted_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `variance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `target_mode` varchar(32) NOT NULL DEFAULT 'laci',
  `denominations_json` text DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cashier_count_date` (`count_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
