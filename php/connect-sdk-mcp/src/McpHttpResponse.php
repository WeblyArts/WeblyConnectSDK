<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Mcp;

/**
 * HTTP-shaped result of handling one MCP request. The host adapter (a WordPress
 * REST route, a plain PHP endpoint, ...) sets the status code and echoes the body
 * with `Content-Type: application/json`; this SDK never touches headers/output
 * itself so it stays usable from any PHP entry point.
 */
final class McpHttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $jsonBody
    ) {
    }
}
