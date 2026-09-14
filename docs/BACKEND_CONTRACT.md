# MCP federation backend contract

This is the exact wire contract the WeblySuite platform uses when it calls a federated
MCP server over the `streamable_http` transport. `weblyarts/connect-sdk-mcp`
implements this contract for you (see its README); this document exists so you can also
implement it in a different language, or debug an integration by hand with `curl`.

## Transport

- Single HTTP endpoint, any path, as long as it ends in `/mcp` (e.g.
  `https://yourapp.example.com/wp-json/weblyconnect/v1/mcp` or a plain
  `https://yourapp.example.com/mcp.php`).
- Every call is `POST` with a JSON-RPC 2.0 body and `Content-Type: application/json`.
- Auth: static `Authorization: Bearer <token>` header. The token is a secret you
  generate yourself and configure both on your server and in your WeblySuite tenant
  MCP federation config. There is no OAuth or key exchange: treat it like any other
  API secret (rotate it, never log it, never send it to the browser).
- No `initialize` handshake. WeblySuite calls `tools/list` and `tools/call` directly.

## `tools/list`

Request:

```json
{"jsonrpc": "2.0", "id": 1, "method": "tools/list"}
```

Response:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "tools": [
      {
        "name": "lookup_order",
        "description": "Looks up an order by ID.",
        "inputSchema": {
          "type": "object",
          "properties": {"order_id": {"type": "string"}},
          "required": ["order_id"]
        }
      }
    ]
  }
}
```

## `tools/call`

Request:

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/call",
  "params": {"name": "lookup_order", "arguments": {"order_id": "A-123"}}
}
```

Response (success):

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "result": {
    "content": [{"type": "text", "text": "{\"order_id\":\"A-123\",\"status\":\"shipped\"}"}],
    "isError": false
  }
}
```

Response (tool-level failure, still HTTP 200): set `"isError": true` and put a
human-readable explanation in the text content, instead of returning an HTTP error
status. Reserve HTTP-level errors for auth failures or malformed requests.

## Registering your server with WeblySuite

Add an entry to your tenant's MCP federation config (via the WeblySuite console, or
through the tenant configuration API you already use for other WeblySuite settings):

```json
{
  "servers": [
    {
      "alias": "myapp",
      "url": "https://yourapp.example.com/mcp.php",
      "auth_header": "your-shared-secret",
      "transport": "streamable_http",
      "enabled": true
    }
  ]
}
```

Your tools then show up in WeblyConnect chat prefixed as `ext_myapp__lookup_order`,
alongside native WeblySuite tools and any connected WordPress site's tools.

## Limits

- Aliases: lowercase letters, digits and underscores, 1-32 characters.
- Up to 10 federated servers per tenant, 200 tools per server (platform-side limits;
  design your catalog accordingly).
- `tools/list` results are cached for 60 seconds on the platform side, so schema
  changes on your end appear with a short delay.
