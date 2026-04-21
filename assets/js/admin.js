(function () {
	'use strict';

	if (!window.sioAdmin) {
		return;
	}

	var state = {
		ids: [],
		processed: 0,
		total: 0,
		scanning: false,
		optimizing: false
	};

	function qs(selector) {
		return document.querySelector(selector);
	}

	function post(action, data) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', sioAdmin.nonce);

		Object.keys(data || {}).forEach(function (key) {
			if (Array.isArray(data[key])) {
				data[key].forEach(function (value) {
					body.append(key + '[]', value);
				});
			} else {
				body.append(key, data[key]);
			}
		});

		return fetch(sioAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (response) {
			return response.json();
		}).then(function (json) {
			if (!json || !json.success) {
				throw new Error(json && json.data && json.data.message ? json.data.message : sioAdmin.genericError);
			}
			return json.data;
		});
	}

	function setProgress(done, total, message) {
		var bar = qs('[data-sio-progress-bar]');
		var text = qs('[data-sio-progress-text]');
		var percent = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;

		if (bar) {
			bar.style.width = percent + '%';
		}
		if (text) {
			text.textContent = message || (done + ' / ' + total);
		}
	}

	function addLog(message, type) {
		var log = qs('[data-sio-log]');
		if (!log) {
			return;
		}
		var item = document.createElement('li');
		if (type) {
			item.className = 'sio-log-' + type;
		}
		item.textContent = message;
		log.prepend(item);
	}

	function setButtons() {
		var scan = qs('[data-sio-scan]');
		var optimize = qs('[data-sio-optimize]');
		if (scan) {
			scan.disabled = state.scanning || state.optimizing || scan.hasAttribute('data-disabled-by-server');
		}
		if (optimize) {
			optimize.disabled = state.scanning || state.optimizing || state.ids.length === 0;
		}
	}

	function scanPage(page) {
		return post('sio_scan_media', { page: page, per_page: 100 }).then(function (data) {
			state.ids = state.ids.concat(data.ids || []);
			state.total = data.total || state.ids.length;
			setProgress(Math.min(page, data.total_pages || page), data.total_pages || page, sioAdmin.scanning + ' ' + state.ids.length + ' found');

			if (data.total_pages && page < data.total_pages) {
				return scanPage(page + 1);
			}

			return data;
		});
	}

	function scanMedia() {
		state.ids = [];
		state.processed = 0;
		state.total = 0;
		state.scanning = true;
		setButtons();
		setProgress(0, 1, sioAdmin.scanning);
		addLog(sioAdmin.scanning);

		scanPage(1).then(function () {
			state.scanning = false;
			state.total = state.ids.length;
			setProgress(state.total, state.total || 1, state.total ? (sioAdmin.scanComplete + ' ' + state.total + ' images found.') : sioAdmin.noImages);
			addLog(state.total ? (sioAdmin.scanComplete + ' ' + state.total + ' candidates.') : sioAdmin.noImages, state.total ? 'success' : '');
			setButtons();
		}).catch(function (error) {
			state.scanning = false;
			setProgress(0, 1, error.message);
			addLog(error.message, 'error');
			setButtons();
		});
	}

	function updateStats(stats) {
		if (!stats) {
			return;
		}
		['processed', 'skipped', 'errors'].forEach(function (key) {
			var el = qs('[data-sio-stat="' + key + '"]');
			if (el && typeof stats[key] !== 'undefined') {
				el.textContent = stats[key];
			}
		});

		var saved = qs('[data-sio-stat="saved"]');
		if (saved && typeof stats.bytes_before !== 'undefined' && typeof stats.bytes_after !== 'undefined') {
			var bytes = Math.max(0, parseInt(stats.bytes_before, 10) - parseInt(stats.bytes_after, 10));
			saved.textContent = formatBytes(bytes);
		}

		var lastRun = qs('[data-sio-stat="last_run"]');
		if (lastRun && stats.last_run) {
			lastRun.textContent = stats.last_run;
		}
	}

	function formatBytes(bytes) {
		if (bytes < 1024) {
			return bytes + ' B';
		}
		var units = ['KB', 'MB', 'GB'];
		var value = bytes / 1024;
		var unit = 0;
		while (value >= 1024 && unit < units.length - 1) {
			value = value / 1024;
			unit++;
		}
		return value.toFixed(1) + ' ' + units[unit];
	}

	function optimizeNextBatch() {
		if (!state.ids.length) {
			state.optimizing = false;
			setProgress(state.processed, state.total || 1, sioAdmin.complete);
			addLog(sioAdmin.complete, 'success');
			setButtons();
			return;
		}

		var batchSize = Math.max(1, parseInt(sioAdmin.batchSize || 3, 10));
		var batch = state.ids.splice(0, batchSize);

		post('sio_optimize_batch', { ids: batch }).then(function (data) {
			(data.results || []).forEach(function (result) {
				state.processed++;
				addLog('#' + result.id + ' - ' + result.message, result.optimized ? 'success' : (result.skipped ? '' : 'error'));
			});
			updateStats(data.stats);
			setProgress(state.processed, state.total, sioAdmin.optimizing + ' ' + state.processed + ' / ' + state.total);
			optimizeNextBatch();
		}).catch(function (error) {
			state.optimizing = false;
			addLog(error.message, 'error');
			setButtons();
		});
	}

	function startOptimization() {
		if (!state.ids.length || !window.confirm(sioAdmin.confirmOptimize)) {
			return;
		}
		state.optimizing = true;
		state.processed = 0;
		state.total = state.ids.length;
		setButtons();
		setProgress(0, state.total, sioAdmin.optimizing);
		addLog(sioAdmin.optimizing);
		optimizeNextBatch();
	}

	document.addEventListener('DOMContentLoaded', function () {
		var scan = qs('[data-sio-scan]');
		var optimize = qs('[data-sio-optimize]');

		if (scan && scan.disabled) {
			scan.setAttribute('data-disabled-by-server', '1');
		}

		if (scan) {
			scan.addEventListener('click', scanMedia);
		}
		if (optimize) {
			optimize.addEventListener('click', startOptimization);
		}
	});
}());
