(function () {
	'use strict';
	var forms = document.querySelectorAll('form[data-database-operation]');
	var status = document.getElementById('database-operation-status');
	var label = document.getElementById('database-operation-label');
	var seconds = document.getElementById('database-operation-seconds');
	var busy = false;
	if (!status || !label || !seconds) { return; }
	for (var i = 0; i < forms.length; i++) {
		forms[i].addEventListener('submit', function (event) {
			if (busy) {
				event.preventDefault();
				return;
			}
			busy = true;
			label.textContent = this.getAttribute('data-database-operation');
			status.style.display = '';
			status.focus();
			var buttons = document.querySelectorAll('button[type="submit"]');
			for (var j = 0; j < buttons.length; j++) { buttons[j].disabled = true; }
			var started = Date.now();
			window.setInterval(function () {
				seconds.textContent = Math.floor((Date.now() - started) / 1000);
			}, 1000);
		});
	}
	window.addEventListener('pageshow', function (event) {
		if (event.persisted) { window.location.reload(); }
	});
}());
