/**
 * @microsoft/fetch-event-source v2.0.1 (MIT) · bundled for WordPress (no ESM).
 * https://github.com/Azure/fetch-event-source
 */
(function (global) {
	'use strict';

	var EventStreamContentType = 'text/event-stream';
	var DefaultRetryInterval = 1000;

	function concat(a, b) {
		var res = new Uint8Array(a.length + b.length);
		res.set(a);
		res.set(b, a.length);
		return res;
	}

	function newMessage() {
		return { data: '', event: '', id: '', retry: undefined };
	}

	function getBytesFixed(stream, onChunk) {
		var reader = stream.getReader();
		function pump() {
			return reader.read().then(function (result) {
				if (result.value) {
					onChunk(result.value);
				}
				if (result.done) {
					return;
				}
				return pump();
			});
		}
		return pump();
	}

	function getLines(onLine) {
		var buffer;
		var position;
		var fieldLength;
		var discardTrailingNewline = false;

		return function onChunk(arr) {
			if (buffer === undefined) {
				buffer = arr;
				position = 0;
				fieldLength = -1;
			} else {
				buffer = concat(buffer, arr);
			}
			var bufLength = buffer.length;
			var lineStart = 0;
			while (position < bufLength) {
				if (discardTrailingNewline) {
					if (buffer[position] === 10) {
						lineStart = ++position;
					}
					discardTrailingNewline = false;
				}
				var lineEnd = -1;
				for (; position < bufLength && lineEnd === -1; ++position) {
					switch (buffer[position]) {
						case 58:
							if (fieldLength === -1) {
								fieldLength = position - lineStart;
							}
							break;
						case 13:
							discardTrailingNewline = true;
							// fallthrough
						case 10:
							lineEnd = position;
							break;
					}
				}
				if (lineEnd === -1) {
					break;
				}
				onLine(buffer.subarray(lineStart, lineEnd), fieldLength);
				lineStart = position;
				fieldLength = -1;
			}
			if (lineStart === bufLength) {
				buffer = undefined;
			} else if (lineStart !== 0) {
				buffer = buffer.subarray(lineStart);
				position -= lineStart;
			}
		};
	}

	function getMessages(onId, onRetry, onMessage) {
		var message = newMessage();
		var decoder = new TextDecoder();
		return function onLine(line, fieldLength) {
			if (line.length === 0) {
				if (onMessage) {
					onMessage(message);
				}
				message = newMessage();
			} else if (fieldLength > 0) {
				var field = decoder.decode(line.subarray(0, fieldLength));
				var valueOffset = fieldLength + (line[fieldLength + 1] === 32 ? 2 : 1);
				var value = decoder.decode(line.subarray(valueOffset));
				switch (field) {
					case 'data':
						message.data = message.data ? message.data + '\n' + value : value;
						break;
					case 'event':
						message.event = value;
						break;
					case 'id':
						message.id = value;
						if (onId) {
							onId(value);
						}
						break;
					case 'retry':
						var retry = parseInt(value, 10);
						if (!isNaN(retry) && onRetry) {
							onRetry(retry);
						}
						break;
				}
			}
		};
	}

	function defaultOnOpen(response) {
		var contentType = response.headers.get('content-type') || '';
		if (contentType.indexOf(EventStreamContentType) === -1) {
			throw new Error('Expected content-type ' + EventStreamContentType + ', got: ' + contentType);
		}
	}

	function fetchEventSource(input, options) {
		options = options || {};
		var inputSignal = options.signal;
		var inputHeaders = options.headers || {};
		var inputOnOpen = options.onopen;
		var onmessage = options.onmessage;
		var onclose = options.onclose;
		var onerror = options.onerror;
		var openWhenHidden = options.openWhenHidden;
		var inputFetch = options.fetch;
		var rest = {};
		var k;
		for (k in options) {
			if (
				Object.prototype.hasOwnProperty.call(options, k) &&
				k !== 'signal' &&
				k !== 'headers' &&
				k !== 'onopen' &&
				k !== 'onmessage' &&
				k !== 'onclose' &&
				k !== 'onerror' &&
				k !== 'openWhenHidden' &&
				k !== 'fetch'
			) {
				rest[k] = options[k];
			}
		}

		return new Promise(function (resolve, reject) {
			var headers = {};
			for (k in inputHeaders) {
				if (Object.prototype.hasOwnProperty.call(inputHeaders, k)) {
					headers[k] = inputHeaders[k];
				}
			}
			if (!headers.accept) {
				headers.accept = EventStreamContentType;
			}

			var curRequestController;
			var retryInterval = DefaultRetryInterval;
			var retryTimer = 0;

			function onVisibilityChange() {
				curRequestController.abort();
				if (!document.hidden) {
					create();
				}
			}

			if (!openWhenHidden) {
				document.addEventListener('visibilitychange', onVisibilityChange);
			}

			function dispose() {
				document.removeEventListener('visibilitychange', onVisibilityChange);
				window.clearTimeout(retryTimer);
				if (curRequestController) {
					curRequestController.abort();
				}
			}

			if (inputSignal) {
				inputSignal.addEventListener('abort', function () {
					dispose();
					resolve();
				});
			}

			var fetchFn = inputFetch || global.fetch;
			var onopen = inputOnOpen || defaultOnOpen;

			function create() {
				curRequestController = new AbortController();
				var req = {};
				for (k in rest) {
					if (Object.prototype.hasOwnProperty.call(rest, k)) {
						req[k] = rest[k];
					}
				}
				req.headers = headers;
				req.signal = curRequestController.signal;

				fetchFn(input, req)
					.then(function (response) {
						return onopen(response).then(function () {
							return response;
						});
					})
					.then(function (response) {
						return getBytesFixed(
							response.body,
							getLines(
								getMessages(
									function () {},
									function (retry) {
										retryInterval = retry;
									},
									onmessage
								)
							)
						);
					})
					.then(function () {
						if (onclose) {
							onclose();
						}
						dispose();
						resolve();
					})
					.catch(function (err) {
						if (curRequestController.signal.aborted) {
							return;
						}
						try {
							if (onerror) {
								var interval = onerror(err);
								if (interval === null || interval === undefined) {
									dispose();
									reject(err);
									return;
								}
								window.clearTimeout(retryTimer);
								retryTimer = window.setTimeout(create, interval);
								return;
							}
							dispose();
							reject(err);
						} catch (innerErr) {
							dispose();
							reject(innerErr);
						}
					});
			}

			create();
		});
	}

	global.fetchEventSource = fetchEventSource;
})(typeof window !== 'undefined' ? window : this);
