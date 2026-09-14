<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Http;

use RuntimeException;

/**
 * cURL-based implementation of {@see HttpClientInterface}.
 *
 * This is the only HTTP transport in the SDK. It is used as-is by non-WordPress hosts,
 * and by WordPress adapters too (in place of `wp_remote_*`), so streaming and
 * non-streaming requests never diverge into separate implementations.
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeoutSec = 60
    ): HttpResponse {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL handle.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $transportOk = $raw !== false;
        curl_close($ch);

        return new HttpResponse($statusCode, $transportOk ? (string) $raw : '', $transportOk);
    }

    public function streamPost(
        string $url,
        array $headers,
        string $body,
        callable $onChunk,
        callable $onIdle,
        int $idleIntervalSec = 8,
        int $connectTimeoutSec = 10,
        int $timeoutSec = 300
    ): bool {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $lastIdleAt = time();

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => $connectTimeoutSec,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TCP_NODELAY => true,
            CURLOPT_BUFFERSIZE => 1024,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static function () use (&$lastIdleAt, $idleIntervalSec, $onIdle): int {
                $now = time();
                if (($now - $lastIdleAt) >= $idleIntervalSec) {
                    $lastIdleAt = $now;
                    $onIdle();
                }

                return 0;
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$lastIdleAt, $onChunk): int {
                $lastIdleAt = time();
                $onChunk($chunk);

                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $ok !== false && $httpCode >= 200 && $httpCode < 300;
    }
}
