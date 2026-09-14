<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\AgentHub;

use WeblyConnect\Sdk\Auth\TenantAuth;
use WeblyConnect\Sdk\Exception\AgentHubException;
use WeblyConnect\Sdk\Http\HttpClientInterface;

/**
 * Non-streaming REST client for WeblyAgentHub.
 *
 * The base URL is supplied by the host application (dev VPS vs production), the SDK
 * never hardcodes environment endpoints.
 */
final class AgentHubClient
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly TenantAuth $auth,
        private readonly string $baseUrl,
        private readonly string $userAgent = 'WeblyConnectSDK/1.0'
    ) {
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        $token = $this->auth->getBearerToken();
        if ($token === '') {
            throw new AgentHubException('No valid tenant token configured for AgentHub.');
        }

        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'User-Agent' => $this->userAgent,
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $body = null, int $timeoutSec = 60): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $encodedBody = $body === null ? null : (string) json_encode($body);

        $response = $this->http->request($method, $url, $this->authHeaders(), $encodedBody, $timeoutSec);

        if (! $response->transportOk) {
            throw new AgentHubException('AgentHub request failed at the transport level.');
        }

        $data = $response->jsonBody();

        if ($response->statusCode >= 400) {
            $message = (string) ($data['detail'] ?? $data['message'] ?? $data['error'] ?? 'AgentHub error.');
            throw new AgentHubException($message, $response->statusCode, $data);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAgent(string $agentId): array
    {
        return $this->request('GET', '/agents/' . rawurlencode($agentId));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createAgent(array $payload): array
    {
        return $this->request('POST', '/agents', $payload, 90);
    }

    /**
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    public function updateAgent(string $agentId, array $patch): array
    {
        return $this->request('PATCH', '/agents/' . rawurlencode($agentId), $patch, 90);
    }

    /**
     * Tool catalog for agent settings (native Hub tools + MCP tools/list).
     *
     * @return array<string, mixed>
     */
    public function listAgentTools(string $agentId, string $context = 'chat', string $catalog = 'bound'): array
    {
        $path = '/agents/' . rawurlencode($agentId) . '/tools'
            . '?context=' . rawurlencode($context)
            . '&catalog=' . rawurlencode($catalog);

        return $this->request('GET', $path, null, 90);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAgents(): array
    {
        $result = $this->request('GET', '/agents', null, 90);

        if (isset($result['agents']) && is_array($result['agents'])) {
            return array_values($result['agents']);
        }

        if ($result !== [] && array_keys($result) === range(0, count($result) - 1)) {
            return array_values($result);
        }

        return [];
    }

    /**
     * LLM model and AI router catalog (GET /models).
     *
     * @return array<string, mixed>
     */
    public function listModels(): array
    {
        return $this->request('GET', '/models', null, 90);
    }

    /**
     * Non-streaming chat call against AgentHub's ReAct endpoint.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function chat(string $agentId, string $message, array $extra = []): array
    {
        $message = trim($message);
        if ($agentId === '' || $message === '') {
            throw new AgentHubException('Agent ID or message is missing.');
        }

        $body = array_merge(
            [
                'message' => $message,
                'streamable_inference' => false,
            ],
            $extra
        );

        return $this->request('POST', '/agents/' . rawurlencode($agentId) . '/chat', $body, 120);
    }
}
