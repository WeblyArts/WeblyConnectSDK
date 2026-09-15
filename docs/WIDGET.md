# WeblyConnect chat widget (handmade sites)

Embed the WeblyConnect visitor chat on any site that can run a small PHP proxy.
The browser never receives your Hub `agent_id` or Connect token.

## Architecture

```
Browser (widget JS)  --POST {message, session_id}-->  your stream.php
                                                           |
                     config/site-connect.php (SiteConnect) |
                                                           v
                                                    AgentHub (SDK + token)
```

RAG dossier binding, if you use one, is configured once on the **Hub agent itself**
(`rag_config.agent_ids` on `POST`/`PATCH https://agenthub.weblyarts.com/agents/{id}`),
not in `SiteConnect` and not per chat message. See
[hub-rag-stack.md](https://suite.weblyarts.com/docs/hub-rag-stack) in the public docs.

## Distribution channel: jsDelivr from a dedicated widget tag

The widget JS is **not** part of the `connect-sdk-core` Composer package, even
though its source lives in the same `js/` folder of this monorepo. Backend SDK
(auth, HTTP client, streaming) and browser widget are deliberately two different
distributions, released independently, the same way `stripe-php` ships separately
from `Stripe.js`, or `sentry/sentry` from `@sentry/browser`:

- **Different audience.** A Composer package targets PHP developers running
  `composer install`. A `<script>` tag needs to work for anyone embedding the
  widget, including sites that never touch `vendor/`.
- **Different release cadence.** A CSS tweak or an XSS fix in the widget should
  reach every site immediately, without depending on each integrator running
  `composer update` on an unrelated backend package.
- **No fragile cross-package reach.** A class inside `connect-sdk-core` has no
  business assuming a monorepo layout with sibling folders (`js/`, `docs/`,
  `examples/`) exists next to it in `vendor/`. That assumption breaks the moment
  Composer installs from a dist archive instead of a full git clone, or if
  `connect-sdk-core` is ever split into its own repository.

`cdn.weblyarts.com` is **not** used either: it currently points at a legacy VPS
with no vhost for this bundle, and the equivalent S3+CloudFront pattern
(`releases.weblyarts.com`) was decommissioned on 2026-08-26. Instead, the widget is
served by **jsDelivr directly from a GitHub tag** of this public repository. Zero
infra, zero CI, zero AWS cost: jsDelivr fetches the files straight from GitHub and
puts them behind its own CDN, keyed by the immutable tag.

```
https://cdn.jsdelivr.net/gh/WeblyArts/WeblyConnectSDK@widget-v0.1.0/js/css/widget.css
https://cdn.jsdelivr.net/gh/WeblyArts/WeblyConnectSDK@widget-v0.1.0/js/vendor/fetch-event-source.js
https://cdn.jsdelivr.net/gh/WeblyArts/WeblyConnectSDK@widget-v0.1.0/js/src/stream-kit.js
https://cdn.jsdelivr.net/gh/WeblyArts/WeblyConnectSDK@widget-v0.1.0/js/src/widget.js
```

**Always pin an exact `widget-vX.Y.Z` tag**, never `@main`/`@latest`: jsDelivr caches
tagged paths aggressively (which is what makes it fast and free), so a moving ref
would serve stale or unpredictable content. This tag is independent of whatever
`connect-sdk-core` git ref a site's `composer.json` pins; bumping one does not
require bumping the other.

### Cutting a new widget release

The JS bundle has no build step, so a release is just a tag on the commit you want
to publish:

```bash
git tag widget-v0.1.0
git push origin widget-v0.1.0
```

jsDelivr picks up new tags within minutes (no purge needed for a tag used for the
first time, since each tag is a distinct, immutable URL). Bump the tag
(`widget-v0.1.1`, …) for any change to `js/`; do not reuse a tag.

Pushing a `widget-v*` tag runs
[`.github/workflows/widget-release-check.yml`](../.github/workflows/widget-release-check.yml):
it syntax-checks the JS files, confirms none are missing or empty, then polls the
four jsDelivr URLs above until they return `200`. It does not publish anything
itself (there is nothing to build), it only catches a bad tag or a jsDelivr outage
before you update the embed snippet on a live site.

## 1. Site init (PHP only)

Create one bootstrap file, for example `config/site-connect.php`. This is where you
declare everything about the Connect agent on this site:

```php
<?php

use WeblyConnect\Sdk\Auth\StaticTokenStore;
use WeblyConnect\Sdk\Site\ConnectWidgetUi;
use WeblyConnect\Sdk\Site\SiteConnect;

return new SiteConnect(
    tokenStore: new StaticTokenStore('wbly_live_…'),
    agentId: 'your-hub-agent-uuid',
    agentHubBaseUrl: 'https://agenthub.weblyarts.com',
    chatExtra: [],
    widget: new ConnectWidgetUi(
        streamPath: '/stream.php',
        botTitle: 'Support',
        avatarUrl: 'https://example.com/avatar.png',
        primaryRgb: '99, 102, 241',
        launcherPosition: 'bottom-right',
    ),
);
```

Keep this file out of version control if it contains production secrets, or load the
token from your existing secret store and pass it to `StaticTokenStore`.

## 2. Stream endpoint

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$connect = require __DIR__ . '/config/site-connect.php';
$connect->handleStreamRequest();
```

Accepts `POST` JSON `{ "message": "…", "session_id": "…" }`. Returns SSE frames
`{ "event": "…", "payload": { … } }`.

## 3. Widget embed (browser)

```php
<?php
$connect = require __DIR__ . '/config/site-connect.php';
$widgetConfig = $connect->widgetBrowserConfig();
?>
<div id="weblyconnect-widget"></div>
<script>
window.weblyConnectWidget = <?= json_encode($widgetConfig, JSON_UNESCAPED_SLASHES) ?>;
</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/WeblyArts/WeblyConnectSDK@widget-v0.1.0/js/css/widget.css">
<script src="https://cdn.jsdelivr.net/gh/WeblyArts/WeblyConnectSDK@widget-v0.1.0/js/vendor/fetch-event-source.js"></script>
<script src="https://cdn.jsdelivr.net/gh/WeblyArts/WeblyConnectSDK@widget-v0.1.0/js/src/stream-kit.js"></script>
<script src="https://cdn.jsdelivr.net/gh/WeblyArts/WeblyConnectSDK@widget-v0.1.0/js/src/widget.js"></script>
```

Do **not** put `agent_id` or the Connect token in `window.weblyConnectWidget`.

## Runnable example

```bash
cd examples/handmade-php
composer install
cp config/site-connect.example.php config/site-connect.php
# Edit config/site-connect.php with your token and agent id
php -S localhost:8080 router.php
```

Open `http://localhost:8080/widget.php`. `router.php` serves `/js/*` from this
monorepo's `js/` folder for local iteration, using the exact same source that gets
tagged and published to jsDelivr, no manual copy step.

## SDK install

PHP packages are not on Packagist yet. See [QUICKSTART.md](../QUICKSTART.md) for
GitHub VCS install instructions.
