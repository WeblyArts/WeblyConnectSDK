<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Mcp;

/**
 * Result of a `tools/call` invocation, in MCP wire shape: a list of content blocks
 * plus an error flag. Mirrors the shape WeblySuite's MCP federation proxy already
 * expects from any streamable-HTTP MCP server.
 */
final class ToolCallResult
{
    /**
     * @param list<array{type: string, text: string}> $content
     */
    private function __construct(
        public readonly array $content,
        public readonly bool $isError
    ) {
    }

    public static function text(string $text): self
    {
        return new self([['type' => 'text', 'text' => $text]], false);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data): self
    {
        return self::text((string) json_encode($data));
    }

    public static function error(string $message): self
    {
        return new self([['type' => 'text', 'text' => $message]], true);
    }

    /**
     * @return array{content: list<array{type: string, text: string}>, isError: bool}
     */
    public function toArray(): array
    {
        return ['content' => $this->content, 'isError' => $this->isError];
    }
}
