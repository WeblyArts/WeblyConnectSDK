# weblyarts/connect-sdk-mcp

Turns any PHP backend into an MCP tool server that WeblyAgentHub's federation layer
can call, so tools you write in your own PHP project become usable from an AgentHub
chat agent alongside its native and WordPress tools.

## Protocol

AgentHub's federation client (streamable HTTP transport) speaks plain JSON-RPC 2.0
over a single `POST` endpoint, with a static bearer token and no `initialize`
handshake. This package implements exactly that surface: `tools/list` and
`tools/call`. See `WeblyConnectSDK/docs/BACKEND_CONTRACT.md` for the field-level wire
contract and how to register the endpoint on the AgentHub side.

## Building blocks

| Class | Responsibility |
|---|---|
| `ToolDefinition` | Name, JSON Schema for arguments, and the PHP handler for one tool. |
| `ToolCallResult` | MCP content-block result of a tool call (`text()`, `json()`, `error()`). |
| `ToolRegistry` | Holds registered tools; executes `tools/call`, catching handler exceptions into an error result. |
| `BearerTokenAuthenticator` | Constant-time check of the `Authorization: Bearer <token>` header against your configured secret. |
| `JsonRpcDispatcher` | Routes a decoded JSON-RPC request to the registry, building the JSON-RPC response/error envelope. |
| `McpServer` | Single entry point: auth check + JSON decode + dispatch, returning an `McpHttpResponse` (status + JSON body) for your endpoint to output. |

## Usage sketch

```php
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

// In your HTTP entry point (plain PHP script, WordPress REST route, ...):
$response = $server->handleHttpRequest(
    $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    file_get_contents('php://input')
);

http_response_code($response->statusCode);
header('Content-Type: application/json');
echo $response->jsonBody;
```

Then configure the same URL and shared secret as a federated server in AgentHub's
tenant config, `transport: "streamable_http"`.
