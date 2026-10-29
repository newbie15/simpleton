(function ($) {
	'use strict';

	$(function () {
		var paymentsTable = $('#management-payments-table');
		var closedTable = $('#closed-po-payments-table');
		var language = {
			processing: 'Memuat data...',
			emptyTable: 'Tidak ada data untuk ditampilkan.',
			info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
			infoEmpty: 'Menampilkan 0 data',
			infoFiltered: '(difilter dari _MAX_ total data)',
			lengthMenu: 'Tampilkan _MENU_ data',
			search: 'Cari:',
			zeroRecords: 'Data yang cocok tidak ditemukan.',
			paginate: {
				first: 'Pertama',
				last: 'Terakhir',
				next: 'Berikutnya',
				previous: 'Sebelumnya'
			}
		};

		function escapeText(value, type) {
			if (value === null || value === undefined) {
				return '';
			}
			return type === 'display' ? $('<div>').text(value).html() : value;
		}

		function currency(value, type) {
			var amount = Number(value);
			if (type !== 'display' || !isFinite(amount)) {
				return value;
			}
			return 'Rp ' + amount.toLocaleString('id-ID', {
				minimumFractionDigits: 0,
				maximumFractionDigits: 2
			});
		}

		if (paymentsTable.length) {
			paymentsTable.DataTable({
				processing: true,
				serverSide: true,
				deferRender: true,
				searchDelay: 400,
				order: [[1, 'desc']],
				pageLength: 25,
				lengthMenu: [10, 25, 50, 100],
				ajax: {
					url: paymentsTable.data('source'),
					data: function (request) {
						request.status = $('#payment-status').val();
						request.supplier = $('#payment-supplier').val();
						request.receipt = $('#payment-receipt').val();
						request.start_date = $('#payment-start').val();
						request.end_date = $('#payment-end').val();
					}
				},
				columns: [
					{
						data: 'status',
						render: function (value, type) {
							var labels = {
								pending: 'Menunggu approval',
								posted: 'Sudah dibayar',
								rejected: 'Ditolak'
							};
							return escapeText(labels[value] || value, type);
						}
					},
					{ data: 'transaction_date', render: escapeText },
					{
						data: null,
						render: function (row, type) {
							if (type !== 'display') {
								return row.supplier_name + ' ' + row.receipt_number;
							}
							var receiveUrl = paymentsTable.data('receive-url') + '/' + encodeURIComponent(row.purchase_order_id);
							return '<strong>' + escapeText(row.supplier_name, 'display') + '</strong><br><a href="' +
								escapeText(receiveUrl, 'display') + '">' + escapeText(row.receipt_number, 'display') +
								'</a>' + (row.supplier_document ? '<br><small>Surat jalan: ' +
									escapeText(row.supplier_document, 'display') + '</small>' : '');
						}
					},
					{
						data: null,
						render: function (row, type) {
							if (type !== 'display') {
								return row.evidence_reference || '';
							}
							var result = escapeText(row.evidence_reference, 'display');
							if (row.payment_source_code) {
								result += '<br><small>Akun sumber: ' + escapeText(row.payment_source_code, 'display') +
									' — ' + escapeText(row.payment_source_name, 'display') + '</small>';
							}
							if (row.proof_filename) {
								var proofUrl = paymentsTable.data('proof-url') + '/' + encodeURIComponent(row.id);
								result += '<br><a href="' + escapeText(proofUrl, 'display') +
									'" class="js-proof-preview">Lihat bukti</a>';
							}
							return result;
						}
					},
					{ data: 'description', render: escapeText },
					{ data: 'amount', className: 'text-right', render: currency },
					{ data: 'outstanding_after_requests', className: 'text-right', render: currency },
					{
						data: null,
						render: function (row, type) {
							if (type !== 'display') {
								return row.creator_name || '';
							}
							return escapeText(row.creator_name, 'display') +
								(row.approver_name ? '<br><small>Oleh ' + escapeText(row.approver_name, 'display') +
									' · ' + escapeText(row.approved_at, 'display') + '</small>' : '');
						}
					},
					{ data: 'action_html', orderable: false, searchable: false }
				],
				language: language
			});
		}

		if (closedTable.length) {
			closedTable.DataTable({
				processing: true,
				serverSide: true,
				deferRender: true,
				searchDelay: 400,
				order: [[2, 'desc']],
				pageLength: 25,
				lengthMenu: [10, 25, 50, 100],
				ajax: { url: closedTable.data('source') },
				columns: [
					{
						data: null,
						render: function (row, type) {
							if (type !== 'display') {
								return row.order_number + ' ' + row.receipt_number;
							}
							var receiveUrl = closedTable.data('receive-url') + '/' + encodeURIComponent(row.purchase_order_id);
							return '<strong>' + escapeText(row.order_number, 'display') + '</strong><br><a href="' +
								escapeText(receiveUrl, 'display') + '">' + escapeText(row.receipt_number, 'display') +
								'</a>' + (row.supplier_document ? '<br><small>Surat jalan: ' +
									escapeText(row.supplier_document, 'display') + '</small>' : '');
						}
					},
					{ data: 'supplier_name', render: escapeText },
					{ data: 'received_at', render: escapeText },
					{ data: 'outstanding', className: 'text-right', render: currency },
					{
						data: null,
						orderable: false,
						searchable: false,
						render: function (row, type) {
							if (type !== 'display') {
								return '';
							}
							var requestUrl = closedTable.data('request-url') + '?goods_receipt_id=' +
								encodeURIComponent(row.receipt_id);
							return '<a class="btn btn-xs btn-success" href="' + escapeText(requestUrl, 'display') +
								'"><i class="fa fa-money" aria-hidden="true"></i> Mulai pembayaran</a>';
						}
					}
				],
				language: language
			});
		}
	});
}(jQuery));
