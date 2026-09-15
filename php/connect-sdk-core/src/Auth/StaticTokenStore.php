<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Auth;

/**
 * Token store backed by a fixed string, typically from site bootstrap config.
 */
final class StaticTokenStore implements TokenStoreInterface
{
    public function __construct(private readonly string $token)
    {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function saveToken(string $plainToken): bool
    {
        return false;
    }

    public function clear(): void
    {
    }
}
