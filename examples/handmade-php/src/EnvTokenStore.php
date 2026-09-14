<?php

declare(strict_types=1);

namespace WeblyConnect\Example;

use WeblyConnect\Sdk\Auth\TokenStoreInterface;

/**
 * Minimal TokenStoreInterface backed by a single environment variable.
 *
 * Good enough for a script or a single-tenant service. A multi-tenant handmade
 * backend would instead look the token up per-tenant (database row, config file,
 * secret manager) inside getToken()/saveToken().
 */
final class EnvTokenStore implements TokenStoreInterface
{
    private const ENV_VAR = 'WEBLYCONNECT_TOKEN';

    public function getToken(): string
    {
        $token = getenv(self::ENV_VAR);

        return $token === false ? '' : $token;
    }

    public function saveToken(string $plainToken): bool
    {
        // Env vars are read-only for the running process. This example expects
        // the token to be provided at process start (see README.md).
        return false;
    }

    public function clear(): void
    {
    }
}
