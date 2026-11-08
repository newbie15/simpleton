CREATE OR REPLACE VIEW `purchase_order_payment_details` AS
SELECT po.*,
	(SELECT COUNT(*) FROM `goods_receipts` receipt
		WHERE receipt.purchase_order_id = po.id) AS `receipt_count`,
	COALESCE((
		SELECT SUM(CASE
			WHEN receipt_item.subtotal > 0 OR receipt_item.qty_received * COALESCE(receipt_item.unit_price, 0) = 0
				THEN receipt_item.subtotal
			ELSE GREATEST(
				receipt_item.qty_received * COALESCE(receipt_item.unit_price, 0) - COALESCE(receipt_item.discount_amount, 0),
				0
			)
		END)
		FROM `goods_receipts` receipt
		JOIN `goods_receipt_items` receipt_item ON receipt_item.goods_receipt_id = receipt.id
		WHERE receipt.purchase_order_id = po.id
	), 0)
	+ COALESCE((
		SELECT SUM(receipt.ppn_nominal)
		FROM `goods_receipts` receipt
		WHERE receipt.purchase_order_id = po.id
	), 0) AS `invoice_amount`,
	COALESCE((
		SELECT SUM(return_header.total_nominal)
		FROM `retur_supplier` return_header
		JOIN `goods_receipts` receipt ON receipt.id = return_header.goods_receipt_id
		WHERE receipt.purchase_order_id = po.id AND return_header.status = 'final'
	), 0) AS `return_amount`,
	COALESCE((
		SELECT SUM(payment.amount)
		FROM `acc_cash_transactions` payment
		JOIN `goods_receipts` receipt ON receipt.id = payment.goods_receipt_id
		WHERE receipt.purchase_order_id = po.id
			AND payment.transaction_type = 'supplier_payment' AND payment.status = 'posted'
	), 0) AS `paid_amount`,
	COALESCE((
		SELECT SUM(payment.amount)
		FROM `acc_cash_transactions` payment
		JOIN `goods_receipts` receipt ON receipt.id = payment.goods_receipt_id
		WHERE receipt.purchase_order_id = po.id
			AND payment.transaction_type = 'supplier_payment' AND payment.status = 'pending'
	), 0) AS `pending_payment_amount`
FROM `purchase_order_list` po;

CREATE OR REPLACE VIEW `purchase_order_payment_list` AS
SELECT payment_details.*,
	GREATEST(payment_details.invoice_amount - payment_details.return_amount - payment_details.paid_amount, 0) AS `outstanding_amount`,
	CASE
		WHEN payment_details.receipt_count = 0 THEN 'Belum ada tagihan'
		WHEN payment_details.invoice_amount <= 0.01 THEN 'Perlu cek nilai faktur'
		WHEN GREATEST(payment_details.invoice_amount - payment_details.return_amount - payment_details.paid_amount, 0) <= 0.01 THEN 'Lunas'
		WHEN payment_details.pending_payment_amount > 0 AND payment_details.paid_amount > 0 THEN 'Sebagian dibayar - menunggu approval'
		WHEN payment_details.pending_payment_amount > 0 THEN 'Menunggu approval'
		WHEN payment_details.paid_amount > 0 THEN 'Sebagian dibayar'
		ELSE 'Belum dibayar'
	END AS `payment_status`
FROM `purchase_order_payment_details` payment_details;