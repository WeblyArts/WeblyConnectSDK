<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Tests\Fixtures;

use WeblyConnect\Sdk\Auth\TokenStoreInterface;

final class FakeTokenStore implements TokenStoreInterface
{
    public function __construct(private string $token = '')
    {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function saveToken(string $plainToken): bool
    {
        $this->token = $plainToken;

        return true;
    }

    public function clear(): void
    {
        $this->token = '';
    }
}
