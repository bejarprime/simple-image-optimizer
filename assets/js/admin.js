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

	function prependRecentResult(result) {
		var list = qs('[data-sio-results-list]');
		var empty = qs('[data-sio-empty-results]');
		var labels = sioAdmin.labels || {};

		if (!list || !result) {
			return;
		}

		if (empty) {
			empty.remove();
		}

		var row = document.createElement('div');
		row.className = 'sio-result-row';

		var status = result.status || (result.optimized ? 'optimized' : (result.skipped ? 'skipped' : 'error'));
		var badgeClass = status === 'optimized' ? 'wphubb-badge-active' : (status === 'skipped' ? 'wphubb-badge-inactive' : 'wphubb-badge-warning');
		var title = result.title || result.filename || ('#' + result.id);
		var saved = parseInt(result.bytes_saved || 0, 10);

		row.appendChild(buildResultMain(status, badgeClass, title, result.message || ''));
		row.appendChild(buildResultMeta(result, saved, labels));
		row.appendChild(buildResultFlags(result, labels));

		list.prepend(row);

		while (list.children.length > 10) {
			list.removeChild(list.lastElementChild);
		}
	}

	function buildResultMain(status, badgeClass, title, message) {
		var wrap = document.createElement('div');
		var badge = document.createElement('span');
		var strong = document.createElement('strong');
		var text = document.createElement('span');
		var labels = sioAdmin.labels || {};

		wrap.className = 'sio-result-main';
		badge.className = 'wphubb-badge ' + badgeClass;
		badge.textContent = labels[status] || status;
		strong.textContent = title;
		text.textContent = message;

		wrap.appendChild(badge);
		wrap.appendChild(strong);
		wrap.appendChild(text);

		return wrap;
	}

	function buildResultMeta(result, saved, labels) {
		var wrap = document.createElement('div');
		wrap.className = 'sio-result-meta';

		[
			[labels.before || 'Before:', formatBytes(parseInt(result.bytes_before || 0, 10))],
			[labels.after || 'After:', formatBytes(parseInt(result.bytes_after || 0, 10))],
			[labels.saved || 'Saved:', formatBytes(saved)],
			[labels.sizes || 'Sizes:', parseInt(result.sizes_processed || 0, 10)]
		].forEach(function (item) {
			var span = document.createElement('span');
			var strong = document.createElement('strong');
			span.appendChild(document.createTextNode(item[0] + ' '));
			strong.textContent = item[1];
			span.appendChild(strong);
			wrap.appendChild(span);
		});

		return wrap;
	}

	function buildResultFlags(result, labels) {
		var wrap = document.createElement('div');
		var webp = document.createElement('span');
		var backup = document.createElement('span');
		var kept = parseInt(result.kept_originals || 0, 10);

		wrap.className = 'sio-result-flags';
		webp.className = 'sio-flag' + (result.webp_created ? ' sio-flag-ok' : '');
		backup.className = 'sio-flag' + (result.backup_created ? ' sio-flag-ok' : '');
		webp.textContent = labels.webp || 'WebP';
		backup.textContent = labels.backup || 'Backup';

		wrap.appendChild(webp);
		wrap.appendChild(backup);

		if (kept > 0) {
			var keptFlag = document.createElement('span');
			keptFlag.className = 'sio-flag';
			keptFlag.textContent = kept + ' ' + (labels.kept || 'kept');
			wrap.appendChild(keptFlag);
		}

		if (result.time) {
			var time = document.createElement('span');
			time.className = 'sio-result-time';
			time.textContent = result.time;
			wrap.appendChild(time);
		}

		return wrap;
	}

	function restoreAttachment(button) {
		var id = button.getAttribute('data-sio-restore-media');
		var status = button.closest('.sio-media-status');

		if (!id || !window.confirm(sioAdmin.confirmRestore)) {
			return;
		}

		button.disabled = true;

		post('sio_restore_attachment', { id: id }).then(function () {
			if (status) {
				status.innerHTML = '<span class="sio-media-badge sio-media-badge-pending">' + sioAdmin.restored + '</span>';
			}
		}).catch(function (error) {
			button.disabled = false;
			window.alert(error.message);
		});
	}

	function copyWebpUrl(button) {
		var url = button.getAttribute('data-sio-copy-webp');
		var originalText = button.textContent;

		if (!url) {
			return;
		}

		function markCopied() {
			button.textContent = sioAdmin.copied || 'Copied.';
			window.setTimeout(function () {
				button.textContent = originalText;
			}, 1600);
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(markCopied).catch(function () {
				window.prompt(sioAdmin.copyFailed || 'Copy this URL:', url);
			});
			return;
		}

		window.prompt(sioAdmin.copyFailed || 'Copy this URL:', url);
	}

	function copyDiagnosticReport(button) {
		var selector = button.getAttribute('data-sio-copy-report');
		var report = selector ? qs(selector) : null;
		var value = report ? report.value : '';
		var originalText = button.textContent;

		if (!value) {
			return;
		}

		function markCopied() {
			button.textContent = sioAdmin.copied || 'Copied.';
			window.setTimeout(function () {
				button.textContent = originalText;
			}, 1600);
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(value).then(markCopied).catch(function () {
				if (report) {
					report.focus();
					report.select();
				}
				window.prompt(sioAdmin.copyFailed || 'Copy this report:', value);
			});
			return;
		}

		if (report) {
			report.focus();
			report.select();
		}
		window.prompt(sioAdmin.copyFailed || 'Copy this report:', value);
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
				prependRecentResult(result);
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

		document.addEventListener('click', function (event) {
			var restoreButton = event.target.closest('[data-sio-restore-media]');
			if (restoreButton) {
				restoreAttachment(restoreButton);
			}

			var copyButton = event.target.closest('[data-sio-copy-webp]');
			if (copyButton) {
				copyWebpUrl(copyButton);
			}

			var copyReportButton = event.target.closest('[data-sio-copy-report]');
			if (copyReportButton) {
				copyDiagnosticReport(copyReportButton);
			}
		});
	});
}());
