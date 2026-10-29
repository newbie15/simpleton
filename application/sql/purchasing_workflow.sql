SET @po_status_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND COLUMN_NAME = 'status'
);
SET @purchasing_workflow_sql = IF(
	@po_status_exists = 0,
	"ALTER TABLE `purchase_orders` ADD COLUMN `status` enum('open','closed','cancelled') NOT NULL DEFAULT 'open' AFTER `notes`",
	'SELECT 1'
);
PREPARE purchasing_workflow_stmt FROM @purchasing_workflow_sql;
EXECUTE purchasing_workflow_stmt;
DEALLOCATE PREPARE purchasing_workflow_stmt;

SET @po_status_index_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders'
		AND INDEX_NAME = 'idx_purchase_orders_status'
);
SET @purchasing_workflow_sql = IF(
	@po_status_index_exists = 0,
	'CREATE INDEX `idx_purchase_orders_status` ON `purchase_orders` (`status`)',
	'SELECT 1'
);
PREPARE purchasing_workflow_stmt FROM @purchasing_workflow_sql;
EXECUTE purchasing_workflow_stmt;
DEALLOCATE PREPARE purchasing_workflow_stmt;

SET @po_closed_at_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND COLUMN_NAME = 'closed_at'
);
SET @purchasing_workflow_sql = IF(
	@po_closed_at_exists = 0,
	'ALTER TABLE `purchase_orders` ADD COLUMN `closed_at` datetime DEFAULT NULL AFTER `status`',
	'SELECT 1'
);
PREPARE purchasing_workflow_stmt FROM @purchasing_workflow_sql;
EXECUTE purchasing_workflow_stmt;
DEALLOCATE PREPARE purchasing_workflow_stmt;

SET @po_closed_by_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND COLUMN_NAME = 'closed_by'
);
SET @purchasing_workflow_sql = IF(
	@po_closed_by_exists = 0,
	'ALTER TABLE `purchase_orders` ADD COLUMN `closed_by` varchar(32) DEFAULT NULL AFTER `closed_at`',
	'SELECT 1'
);
PREPARE purchasing_workflow_stmt FROM @purchasing_workflow_sql;
EXECUTE purchasing_workflow_stmt;
DEALLOCATE PREPARE purchasing_workflow_stmt;

SET @po_close_reason_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND COLUMN_NAME = 'close_reason'
);
SET @purchasing_workflow_sql = IF(
	@po_close_reason_exists = 0,
	'ALTER TABLE `purchase_orders` ADD COLUMN `close_reason` varchar(500) DEFAULT NULL AFTER `closed_by`',
	'SELECT 1'
);
PREPARE purchasing_workflow_stmt FROM @purchasing_workflow_sql;
EXECUTE purchasing_workflow_stmt;
DEALLOCATE PREPARE purchasing_workflow_stmt;

SET @po_item_adhoc_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_order_items' AND COLUMN_NAME = 'is_adhoc'
);
SET @purchasing_workflow_sql = IF(
	@po_item_adhoc_exists = 0,
	'ALTER TABLE `purchase_order_items` ADD COLUMN `is_adhoc` tinyint(1) NOT NULL DEFAULT 0 AFTER `purchase_price`',
	'SELECT 1'
);
PREPARE purchasing_workflow_stmt FROM @purchasing_workflow_sql;
EXECUTE purchasing_workflow_stmt;
DEALLOCATE PREPARE purchasing_workflow_stmt;

SET @po_item_authorized_by_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_order_items' AND COLUMN_NAME = 'authorized_by'
);
SET @purchasing_workflow_sql = IF(
	@po_item_authorized_by_exists = 0,
	'ALTER TABLE `purchase_order_items` ADD COLUMN `authorized_by` varchar(32) DEFAULT NULL AFTER `is_adhoc`',
	'SELECT 1'
);
PREPARE purchasing_workflow_stmt FROM @purchasing_workflow_sql;
EXECUTE purchasing_workflow_stmt;
DEALLOCATE PREPARE purchasing_workflow_stmt;

SET @po_item_authorized_at_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_order_items' AND COLUMN_NAME = 'authorized_at'
);
SET @purchasing_workflow_sql = IF(
	@po_item_authorized_at_exists = 0,
	'ALTER TABLE `purchase_order_items` ADD COLUMN `authorized_at` datetime DEFAULT NULL AFTER `authorized_by`',
	'SELECT 1'
);
PREPARE purchasing_workflow_stmt FROM @purchasing_workflow_sql;
EXECUTE purchasing_workflow_stmt;
DEALLOCATE PREPARE purchasing_workflow_stmt;

CREATE OR REPLACE VIEW `purchase_order_list` AS
SELECT
	po.`id`,
	po.`order_number`,
	po.`ordered_at`,
	supplier.`name` AS `supplier_name`,
	COALESCE((
		SELECT SUM(item.`qty_ordered`)
		FROM `purchase_order_items` item
		WHERE item.`purchase_order_id` = po.`id` AND item.`is_adhoc` = 0
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
			SELECT COUNT(*) FROM `purchase_order_items` item
			WHERE item.`purchase_order_id` = po.`id`
				AND item.`is_adhoc` = 0
				AND item.`qty_ordered` < COALESCE((
					SELECT SUM(receipt_item.`qty_received`)
					FROM `goods_receipt_items` receipt_item
					WHERE receipt_item.`purchase_order_item_id` = item.`id`
				), 0)
		) > 0 THEN 'Lebih diterima'
		WHEN (
			SELECT COUNT(*) FROM `purchase_order_items` item
			WHERE item.`purchase_order_id` = po.`id`
				AND item.`is_adhoc` = 0
				AND item.`qty_ordered` > COALESCE((
					SELECT SUM(receipt_item.`qty_received`)
					FROM `goods_receipt_items` receipt_item
					WHERE receipt_item.`purchase_order_item_id` = item.`id`
				), 0)
		) > 0 THEN 'Diterima sebagian'
		ELSE 'Sesuai'
	END AS `receipt_status`,
	(
		SELECT COUNT(*) FROM `purchase_order_items` item
		WHERE item.`purchase_order_id` = po.`id`
			AND item.`is_adhoc` = 0
			AND item.`qty_ordered` > COALESCE((
				SELECT SUM(receipt_item.`qty_received`)
				FROM `goods_receipt_items` receipt_item
				WHERE receipt_item.`purchase_order_item_id` = item.`id`
			), 0)
	) AS `incomplete_items`,
	(
		SELECT COUNT(*) FROM `purchase_order_items` item
		WHERE item.`purchase_order_id` = po.`id`
			AND item.`is_adhoc` = 0
			AND item.`qty_ordered` < COALESCE((
				SELECT SUM(receipt_item.`qty_received`)
				FROM `goods_receipt_items` receipt_item
				WHERE receipt_item.`purchase_order_item_id` = item.`id`
			), 0)
	) AS `excess_items`,
	po.`printed_at`, po.`status`, po.`closed_at`, po.`closed_by`, po.`close_reason`
FROM `purchase_orders` po
JOIN `suppliers` supplier ON supplier.`id` = po.`supplier_id`;