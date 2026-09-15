/**
 * WeblyConnect handmade chat widget.
 * Config via window.weblyConnectWidget from PHP only. No agent_id in the browser.
 */
(function () {
	'use strict';

	const Stream = window.WeblyConnectHandmadeStream;

	function cfg() {
		return window.weblyConnectWidget || {};
	}

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

	function mount(root) {
		if (!Stream) {
			return;
		}

		const c = cfg();
		const title = c.botTitle || 'Chat';
		const avatarUrl = c.avatarUrl || '';
		const welcome = (c.i18n && c.i18n.defaultWelcome) || 'Hi! How can I help you today?';
		const primary = c.primaryRgb || '99, 102, 241';

		const position =
			c.launcherPosition === 'bottom-left' ? 'bottom-left' : 'bottom-right';

		root.innerHTML =
			'<div class="wcw-box" data-position="' +
			position +
			'" style="--wcw-primary-rgb:' +
			escapeHtml(primary) +
			'">' +
			'<button type="button" class="wcw-launcher" aria-label="' +
			escapeHtml(title) +
			'">' +
			(avatarUrl
				? '<img src="' + escapeHtml(avatarUrl) + '" alt="" class="wcw-launcher-avatar">'
				: '<span class="wcw-launcher-icon">💬</span>') +
			'</button>' +
			'<div class="wcw-panel" hidden>' +
			'<header class="wcw-header">' +
			(avatarUrl
				? '<img src="' + escapeHtml(avatarUrl) + '" alt="" class="wcw-header-avatar">'
				: '') +
			'<h2 class="wcw-header-title">' +
			escapeHtml(title) +
			'</h2>' +
			'<button type="button" class="wcw-close" aria-label="Close">×</button>' +
			'</header>' +
			'<div class="wcw-messages">' +
			'<div class="wcw-msg wcw-msg-bot"><div class="wcw-bubble">' +
			escapeHtml(welcome) +
			'</div></div>' +
			'</div>' +
			'<footer class="wcw-footer">' +
			'<textarea class="wcw-input" rows="1" placeholder="' +
			escapeHtml((c.i18n && c.i18n.typeMessage) || 'Type a message') +
			'"></textarea>' +
			'<button type="button" class="wcw-send" aria-label="' +
			escapeHtml((c.i18n && c.i18n.sendMessage) || 'Send') +
			'">➤</button>' +
			'</footer>' +
			'</div>' +
			'</div>';

		const launcher = root.querySelector('.wcw-launcher');
		const panel = root.querySelector('.wcw-panel');
		const closeBtn = root.querySelector('.wcw-close');
		const messagesEl = root.querySelector('.wcw-messages');
		const input = root.querySelector('.wcw-input');
		const sendBtn = root.querySelector('.wcw-send');

		let busy = false;
		let liveBubble = null;

		function scrollMessages() {
			messagesEl.scrollTop = messagesEl.scrollHeight;
		}

		function appendUser(text) {
			const row = document.createElement('div');
			row.className = 'wcw-msg wcw-msg-user';
			row.innerHTML = '<div class="wcw-bubble">' + escapeHtml(text) + '</div>';
			messagesEl.appendChild(row);
			scrollMessages();
		}

		function appendBotPlaceholder() {
			const row = document.createElement('div');
			row.className = 'wcw-msg wcw-msg-bot';
			row.innerHTML =
				'<div class="wcw-bubble wcw-bubble-live"><span class="wcw-typing">' +
				escapeHtml((c.i18n && c.i18n.connecting) || '…') +
				'</span></div>';
			messagesEl.appendChild(row);
			scrollMessages();
			return row.querySelector('.wcw-bubble');
		}

		function setOpen(open) {
			panel.hidden = !open;
			launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) {
				input.focus();
			}
		}

		launcher.addEventListener('click', function () {
			setOpen(panel.hidden);
		});
		closeBtn.addEventListener('click', function () {
			setOpen(false);
		});

		function submit() {
			const text = (input.value || '').trim();
			if (!text || busy) {
				return;
			}
			busy = true;
			input.value = '';
			appendUser(text);
			liveBubble = appendBotPlaceholder();

			Stream.send({
				message: text,
				onDelta: function (answer) {
					if (!liveBubble) {
						return;
					}
					liveBubble.innerHTML = renderMarkdown(answer);
					scrollMessages();
				},
				onComplete: function (answer) {
					if (liveBubble && answer) {
						liveBubble.innerHTML = renderMarkdown(answer);
					}
					liveBubble = null;
					busy = false;
					scrollMessages();
				},
				onError: function (errMsg) {
					if (liveBubble) {
						liveBubble.innerHTML =
							'⚠️ ' + escapeHtml(errMsg || (c.i18n && c.i18n.connectionError) || 'Error');
					}
					liveBubble = null;
					busy = false;
					scrollMessages();
				},
			});
		}

		sendBtn.addEventListener('click', submit);
		input.addEventListener('keydown', function (ev) {
			if (ev.key === 'Enter' && !ev.shiftKey) {
				ev.preventDefault();
				submit();
			}
		});

		if (c.autoOpen) {
			setTimeout(function () {
				setOpen(true);
			}, typeof c.autoOpenDelayMs === 'number' ? c.autoOpenDelayMs : 0);
		}
	}

	function init() {
		const selector = (cfg().mountSelector || '#weblyconnect-widget');
		const root = document.querySelector(selector);
		if (root) {
			mount(root);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	window.WeblyConnectHandmadeWidget = { mount: mount, init: init };
})();
