<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use WeblyConnect\Example\EnvTokenStore;
use WeblyConnect\Sdk\Auth\TenantAuth;
use WeblyConnect\Sdk\AgentHub\AgentHubClient;
use WeblyConnect\Sdk\Http\CurlHttpClient;

header('Content-Type: application/json');

$agentId = getenv('HUB_AGENT_ID') ?: '';
if ($agentId === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Set HUB_AGENT_ID before starting the server.']);
    exit;
}

$baseUrl = getenv('AGENTHUB_BASE_URL') ?: 'https://agenthub.weblyarts.com';
$message = isset($_GET['message']) ? (string) $_GET['message'] : 'Say hello in one short sentence.';

$auth = new TenantAuth(new EnvTokenStore());
$client = new AgentHubClient(new CurlHttpClient(), $auth, $baseUrl);

try {
    $reply = $client->chat($agentId, $message);
    echo json_encode($reply, JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()]);
}
