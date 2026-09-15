# WeblyConnectSDK

Official SDK for **WeblyConnect**, the integration layer of **WeblySuite**.

Single source of truth for connecting any PHP or JavaScript project to the WeblySuite
ecosystem: tenant authentication (Connect flow), embeddable AI chat, backend proxy
streaming, and MCP tool exposure so your own app becomes a tool source for WeblySuite
agents.

This SDK is consumed both by the official WordPress plugins (`WeblySuitePress`,
`WeblyAgentPress`, `WeblyMCPress`) and by handmade PHP projects that want the same
capabilities without WordPress.

## Packages

| Package | Path | Purpose |
|---|---|---|
| `weblyarts/connect-sdk-core` | `php/connect-sdk-core` | Framework-agnostic Connect auth, HTTP (incl. SSE streaming), WeblySuite backend client and chat proxy orchestration. No WordPress functions. |
| `weblyarts/connect-sdk-mcp` | `php/connect-sdk-mcp` | Turns a PHP backend into an MCP tool server consumable by WeblySuite agents (federation-compatible). Standalone package (no dependency on core). |

## Design rules

- **No WordPress functions in `src/`.** Anything WordPress-specific (`wp_remote_*`,
  `sanitize_*`, `wp_options` storage, nonces, `__()`) lives in the consuming plugin as a
  thin adapter that implements the SDK interfaces (`TokenStoreInterface`, etc.).
- **One HTTP stack.** All HTTP calls, including Server-Sent Events streaming, go through
  the cURL-based client in `connect-sdk-core`. `wp_remote_*` cannot stream (it buffers the
  full response body), so WordPress call sites use the same SDK client instead of a
  separate code path.
- **Single package per shared dependency in WordPress.** To avoid PHP class redeclaration
  fatals when multiple plugins are active at once, only one plugin bundles each SDK
  package: `WeblySuitePress` bundles `connect-sdk-core`, `WeblyMCPress` bundles
  `connect-sdk-mcp` (which requires `connect-sdk-core` too, prefixed under its own vendor
  tree). `WeblyAgentPress` bundles no PHP vendor code; it ships the JS widget only and
  talks to WeblySuite through the REST proxy exposed by `WeblySuitePress`.
- **No back-compat layers.** One code path per concern, updated in place.

See `php/connect-sdk-core/README.md` for the core package contract.

## Chat widget (handmade sites)

Browser bundle in `js/src/`, distributed via jsDelivr from a `widget-vX.Y.Z` git
tag (no build step, no AWS infra), independent of the `connect-sdk-core` Composer
package. Embed guide: `docs/WIDGET.md`. The widget never receives Hub `agent_id` or
Connect token; PHP (`Site\SiteConnect`) proxies streaming via `stream.php`.
