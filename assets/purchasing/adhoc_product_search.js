(function () {
	'use strict';

	var form = document.getElementById('receipt-form');
	var rows = document.getElementById('adhoc-receipt-rows');
	if (!form || !rows) {
		return;
	}

	var searchUrl = form.getAttribute('data-product-search-url');
	var requestDelay = 200;

	function rowParts(input) {
		var picker = input.closest('.adhoc-product-picker');
		var row = input.closest('tr');
		return {
			picker: picker,
			row: row,
			barcode: picker.querySelector('[data-adhoc-barcode]'),
			name: row.querySelector('[data-adhoc-name]'),
			quantity: row.querySelector('[name$="[qty]"]'),
			results: picker.querySelector('[data-adhoc-results]')
		};
	}

	function hideResults(input) {
		var parts = rowParts(input);
		parts.results.style.display = 'none';
		parts.results.innerHTML = '';
	}

	function chooseProduct(input, product) {
		var parts = rowParts(input);
		parts.barcode.value = product.barcode;
		parts.name.value = product.nama;
		input.value = product.barcode + ' - ' + product.nama;
		hideResults(input);
		if (parts.quantity) {
			parts.quantity.focus();
		}
	}

	function displayResults(input, products, term) {
		if (input.value.trim() !== term) {
			return;
		}
		var parts = rowParts(input);
		parts.results.innerHTML = '';
		input._adhocProducts = products;
		input._adhocSearchTerm = term;
		if (!products.length) {
			parts.results.style.display = 'none';
			return;
		}
		products.forEach(function (product, index) {
			var option = document.createElement('button');
			option.type = 'button';
			option.className = 'list-group-item';
			option.setAttribute('data-product-index', String(index));
			option.textContent = product.barcode + ' - ' + product.nama;
			parts.results.appendChild(option);
		});
		parts.results.style.display = 'block';
	}

	function search(input, term, onComplete) {
		if (!term || !searchUrl) {
			displayResults(input, [], term);
			if (onComplete) {
				onComplete([]);
			}
			return;
		}
		var request = new XMLHttpRequest();
		request.open('GET', searchUrl + '?q=' + encodeURIComponent(term), true);
		request.onreadystatechange = function () {
			if (request.readyState !== 4 || input.value.trim() !== term) {
				return;
			}
			var products = [];
			if (request.status >= 200 && request.status < 300) {
				try {
					var response = JSON.parse(request.responseText);
					products = response.products || [];
				} catch (error) {
					products = [];
				}
			}
			displayResults(input, products, term);
			if (onComplete) {
				onComplete(products);
			}
		};
		request.send();
	}

	rows.addEventListener('input', function (event) {
		var input = event.target.closest('[data-adhoc-search]');
		if (!input) {
			return;
		}
		input.placeholder = 'Scan barcode atau cari nama';
		var parts = rowParts(input);
		parts.barcode.value = '';
		if (input._adhocTimer) {
			window.clearTimeout(input._adhocTimer);
		}
		var term = input.value.trim();
		input._adhocTimer = window.setTimeout(function () {
			search(input, term);
		}, requestDelay);
	});

	rows.addEventListener('keydown', function (event) {
		var input = event.target.closest('[data-adhoc-search]');
		if (!input) {
			return;
		}
		if (event.key === 'Escape') {
			hideResults(input);
			return;
		}
		if (event.key !== 'Enter') {
			return;
		}
		event.preventDefault();
		var term = input.value.trim();
		var products = input._adhocSearchTerm === term ? (input._adhocProducts || []) : [];
		if (products.length) {
			var exact = products.filter(function (product) {
				return String(product.barcode).toLowerCase() === term.toLowerCase();
			})[0];
			chooseProduct(input, exact || products[0]);
			return;
		}
		search(input, term, function (matches) {
			if (matches.length) {
				var exact = matches.filter(function (product) {
					return String(product.barcode).toLowerCase() === term.toLowerCase();
				})[0];
				chooseProduct(input, exact || matches[0]);
				return;
			}
			var parts = rowParts(input);
			if (/\s/.test(term)) {
				parts.name.value = term;
				input.value = '';
				input.placeholder = 'Scan barcode barang baru, lalu Enter';
				parts.results.innerHTML = '';
				var hint = document.createElement('div');
				hint.className = 'list-group-item text-muted';
				hint.textContent = 'Nama disiapkan. Scan barcode barang baru.';
				parts.results.appendChild(hint);
				parts.results.style.display = 'block';
				input.focus();
				return;
			}
			parts.barcode.value = term;
			if (parts.name && !parts.name.value.trim()) {
				parts.name.focus();
			} else {
				input.value = '';
				input.placeholder = 'Scan barcode barang baru, lalu Enter';
				input.focus();
			}
			hideResults(input);
		});
	});

	rows.addEventListener('click', function (event) {
		var option = event.target.closest('[data-product-index]');
		if (!option) {
			return;
		}
		var picker = option.closest('.adhoc-product-picker');
		var input = picker.querySelector('[data-adhoc-search]');
		var product = (input._adhocProducts || [])[parseInt(option.getAttribute('data-product-index'), 10)];
		if (product) {
			chooseProduct(input, product);
		}
	});

	document.addEventListener('click', function (event) {
		if (!event.target.closest('.adhoc-product-picker')) {
			rows.querySelectorAll('[data-adhoc-search]').forEach(hideResults);
		}
	});
}());