<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Site;

/**
 * Browser-safe widget appearance. No agent id, dossier id, or Connect token.
 */
final class ConnectWidgetUi
{
    /**
     * @param array<string, string> $i18n
     */
    public function __construct(
        public readonly string $streamPath = '/stream.php',
        public readonly string $mountSelector = '#weblyconnect-widget',
        public readonly string $botTitle = 'Chat',
        public readonly string $avatarUrl = '',
        public readonly string $primaryRgb = '99, 102, 241',
        public readonly string $launcherPosition = 'bottom-right',
        public readonly bool $autoOpen = false,
        public readonly int $autoOpenDelayMs = 0,
        public readonly array $i18n = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultI18n(): array
    {
        return [
            'defaultWelcome' => 'Hi! How can I help you today?',
            'typeMessage' => 'Type a message',
            'sendMessage' => 'Send message',
            'connecting' => 'Connecting…',
            'connectionError' => 'Connection error. Try again in a moment.',
        ];
    }
}
