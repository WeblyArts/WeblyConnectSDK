<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Mcp;

/**
 * Single entry point a host application calls from its HTTP endpoint (WordPress REST
 * route, plain PHP script, ...) to serve one MCP request.
 *
 * The host is responsible for routing a POST to this handler and for reading the
 * `Authorization` header and raw request body; everything else (auth check, JSON-RPC
 * decoding/dispatch, error envelopes) lives here so every integration behaves
 * identically.
 */
final class McpServer
{
    private readonly JsonRpcDispatcher $dispatcher;

    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly BearerTokenAuthenticator $authenticator
    ) {
        $this->dispatcher = new JsonRpcDispatcher($registry);
    }

    public function handleHttpRequest(?string $authorizationHeader, string $rawBody): McpHttpResponse
    {
        if (! $this->authenticator->isAuthorized($authorizationHeader)) {
            return new McpHttpResponse(401, (string) json_encode([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32001, 'message' => 'Unauthorized'],
            ]));
        }

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded)) {
            return new McpHttpResponse(400, (string) json_encode([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32700, 'message' => 'Parse error'],
            ]));
        }

        $response = $this->dispatcher->dispatch($decoded);

        return new McpHttpResponse(200, (string) json_encode($response));
    }
}
