<?php

declare(strict_types=1);

namespace Anhao\PaymentHub;

final class Webhook
{
    /**
     * @param  array<string, string|array<int, string>>  $headers
     */
    public static function verifySignature(
        array $headers,
        string $body,
        string $appSecret,
        ?int $now = null,
        int $toleranceSeconds = 300,
    ): bool {
        $normalized = self::normalizeHeaders($headers);
        $webhookId = $normalized['x-hub-webhook-id'] ?? '';
        $timestamp = $normalized['x-hub-timestamp'] ?? '';
        $nonce = $normalized['x-hub-nonce'] ?? '';
        $signature = $normalized['x-hub-signature'] ?? '';

        if ($webhookId === '' || $timestamp === '' || $nonce === '' || $signature === '' || ctype_digit($timestamp) === false) {
            return false;
        }

        $current = $now ?? time();
        if (abs($current - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = Signature::signWebhook($appSecret, $webhookId, $timestamp, $nonce, $body);

        return hash_equals($expected, strtolower($signature));
    }

    /**
     * @param  array<string, string|array<int, string>>  $headers
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
        }

        return $normalized;
    }
}
