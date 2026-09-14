<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Tests\AgentHub;

use PHPUnit\Framework\TestCase;
use WeblyConnect\Sdk\AgentHub\AgentHubChatStreamer;
use WeblyConnect\Sdk\Auth\TenantAuth;
use WeblyConnect\Sdk\Tests\Fixtures\FakeHttpClient;
use WeblyConnect\Sdk\Tests\Fixtures\FakeTokenStore;
use WeblyConnect\Sdk\Tests\Fixtures\JwtFixture;

final class AgentHubChatStreamerTest extends TestCase
{
    private function makeAuth(): TenantAuth
    {
        $token = JwtFixture::make(['sub' => 'tenant-1', 'billing_scope' => 'weblyconnect']);

        return new TenantAuth(new FakeTokenStore($token));
    }

    public function testDecodesUpstreamSseEventsInOrder(): void
    {
        $http = new FakeHttpClient();
        $sseBlock = static fn (string $event, array $payload): string => 'data: ' . json_encode([
            'event' => $event,
            'payload' => $payload,
        ]) . "\n\n";

        $http->willStream([
            $sseBlock('token', ['text' => 'Hel']),
            $sseBlock('token', ['text' => 'lo']),
            $sseBlock('final_answer', ['text' => 'Hello']),
        ]);

        $streamer = new AgentHubChatStreamer($http, $this->makeAuth(), 'https://hub.example.test');

        $events = [];
        $streamer->stream('agent-1', 'hi', 'sess-1', [], function (string $event, array $payload) use (&$events): void {
            $events[] = [$event, $payload];
        });

        self::assertSame('stream_status', $events[0][0]);
        self::assertSame('token', $events[1][0]);
        self::assertSame('Hel', $events[1][1]['text']);
        self::assertSame('token', $events[2][0]);
        self::assertSame('final_answer', $events[3][0]);
        self::assertSame('done', end($events)[0]);
    }

    public function testFiresOnFirstByteExactlyOnce(): void
    {
        $http = new FakeHttpClient();
        $http->willStream([
            'data: ' . json_encode(['event' => 'token', 'payload' => ['text' => 'a']]) . "\n\n",
            'data: ' . json_encode(['event' => 'token', 'payload' => ['text' => 'b']]) . "\n\n",
        ]);

        $streamer = new AgentHubChatStreamer($http, $this->makeAuth(), 'https://hub.example.test');

        $firstByteCalls = 0;
        $streamer->stream(
            'agent-1',
            'hi',
            'sess-1',
            [],
            function (): void {
            },
            function () use (&$firstByteCalls): void {
                $firstByteCalls++;
            }
        );

        self::assertSame(1, $firstByteCalls);
    }

    public function testMissingAgentIdEmitsNonRetryableErrorWithoutCallingHttp(): void
    {
        $http = new FakeHttpClient();
        $streamer = new AgentHubChatStreamer($http, $this->makeAuth(), 'https://hub.example.test');

        $events = [];
        $streamer->stream('', 'hi', 'sess-1', [], function (string $event, array $payload) use (&$events): void {
            $events[] = [$event, $payload];
        });

        self::assertSame('error', $events[0][0]);
        self::assertFalse($events[0][1]['retryable']);
        self::assertSame('done', $events[1][0]);
    }

    public function testNoBytesAndTransportFailureEmitsRetryableError(): void
    {
        $http = new FakeHttpClient();
        $http->willStream([], false);

        $streamer = new AgentHubChatStreamer($http, $this->makeAuth(), 'https://hub.example.test', maxAttempts: 1);

        $events = [];
        $streamer->stream('agent-1', 'hi', 'sess-1', [], function (string $event, array $payload) use (&$events): void {
            $events[] = [$event, $payload];
        });

        $eventNames = array_column($events, 0);
        self::assertContains('error', $eventNames);
        self::assertTrue($events[array_search('error', $eventNames, true)][1]['retryable']);
    }
}
