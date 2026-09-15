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

## Configure

Edit `config/site-connect.php`: Connect token, Hub `agentId`, widget UI, and Hub
base URL. That file is the single init for chat and streaming on this site. RAG
dossier binding, if used, is set on the Hub agent itself, not here.

## Run

```bash
php -S localhost:8080 router.php
```

`router.php` serves `/js/*` from the monorepo `js/` folder for `widget.php`
(local dev only). For MCP demo only, set `MCP_SHARED_SECRET` when calling
`mcp-server.php` (see below).

## Try it

| Endpoint | What it does |
|---|---|
| `GET /chat.php?message=hello` | Non-streaming chat call, prints the JSON reply. |
| `GET /stream.php?message=hello` | Same call over SSE. Watch with `curl -N` or an `EventSource` in the browser. |
| `GET /widget.php` | Embeddable chat widget. Browser talks only to `stream.php`; config stays in `config/site-connect.php`. |
| `POST /mcp-server.php` with `Authorization: Bearer <MCP_SHARED_SECRET>` and a JSON-RPC body | Serves `tools/list` / `tools/call` for the demo `lookup_order` tool. |

Example MCP call:

```bash
curl -s http://localhost:8080/mcp-server.php \
  -H "Authorization: Bearer any-secret-you-invent" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"lookup_order","arguments":{"order_id":"A-123"}}}'
```

`chat.php`, `stream.php`, and `widget.php` need a real token and agent id in
`config/site-connect.php` against a live tenant to return a real answer.
`mcp-server.php` runs fully offline since it only serves demo data from
`src/DemoOrders.php`.
