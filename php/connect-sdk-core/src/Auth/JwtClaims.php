<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Auth;

/**
 * Extracts unverified claims from a WeblyToken (`wbly_live_*`, `wbly_test_*`,
 * `wbly_ttdk_*`, `wbly_admin_*`).
 *
 * Signature verification always happens server-side on AgentHub / WeblyCA. This class
 * only reads the JWT payload for local decisions (which billing scope, is it expired)
 * and must never be trusted as an authorization check on its own.
 */
final class JwtClaims
{
    public const REQUIRED_BILLING_SCOPE = 'weblyconnect';

    /** @var list<string> */
    private const PREFIXES = ['wbly_live_', 'wbly_test_', 'wbly_ttdk_', 'wbly_admin_'];

    /**
     * @return array<string, mixed>|null
     */
    public static function parseUnverified(string $apiKey): ?array
    {
        $token = trim($apiKey);
        if ($token === '') {
            return null;
        }

        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($token, $prefix)) {
                $token = substr($token, strlen($prefix));
                break;
            }
        }

        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false || $payload === '') {
            return null;
        }

        $data = json_decode($payload, true);

        return is_array($data) ? $data : null;
    }

    public static function billingScope(string $token): string
    {
        $claims = self::parseUnverified($token);
        if ($claims === null) {
            return '';
        }

        return (string) ($claims['billing_scope'] ?? '');
    }

    public static function isPluginBillingScope(string $token): bool
    {
        return self::billingScope($token) === self::REQUIRED_BILLING_SCOPE;
    }

    public static function subject(string $token): string
    {
        $claims = self::parseUnverified($token);

        return $claims === null ? '' : (string) ($claims['sub'] ?? '');
    }

    public static function expirationTimestamp(string $token): ?int
    {
        $claims = self::parseUnverified($token);
        if ($claims === null || ! isset($claims['exp']) || ! is_numeric($claims['exp'])) {
            return null;
        }

        return (int) $claims['exp'];
    }

    public static function isExpired(string $token): bool
    {
        $exp = self::expirationTimestamp($token);
        if ($exp === null) {
            return false;
        }

        return $exp <= time();
    }

    public static function expiresWithin(string $token, int $seconds): bool
    {
        $exp = self::expirationTimestamp($token);
        if ($exp === null) {
            return false;
        }

        return $exp <= (time() + max(0, $seconds));
    }

    public static function looksLikeTenantToken(string $token): bool
    {
        return str_starts_with($token, 'wbly_live_');
    }
}
