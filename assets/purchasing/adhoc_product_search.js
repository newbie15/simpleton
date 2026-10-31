(function () {
	'use strict';

	var form = document.getElementById('receipt-form');
	var rows = document.getElementById('adhoc-receipt-rows');
	var input = document.getElementById('adhoc-search');
	if (!form || !rows || !input) {
		return;
	}

	var picker = input.closest('.adhoc-product-picker');
	var resultsBox = picker.querySelector('[data-adhoc-results]');
	var searchUrl = form.getAttribute('data-product-search-url');
	var defaultPlaceholder = input.placeholder;
	var products = [];
	var activeIndex = -1;
	var sequence = 0;
	var timer = null;
	var request = null;
	var pendingName = '';
	var nextIndex = 0;

	function hideResults() {
		window.clearTimeout(timer);
		sequence++;
		if (request) {
			request.abort();
			request = null;
		}
		resultsBox.style.display = 'none';
		resultsBox.innerHTML = '';
		products = [];
		activeIndex = -1;
	}

	function updateActive() {
		Array.prototype.forEach.call(resultsBox.children, function (option, index) {
			option.classList.toggle('active', index === activeIndex);
		});
	}

	function renderResults(list) {
		products = list;
		activeIndex = list.length ? 0 : -1;
		resultsBox.innerHTML = '';
		if (!list.length) {
			resultsBox.style.display = 'none';
			return;
		}
		list.forEach(function (product, index) {
			var option = document.createElement('button');
			option.type = 'button';
			option.className = 'list-group-item';
			option.style.width = '100%';
			option.style.textAlign = 'left';
			option.textContent = product.barcode + ' - ' + product.nama;
			option.addEventListener('mousedown', function (event) {
				event.preventDefault();
				addProduct(product, 1);
			});
			option.addEventListener('mouseenter', function () {
				activeIndex = index;
				updateActive();
			});
			resultsBox.appendChild(option);
		});
		resultsBox.style.display = 'block';
		updateActive();
	}

	function search(term, callback) {
		var current = ++sequence;
		if (request) {
			request.abort();
		}
		var xhr = new XMLHttpRequest();
		request = xhr;
		xhr.open('GET', searchUrl + '?q=' + encodeURIComponent(term), true);
		xhr.onreadystatechange = function () {
			if (xhr.readyState !== 4 || current !== sequence) {
				return;
			}
			var list = [];
			if (xhr.status >= 200 && xhr.status < 300) {
				try {
					list = JSON.parse(xhr.responseText).products || [];
				} catch (error) {
					list = [];
				}
			}
			if (callback) {
				callback(list);
			} else {
				renderResults(list);
			}
		};
		xhr.send();
	}

	function bumpQuantity(field, quantity) {
		field.value = (parseInt(field.value, 10) || 0) + quantity;
		field.dispatchEvent(new Event('input', { bubbles: true }));
	}

	function findRow(scope, barcode, inside) {
		var found = null;
		Array.prototype.forEach.call(scope.querySelectorAll('tr[data-barcode]'), function (row) {
			if (rows.contains(row) === inside && row.getAttribute('data-barcode') === barcode) {
				found = row;
			}
		});
		return found;
	}

	function makeInput(name, type, value) {
		var field = document.createElement('input');
		field.className = 'form-control';
		field.name = name;
		if (type) {
			field.type = type;
		}
		if (type === 'number') {
			field.min = '0';
			field.step = '1';
		}
		field.value = value;
		return field;
	}

	function appendRow(barcode, name, isNew, quantity) {
		var index = nextIndex++;
		var prefix = 'adhoc[' + index + ']';
		var row = document.createElement('tr');
		row.setAttribute('data-barcode', barcode);

		var cellBarcode = document.createElement('td');
		cellBarcode.textContent = barcode;
		var hidden = makeInput(prefix + '[barcode]', 'hidden', barcode);
		hidden.className = '';
		cellBarcode.appendChild(hidden);

		var nameInput = makeInput(prefix + '[name]', 'text', name);
		nameInput.maxLength = 64;
		nameInput.readOnly = !isNew;
		var qtyInput = makeInput(prefix + '[qty]', 'number', quantity);
		var priceInput = makeInput(prefix + '[unit_price]', 'number', '');
		priceInput.placeholder = 'Harga beli';

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'btn btn-xs btn-default';
		remove.setAttribute('data-remove-adhoc', '');
		remove.textContent = 'Hapus';

		[cellBarcode, nameInput, qtyInput, priceInput, remove].forEach(function (node, i) {
			var cell = i === 0 ? node : document.createElement('td');
			if (i > 0) {
				cell.appendChild(node);
			}
			row.appendChild(cell);
		});
		rows.appendChild(row);
		return { name: nameInput, price: priceInput };
	}

	function resetInput() {
		input.value = '';
		input.placeholder = pendingName ? 'Scan barcode untuk "' + pendingName + '", lalu Enter' : defaultPlaceholder;
		hideResults();
		input.focus();
	}

	function addProduct(product, quantity) {
		var barcode = String(product.barcode);
		var poRow = findRow(form, barcode, false);
		var poQty = poRow ? poRow.querySelector('.receipt-quantity') : null;
		if (poQty && !poQty.disabled) {
			bumpQuantity(poQty, quantity);
			pendingName = '';
			resetInput();
			return;
		}
		var existing = findRow(rows, barcode, true);
		if (existing) {
			bumpQuantity(existing.querySelector('[name$="[qty]"]'), quantity);
			pendingName = '';
			resetInput();
			return;
		}
		var isNew = !product.nama;
		var created = appendRow(barcode, product.nama || pendingName, isNew, quantity);
		pendingName = '';
		resetInput();
		(isNew && !created.name.value ? created.name : created.price).focus();
	}

	input.addEventListener('input', function () {
		window.clearTimeout(timer);
		var term = this.value.trim();
		hideResults();
		if (!term) {
			return;
		}
		timer = window.setTimeout(function () {
			search(term);
		}, 180);
	});

	input.addEventListener('keydown', function (event) {
		if (event.key === 'ArrowDown' && products.length) {
			event.preventDefault();
			activeIndex = (activeIndex + 1) % products.length;
			updateActive();
		} else if (event.key === 'ArrowUp' && products.length) {
			event.preventDefault();
			activeIndex = activeIndex <= 0 ? products.length - 1 : activeIndex - 1;
			updateActive();
		} else if (event.key === 'Escape') {
			hideResults();
		} else if (event.key === 'Enter') {
			event.preventDefault();
			event.stopPropagation();
			window.clearTimeout(timer);
			if (products.length && activeIndex >= 0) {
				addProduct(products[activeIndex], 1);
				return;
			}
			var value = this.value.trim();
			if (!value) {
				return;
			}
			var match = value.match(/^(\d+)\*(.+)$/);
			var quantity = match ? parseInt(match[1], 10) : 1;
			var term = match ? match[2].trim() : value;
			if (!term || quantity < 1) {
				return;
			}
			search(term, function (list) {
				var exact = list.filter(function (product) {
					return String(product.barcode).toLowerCase() === term.toLowerCase();
				})[0];
				if (exact) {
					addProduct(exact, quantity);
				} else if (list.length) {
					renderResults(list);
				} else if (/\s/.test(term)) {
					pendingName = term.substring(0, 64);
					resetInput();
				} else {
					addProduct({ barcode: term, nama: '' }, quantity);
				}
			});
		}
	});

	rows.addEventListener('click', function (event) {
		if (event.target && event.target.hasAttribute('data-remove-adhoc')) {
			var row = event.target.closest('tr');
			if (row) {
				row.parentNode.removeChild(row);
			}
		}
	});

	document.addEventListener('click', function (event) {
		if (!picker.contains(event.target)) {
			hideResults();
		}
	});
}());
