(function () {
	'use strict';

	var form = document.querySelector('form[action*="management_payments/submit_request"]');
	var receipt = document.getElementById('payment-goods-receipt');
	var amount = document.getElementById('payment-amount');
	var warning = document.getElementById('payment-amount-warning');
	if (!form || !receipt || !amount || !warning) {
		return;
	}

	var parseAmount = function (value) {
		var normalized = value.replace(/\./g, '').replace(',', '.');
		if (!/^\d{1,13}(?:\.\d{0,2})?$/.test(normalized)) {
			return null;
		}
		var parsed = Number(normalized);
		return isFinite(parsed) ? parsed : null;
	};

	var formatAmount = function (value) {
		var cleaned = value.replace(/[^\d,.]/g, '');
		var decimalIndex = cleaned.indexOf(',');
		var integerPart = decimalIndex === -1
			? cleaned.replace(/[,.]/g, '')
			: cleaned.slice(0, decimalIndex).replace(/[,.]/g, '');
		var decimalPart = decimalIndex === -1
			? ''
			: cleaned.slice(decimalIndex + 1).replace(/[,.]/g, '').slice(0, 2);
		integerPart = integerPart.replace(/^0+(?=\d)/, '');
		integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
		return integerPart + (decimalIndex === -1 ? '' : ',' + decimalPart);
	};

	var getOutstanding = function () {
		var option = receipt.options[receipt.selectedIndex];
		var rawOutstanding = option ? option.getAttribute('data-outstanding') : null;
		if (rawOutstanding === null || rawOutstanding === '') {
			return null;
		}
		var outstanding = Number(rawOutstanding);
		return isFinite(outstanding) ? outstanding : null;
	};

	var formatFullAmount = function (value) {
		return value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
	};

	var updateWarning = function () {
		var parsed = parseAmount(amount.value);
		var outstanding = getOutstanding();
		var message = '';
		var isPartial = false;
		if (amount.value.trim() !== '' && parsed === null) {
			message = 'Format nominal tidak valid. Gunakan angka dan maksimal dua angka desimal.';
		} else if (parsed !== null && parsed <= 0) {
			message = 'Nominal pembayaran harus lebih besar dari nol.';
		} else if (parsed !== null && outstanding === null) {
			message = 'Pilih nota penerimaan sebelum memasukkan nominal pembayaran.';
		} else if (parsed !== null && outstanding !== null && parsed > outstanding + 0.0001) {
			message = 'Nominal melebihi sisa hutang nota sebesar Rp ' +
				formatFullAmount(outstanding) + '.';
		} else if (parsed !== null && outstanding !== null && parsed < outstanding - 0.0001) {
			isPartial = true;
			message = 'Nominal belum melunasi seluruh hutang. Sisa setelah pembayaran sekitar Rp ' +
				formatFullAmount(outstanding - parsed) +
				'. Jika memang dibayar bertahap, konfirmasi akan diminta saat pengajuan.';
		}
		warning.textContent = message;
		warning.style.display = message ? '' : 'none';
		warning.classList.toggle('alert-warning', isPartial);
		warning.classList.toggle('alert-danger', !!message && !isPartial);
		return {
			valid: parsed !== null && parsed > 0 && outstanding !== null &&
				parsed <= outstanding + 0.0001,
			partial: isPartial
		};
	};

	amount.addEventListener('input', function () {
		var cursor = amount.selectionStart;
		var oldLength = amount.value.length;
		amount.value = formatAmount(amount.value);
		var newLength = amount.value.length;
		var newCursor = Math.max(0, cursor + newLength - oldLength);
		amount.setSelectionRange(newCursor, newCursor);
		updateWarning();
	});

	receipt.addEventListener('change', function () {
		if (amount.value.trim() === '' && getOutstanding() !== null) {
			amount.value = formatFullAmount(getOutstanding());
		}
		updateWarning();
	});
	if (amount.value.trim() === '' && getOutstanding() !== null) {
		amount.value = formatFullAmount(getOutstanding());
	}
	updateWarning();

	form.addEventListener('submit', function (event) {
		var validation = updateWarning();
		if (!validation.valid) {
			event.preventDefault();
			if (amount.value.trim() === '' || !getOutstanding()) {
				warning.textContent = 'Pilih nota dengan sisa hutang dan masukkan nominal pembayaran.';
				warning.style.display = '';
			}
			amount.focus();
			return;
		}
		if (validation.partial && !window.confirm('Nominal lebih kecil dari sisa hutang. Tetap ajukan sebagai pembayaran bertahap?')) {
			event.preventDefault();
			amount.focus();
			return;
		}
		amount.value = amount.value.replace(/\./g, '').replace(',', '.');
	});
}());
