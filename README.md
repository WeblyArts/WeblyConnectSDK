# WeblyConnectSDK

Single source of truth for integrating any PHP or JavaScript project with the WeblyArts
AgentHub platform (AI chat, routed models, tool calling, MCP tool exposure).

This SDK is consumed both by the official WordPress plugins (`WeblySuitePress`,
`WeblyAgentPress`, `WeblyMCPress`) and by handmade PHP projects that want the same
capabilities without WordPress.

## Packages

| Package | Path | Purpose |
|---|---|---|
| `weblyarts/connect-sdk-core` | `php/connect-sdk-core` | Framework-agnostic auth, HTTP (incl. SSE streaming), AgentHub client and chat proxy orchestration. No WordPress functions. |
| `weblyarts/connect-sdk-mcp` | `php/connect-sdk-mcp` | Turns a PHP backend into an MCP tool server consumable by AgentHub (federation-compatible). Depends on `connect-sdk-core`. |

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
  talks to AgentHub through the REST proxy exposed by `WeblySuitePress`.
- **No back-compat layers.** One code path per concern, updated in place.

See `php/connect-sdk-core/README.md` for the core package contract.
