<?php

declare(strict_types=1);

namespace Anhao\PaymentHub;

use JsonException;

final class Client
{
    /** @var callable(string, string, array<string, string>, ?string): array{0:int, 1:string} */
    private $httpHandler;

    /** @var callable(): string */
    private $nonceGenerator;

    /** @var callable(): int */
    private $clock;

    /**
     * @param  null|callable(string, string, array<string, string>, ?string): array{0:int, 1:string}  $httpHandler
     * @param  null|callable(): string  $nonceGenerator
     * @param  null|callable(): int  $clock
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $appKey,
        private readonly string $appSecret,
        ?callable $httpHandler = null,
        ?callable $nonceGenerator = null,
        ?callable $clock = null,
    ) {
        $this->httpHandler = $httpHandler ?? $this->defaultHttpHandler(...);
        $this->nonceGenerator = $nonceGenerator ?? fn (): string => bin2hex(random_bytes(16));
        $this->clock = $clock ?? fn (): int => time();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload): array
    {
        return $this->request('POST', '/api/payments', $payload);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{pay_context: array<string, array<string, mixed>>}
     */
    public static function payContext(string $provider, array $context): array
    {
        return [
            'pay_context' => [
                $provider => $context,
            ],
        ];
    }

    /**
     * @return array{pay_context: array{wechat: array{appid: string, openid: string, scene: string}}}
     */
    public static function wechatPayContext(string $appid, string $openid, string $scene = 'MINI'): array
    {
        return self::payContext('wechat', [
            'appid' => $appid,
            'openid' => $openid,
            'scene' => $scene,
        ]);
    }

    /** @return array<string, mixed> */
    public function getPayment(string $paymentNo): array
    {
        return $this->request('GET', '/api/payments/'.rawurlencode($paymentNo));
    }

    /** @return array<string, mixed> */
    public function getPaymentByOutOrderNo(string $outOrderNo): array
    {
        return $this->request('GET', '/api/payments?out_order_no='.rawurlencode($outOrderNo));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function closePayment(string $orderNo, array $payload = []): array
    {
        return $this->request('POST', '/api/orders/'.rawurlencode($orderNo).'/close', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRefund(array $payload): array
    {
        return $this->request('POST', '/api/refunds', $payload);
    }

    /** @return array<string, mixed> */
    public function getRefund(string $refundNo): array
    {
        return $this->request('GET', '/api/refunds/'.rawurlencode($refundNo));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    public function request(string $method, string $uri, ?array $payload = null): array
    {
        $method = strtoupper($method);
        $body = $payload === null || ($method === 'GET' && $payload === []) ? '' : $this->encodeJson($payload);
        $timestamp = ($this->clock)();
        $nonce = ($this->nonceGenerator)();
        $headers = [
            'Content-Type' => 'application/json',
            'X-App-Key' => $this->appKey,
            'X-Timestamp' => (string) $timestamp,
            'X-Nonce' => $nonce,
            'X-Sign' => Signature::signOpenApi($this->appSecret, $method, $uri, $timestamp, $nonce, $body),
        ];

        [$statusCode, $responseBody] = ($this->httpHandler)(
            $method,
            rtrim($this->baseUrl, '/').$uri,
            $headers,
            $body === '' ? null : $body,
        );

        $decoded = $this->decodeJson($responseBody);
        if ($statusCode < 200 || $statusCode >= 300 || (int) ($decoded['code'] ?? 0) !== 0) {
            throw new PaymentHubException((string) ($decoded['message'] ?? 'Payment Hub request failed'), $statusCode, $decoded);
        }

        return $decoded;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{0:int, 1:string}
     */
    private function defaultHttpHandler(string $method, string $url, array $headers, ?string $body): array
    {
        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = "{$name}: {$value}";
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $formattedHeaders),
                'content' => $body ?? '',
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        $statusCode = 0;

        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches) === 1) {
                $statusCode = (int) $matches[1];
                break;
            }
        }

        return [$statusCode, is_string($response) ? $response : ''];
    }

    /** @param  array<string, mixed>  $payload */
    private function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new PaymentHubException('JSON encode failed: '.$e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new PaymentHubException('JSON decode failed: '.$e->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }
}
