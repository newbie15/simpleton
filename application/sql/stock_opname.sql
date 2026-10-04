CREATE TABLE IF NOT EXISTS `stock_opnames` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `opname_number` varchar(32) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_opnames_number` (`opname_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `stock_opname_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_opname_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `barcode` varchar(64) NOT NULL,
  `product_name` varchar(64) NOT NULL,
  `system_qty` int(11) NOT NULL,
  `counted_qty` int(11) DEFAULT NULL,
  `unit_cost` decimal(15,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_opname_product` (`stock_opname_id`, `product_id`),
  KEY `idx_stock_opname_items_product` (`product_id`),
  CONSTRAINT `fk_stock_opname_items_opname`
    FOREIGN KEY (`stock_opname_id`) REFERENCES `stock_opnames` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `inventory_costs` (
  `product_id` int(11) NOT NULL,
  `average_cost` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
