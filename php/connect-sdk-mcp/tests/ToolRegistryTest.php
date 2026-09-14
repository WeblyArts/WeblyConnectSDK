<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Mcp\Tests;

use PHPUnit\Framework\TestCase;
use WeblyConnect\Sdk\Mcp\ToolCallResult;
use WeblyConnect\Sdk\Mcp\ToolDefinition;
use WeblyConnect\Sdk\Mcp\ToolRegistry;

final class ToolRegistryTest extends TestCase
{
    private function echoTool(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'echo_message',
            description: 'Echoes back the given message.',
            inputSchema: ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]],
            handler: static fn (array $args): ToolCallResult => ToolCallResult::text((string) ($args['message'] ?? ''))
        );
    }

    public function testListDescriptorsReflectsRegisteredTools(): void
    {
        $registry = new ToolRegistry();
        $registry->register($this->echoTool());

        $descriptors = $registry->listDescriptors();

        self::assertCount(1, $descriptors);
        self::assertSame('echo_message', $descriptors[0]['name']);
        self::assertArrayHasKey('inputSchema', $descriptors[0]);
    }

    public function testCallInvokesHandlerAndReturnsResult(): void
    {
        $registry = new ToolRegistry();
        $registry->register($this->echoTool());

        $result = $registry->call('echo_message', ['message' => 'hi']);

        self::assertFalse($result->isError);
        self::assertSame('hi', $result->content[0]['text']);
    }

    public function testCallOnUnknownToolReturnsErrorResult(): void
    {
        $registry = new ToolRegistry();

        $result = $registry->call('does_not_exist', []);

        self::assertTrue($result->isError);
        self::assertStringContainsString('does_not_exist', $result->content[0]['text']);
    }

    public function testCallCatchesHandlerExceptionsAsErrorResult(): void
    {
        $registry = new ToolRegistry();
        $registry->register(new ToolDefinition(
            name: 'broken',
            description: 'Always throws.',
            inputSchema: ['type' => 'object'],
            handler: static function (array $args): ToolCallResult {
                throw new \RuntimeException('boom');
            }
        ));

        $result = $registry->call('broken', []);

        self::assertTrue($result->isError);
        self::assertStringContainsString('boom', $result->content[0]['text']);
    }
}
