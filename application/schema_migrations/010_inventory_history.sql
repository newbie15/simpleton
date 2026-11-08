CREATE TABLE IF NOT EXISTS `inventory_cost_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `barcode` varchar(64) NOT NULL,
  `unit_cost` decimal(15,4) NOT NULL,
  `previous_average_cost` decimal(15,4) DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `created_by` varchar(32) NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_cost_history_product_date` (`product_id`, `recorded_at`),
  KEY `idx_inventory_cost_history_barcode` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SET @average_cost_after_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'goods_receipt_items'
    AND COLUMN_NAME = 'average_cost_after'
);
SET @inventory_cost_history_sql = IF(
  @average_cost_after_exists = 0,
  'ALTER TABLE `goods_receipt_items` ADD COLUMN `average_cost_after` decimal(15,4) DEFAULT NULL AFTER `subtotal`',
  'SELECT 1'
);
PREPARE inventory_cost_history_stmt FROM @inventory_cost_history_sql;
EXECUTE inventory_cost_history_stmt;
DEALLOCATE PREPARE inventory_cost_history_stmt;
