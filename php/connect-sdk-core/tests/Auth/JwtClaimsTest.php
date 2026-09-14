<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Tests\Auth;

use PHPUnit\Framework\TestCase;
use WeblyConnect\Sdk\Auth\JwtClaims;
use WeblyConnect\Sdk\Tests\Fixtures\JwtFixture;

final class JwtClaimsTest extends TestCase
{
    public function testParsesBillingScopeAndSubject(): void
    {
        $token = JwtFixture::make([
            'sub' => 'tenant-123',
            'billing_scope' => 'weblyconnect',
            'exp' => time() + 3600,
        ]);

        self::assertTrue(JwtClaims::isPluginBillingScope($token));
        self::assertSame('tenant-123', JwtClaims::subject($token));
    }

    public function testRejectsWrongBillingScope(): void
    {
        $token = JwtFixture::make([
            'sub' => 'tenant-123',
            'billing_scope' => 'weblysuite',
        ]);

        self::assertFalse(JwtClaims::isPluginBillingScope($token));
    }

    public function testDetectsExpiry(): void
    {
        $expired = JwtFixture::make(['exp' => time() - 10]);
        $valid = JwtFixture::make(['exp' => time() + 3600]);

        self::assertTrue(JwtClaims::isExpired($expired));
        self::assertFalse(JwtClaims::isExpired($valid));
    }

    public function testExpiresWithinWindow(): void
    {
        $soon = JwtFixture::make(['exp' => time() + 30]);

        self::assertTrue(JwtClaims::expiresWithin($soon, 60));
        self::assertFalse(JwtClaims::expiresWithin($soon, 5));
    }

    public function testMalformedTokenReturnsNullClaims(): void
    {
        self::assertNull(JwtClaims::parseUnverified('not-a-jwt'));
        self::assertNull(JwtClaims::parseUnverified(''));
        self::assertFalse(JwtClaims::isPluginBillingScope('wbly_live_garbage'));
    }

    public function testLooksLikeTenantTokenChecksPrefix(): void
    {
        self::assertTrue(JwtClaims::looksLikeTenantToken('wbly_live_abc.def.ghi'));
        self::assertFalse(JwtClaims::looksLikeTenantToken('wbly_test_abc.def.ghi'));
    }
}
