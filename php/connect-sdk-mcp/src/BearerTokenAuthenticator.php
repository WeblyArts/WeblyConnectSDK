<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Mcp;

/**
 * Validates the `Authorization: Bearer <token>` header WeblySuite sends on every
 * federated `tools/list` / `tools/call` request, against the static secret the host
 * configured for this MCP server (the same value entered as `auth_header` in the
 * tenant's MCP federation config).
 */
final class BearerTokenAuthenticator
{
    public function __construct(private readonly string $expectedToken)
    {
    }

    public function isAuthorized(?string $authorizationHeader): bool
    {
        if ($this->expectedToken === '' || $authorizationHeader === null) {
            return false;
        }

        $header = trim($authorizationHeader);
        if (! str_starts_with(strtolower($header), 'bearer ')) {
            return false;
        }

        $token = trim(substr($header, 7));

        return hash_equals($this->expectedToken, $token);
    }
}
