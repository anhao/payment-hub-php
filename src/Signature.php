<?php

declare(strict_types=1);

namespace Anhao\PaymentHub;

final class Signature
{
    public static function openApiStringToSign(
        string $method,
        string $uri,
        int|string $timestamp,
        string $nonce,
        ?string $body,
    ): string {
        return implode("\n", [
            strtoupper($method),
            $uri,
            (string) $timestamp,
            $nonce,
            md5($body ?? ''),
        ]);
    }

    public static function signOpenApi(
        string $secret,
        string $method,
        string $uri,
        int|string $timestamp,
        string $nonce,
        ?string $body,
    ): string {
        return hash_hmac('sha256', self::openApiStringToSign($method, $uri, $timestamp, $nonce, $body), $secret);
    }

    public static function verifyOpenApi(
        string $signature,
        string $secret,
        string $method,
        string $uri,
        int|string $timestamp,
        string $nonce,
        ?string $body,
    ): bool {
        return hash_equals(
            self::signOpenApi($secret, $method, $uri, $timestamp, $nonce, $body),
            strtolower($signature),
        );
    }

    public static function webhookStringToSign(
        string $webhookId,
        int|string $timestamp,
        string $nonce,
        string $body,
    ): string {
        return implode("\n", [
            $webhookId,
            (string) $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);
    }

    public static function signWebhook(
        string $secret,
        string $webhookId,
        int|string $timestamp,
        string $nonce,
        string $body,
    ): string {
        return hash_hmac('sha256', self::webhookStringToSign($webhookId, $timestamp, $nonce, $body), $secret);
    }

    /**
     * @param array<string, scalar|null> $query
     */
    public static function returnUrlStringToSign(array $query): string
    {
        unset($query['sign']);
        ksort($query);

        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, scalar|null> $query
     */
    public static function signReturnUrl(string $secret, array $query): string
    {
        return hash_hmac('sha256', self::returnUrlStringToSign($query), $secret);
    }

    /**
     * @param array<string, scalar|null> $query
     */
    public static function verifyReturnUrl(array $query, string $secret): bool
    {
        if (! isset($query['sign'])) {
            return false;
        }

        return hash_equals(
            self::signReturnUrl($secret, $query),
            strtolower((string) $query['sign']),
        );
    }
}
