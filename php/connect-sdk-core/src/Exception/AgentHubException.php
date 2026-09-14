<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Exception;

/**
 * Raised when AgentHub returns an HTTP error (status >= 400) or is unreachable.
 */
class AgentHubException extends ConnectSdkException
{
    private int $statusCode;

    /** @var array<string, mixed> */
    private array $responseBody;

    /**
     * @param array<string, mixed> $responseBody
     */
    public function __construct(string $message, int $statusCode = 0, array $responseBody = [])
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseBody(): array
    {
        return $this->responseBody;
    }
}
