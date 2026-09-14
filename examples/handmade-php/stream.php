<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use WeblyConnect\Example\EnvTokenStore;
use WeblyConnect\Sdk\Auth\TenantAuth;
use WeblyConnect\Sdk\AgentHub\AgentHubChatStreamer;
use WeblyConnect\Sdk\Http\CurlHttpClient;

$agentId = getenv('HUB_AGENT_ID') ?: '';
if ($agentId === '') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Set HUB_AGENT_ID before starting the server.']);
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
while (ob_get_level() > 0) {
    ob_end_flush();
}

$baseUrl = getenv('AGENTHUB_BASE_URL') ?: 'https://agenthub.weblyarts.com';
$message = isset($_GET['message']) ? (string) $_GET['message'] : 'Say hello in one short sentence.';
$sessionId = isset($_GET['session_id']) ? (string) $_GET['session_id'] : '';

$auth = new TenantAuth(new EnvTokenStore());
$streamer = new AgentHubChatStreamer(new CurlHttpClient(), $auth, $baseUrl);

$streamer->stream(
    agentId: $agentId,
    message: $message,
    sessionId: $sessionId,
    extra: [],
    onEvent: function (string $event, array $payload) {
        echo 'data: ' . json_encode(['event' => $event, 'payload' => $payload]) . "\n\n";
        @flush();
    },
);
