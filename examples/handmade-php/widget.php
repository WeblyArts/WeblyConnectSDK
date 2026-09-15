<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

/** @var \WeblyConnect\Sdk\Site\SiteConnect $connect */
$connect = require __DIR__ . '/config/site-connect.php';

$widgetConfig = $connect->widgetBrowserConfig();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>WeblyConnect widget example</title>
	<!--
		This example serves js/ through router.php (local dev only:
		`php -S localhost:8080 router.php`) so you can iterate on the widget
		without a jsDelivr tag. Production sites load the widget from jsDelivr
		instead, at a pinned widget-vX.Y.Z tag — see docs/WIDGET.md.
	-->
	<link rel="stylesheet" href="/js/css/widget.css">
	<style>
		body { font-family: system-ui, sans-serif; margin: 2rem; max-width: 40rem; }
	</style>
</head>
<body>
	<h1>Handmade site with WeblyConnect widget</h1>
	<p>
		The chat posts to <code>stream.php</code> on this server. Agent id and the
		Connect token are declared only in <code>config/site-connect.php</code>.
	</p>

	<div id="weblyconnect-widget"></div>

	<script>
		window.weblyConnectWidget = <?= json_encode($widgetConfig, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
	</script>
	<script src="/js/vendor/fetch-event-source.js"></script>
	<script src="/js/src/stream-kit.js"></script>
	<script src="/js/src/widget.js"></script>
</body>
</html>
