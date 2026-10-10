(function ($) {
	'use strict';

	$(function () {
		var table = $('#voucher-usage-table');
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
			searchDelay: 350,
			pageLength: 25,
			lengthMenu: [10, 25, 50, 100],
			order: [[0, 'desc']],
			ajax: table.data('source'),
			columns: [
				{ data: 'used_at', render: escapeText },
				{ data: 'kode', render: escapeText },
				{ data: 'nama', render: escapeText },
				{ data: 'nota', render: escapeText },
				{
					data: 'npk_anggota',
					render: function (value, type) {
						return type === 'display' && (value === null || value === '') ? '-' : escapeText(value, type);
					}
				},
				{
					data: 'nominal_diskon',
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
				}
			],
			language: {
				processing: 'Memuat riwayat voucher...',
				search: 'Cari:',
				lengthMenu: 'Tampilkan _MENU_ pemakaian',
				info: 'Menampilkan _START_–_END_ dari _TOTAL_ pemakaian',
				infoEmpty: 'Belum ada pemakaian voucher',
				infoFiltered: '(disaring dari _MAX_ total pemakaian)',
				zeroRecords: 'Pemakaian voucher tidak ditemukan',
				emptyTable: 'Belum ada pemakaian voucher',
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
