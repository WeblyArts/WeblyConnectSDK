# WeblyConnect JS widget

Browser bundle for handmade sites. `src/` is the single source of truth; there is no
build step (plain ES5, no bundler) and no committed `dist/` copy, to avoid drift
between what ships and what is reviewed.

## Security contract

- `window.weblyConnectWidget` is set from **PHP only**.
- Allowed keys: `streamUrl`, UI (`botTitle`, `avatarUrl`, `primaryRgb`, `i18n`, …).
- Forbidden in browser config: `agent_id`, Connect token, Hub base URL.

The widget POSTs `{ message, session_id }` to your `stream.php`. PHP binds the
agent when calling AgentHub. RAG dossier binding, if any, lives on the Hub agent
config, not in this widget.

## Distribution

Served by **jsDelivr from a `widget-vX.Y.Z` git tag** on this public repo, not
`cdn.weblyarts.com`, and not bundled into the `connect-sdk-core` Composer package
either: backend SDK and browser widget are released independently, on purpose (see
[docs/WIDGET.md](../docs/WIDGET.md#distribution-channel-jsdelivr-from-a-dedicated-widget-tag)
for why, and for the release/tagging steps).

`examples/handmade-php/router.php` serves these files directly for local
development without any copy step.
