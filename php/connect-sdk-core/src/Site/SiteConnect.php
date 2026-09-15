<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Site;

use WeblyConnect\Sdk\AgentHub\AgentHubChatStreamer;
use WeblyConnect\Sdk\AgentHub\AgentHubClient;
use WeblyConnect\Sdk\Auth\TenantAuth;
use WeblyConnect\Sdk\Auth\TokenStoreInterface;
use WeblyConnect\Sdk\Http\CurlHttpClient;
use WeblyConnect\Sdk\Http\HttpClientInterface;

/**
 * Single bootstrap object for a handmade site integration.
 *
 * Declare the Hub agent id, Connect token, Hub base URL, chat extras, and widget UI
 * once. Endpoints such as stream.php and widget.php only require this instance.
 *
 * RAG dossier binding is NOT configured here: it is set once when the Hub agent is
 * created or updated (`rag_config.agent_ids` on `POST/PATCH /agents/{id}`), never as
 * a per-message field on `/agents/{id}/chat`. See docs/hub-rag-stack.md.
 */
final class SiteConnect
{
    private readonly TenantAuth $tenantAuth;
    private readonly HttpClientInterface $http;
    private readonly ConnectWidgetUi $widgetUi;

    /**
     * @param array<string, mixed> $chatExtra Merged into every Hub chat/stream call.
     *        Only documented AgentHub chat fields belong here (e.g. `output_language`,
     *        `context`, `model_override`). See docs/agenthub.md for the full body.
     */
    public function __construct(
        private readonly TokenStoreInterface $tokenStore,
        private readonly string $agentId,
        private readonly string $agentHubBaseUrl = 'https://agenthub.weblyarts.com',
        private readonly array $chatExtra = [],
        ?ConnectWidgetUi $widget = null,
        ?HttpClientInterface $http = null,
    ) {
        $this->tenantAuth = new TenantAuth($this->tokenStore);
        $this->http = $http ?? new CurlHttpClient();
        $this->widgetUi = $widget ?? new ConnectWidgetUi();
    }

    public function agentId(): string
    {
        return $this->agentId;
    }

    public function widgetUi(): ConnectWidgetUi
    {
        return $this->widgetUi;
    }

    public function tenantAuth(): TenantAuth
    {
        return $this->tenantAuth;
    }

    public function chatClient(): AgentHubClient
    {
        return new AgentHubClient($this->http, $this->tenantAuth, $this->agentHubBaseUrl);
    }

    public function chatStreamer(): AgentHubChatStreamer
    {
        return new AgentHubChatStreamer($this->http, $this->tenantAuth, $this->agentHubBaseUrl);
    }

    /**
     * @return array<string, mixed>
     */
    public function chatExtra(): array
    {
        return $this->chatExtra;
    }

    /**
     * Safe config for window.weblyConnectWidget (no secrets, no agent ids).
     *
     * @return array<string, mixed>
     */
    public function widgetBrowserConfig(?string $streamPath = null): array
    {
        $ui = $this->widgetUi;
        $path = $streamPath ?? $ui->streamPath;

        return [
            'streamUrl' => self::absoluteUrl($path),
            'mountSelector' => $ui->mountSelector,
            'botTitle' => $ui->botTitle,
            'avatarUrl' => $ui->avatarUrl,
            'primaryRgb' => $ui->primaryRgb,
            'launcherPosition' => $ui->launcherPosition,
            'autoOpen' => $ui->autoOpen,
            'autoOpenDelayMs' => $ui->autoOpenDelayMs,
            'i18n' => array_merge($ui->defaultI18n(), $ui->i18n),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function chat(string $message, array $extra = []): array
    {
        return $this->chatClient()->chat(
            $this->agentId,
            $message,
            array_merge($this->chatExtra(), $extra)
        );
    }

    /**
     * Visitor stream endpoint handler. Reads the current HTTP request.
     */
    public function handleStreamRequest(): void
    {
        if ($this->agentId === '') {
            $this->respondJson(500, ['error' => 'SiteConnect: agentId is not configured.']);

            return;
        }

        [$message, $sessionId] = $this->readStreamInput();

        if ($message === '') {
            $this->respondJson(400, ['error' => 'message is required']);

            return;
        }

        if ($sessionId === '') {
            $sessionId = 'wcn_visitor_' . bin2hex(random_bytes(8));
        }

        $this->prepareSseResponse();
        echo 'data: ' . json_encode(['event' => 'session', 'payload' => ['session_id' => $sessionId]]) . "\n\n";
        @flush();

        $this->chatStreamer()->stream(
            agentId: $this->agentId,
            message: $message,
            sessionId: $sessionId,
            extra: $this->chatExtra(),
            onEvent: function (string $event, array $payload): void {
                echo 'data: ' . json_encode(['event' => $event, 'payload' => $payload]) . "\n\n";
                @flush();
            },
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function readStreamInput(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $raw = file_get_contents('php://input');
            $body = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($body)) {
                return [
                    isset($body['message']) ? trim((string) $body['message']) : '',
                    isset($body['session_id']) ? trim((string) $body['session_id']) : '',
                ];
            }

            return ['', ''];
        }

        return [
            isset($_GET['message']) ? trim((string) $_GET['message']) : 'Say hello in one short sentence.',
            isset($_GET['session_id']) ? trim((string) $_GET['session_id']) : '',
        ];
    }

    private function prepareSseResponse(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function respondJson(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }

    private static function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . $path;
    }
}
