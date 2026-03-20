(function() {
	'use strict';

	var APP_ID = 'simpledms_integration';
	var ACTION_ID = 'simpledms-upload';
	var ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l4 4h-3v7h-2V7H8l4-4zm-7 12h14v6H5v-6zm2 2v2h10v-2H7z" fill="currentColor"/></svg>';

	function translate(text) {
		if (typeof window.t === 'function') {
			return window.t(APP_ID, text);
		}
		return text;
	}

	function showNotice(message) {
		if (window.OC && OC.Notification && typeof OC.Notification.showTemporary === 'function') {
			OC.Notification.showTemporary(message);
		}
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

	function tryParseJson(text) {
		if (typeof text !== 'string' || text.trim() === '') {
			return null;
		}

		try {
			return JSON.parse(text);
		} catch (error) {
			return null;
		}
	}

	function trimTrailingSlashes(value) {
		return value.replace(/\/+$/, '');
	}

	function splitPath(path) {
		if (!path || path === '/') {
			return [];
		}
		return path.split('/').filter(Boolean);
	}

	function isFolderType(type) {
		return type === 'dir' || type === 'folder';
	}

	function isFolderNode(node) {
		if (!node) {
			return false;
		}

		var type = node.type;
		if (typeof type === 'function') {
			try {
				type = type();
			} catch (error) {
				type = undefined;
			}
		}

		return isFolderType(type);
	}

	function resolveDirectory(context) {
		if (context && typeof context.dir === 'string') {
			return context.dir;
		}

		if (context && context.fileInfoModel && typeof context.fileInfoModel.get === 'function') {
			var path = context.fileInfoModel.get('path');
			if (typeof path === 'string') {
				return path;
			}
		}

		return '/';
	}

	function buildLegacyPath(fileName, context) {
		var parts = splitPath(resolveDirectory(context)).concat([fileName]).filter(Boolean);
		return '/' + parts.join('/');
	}

	function extractPathFromDavSource(source) {
		if (typeof source !== 'string' || source.length === 0) {
			return '';
		}

		try {
			var parsed = new URL(source, window.location.origin);
			var marker = '/remote.php/dav/files/';
			var index = parsed.pathname.indexOf(marker);
			if (index < 0) {
				return '';
			}

			var suffix = parsed.pathname.slice(index + marker.length);
			var segments = suffix.split('/').filter(Boolean);
			if (segments.length <= 1) {
				return '';
			}

			segments.shift();
			return '/' + segments.map(function(segment) {
				return decodeURIComponent(segment);
			}).join('/');
		} catch (error) {
			return '';
		}
	}

	function getNodePath(node) {
		if (!node) {
			return '';
		}

		var bySource = extractPathFromDavSource(node.encodedSource || node.source);
		if (bySource !== '') {
			return bySource;
		}

		if (typeof node.path === 'string' && node.path !== '') {
			var rawPath = node.path[0] === '/' ? node.path : '/' + node.path;
			var uid = (window.OC && OC.getCurrentUser && OC.getCurrentUser()) ? OC.getCurrentUser().uid : '';
			if (uid && rawPath.indexOf('/files/' + uid + '/') === 0) {
				return rawPath.substring(('/files/' + uid).length);
			}
			if (uid && rawPath.indexOf('/' + uid + '/files/') === 0) {
				return rawPath.substring(('/' + uid + '/files').length);
			}

			return rawPath;
		}

		if (typeof node.basename === 'string' && node.basename !== '') {
			return '/' + node.basename;
		}

		return '';
	}

	async function getConfig() {
		var requestToken = getRequestToken();
		var response = await fetch(generateAppUrl('/apps/' + APP_ID + '/api/config'), {
			method: 'GET',
			headers: requestToken === '' ? {} : { requesttoken: requestToken },
			credentials: 'same-origin',
		});

		var raw = await response.text();
		var json = tryParseJson(raw);

		if (!response.ok) {
			var message = (json && json.message) ? json.message : (translate('Could not read SimpleDMS settings.') + ' (HTTP ' + response.status + ')');
			throw new Error(message);
		}

		if (!json) {
			throw new Error(translate('Could not read SimpleDMS settings. Invalid response payload.'));
		}

		return json;
	}

	async function createSignedDownloadUrl(path) {
		var requestToken = getRequestToken();
		var payload = new FormData();
		payload.append('path', path);
		if (requestToken !== '') {
			payload.append('requesttoken', requestToken);
		}

		var response = await fetch(generateAppUrl('/apps/' + APP_ID + '/api/create-signed-url'), {
			method: 'POST',
			headers: requestToken === '' ? {} : { requesttoken: requestToken },
			credentials: 'same-origin',
			body: payload,
		});

		var raw = await response.text();
		var json = tryParseJson(raw);

		if (!response.ok) {
			var message = (json && json.message) ? json.message : (translate('Could not create a signed URL.') + ' (HTTP ' + response.status + ')');
			throw new Error(message);
		}

		if (!json || typeof json.downloadUrl !== 'string' || json.downloadUrl === '') {
			throw new Error(translate('Signed download URL was not returned.'));
		}

		return json.downloadUrl;
	}

	function openSimpleDmsImport(baseUrl, downloadUrl) {
		var target = trimTrailingSlashes(baseUrl) + '/open-file/from-url?url=' + encodeURIComponent(downloadUrl);
		var win = window.open(target, '_blank');
		if (!win) {
			window.location.href = target;
		}
	}

	async function uploadFromPath(path) {
		if (!path || path === '/') {
			throw new Error(translate('Could not resolve selected file path.'));
		}

		showNotice(translate('Preparing upload to SimpleDMS...'));

		var config = await getConfig();
		var baseUrl = (config.simpledmsBaseUrl || '').trim();
		if (!baseUrl) {
			throw new Error(translate('SimpleDMS is not configured by the administrator.'));
		}

		var downloadUrl = await createSignedDownloadUrl(path);
		openSimpleDmsImport(baseUrl, downloadUrl);
		showNotice(translate('Opening SimpleDMS import...'));
	}

	function registerLegacyFileAction() {
		if (!window.OCA || !OCA.Files || !OCA.Files.fileActions || typeof OCA.Files.fileActions.registerAction !== 'function') {
			return false;
		}

		OCA.Files.fileActions.registerAction({
			name: ACTION_ID,
			displayName: translate('Upload to SimpleDMS'),
			mime: 'all',
			permissions: (window.OC && typeof OC.PERMISSION_READ !== 'undefined') ? OC.PERMISSION_READ : 1,
			actionHandler: function(fileName, context) {
				if (context && context.fileInfoModel && typeof context.fileInfoModel.get === 'function' && isFolderType(context.fileInfoModel.get('type'))) {
					showNotice(translate('Only files can be uploaded to SimpleDMS.'));
					return;
				}

				void uploadFromPath(buildLegacyPath(fileName, context)).catch(function(error) {
					showNotice(error.message || translate('Upload to SimpleDMS failed.'));
				});
			},
			iconClass: 'icon-upload',
			type: OCA.Files.FileActions.TYPE_DROPDOWN,
		});

		return true;
	}

	function registerModernFileAction() {
		if (!window._nc_files_scope || !window._nc_files_scope.v4_0) {
			return false;
		}

		var scoped = window._nc_files_scope.v4_0;
		scoped.fileActions = scoped.fileActions || new Map();
		if (scoped.fileActions.has(ACTION_ID)) {
			return true;
		}

		var action = {
			id: ACTION_ID,
			displayName: function() {
				return translate('Upload to SimpleDMS');
			},
			iconSvgInline: function() {
				return ICON_SVG;
			},
			enabled: function(context) {
				return !!(context && Array.isArray(context.nodes) && context.nodes.length === 1 && !isFolderNode(context.nodes[0]));
			},
			exec: async function(context) {
				if (!context || !Array.isArray(context.nodes) || context.nodes.length !== 1) {
					showNotice(translate('Please select exactly one file.'));
					return false;
				}

				try {
					await uploadFromPath(getNodePath(context.nodes[0]));
					return true;
				} catch (error) {
					showNotice(error.message || translate('Upload to SimpleDMS failed.'));
					return false;
				}
			},
		};

		scoped.fileActions.set(ACTION_ID, action);

		if (scoped.registry) {
			var event = new CustomEvent('register:action', { detail: action });
			if (typeof scoped.registry.dispatchTypedEvent === 'function') {
				scoped.registry.dispatchTypedEvent('register:action', event);
			} else if (typeof scoped.registry.dispatchEvent === 'function') {
				scoped.registry.dispatchEvent(event);
			}
		}

		return true;
	}

	function registerWhenReady(triesLeft) {
		if (registerModernFileAction() || registerLegacyFileAction()) {
			return;
		}

		if (triesLeft <= 0) {
			return;
		}

		window.setTimeout(function() {
			registerWhenReady(triesLeft - 1);
		}, 250);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			registerWhenReady(80);
		});
	} else {
		registerWhenReady(80);
	}
})();
