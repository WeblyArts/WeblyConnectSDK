<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Tests\Site;

use PHPUnit\Framework\TestCase;
use WeblyConnect\Sdk\Auth\StaticTokenStore;
use WeblyConnect\Sdk\Site\ConnectWidgetUi;
use WeblyConnect\Sdk\Site\SiteConnect;
use WeblyConnect\Sdk\Tests\Fixtures\JwtFixture;

final class SiteConnectTest extends TestCase
{
    public function testWidgetBrowserConfigOmitsSecrets(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.test';

        $connect = new SiteConnect(
            tokenStore: new StaticTokenStore(JwtFixture::make(['billing_scope' => 'weblyconnect'])),
            agentId: 'agent-uuid-123',
            widget: new ConnectWidgetUi(
                streamPath: '/stream.php',
                botTitle: 'Support',
            ),
        );

        $config = $connect->widgetBrowserConfig();

        self::assertSame('https://example.test/stream.php', $config['streamUrl']);
        self::assertSame('Support', $config['botTitle']);
        self::assertArrayNotHasKey('agentId', $config);
        self::assertArrayNotHasKey('agent_id', $config);
        self::assertArrayNotHasKey('dossierId', $config);
        self::assertArrayNotHasKey('token', $config);
    }

    /**
     * chatExtra() is a passthrough for documented AgentHub chat fields only
     * (see docs/agenthub.md). It must never fabricate wire fields that are not
     * part of the real POST /agents/{id}/chat contract.
     */
    public function testChatExtraIsPlainPassthrough(): void
    {
        $connect = new SiteConnect(
            tokenStore: new StaticTokenStore(JwtFixture::make(['billing_scope' => 'weblyconnect'])),
            agentId: 'agent-uuid-123',
            chatExtra: ['output_language' => 'it'],
        );

        self::assertSame(['output_language' => 'it'], $connect->chatExtra());
    }
}
