<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Mcp\Tests;

use PHPUnit\Framework\TestCase;
use WeblyConnect\Sdk\Mcp\JsonRpcDispatcher;
use WeblyConnect\Sdk\Mcp\ToolCallResult;
use WeblyConnect\Sdk\Mcp\ToolDefinition;
use WeblyConnect\Sdk\Mcp\ToolRegistry;

final class JsonRpcDispatcherTest extends TestCase
{
    private function makeDispatcher(): JsonRpcDispatcher
    {
        $registry = new ToolRegistry();
        $registry->register(new ToolDefinition(
            name: 'get_status',
            description: 'Returns a fixed status.',
            inputSchema: ['type' => 'object', 'properties' => new \stdClass()],
            handler: static fn (array $args): ToolCallResult => ToolCallResult::text('ok')
        ));

        return new JsonRpcDispatcher($registry);
    }

    public function testToolsListReturnsRegisteredTools(): void
    {
        $response = $this->makeDispatcher()->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        self::assertSame('2.0', $response['jsonrpc']);
        self::assertSame(1, $response['id']);
        self::assertCount(1, $response['result']['tools']);
        self::assertSame('get_status', $response['result']['tools'][0]['name']);
    }

    public function testToolsCallReturnsToolResult(): void
    {
        $response = $this->makeDispatcher()->dispatch([
            'jsonrpc' => '2.0',
            'id' => 'abc',
            'method' => 'tools/call',
            'params' => ['name' => 'get_status', 'arguments' => []],
        ]);

        self::assertSame('abc', $response['id']);
        self::assertSame('ok', $response['result']['content'][0]['text']);
        self::assertFalse($response['result']['isError']);
    }

    public function testToolsCallWithoutNameReturnsInvalidParamsError(): void
    {
        $response = $this->makeDispatcher()->dispatch([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [],
        ]);

        self::assertSame(-32602, $response['error']['code']);
    }

    public function testUnknownMethodReturnsMethodNotFoundError(): void
    {
        $response = $this->makeDispatcher()->dispatch(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'ping']);

        self::assertSame(-32601, $response['error']['code']);
    }

    public function testMissingMethodReturnsInvalidRequestError(): void
    {
        $response = $this->makeDispatcher()->dispatch(['jsonrpc' => '2.0', 'id' => 4]);

        self::assertSame(-32600, $response['error']['code']);
    }
}
