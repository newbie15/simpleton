(function ($) {
	'use strict';

	var fieldLabels = {
		kode: 'Kode voucher',
		nama: 'Nama voucher',
		tipe: 'Jenis voucher',
		nilai: 'Nilai',
		min_belanja: 'Minimum belanja',
		max_diskon: 'Maksimum diskon',
		kuota: 'Kuota',
		tgl_mulai: 'Tanggal mulai',
		tgl_kadaluarsa: 'Tanggal kedaluwarsa'
	};

	function fieldMessage(field) {
		var validity = field.validity;
		var label = fieldLabels[field.name] || 'Field ini';

		if (validity.valueMissing) {
			return label + ' wajib diisi.';
		}
		if (validity.badInput || validity.typeMismatch) {
			return label + ' harus menggunakan format yang benar.';
		}
		if (validity.patternMismatch) {
			return 'Gunakan maksimal 32 huruf, angka, titik, garis bawah, atau tanda hubung.';
		}
		if (validity.rangeUnderflow || validity.rangeOverflow) {
			if (field.name === 'nilai' && field.form.elements.tipe.value === 'diskon_persen') {
				return 'Diskon persentase harus lebih dari 0 dan tidak boleh lebih dari 100%.';
			}
			return label + ' berada di luar batas yang diizinkan.';
		}
		if (validity.stepMismatch) {
			if (field.name === 'min_belanja' || field.name === 'kuota') {
				return label + ' harus berupa bilangan bulat.';
			}
			return label + ' hanya boleh memiliki maksimal 2 angka desimal.';
		}
		return label + ' tidak valid.';
	}

	function clearFieldError(form, field) {
		var group = $(field).closest('.form-group');
		group.removeClass('has-error');
		group.find('[data-error-for="' + field.name + '"]').text('');
	}

	function showFieldError(form, field, message) {
		var group = $(field).closest('.form-group');
		group.addClass('has-error');
		group.find('[data-error-for="' + field.name + '"]').text(message);
	}

	function validateForm(form) {
		var fields = Array.prototype.slice.call(form.querySelectorAll('[name]'))
			.filter(function (field) { return field.type !== 'submit' && !field.disabled; });
		var errors = [];
		var values = {};

		fields.forEach(function (field) {
			clearFieldError(form, field);
			if (field.name) {
				values[field.name] = field.value;
			}
			if (!field.checkValidity()) {
				errors.push({ field: field, message: fieldMessage(field) });
			}
		});

		var type = form.elements.tipe ? form.elements.tipe.value : '';
		var value = form.elements.nilai;
		if (type === 'diskon_persen' && value && value.value !== '' &&
			parseFloat(value.value) > 100 &&
			!errors.some(function (error) { return error.field === value; })) {
			errors.push({
				field: value,
				message: 'Diskon persentase tidak boleh lebih dari 100%.'
			});
		}
		var maximum = form.elements.max_diskon;
		if (maximum && maximum.value !== '' && type !== 'diskon_persen' &&
			!errors.some(function (error) { return error.field === maximum; })) {
			errors.push({
				field: maximum,
				message: 'Maksimum diskon hanya digunakan untuk voucher diskon persentase.'
			});
		}

		var start = form.elements.tgl_mulai;
		var expiry = form.elements.tgl_kadaluarsa;
		if (start && expiry && start.value && expiry.value &&
			expiry.value < start.value &&
			!errors.some(function (error) { return error.field === expiry; })) {
			errors.push({
				field: expiry,
				message: 'Waktu kedaluwarsa harus sama dengan atau setelah waktu mulai.'
			});
		}

		return errors;
	}

	$(function () {
		$('[data-voucher-form]').each(function () {
			var form = this;
			var summary = $(form).find('.voucher-form-error-summary');
			var existingErrors = $(form).find('.voucher-field-error').filter(function () {
				return $.trim($(this).text()) !== '';
			});

			if (existingErrors.length) {
				summary.text('Periksa kembali field yang ditandai sebelum menyimpan.').show();
				var detail = $(form).closest('details');
				if (detail.length) {
					detail.prop('open', true);
				}
				form.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}

			$(form).on('submit', function (event) {
				var errors = validateForm(form);
				if (!errors.length) {
					summary.hide().text('');
					return;
				}

				event.preventDefault();
				errors.forEach(function (error) {
					showFieldError(form, error.field, error.message);
				});
				summary.text('Ada ' + errors.length + ' field yang perlu diperbaiki. Periksa pesan di bawah setiap field.').show();
				errors[0].field.focus();
				errors[0].field.scrollIntoView({ behavior: 'smooth', block: 'center' });
			});

			$(form).on('input change', '[name]', function () {
				clearFieldError(form, this);
				summary.hide().text('');
			});
		});
	});
}(jQuery));
