(function ($) {
	'use strict';

	$(function () {
		var table = $('#voucher-usage-table');
		var npkInput = $('#voucher-npk');
		var ajaxBase = table.data('source').replace(/\/voucher\/usage_data\/?$/, '/ajax');
		var selectedEmployee = null;

		function escapeText(value, type) {
			if (value === null || value === undefined) {
				return '';
			}
			return type === 'display' ? $('<div>').text(value).html() : value;
		}
		if (!table.length) {
			return;
		}

		function selectedNpk() {
			var value = $.trim(npkInput.val() || '');
			if (selectedEmployee && selectedEmployee.label === value) {
				return selectedEmployee.npk;
			}
			return value;
		}

		table.DataTable({
			processing: true,
			serverSide: true,
			searchDelay: 350,
			pageLength: 25,
			lengthMenu: [10, 25, 50, 100],
			order: [[0, 'desc']],
			ajax: {
				url: table.data('source'),
				data: function (request) {
					request.npk = selectedNpk();
				}
			},
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
					data: 'serial_code',
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

		npkInput.easyAutocomplete({
			url: ajaxBase + '/karyawan_list',
			getValue: 'nama',
			list: {
				match: { enabled: true },
				onChooseEvent: function () {
					var label = $.trim(npkInput.val() || '');
					var match = label.match(/-([^-]+)$/);
					selectedEmployee = {
						label: label,
						npk: match ? $.trim(match[1]) : label
					};
					table.DataTable().ajax.reload();
				}
			}
		});
		npkInput.on('input', function () {
			selectedEmployee = null;
		});

		$('#voucher-npk-filter').on('submit', function (event) {
			event.preventDefault();
			table.DataTable().ajax.reload();
		});
		$('#voucher-npk-reset').on('click', function () {
			npkInput.val('').trigger('input');
			table.DataTable().ajax.reload();
		});
	});
}(jQuery));
