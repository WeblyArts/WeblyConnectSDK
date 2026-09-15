/**
 * WeblyConnect handmade stream kit.
 * Posts to a server-side streamUrl only. Never sends agent_id or dossier_id.
 */
(function () {
	'use strict';

	const cfg = () => window.weblyConnectWidget || {};
	let streamController = null;
	let currentSessionId = '';

	function escapeHtml(str) {
		if (!str) {
			return '';
		}
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function renderMarkdown(text) {
		if (!text) {
			return '';
		}
		let html = escapeHtml(text);
		html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
		html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
		html = html.replace(/`([^`]+)`/g, '<code class="wcw-inline-code">$1</code>');
		html = html.replace(/\n\n+/g, '</p><p class="wcw-p">');
		html = '<p class="wcw-p">' + html + '</p>';
		html = html.replace(/\n/g, '<br>');
		return html;
	}

	function handleEvent(ev, state) {
		const event = ev.event || '';
		const payload = ev.payload || {};

		if (event === 'session' && payload.session_id) {
			currentSessionId = String(payload.session_id);
			if (typeof state.onSession === 'function') {
				state.onSession(currentSessionId);
			}
			return;
		}

		if (event === 'react_answer') {
			const answer = payload.content || payload.answer || '';
			if (answer) {
				state.answerText = String(answer);
				if (typeof state.onDelta === 'function') {
					state.onDelta(state.answerText);
				}
			}
			return;
		}

		if (event === 'error') {
			const msg = payload.message || (cfg().i18n && cfg().i18n.connectionError) || 'Stream error';
			state.failed = true;
			state.errorMsg = String(msg);
			return;
		}

		if (event === 'done') {
			state.done = true;
		}
	}

	async function send(options) {
		options = options || {};
		const message = (options.message || '').trim();
		if (!message) {
			return;
		}

		const c = cfg();
		const streamUrl = options.streamUrl || c.streamUrl || '';
		if (!streamUrl) {
			if (typeof options.onError === 'function') {
				options.onError((c.i18n && c.i18n.connectionError) || 'Missing streamUrl');
			}
			return;
		}

		if (streamController) {
			streamController.abort();
		}
		const thisController = new AbortController();
		streamController = thisController;

		const state = {
			answerText: '',
			failed: false,
			errorMsg: '',
			done: false,
		};

		if (typeof options.onStart === 'function') {
			options.onStart();
		}

		const body = {
			message: message,
			session_id: currentSessionId || '',
		};

		try {
			if (typeof fetchEventSource !== 'function') {
				throw new Error((c.i18n && c.i18n.connectionError) || 'SSE unavailable');
			}

			await fetchEventSource(streamUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(body),
				credentials: 'same-origin',
				cache: 'no-store',
				signal: thisController.signal,
				openWhenHidden: true,
				onopen: async function (response) {
					if (!response.ok) {
						const errMsg =
							(c.i18n && c.i18n.connectionError) || 'HTTP ' + response.status;
						state.failed = true;
						state.errorMsg = errMsg;
						throw new Error(errMsg);
					}
					const contentType = response.headers.get('content-type') || '';
					if (contentType.indexOf('text/event-stream') === -1) {
						state.failed = true;
						state.errorMsg = (c.i18n && c.i18n.connectionError) || 'Invalid stream';
						throw new Error(state.errorMsg);
					}
				},
				onmessage: function (ev) {
					if (!ev.data) {
						return;
					}
					let parsed;
					try {
						parsed = JSON.parse(ev.data);
					} catch (parseErr) {
						return;
					}
					if (parsed && parsed.event) {
						handleEvent(
							{ event: parsed.event, payload: parsed.payload || {} },
							state
						);
					}
				},
				onerror: function (err) {
					if (err && err.name === 'AbortError') {
						return null;
					}
					state.failed = true;
					if (err && err.message) {
						state.errorMsg = String(err.message);
					}
					return null;
				},
			});

			if (state.failed && !state.answerText) {
				if (typeof options.onError === 'function') {
					options.onError(state.errorMsg || (c.i18n && c.i18n.connectionError) || 'Error');
				}
				return;
			}

			if (typeof options.onComplete === 'function') {
				options.onComplete(state.answerText);
			}
		} catch (err) {
			if (err && err.name === 'AbortError') {
				return;
			}
			if (typeof options.onError === 'function') {
				options.onError(
					state.errorMsg ||
						(err && err.message ? String(err.message) : '') ||
						(c.i18n && c.i18n.connectionError) ||
						'Error'
				);
			}
		} finally {
			if (streamController === thisController) {
				streamController = null;
			}
		}
	}

	function stop() {
		if (streamController) {
			streamController.abort();
			streamController = null;
		}
	}

	function resetSession() {
		currentSessionId = '';
		stop();
	}

	window.WeblyConnectHandmadeStream = {
		send: send,
		stop: stop,
		resetSession: resetSession,
		getSessionId: function () {
			return currentSessionId;
		},
	};
})();
