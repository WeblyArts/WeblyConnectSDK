<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Mcp;

/**
 * Dispatches decoded JSON-RPC 2.0 requests to a {@see ToolRegistry}.
 *
 * Only the two methods WeblySuite's MCP federation client actually calls are
 * supported: `tools/list` and `tools/call`. There is no `initialize` handshake in
 * the streamable-HTTP federation contract, so this dispatcher does not implement
 * one either; adding unused protocol surface here would be speculative complexity
 * with no current caller.
 */
final class JsonRpcDispatcher
{
    public function __construct(private readonly ToolRegistry $registry)
    {
    }

    /**
     * @param array<string, mixed> $request Decoded JSON-RPC request body.
     * @return array<string, mixed> JSON-RPC response body (success or error envelope).
     */
    public function dispatch(array $request): array
    {
        $id = $request['id'] ?? null;
        $method = $request['method'] ?? null;

        if (! is_string($method) || $method === '') {
            return $this->errorResponse($id, -32600, 'Invalid Request');
        }

        $params = $request['params'] ?? [];
        if (! is_array($params)) {
            $params = [];
        }

        return match ($method) {
            'tools/list' => $this->successResponse($id, ['tools' => $this->registry->listDescriptors()]),
            'tools/call' => $this->handleToolsCall($id, $params),
            default => $this->errorResponse($id, -32601, "Method not found: {$method}"),
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private function handleToolsCall(mixed $id, array $params): array
    {
        $name = $params['name'] ?? null;
        if (! is_string($name) || $name === '') {
            return $this->errorResponse($id, -32602, 'Invalid params: "name" is required');
        }

        $arguments = $params['arguments'] ?? [];
        if (! is_array($arguments)) {
            $arguments = [];
        }

        $result = $this->registry->call($name, $arguments);

        return $this->successResponse($id, $result->toArray());
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function successResponse(mixed $id, array $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
