<?php

declare(strict_types=1);

namespace WeblyConnect\Example;

/**
 * Fake data source for the mcp-server.php example, standing in for whatever
 * database or API a real handmade backend would query.
 */
final class DemoOrders
{
    /** @var array<string, array<string, mixed>> */
    private static array $orders = [
        'A-123' => ['order_id' => 'A-123', 'status' => 'shipped', 'total_usd' => 42.50],
        'A-124' => ['order_id' => 'A-124', 'status' => 'processing', 'total_usd' => 18.00],
    ];

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $orderId): ?array
    {
        return self::$orders[$orderId] ?? null;
    }
}
