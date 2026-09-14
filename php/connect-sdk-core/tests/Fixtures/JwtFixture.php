<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Tests\Fixtures;

/**
 * Builds unsigned test tokens shaped like real WeblyToken JWTs, without needing a real
 * signing key. Good enough for exercising WeblyConnect\Sdk\Auth\JwtClaims, which never
 * verifies signatures locally either.
 */
final class JwtFixture
{
    /**
     * @param array<string, mixed> $claims
     */
    public static function make(array $claims, string $prefix = 'wbly_live_'): string
    {
        $header = self::b64url((string) json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = self::b64url((string) json_encode($claims));

        return $prefix . $header . '.' . $payload . '.sig';
    }

    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
