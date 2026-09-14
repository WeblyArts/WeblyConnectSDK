# Quickstart: WeblyConnect SDK in a handmade PHP project

You do not need WordPress to use WeblySuite. This guide gets a plain PHP project
talking to AgentHub (chat, streaming) and, optionally, exposing its own tools back to
WeblySuite agents, in about 10 minutes.

If you are building a WordPress plugin, use the same packages the official plugins
use (`WeblySuitePress`, `WeblyMCPress`) as a reference instead of this guide.

## 1. Prerequisites

- PHP 8.1+ with `ext-curl` and `ext-json`.
- Composer.
- A WeblySuite tenant. From the WeblySuite console, authorize a **WeblyConnect**
  integration to get a token. It looks like `wbly_live_…` and its `billing_scope`
  claim is `weblyconnect`. `TenantAuth` (below) rejects any other token, so you
  cannot accidentally reuse a token issued for a different WeblySuite product.
- A Hub agent id (`YOUR_HUB_AGENT_ID`), created via `POST /agents` on AgentHub or
  from the WeblySuite console. See the [AgentHub guide](https://suite.weblyarts.com/docs/agenthub).

Treat the token like a password: never commit it, never send it to a browser, store
it in an env var or secret manager.

## 2. Install

This SDK is not on Packagist yet; install it straight from GitHub:

```bash
composer require weblyarts/connect-sdk-core:dev-main
```

If Composer cannot resolve the package, add the repository explicitly in your
`composer.json`:

```json
{
    "require": {
        "weblyarts/connect-sdk-core": "dev-main"
    },
    "repositories": [
        { "type": "vcs", "url": "https://github.com/WeblyArts/WeblyConnectSDK" }
    ]
}
```

Add `weblyarts/connect-sdk-mcp:dev-main` too if you also want to expose your own
tools (step 5).

## 3. Store the token

Implement `TokenStoreInterface` for however your project keeps secrets. The
simplest version, backed by an environment variable:

```php
<?php

use WeblyConnect\Sdk\Auth\TokenStoreInterface;

final class EnvTokenStore implements TokenStoreInterface
{
    public function getToken(): string
    {
        return (string) (getenv('WEBLYCONNECT_TOKEN') ?: '');
    }

    public function saveToken(string $plainToken): bool
    {
        // Env vars are read-only at runtime; persist elsewhere if you need
        // to accept tokens from an admin UI instead of a fixed env var.
        return false;
    }

    public function clear(): void
    {
    }
}
```

## 4. Call AgentHub (non-streaming)

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use WeblyConnect\Sdk\Auth\TenantAuth;
use WeblyConnect\Sdk\Http\CurlHttpClient;
use WeblyConnect\Sdk\AgentHub\AgentHubClient;

$auth = new TenantAuth(new EnvTokenStore());
$client = new AgentHubClient(new CurlHttpClient(), $auth, 'https://agenthub.weblyarts.com');

$reply = $client->chat('YOUR_HUB_AGENT_ID', 'What is on the roadmap this week?');

echo $reply['answer'], "\n";
```

## 5. Stream a chat reply (SSE)

Streaming needs a real HTTP entry point (`wp_remote_*`-style buffered clients cannot
do this). From a plain PHP script served by any web server:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use WeblyConnect\Sdk\Auth\TenantAuth;
use WeblyConnect\Sdk\Http\CurlHttpClient;
use WeblyConnect\Sdk\AgentHub\AgentHubChatStreamer;

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
while (ob_get_level() > 0) {
    ob_end_flush();
}

$auth = new TenantAuth(new EnvTokenStore());
$streamer = new AgentHubChatStreamer(new CurlHttpClient(), $auth, 'https://agenthub.weblyarts.com');

$streamer->stream(
    agentId: 'YOUR_HUB_AGENT_ID',
    message: $_GET['message'] ?? 'Hello!',
    sessionId: $_GET['session_id'] ?? '',
    extra: [],
    onEvent: function (string $event, array $payload) {
        echo 'data: ' . json_encode(['event' => $event, 'payload' => $payload]) . "\n\n";
        @flush();
    },
);
```

Point your frontend `EventSource` at this script.

## 6. Expose your own tools over MCP (optional)

If your project has data or actions that a WeblySuite agent should be able to call
(look up an order, check inventory, ...), turn your backend into a federated MCP
server with `connect-sdk-mcp`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use WeblyConnect\Sdk\Mcp\BearerTokenAuthenticator;
use WeblyConnect\Sdk\Mcp\McpServer;
use WeblyConnect\Sdk\Mcp\ToolCallResult;
use WeblyConnect\Sdk\Mcp\ToolDefinition;
use WeblyConnect\Sdk\Mcp\ToolRegistry;

$registry = new ToolRegistry();
$registry->register(new ToolDefinition(
    name: 'lookup_order',
    description: 'Looks up an order by ID in our own database.',
    inputSchema: ['type' => 'object', 'properties' => ['order_id' => ['type' => 'string']], 'required' => ['order_id']],
    handler: function (array $args): ToolCallResult {
        $order = MyOrders::find($args['order_id']);
        return $order === null
            ? ToolCallResult::error('Order not found.')
            : ToolCallResult::json($order->toArray());
    }
));

$server = new McpServer($registry, new BearerTokenAuthenticator(getenv('MCP_SHARED_SECRET')));

$response = $server->handleHttpRequest(
    $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    file_get_contents('php://input')
);

http_response_code($response->statusCode);
header('Content-Type: application/json');
echo $response->jsonBody;
```

Deploy that script behind any URL ending in `/mcp` (e.g. `mcp.php`), generate your
own `MCP_SHARED_SECRET`, then register the endpoint and secret as a federated MCP
server in your tenant config. Full wire contract: `docs/BACKEND_CONTRACT.md`.

## 7. Full runnable example

A complete, runnable version of steps 4-6 (with a built-in PHP server, no
WordPress) lives in [`examples/handmade-php`](examples/handmade-php). Run it with:

```bash
cd examples/handmade-php
composer install
WEBLYCONNECT_TOKEN=wbly_live_... HUB_AGENT_ID=your-agent php -S localhost:8080
```

Then open `http://localhost:8080/chat.php` (JSON reply) or
`http://localhost:8080/stream.php?message=hello` (SSE, watch it in a browser
`EventSource` or `curl -N`).

## Next steps

- `php/connect-sdk-core/README.md` — full class reference for auth, HTTP, chat.
- `php/connect-sdk-mcp/README.md` — full class reference for MCP tool exposure.
- `docs/BACKEND_CONTRACT.md` — exact MCP wire contract if you want to implement it
  in another language.
- [AgentHub API reference](https://suite.weblyarts.com/docs/agenthub) — every field
  `chat()` and the streamer accept, plus the SSE event catalog.
