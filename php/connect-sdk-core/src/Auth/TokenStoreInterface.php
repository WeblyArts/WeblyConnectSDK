<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Auth;

/**
 * Persists the tenant WeblyToken (`wbly_live_*`) for the current integration.
 *
 * Implementations are provided by the host application: WordPress plugins store the
 * encrypted token in `wp_options`, handmade PHP projects may use a config file, env var
 * or their own encrypted storage. The SDK core never touches storage directly.
 */
interface TokenStoreInterface
{
    /**
     * Returns the raw token string, or an empty string if none is configured.
     */
    public function getToken(): string;

    /**
     * Persists a new token. Returns true on success.
     */
    public function saveToken(string $plainToken): bool;

    public function clear(): void;
}
