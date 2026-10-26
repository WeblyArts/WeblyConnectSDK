<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\AgentHub;

use WeblyConnect\Sdk\Auth\TenantAuth;
use WeblyConnect\Sdk\Http\HttpClientInterface;

/**
 * Streams `POST /agents/{id}/chat` (streamable_inference=true) from AgentHub and
 * re-emits each decoded Anthropic-style SSE event through a callback.
 *
 * This class only decodes the wire protocol and handles upstream retries. It never
 * writes to an output buffer, sets HTTP headers or performs storage side effects
 * (quota, session bookkeeping): those are the host adapter's job, wired through
 * $onEvent and the optional $onFirstByte callback.
 */
final class AgentHubChatStreamer
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly TenantAuth $auth,
        private readonly string $baseUrl,
        private readonly string $userAgent = 'WeblyConnectSDK/1.0',
        private readonly int $maxAttempts = 3,
        private readonly int $retryBaseDelayMs = 400,
        private readonly int $idleIntervalSec = 8,
        private readonly int $connectTimeoutSec = 10,
        private readonly int $timeoutSec = 300
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     * @param callable(string, array<string, mixed>): void $onEvent
     * @param (callable(): void)|null $onFirstByte
     */
    public function stream(
        string $agentId,
        string $message,
        string $sessionId,
        array $extra,
        callable $onEvent,
        ?callable $onFirstByte = null
    ): void {
        $token = $this->auth->getBearerToken();
        if ($this->baseUrl === '' || $token === '' || $agentId === '') {
            $onEvent('error', [
                'message' => 'AgentHub is not configured. Check the tenant token and agent ID.',
                'retryable' => false,
            ]);
            $onEvent('done', []);

            return;
        }

        $body = (string) json_encode(array_merge(
            [
                'message' => $message,
                'session_id' => $sessionId,
                'streamable_inference' => true,
                'react_pnl' => (object) [],
            ],
            $extra
        ));

        $this->pipe($this->agentUrl($agentId, 'chat'), $token, $body, $onEvent, $onFirstByte);
    }

    /**
     * Resumes a graph paused by `interrupt()` (SSE `tool_request`) with the page outcome.
     *
     * @param array<string, mixed>|string $toolResult
     * @param callable(string, array<string, mixed>): void $onEvent
     */
    public function resume(
        string $agentId,
        string $sessionId,
        array|string $toolResult,
        callable $onEvent
    ): void {
        $token = $this->auth->getBearerToken();
        if ($this->baseUrl === '' || $token === '' || $agentId === '' || $sessionId === '') {
            $onEvent('error', [
                'message' => 'AgentHub is not configured. Check the tenant token and agent ID.',
                'retryable' => false,
            ]);
            $onEvent('done', []);

            return;
        }

        $body = (string) json_encode([
            'session_id' => $sessionId,
            'tool_result' => $toolResult,
        ]);

        $this->pipe($this->agentUrl($agentId, 'resume'), $token, $body, $onEvent, null, 1);
    }

    private function agentUrl(string $agentId, string $action): string
    {
        return rtrim($this->baseUrl, '/') . '/agents/' . rawurlencode($agentId) . '/' . $action;
    }

    /**
     * @param callable(string, array<string, mixed>): void $onEvent
     * @param (callable(): void)|null $onFirstByte
     */
    private function pipe(
        string $url,
        string $token,
        string $body,
        callable $onEvent,
        ?callable $onFirstByte,
        ?int $maxAttempts = null
    ): void {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'text/event-stream',
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => $this->userAgent,
            'Connection' => 'keep-alive',
        ];

        $bytesSeen = false;
        $firstByteFired = false;
        $buffer = '';
        $ok = false;

        $attempts = $maxAttempts ?? $this->maxAttempts;
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $bytesSeen = false;
            $buffer = '';

            if ($attempt === 0) {
                $onEvent('stream_status', ['phase' => 'connecting']);
            } else {
                $onEvent('stream_status', ['phase' => 'retrying', 'attempt' => $attempt + 1]);
                usleep($this->retryBaseDelayMs * 1000 * (2 ** ($attempt - 1)));
            }

            $onChunk = function (string $chunk) use (&$bytesSeen, &$buffer, &$firstByteFired, $onFirstByte, $onEvent): void {
                $bytesSeen = true;
                if (! $firstByteFired && $onFirstByte !== null) {
                    $firstByteFired = true;
                    $onFirstByte();
                }

                $buffer .= $chunk;
                $buffer = $this->drainBuffer($buffer, $onEvent, false);
            };

            $onIdle = function () use (&$bytesSeen, $onEvent): void {
                $onEvent('stream_ping', ['ts' => time()]);
                if ($bytesSeen) {
                    $onEvent('stream_status', ['phase' => 'waiting_hub']);
                }
            };

            $ok = $this->http->streamPost(
                $url,
                $headers,
                $body,
                $onChunk,
                $onIdle,
                $this->idleIntervalSec,
                $this->connectTimeoutSec,
                $this->timeoutSec
            );

            if ($ok || $bytesSeen) {
                break;
            }
        }

        $this->drainBuffer($buffer, $onEvent, true);

        if (! $ok && ! $bytesSeen) {
            $onEvent('error', [
                'message' => 'Connection to AgentHub failed.',
                'retryable' => true,
            ]);
        }

        $onEvent('done', []);
    }

    /**
     * @param callable(string, array<string, mixed>): void $onEvent
     */
    private function drainBuffer(string $buffer, callable $onEvent, bool $final): string
    {
        $sep = "\n\n";

        while (($pos = strpos($buffer, $sep)) !== false) {
            $block = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + strlen($sep));
            $this->forwardBlock($block, $onEvent);
        }

        if ($final && $buffer !== '') {
            $this->forwardBlock($buffer, $onEvent);
            $buffer = '';
        }

        return $buffer;
    }

    /**
     * @param callable(string, array<string, mixed>): void $onEvent
     */
    private function forwardBlock(string $block, callable $onEvent): void
    {
        $block = trim($block);
        if ($block === '' || str_starts_with($block, ':')) {
            return;
        }

        $dataLine = '';
        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'data:')) {
                $dataLine .= trim(substr($line, 5));
            }
        }

        if ($dataLine === '') {
            return;
        }

        $decoded = json_decode($dataLine, true);
        if (! is_array($decoded) || ! isset($decoded['event'])) {
            return;
        }

        $payload = $decoded['payload'] ?? [];
        $onEvent((string) $decoded['event'], is_array($payload) ? $payload : []);
    }
}
