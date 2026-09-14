<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Mcp;

/**
 * One MCP tool exposed by this backend: a name, a JSON Schema for its arguments, and
 * the handler that executes it.
 *
 * The handler receives the raw arguments array decoded from the `tools/call` request
 * and must return a {@see ToolCallResult}. Any exception it throws is caught by
 * {@see ToolRegistry::call()} and turned into an error result: handlers do not need
 * to worry about the JSON-RPC error envelope.
 */
final class ToolDefinition
{
    /**
     * @param array<string, mixed> $inputSchema JSON Schema object for the tool arguments.
     * @param callable(array<string, mixed>): ToolCallResult $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        private readonly mixed $handler
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function invoke(array $arguments): ToolCallResult
    {
        return ($this->handler)($arguments);
    }

    /**
     * @return array{name: string, description: string, inputSchema: array<string, mixed>}
     */
    public function toDescriptor(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
        ];
    }
}
