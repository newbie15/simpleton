'use strict';
var assert = require('assert');
var fs = require('fs');
var path = require('path');
var vm = require('vm');
var listeners = {};
var timers = [];
var clock = 1000;
var forms = ['Backup ZIP sedang diproses', 'Restore sedang diproses'].map(function (text) {
	return {
		addEventListener: function (event, callback) { this.submit = callback; },
		getAttribute: function () { return text; }
	};
});
var status = { style: {}, focus: function () { this.focused = true; } };
var label = {};
var seconds = {};
var buttons = [{ disabled: false }, { disabled: false }];
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '..', 'assets', 'misc', 'database_tools.js'), 'utf8'), {
	document: {
		querySelectorAll: function (selector) { return selector.indexOf('form') === 0 ? forms : buttons; },
		getElementById: function (id) {
			return {
				'database-operation-status': status,
				'database-operation-label': label,
				'database-operation-seconds': seconds
			}[id];
		}
	},
	window: {
		setInterval: function (callback) { timers.push(callback); },
		addEventListener: function (event, callback) { listeners[event] = callback; },
		location: { reload: function () {} }
	},
	Date: { now: function () { return clock; } }
});
var prevented = false;
forms[0].submit({ preventDefault: function () { prevented = true; } });
assert.strictEqual(prevented, false);
assert.strictEqual(status.style.display, '');
assert.strictEqual(status.focused, true);
assert.strictEqual(label.textContent, 'Backup ZIP sedang diproses');
assert(buttons.every(function (button) { return button.disabled; }));
clock = 6500;
timers[0]();
assert.strictEqual(seconds.textContent, 5);
forms[1].submit({ preventDefault: function () { prevented = true; } });
assert.strictEqual(prevented, true);
assert.strictEqual(timers.length, 1);
console.log('PASS Immediate status, elapsed time, disabled buttons and duplicate-submit guard.');
