-- Resolve duplicate barcodes in stok before applying this schema update.
SET @stok_barcode_index_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'stok'
    AND INDEX_NAME = 'uq_stok_barcode'
);
SET @purchasing_sql = IF(
  @stok_barcode_index_exists = 0,
  'ALTER TABLE `stok` ADD UNIQUE KEY `uq_stok_barcode` (`barcode`)',
  'SELECT 1'
);
PREPARE purchasing_stmt FROM @purchasing_sql;
EXECUTE purchasing_stmt;
DEALLOCATE PREPARE purchasing_stmt;

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `address` text,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_suppliers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Add the supplier contact columns when upgrading an existing installation.
SET @supplier_phone_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'suppliers'
    AND COLUMN_NAME = 'phone'
);
SET @purchasing_sql = IF(
  @supplier_phone_exists = 0,
  'ALTER TABLE `suppliers` ADD COLUMN `phone` varchar(32) DEFAULT NULL AFTER `name`',
  'SELECT 1'
);
PREPARE purchasing_stmt FROM @purchasing_sql;
EXECUTE purchasing_stmt;
DEALLOCATE PREPARE purchasing_stmt;

SET @supplier_address_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'suppliers'
    AND COLUMN_NAME = 'address'
);
SET @purchasing_sql = IF(
  @supplier_address_exists = 0,
  'ALTER TABLE `suppliers` ADD COLUMN `address` text AFTER `phone`',
  'SELECT 1'
);
PREPARE purchasing_stmt FROM @purchasing_sql;
EXECUTE purchasing_stmt;
DEALLOCATE PREPARE purchasing_stmt;

CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(32) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `ordered_at` datetime NOT NULL,
  `printed_at` datetime DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase_orders_number` (`order_number`),
  KEY `idx_purchase_orders_supplier` (`supplier_id`),
  CONSTRAINT `fk_purchase_orders_supplier`
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Track the most recent PDF/print action on existing installations.
SET @purchase_orders_printed_at_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'purchase_orders'
    AND COLUMN_NAME = 'printed_at'
);
SET @purchasing_sql = IF(
  @purchase_orders_printed_at_exists = 0,
  'ALTER TABLE `purchase_orders` ADD COLUMN `printed_at` datetime DEFAULT NULL AFTER `ordered_at`',
  'SELECT 1'
);
PREPARE purchasing_stmt FROM @purchasing_sql;
EXECUTE purchasing_stmt;
DEALLOCATE PREPARE purchasing_stmt;

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `barcode` varchar(64) NOT NULL,
  `product_name` varchar(64) NOT NULL,
  `qty_ordered` int(11) NOT NULL,
  `purchase_price` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_po_items_order` (`purchase_order_id`),
  KEY `idx_po_items_product` (`product_id`),
  CONSTRAINT `fk_po_items_order`
    FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `fk_po_items_product`
    FOREIGN KEY (`product_id`) REFERENCES `stok` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `goods_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(32) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `supplier_document` varchar(64) DEFAULT NULL,
  `received_at` datetime NOT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_goods_receipts_number` (`receipt_number`),
  KEY `idx_goods_receipts_order` (`purchase_order_id`),
  CONSTRAINT `fk_goods_receipts_order`
    FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `goods_receipt_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_receipt_id` int(11) NOT NULL,
  `purchase_order_item_id` int(11) NOT NULL,
  `qty_received` int(11) NOT NULL,
  `unit_price` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_receipt_items_receipt` (`goods_receipt_id`),
  KEY `idx_receipt_items_po_item` (`purchase_order_item_id`),
  CONSTRAINT `fk_receipt_items_receipt`
    FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`),
  CONSTRAINT `fk_receipt_items_po_item`
    FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Add the actual unit purchase price to existing receipt details.
SET @goods_receipt_items_unit_price_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'goods_receipt_items'
    AND COLUMN_NAME = 'unit_price'
);
SET @purchasing_sql = IF(
  @goods_receipt_items_unit_price_exists = 0,
  'ALTER TABLE `goods_receipt_items` ADD COLUMN `unit_price` int(11) DEFAULT NULL AFTER `qty_received`',
  'SELECT 1'
);
PREPARE purchasing_stmt FROM @purchasing_sql;
EXECUTE purchasing_stmt;
DEALLOCATE PREPARE purchasing_stmt;

CREATE OR REPLACE VIEW `purchase_order_list` AS
SELECT
  po.`id`,
  po.`order_number`,
  po.`ordered_at`,
  supplier.`name` AS `supplier_name`,
  COALESCE((
    SELECT SUM(item.`qty_ordered`)
    FROM `purchase_order_items` item
    WHERE item.`purchase_order_id` = po.`id`
  ), 0) AS `ordered_qty`,
  COALESCE((
    SELECT SUM(receipt_item.`qty_received`)
    FROM `purchase_order_items` item
    JOIN `goods_receipt_items` receipt_item ON receipt_item.`purchase_order_item_id` = item.`id`
    WHERE item.`purchase_order_id` = po.`id`
  ), 0) AS `received_qty`,
  CASE
    WHEN COALESCE((
      SELECT SUM(receipt_item.`qty_received`)
      FROM `purchase_order_items` item
      JOIN `goods_receipt_items` receipt_item ON receipt_item.`purchase_order_item_id` = item.`id`
      WHERE item.`purchase_order_id` = po.`id`
    ), 0) = 0 THEN 'Belum diterima'
    WHEN (
      SELECT COUNT(*)
      FROM `purchase_order_items` item
      WHERE item.`purchase_order_id` = po.`id`
        AND item.`qty_ordered` < COALESCE((
          SELECT SUM(receipt_item.`qty_received`)
          FROM `goods_receipt_items` receipt_item
          WHERE receipt_item.`purchase_order_item_id` = item.`id`
        ), 0)
    ) > 0 THEN 'Lebih diterima'
    WHEN (
      SELECT COUNT(*)
      FROM `purchase_order_items` item
      WHERE item.`purchase_order_id` = po.`id`
        AND item.`qty_ordered` > COALESCE((
          SELECT SUM(receipt_item.`qty_received`)
          FROM `goods_receipt_items` receipt_item
          WHERE receipt_item.`purchase_order_item_id` = item.`id`
        ), 0)
    ) > 0 THEN 'Diterima sebagian'
    ELSE 'Sesuai'
  END AS `receipt_status`,
  (
    SELECT COUNT(*)
    FROM `purchase_order_items` item
    WHERE item.`purchase_order_id` = po.`id`
      AND item.`qty_ordered` > COALESCE((
        SELECT SUM(receipt_item.`qty_received`)
        FROM `goods_receipt_items` receipt_item
        WHERE receipt_item.`purchase_order_item_id` = item.`id`
      ), 0)
  ) AS `incomplete_items`,
  (
    SELECT COUNT(*)
    FROM `purchase_order_items` item
    WHERE item.`purchase_order_id` = po.`id`
      AND item.`qty_ordered` < COALESCE((
        SELECT SUM(receipt_item.`qty_received`)
        FROM `goods_receipt_items` receipt_item
        WHERE receipt_item.`purchase_order_item_id` = item.`id`
      ), 0)
  ) AS `excess_items`,
  po.`printed_at`
FROM `purchase_orders` po
JOIN `suppliers` supplier ON supplier.`id` = po.`supplier_id`;
