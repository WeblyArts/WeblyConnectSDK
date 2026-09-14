<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Http;

/**
 * Single HTTP transport used everywhere in the SDK, including WordPress adapters.
 *
 * WordPress's `wp_remote_*` functions buffer the entire response body and cannot be
 * used for Server-Sent Events streaming, so both streaming and non-streaming calls go
 * through this same cURL-based contract instead of two divergent code paths.
 */
interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeoutSec = 60
    ): HttpResponse;

    /**
     * Streams a POST request and invokes $onChunk for every chunk of bytes received on
     * the wire, and $onIdle at most once per $idleIntervalSec while waiting for data
     * (used by callers to emit keepalive ticks to their own downstream client).
     *
     * Returns true if the transport completed the request and the upstream responded
     * with a 2xx status. Returns false on transport failure or non-2xx status; callers
     * inspect $onChunk side effects (bytes already forwarded) to decide whether a retry
     * is safe.
     *
     * @param array<string, string> $headers
     * @param callable(string): void $onChunk
     * @param callable(): void $onIdle
     */
    public function streamPost(
        string $url,
        array $headers,
        string $body,
        callable $onChunk,
        callable $onIdle,
        int $idleIntervalSec = 8,
        int $connectTimeoutSec = 10,
        int $timeoutSec = 300
    ): bool;
}
