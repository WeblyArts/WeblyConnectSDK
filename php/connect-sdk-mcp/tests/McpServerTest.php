<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Mcp\Tests;

use PHPUnit\Framework\TestCase;
use WeblyConnect\Sdk\Mcp\BearerTokenAuthenticator;
use WeblyConnect\Sdk\Mcp\McpServer;
use WeblyConnect\Sdk\Mcp\ToolCallResult;
use WeblyConnect\Sdk\Mcp\ToolDefinition;
use WeblyConnect\Sdk\Mcp\ToolRegistry;

final class McpServerTest extends TestCase
{
    private function makeServer(): McpServer
    {
        $registry = new ToolRegistry();
        $registry->register(new ToolDefinition(
            name: 'ping',
            description: 'Pong.',
            inputSchema: ['type' => 'object', 'properties' => new \stdClass()],
            handler: static fn (array $args): ToolCallResult => ToolCallResult::text('pong')
        ));

        return new McpServer($registry, new BearerTokenAuthenticator('secret-token'));
    }

    public function testRejectsMissingAuthorizationHeader(): void
    {
        $response = $this->makeServer()->handleHttpRequest(null, '{}');

        self::assertSame(401, $response->statusCode);
    }

    public function testRejectsWrongToken(): void
    {
        $response = $this->makeServer()->handleHttpRequest('Bearer wrong-token', '{}');

        self::assertSame(401, $response->statusCode);
    }

    public function testRejectsMalformedJsonBody(): void
    {
        $response = $this->makeServer()->handleHttpRequest('Bearer secret-token', 'not json');

        self::assertSame(400, $response->statusCode);
        self::assertStringContainsString('Parse error', $response->jsonBody);
    }

    public function testHandlesValidToolsListRequest(): void
    {
        $response = $this->makeServer()->handleHttpRequest(
            'Bearer secret-token',
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        );

        self::assertSame(200, $response->statusCode);
        $decoded = json_decode($response->jsonBody, true);
        self::assertSame('ping', $decoded['result']['tools'][0]['name']);
    }

    public function testHandlesValidToolsCallRequest(): void
    {
        $response = $this->makeServer()->handleHttpRequest(
            'Bearer secret-token',
            (string) json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['name' => 'ping', 'arguments' => []],
            ])
        );

        $decoded = json_decode($response->jsonBody, true);
        self::assertSame('pong', $decoded['result']['content'][0]['text']);
    }
}
