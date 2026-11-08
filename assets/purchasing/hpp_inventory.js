(function ($) {
	'use strict';

	$(function () {
		var table = $('#hpp-inventory-table');
		if (!table.length) {
			return;
		}

		var escapeText = function (value, type) {
			if (value === null || value === undefined) {
				return '';
			}
			return type === 'display' ? $('<div>').text(value).html() : value;
		};

		table.DataTable({
			processing: true,
			serverSide: true,
			deferRender: true,
			searchDelay: 400,
			ajax: { url: table.data('source'), type: 'GET' },
			order: [[1, 'asc']],
			pageLength: 25,
			lengthMenu: [10, 25, 50, 100],
			columnDefs: [{ orderable: false, targets: 5 }],
			columns: [
				{ data: 'barcode', render: escapeText },
				{ data: 'nama', render: escapeText },
				{
					data: 'jumlah',
					render: function (value, type) {
						var quantity = Number(value) || 0;
						return type === 'display' ? quantity.toLocaleString('id-ID') : quantity;
					}
				},
				{
					data: 'average_cost',
					render: function (value, type) {
						if (value === null || value === '' || !isFinite(Number(value)) || Number(value) <= 0) {
							return type === 'display' ? '<span class="text-danger">Belum diatur</span>' : -1;
						}
						var amount = Number(value);
						return type === 'display'
							? 'Rp ' + amount.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
							: amount;
					}
				},
				{ data: 'updated_at', defaultContent: '-', render: escapeText },
				{
					data: 'barcode',
					render: function (value, type) {
						if (type !== 'display') {
							return value;
						}
						var href = table.data('detail-url') + encodeURIComponent(value);
						return '<a class="btn btn-xs btn-info" href="' +
							escapeText(href, 'display') + '">Lihat detail</a>';
					}
				}
			],
			language: {
				processing: 'Memuat data...',
				emptyTable: 'Belum ada data stok.',
				info: 'Menampilkan _START_ - _END_ dari _TOTAL_ barang',
				infoEmpty: 'Menampilkan 0 barang',
				infoFiltered: '(difilter dari _MAX_ total barang)',
				lengthMenu: 'Tampilkan _MENU_ barang',
				search: 'Cari:',
				zeroRecords: 'Barang tidak ditemukan.',
				paginate: { first: 'Awal', last: 'Terakhir', next: 'Berikut', previous: 'Sebelumnya' }
			}
		});
	});
}(jQuery));
