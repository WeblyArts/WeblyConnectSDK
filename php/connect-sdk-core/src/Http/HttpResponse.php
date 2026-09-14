<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Http;

/**
 * Value object for a completed (non-streaming) HTTP response.
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $rawBody,
        public readonly bool $transportOk
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonBody(): array
    {
        $data = json_decode($this->rawBody, true);

        return is_array($data) ? $data : [];
    }

    public function isSuccess(): bool
    {
        return $this->transportOk && $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
