<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Tests\AgentHub;

use PHPUnit\Framework\TestCase;
use WeblyConnect\Sdk\AgentHub\AgentHubClient;
use WeblyConnect\Sdk\Auth\TenantAuth;
use WeblyConnect\Sdk\Exception\AgentHubException;
use WeblyConnect\Sdk\Http\HttpResponse;
use WeblyConnect\Sdk\Tests\Fixtures\FakeHttpClient;
use WeblyConnect\Sdk\Tests\Fixtures\FakeTokenStore;
use WeblyConnect\Sdk\Tests\Fixtures\JwtFixture;

final class AgentHubClientTest extends TestCase
{
    private function makeClient(FakeHttpClient $http): AgentHubClient
    {
        $token = JwtFixture::make(['sub' => 'tenant-1', 'billing_scope' => 'weblyconnect']);
        $auth = new TenantAuth(new FakeTokenStore($token));

        return new AgentHubClient($http, $auth, 'https://hub.example.test');
    }

    public function testChatReturnsDecodedJsonBody(): void
    {
        $http = new FakeHttpClient();
        $http->willReturn(new HttpResponse(200, '{"final_answer":"hi"}', true));

        $client = $this->makeClient($http);
        $result = $client->chat('agent-1', 'hello');

        self::assertSame('hi', $result['final_answer']);
    }

    public function testHttpErrorStatusThrowsAgentHubException(): void
    {
        $http = new FakeHttpClient();
        $http->willReturn(new HttpResponse(403, '{"detail":"forbidden"}', true));

        $client = $this->makeClient($http);

        try {
            $client->getAgent('agent-1');
            self::fail('Expected AgentHubException.');
        } catch (AgentHubException $e) {
            self::assertSame(403, $e->getStatusCode());
            self::assertSame('forbidden', $e->getMessage());
        }
    }

    public function testTransportFailureThrowsAgentHubException(): void
    {
        $http = new FakeHttpClient();
        $http->willReturn(new HttpResponse(0, '', false));

        $client = $this->makeClient($http);

        $this->expectException(AgentHubException::class);
        $client->listModels();
    }

    public function testMissingTokenThrowsBeforeAnyRequest(): void
    {
        $http = new FakeHttpClient();
        $auth = new TenantAuth(new FakeTokenStore(''));
        $client = new AgentHubClient($http, $auth, 'https://hub.example.test');

        $this->expectException(AgentHubException::class);
        $client->listAgents();
    }
}
