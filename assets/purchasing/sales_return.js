(function ($) {
	'use strict';

	$(function () {
		var table = $('#sales-return-history');
		if (!table.length) {
			return;
		}

		function escapeText(value, type) {
			if (value === null || value === undefined) {
				return '';
			}
			return type === 'display' ? $('<div>').text(value).html() : value;
		}

		table.DataTable({
			processing: true,
			serverSide: true,
			deferRender: true,
			searchDelay: 400,
			order: [[2, 'desc']],
			pageLength: 25,
			lengthMenu: [10, 25, 50, 100],
			ajax: {
				url: table.data('source')
			},
			columns: [
				{ data: 'nomor_retur', render: escapeText },
				{ data: 'nota_penjualan', render: escapeText },
				{ data: 'tgl_retur', render: escapeText },
				{ data: 'item_count', className: 'text-right', orderable: false },
				{
					data: 'total_retur',
					className: 'text-right',
					render: function (value, type) {
						var amount = Number(value);
						if (type !== 'display' || !isFinite(amount)) {
							return value;
						}
						return 'Rp ' + amount.toLocaleString('id-ID', {
							minimumFractionDigits: 0,
							maximumFractionDigits: 2
						});
					}
				},
				{ data: 'id_user', render: escapeText },
				{
					data: null,
					render: function (row, type) {
						var status = row.status === 'completed' ? 'approved' : row.status;
						if (type !== 'display') {
							return status + ' ' + (row.rejection_reason || '');
						}
						return escapeText(status.charAt(0).toUpperCase() + status.slice(1), 'display') +
							(row.status === 'rejected' && row.rejection_reason
								? '<br><small>' + escapeText(row.rejection_reason, 'display') + '</small>'
								: '');
					}
				},
				{ data: 'action_html', orderable: false, searchable: false }
			],
			language: {
				processing: 'Memuat riwayat retur...',
				emptyTable: 'Belum ada retur penjualan.',
				info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ retur',
				infoEmpty: 'Menampilkan 0 retur',
				infoFiltered: '(difilter dari _MAX_ total retur)',
				lengthMenu: 'Tampilkan _MENU_ retur',
				search: 'Cari:',
				zeroRecords: 'Retur yang cocok tidak ditemukan.',
				paginate: {
					first: 'Pertama',
					last: 'Terakhir',
					next: 'Berikutnya',
					previous: 'Sebelumnya'
				}
			}
		});
	});
}(jQuery));
