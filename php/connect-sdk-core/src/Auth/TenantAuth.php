<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Auth;

use WeblyConnect\Sdk\Exception\InvalidTokenException;

/**
 * Reads and validates the tenant WeblyToken from a {@see TokenStoreInterface}.
 *
 * Enforces the `weblyconnect` billing scope so a token issued for another WeblyArts
 * product (e.g. a WeblySuite tenant hub token) cannot be reused here by mistake.
 */
final class TenantAuth
{
    public function __construct(private readonly TokenStoreInterface $tokenStore)
    {
    }

    public function hasToken(): bool
    {
        return $this->getBearerToken() !== '';
    }

    /**
     * Returns the token only if it is present, well-formed and scoped for WeblyConnect.
     * Returns an empty string otherwise (never throws) so callers can degrade cleanly.
     */
    public function getBearerToken(): string
    {
        $token = $this->tokenStore->getToken();
        if ($token === '' || ! JwtClaims::isPluginBillingScope($token)) {
            return '';
        }

        return $token;
    }

    public function getClientId(): string
    {
        $token = $this->getBearerToken();

        return $token === '' ? '' : JwtClaims::subject($token);
    }

    /**
     * Validates and persists a token supplied by the host application (admin UI paste,
     * connect flow callback, env var, ...). Throws on any validation failure.
     */
    public function saveToken(string $plainToken): void
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            throw new InvalidTokenException('Token is empty.');
        }

        if (! JwtClaims::looksLikeTenantToken($plainToken)) {
            throw new InvalidTokenException('Token must start with wbly_live_.');
        }

        if (! JwtClaims::isPluginBillingScope($plainToken)) {
            throw new InvalidTokenException('Token billing scope must be "weblyconnect".');
        }

        if (! $this->tokenStore->saveToken($plainToken)) {
            throw new InvalidTokenException('Failed to persist token.');
        }
    }

    public function clear(): void
    {
        $this->tokenStore->clear();
    }
}
