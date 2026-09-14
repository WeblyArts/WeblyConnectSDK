<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Tests\Fixtures;

use WeblyConnect\Sdk\Http\HttpClientInterface;
use WeblyConnect\Sdk\Http\HttpResponse;

/**
 * In-memory HttpClientInterface for tests. `request()` returns a canned response;
 * `streamPost()` replays a list of raw SSE chunks (as if received from the wire) to
 * the given callback, in one attempt.
 */
final class FakeHttpClient implements HttpClientInterface
{
    private ?HttpResponse $nextResponse = null;

    /** @var list<string> */
    private array $streamChunks = [];

    private bool $streamOk = true;

    public function willReturn(HttpResponse $response): void
    {
        $this->nextResponse = $response;
    }

    /**
     * @param list<string> $chunks
     */
    public function willStream(array $chunks, bool $ok = true): void
    {
        $this->streamChunks = $chunks;
        $this->streamOk = $ok;
    }

    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeoutSec = 60
    ): HttpResponse {
        return $this->nextResponse ?? new HttpResponse(200, '{}', true);
    }

    public function streamPost(
        string $url,
        array $headers,
        string $body,
        callable $onChunk,
        callable $onIdle,
        int $idleIntervalSec = 8,
        int $connectTimeoutSec = 10,
        int $timeoutSec = 300
    ): bool {
        foreach ($this->streamChunks as $chunk) {
            $onChunk($chunk);
        }

        return $this->streamOk;
    }
}
