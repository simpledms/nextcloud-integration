(function() {
	'use strict';

	var APP_ID = 'simpledms_integration';

	function translate(text) {
		if (typeof window.t === 'function') {
			return window.t(APP_ID, text);
		}
		return text;
	}

	function showNotice(message, isError) {
		if (window.OC && OC.Notification && typeof OC.Notification.showTemporary === 'function') {
			OC.Notification.showTemporary(message);
		}

		var status = document.getElementById('simpledms-save-status');
		if (!status) {
			return;
		}

		status.textContent = message;
		status.style.color = isError ? '#c62828' : '#1e7e34';
	}

	function generateAppUrl(path) {
		if (window.OC && typeof OC.generateUrl === 'function') {
			return OC.generateUrl(path);
		}
		return path;
	}

	function getRequestToken() {
		if (window.OC && typeof OC.requestToken === 'string' && OC.requestToken !== '') {
			return OC.requestToken;
		}

		var meta = document.querySelector('meta[name="requesttoken"]');
		if (meta && typeof meta.content === 'string' && meta.content !== '') {
			return meta.content;
		}

		return '';
	}

	function parseJsonSafely(text) {
		if (typeof text !== 'string' || text.trim() === '') {
			return null;
		}

		try {
			return JSON.parse(text);
		} catch (error) {
			return null;
		}
	}

	async function saveConfig() {
		var input = document.getElementById('simpledms-base-url');
		var button = document.getElementById('simpledms-save');
		if (!input || !button) {
			return;
		}

		button.disabled = true;
		showNotice(translate('Saving...'), false);

		var payload = new FormData();
		var requestToken = getRequestToken();
		payload.append('simpledmsBaseUrl', input.value.trim());
		if (requestToken !== '') {
			payload.append('requesttoken', requestToken);
		}

		try {
			var response = await fetch(generateAppUrl('/apps/' + APP_ID + '/api/config'), {
				method: 'POST',
				headers: requestToken === '' ? {} : { requesttoken: requestToken },
				credentials: 'same-origin',
				body: payload,
			});

			var raw = await response.text();
			var json = parseJsonSafely(raw);
			if (!response.ok) {
				throw new Error((json && json.message) ? json.message : (translate('Could not save settings.') + ' (HTTP ' + response.status + ')'));
			}

			if (!json) {
				throw new Error(translate('Could not save settings. Invalid server response.'));
			}

			input.value = json.simpledmsBaseUrl || '';
			showNotice(translate('Settings saved.'), false);
		} catch (error) {
			showNotice(error.message || translate('Could not save settings.'), true);
		} finally {
			button.disabled = false;
		}
	}

	function init() {
		var root = document.getElementById('simpledms-nextcloud-admin-settings');
		if (!root) {
			return;
		}

		var button = document.getElementById('simpledms-save');
		if (!button) {
			return;
		}

		button.addEventListener('click', function() {
			void saveConfig();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
