# weblyarts/connect-sdk-core

Core package of **WeblyConnect**, the official WeblySuite integration SDK.

Framework-agnostic PHP building blocks: Connect tenant token handling, a single cURL-based
HTTP transport (including SSE streaming), the WeblySuite backend REST client, and the chat
streaming proxy decoder.

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
| `AgentHub\AgentHubClient` | Non-streaming REST calls to the WeblySuite agent backend: agents CRUD, tool catalog, model catalog, non-streaming chat. |
| `AgentHub\AgentHubChatStreamer` | Streams chat inference from the WeblySuite agent backend and decodes each SSE event, forwarding it through a callback. Retries transient upstream failures before any bytes are seen; never retries after the first byte to avoid duplicate output. |
| `Auth\StaticTokenStore` | Fixed-token `TokenStoreInterface` for site bootstrap config (no env lookup in endpoints). |
| `Site\SiteConnect` | Single init object for a handmade site: agent id, dossier id, token, widget UI, stream handler. |
| `Site\ConnectWidgetUi` | Browser-safe widget appearance (stream path, title, colors, i18n). |

## Handmade site bootstrap

Declare the integration once in PHP (for example `config/site-connect.php`). Endpoints
only require that file:

```php
use WeblyConnect\Sdk\Auth\StaticTokenStore;
use WeblyConnect\Sdk\Site\ConnectWidgetUi;
use WeblyConnect\Sdk\Site\SiteConnect;

return new SiteConnect(
    tokenStore: new StaticTokenStore('wbly_live_…'),
    agentId: 'your-hub-agent-uuid',
    widget: new ConnectWidgetUi(streamPath: '/stream.php', botTitle: 'Support'),
);

// RAG dossier binding (if any) is set once on the Hub agent itself via
// `rag_config.agent_ids`, not per SiteConnect instance. See docs/hub-rag-stack.md
// in the WeblySuite public docs.
```

```php
// stream.php
$connect = require __DIR__ . '/config/site-connect.php';
$connect->handleStreamRequest();
```

```php
// layout snippet (widget init, no secrets in JS)
$widgetConfig = $connect->widgetBrowserConfig();
```

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
