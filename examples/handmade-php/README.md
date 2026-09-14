# Handmade PHP example

Runnable, no-WordPress example of `connect-sdk-core` and `connect-sdk-mcp`. This
folder uses local `path` repositories to the sibling packages, so it works without
network access when you clone the whole monorepo. In your own project, swap those
`repositories` entries for the GitHub `vcs` repository shown in `../../QUICKSTART.md`.

## Setup

```bash
cd examples/handmade-php
composer install
```

## Run

```bash
WEBLYCONNECT_TOKEN=wbly_live_your_token \
HUB_AGENT_ID=your-hub-agent-id \
MCP_SHARED_SECRET=any-secret-you-invent \
php -S localhost:8080
```

Optional: `AGENTHUB_BASE_URL` (defaults to `https://agenthub.weblyarts.com`; point it
at a dev/staging Hub if you have one).

## Try it

| Endpoint | What it does |
|---|---|
| `GET /chat.php?message=hello` | Non-streaming chat call, prints the JSON reply. |
| `GET /stream.php?message=hello` | Same call over SSE. Watch with `curl -N` or an `EventSource` in the browser. |
| `POST /mcp-server.php` with `Authorization: Bearer <MCP_SHARED_SECRET>` and a JSON-RPC body | Serves `tools/list` / `tools/call` for the demo `lookup_order` tool. |

Example MCP call:

```bash
curl -s http://localhost:8080/mcp-server.php \
  -H "Authorization: Bearer any-secret-you-invent" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"lookup_order","arguments":{"order_id":"A-123"}}}'
```

`chat.php` and `stream.php` need a real `WEBLYCONNECT_TOKEN` and `HUB_AGENT_ID`
against a live tenant to return a real answer; `mcp-server.php` runs fully offline
since it only serves demo data from `src/DemoOrders.php`.
