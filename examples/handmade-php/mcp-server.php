<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use WeblyConnect\Example\DemoOrders;
use WeblyConnect\Sdk\Mcp\BearerTokenAuthenticator;
use WeblyConnect\Sdk\Mcp\McpServer;
use WeblyConnect\Sdk\Mcp\ToolCallResult;
use WeblyConnect\Sdk\Mcp\ToolDefinition;
use WeblyConnect\Sdk\Mcp\ToolRegistry;

$secret = getenv('MCP_SHARED_SECRET') ?: '';
if ($secret === '') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Set MCP_SHARED_SECRET before starting the server.']);
    exit;
}

$registry = new ToolRegistry();
$registry->register(new ToolDefinition(
    name: 'lookup_order',
    description: 'Looks up a demo order by ID (example data only).',
    inputSchema: [
        'type' => 'object',
        'properties' => ['order_id' => ['type' => 'string']],
        'required' => ['order_id'],
    ],
    handler: function (array $args): ToolCallResult {
        $orderId = (string) ($args['order_id'] ?? '');
        $order = DemoOrders::find($orderId);

        return $order === null
            ? ToolCallResult::error("Order {$orderId} not found.")
            : ToolCallResult::json($order);
    }
));

$server = new McpServer($registry, new BearerTokenAuthenticator($secret));

$response = $server->handleHttpRequest(
    $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    file_get_contents('php://input') ?: ''
);

http_response_code($response->statusCode);
header('Content-Type: application/json');
echo $response->jsonBody;
