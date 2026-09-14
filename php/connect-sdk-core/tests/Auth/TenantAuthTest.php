<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Tests\Auth;

use PHPUnit\Framework\TestCase;
use WeblyConnect\Sdk\Auth\TenantAuth;
use WeblyConnect\Sdk\Exception\InvalidTokenException;
use WeblyConnect\Sdk\Tests\Fixtures\FakeTokenStore;
use WeblyConnect\Sdk\Tests\Fixtures\JwtFixture;

final class TenantAuthTest extends TestCase
{
    public function testGetBearerTokenReturnsEmptyWhenScopeIsWrong(): void
    {
        $wrongScope = JwtFixture::make(['sub' => 't1', 'billing_scope' => 'weblysuite']);
        $auth = new TenantAuth(new FakeTokenStore($wrongScope));

        self::assertSame('', $auth->getBearerToken());
        self::assertFalse($auth->hasToken());
    }

    public function testGetBearerTokenReturnsTokenWhenScopeMatches(): void
    {
        $token = JwtFixture::make(['sub' => 't1', 'billing_scope' => 'weblyconnect']);
        $auth = new TenantAuth(new FakeTokenStore($token));

        self::assertSame($token, $auth->getBearerToken());
        self::assertSame('t1', $auth->getClientId());
        self::assertTrue($auth->hasToken());
    }

    public function testSaveTokenRejectsWrongPrefix(): void
    {
        $auth = new TenantAuth(new FakeTokenStore());

        $this->expectException(InvalidTokenException::class);
        $auth->saveToken('wbly_test_' . 'abc.def.ghi');
    }

    public function testSaveTokenRejectsWrongScope(): void
    {
        $auth = new TenantAuth(new FakeTokenStore());
        $token = JwtFixture::make(['sub' => 't1', 'billing_scope' => 'weblysuite']);

        $this->expectException(InvalidTokenException::class);
        $auth->saveToken($token);
    }

    public function testSaveTokenPersistsValidToken(): void
    {
        $store = new FakeTokenStore();
        $auth = new TenantAuth($store);
        $token = JwtFixture::make(['sub' => 't1', 'billing_scope' => 'weblyconnect']);

        $auth->saveToken($token);

        self::assertSame($token, $store->getToken());
    }

    public function testClearDelegatesToStore(): void
    {
        $token = JwtFixture::make(['sub' => 't1', 'billing_scope' => 'weblyconnect']);
        $store = new FakeTokenStore($token);
        $auth = new TenantAuth($store);

        $auth->clear();

        self::assertSame('', $store->getToken());
    }
}
