# weblyarts/connect-sdk-core

Framework-agnostic PHP SDK for integrating with WeblyAgentHub: token handling, a single
cURL-based HTTP transport (including SSE streaming), the AgentHub REST client, and the
chat streaming proxy decoder.

No WordPress function is called anywhere in `src/`. Host applications (WordPress
plugins, handmade PHP backends) provide the pieces that are inherently
platform-specific by implementing `TokenStoreInterface` and wiring the other classes.

## Building blocks

| Class | Responsibility |
|---|---|
| `Auth\TokenStoreInterface` | Contract for persisting the tenant WeblyToken. Implement against `wp_options`, a config file, env var, etc. |
| `Auth\JwtClaims` | Reads unverified claims from a `wbly_live_*` token (billing scope, subject, expiry). Never a substitute for server-side signature verification. |
| `Auth\TenantAuth` | Validates a token's format/scope before persisting it, and exposes the current bearer token to callers. |
| `Http\HttpClientInterface` / `CurlHttpClient` | The only HTTP transport in the SDK. Used for both regular JSON calls and SSE streaming, so there is one code path instead of `wp_remote_*` plus a separate streaming hack. |
| `AgentHub\AgentHubClient` | Non-streaming REST calls: agents CRUD, tool catalog, model catalog, non-streaming chat. |
| `AgentHub\AgentHubChatStreamer` | Streams `POST /agents/{id}/chat` and decodes each Anthropic-style SSE event, forwarding it through a callback. Retries transient upstream failures before any bytes are seen; never retries after the first byte to avoid duplicate output. |

## Usage sketch

```php
use WeblyConnect\Sdk\Auth\TenantAuth;
use WeblyConnect\Sdk\Http\CurlHttpClient;
use WeblyConnect\Sdk\AgentHub\AgentHubClient;
use WeblyConnect\Sdk\AgentHub\AgentHubChatStreamer;

$http = new CurlHttpClient();
$auth = new TenantAuth($myTokenStore); // implements TokenStoreInterface
$client = new AgentHubClient($http, $auth, 'https://agenthub.weblyarts.com');

$reply = $client->chat('my-agent-id', 'Hello!');

// Streaming (SSE), e.g. from a plain PHP endpoint or a WordPress admin-ajax handler:
$streamer = new AgentHubChatStreamer($http, $auth, 'https://agenthub.weblyarts.com');
$streamer->stream(
    agentId: 'my-agent-id',
    message: 'Hello!',
    sessionId: $sessionId,
    extra: [],
    onEvent: function (string $event, array $payload) {
        echo 'data: ' . json_encode(['event' => $event, 'payload' => $payload]) . "\n\n";
        @flush();
    },
);
```

The host adapter is responsible for: setting SSE response headers, disabling output
buffering, authenticating the inbound request (nonce, API key, ...), and any storage
side effects (quota, session bookkeeping) triggered via the optional `onFirstByte`
callback.
