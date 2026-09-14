<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Mcp;

use Throwable;

/**
 * Holds the tools this backend exposes and executes `tools/call` requests against
 * them. This is the only place tool implementations are registered; the JSON-RPC
 * dispatcher never knows about individual tools.
 */
final class ToolRegistry
{
    /** @var array<string, ToolDefinition> */
    private array $tools = [];

    public function register(ToolDefinition $tool): void
    {
        $this->tools[$tool->name] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    public function listDescriptors(): array
    {
        return array_values(array_map(
            static fn (ToolDefinition $tool): array => $tool->toDescriptor(),
            $this->tools
        ));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function call(string $name, array $arguments): ToolCallResult
    {
        $tool = $this->tools[$name] ?? null;
        if ($tool === null) {
            return ToolCallResult::error("Unknown tool: {$name}");
        }

        try {
            return $tool->invoke($arguments);
        } catch (Throwable $e) {
            return ToolCallResult::error("Tool '{$name}' failed: " . $e->getMessage());
        }
    }
}
