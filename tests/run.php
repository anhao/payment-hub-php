<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Anhao\PaymentHub\Client;
use Anhao\PaymentHub\Signature;
use Anhao\PaymentHub\Webhook;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
        exit(1);
    }
}

function assertTrueValue(bool $actual, string $message): void
{
    assertSameValue(true, $actual, $message);
}

$body = '{"amount":9900}';
$openApiString = Signature::openApiStringToSign('post', '/api/payments', 1781690400, 'nonce-001', $body);
assertSameValue("POST\n/api/payments\n1781690400\nnonce-001\n".md5($body), $openApiString, 'OpenAPI string_to_sign mismatch.');

$openApiSign = Signature::signOpenApi('app-secret', 'POST', '/api/payments', 1781690400, 'nonce-001', $body);
assertSameValue(hash_hmac('sha256', $openApiString, 'app-secret'), $openApiSign, 'OpenAPI signature mismatch.');

$webhookBody = '{"event":"payment.success"}';
$webhookString = Signature::webhookStringToSign('WH01HZTEST', 1781690400, 'nonce-wh-001', $webhookBody);
assertSameValue("WH01HZTEST\n1781690400\nnonce-wh-001\n".hash('sha256', $webhookBody), $webhookString, 'Webhook string_to_sign mismatch.');

$webhookSign = Signature::signWebhook('app-secret', 'WH01HZTEST', 1781690400, 'nonce-wh-001', $webhookBody);
assertTrueValue(Webhook::verifySignature([
    'X-Hub-Webhook-Id' => 'WH01HZTEST',
    'X-Hub-Timestamp' => '1781690400',
    'X-Hub-Nonce' => 'nonce-wh-001',
    'X-Hub-Signature' => $webhookSign,
], $webhookBody, 'app-secret', 1781690400), 'Webhook signature should verify.');

$returnQuery = [
    'payment_no' => 'PH001',
    'out_order_no' => 'OUT001',
    'status' => 'PAID',
    'amount' => '9900',
    'paid_at' => '1781690400',
    'timestamp' => '1781690401',
    'nonce' => 'nonce-return-001',
];
$returnSign = Signature::signReturnUrl('app-secret', $returnQuery);
$returnQuery['sign'] = $returnSign;
assertSameValue(
    hash_hmac('sha256', 'amount=9900&nonce=nonce-return-001&out_order_no=OUT001&paid_at=1781690400&payment_no=PH001&status=PAID&timestamp=1781690401', 'app-secret'),
    $returnSign,
    'Return URL signature mismatch.',
);
assertTrueValue(Signature::verifyReturnUrl($returnQuery, 'app-secret'), 'Return URL signature should verify.');

$wechatContext = Client::wechatPayContext('wx-mini-appid', 'openid-from-miniapp');
assertSameValue([
    'pay_context' => [
        'wechat' => [
            'appid' => 'wx-mini-appid',
            'openid' => 'openid-from-miniapp',
            'scene' => 'MINI',
        ],
    ],
], $wechatContext, 'Wechat pay context mismatch.');

$alipayContext = Client::payContext('alipay', ['app_id' => '2021000000000001']);
assertSameValue([
    'pay_context' => [
        'alipay' => [
            'app_id' => '2021000000000001',
        ],
    ],
], $alipayContext, 'Generic pay context mismatch.');

$captured = [];
$client = new Client(
    baseUrl: 'https://payhub.example.test',
    appKey: 'app-key',
    appSecret: 'app-secret',
    httpHandler: function (string $method, string $url, array $headers, ?string $body) use (&$captured): array {
        $captured = compact('method', 'url', 'headers', 'body');

        return [200, '{"code":0,"message":"success","data":{"payment_no":"PH001"},"request_id":"REQ001"}'];
    },
    nonceGenerator: fn (): string => 'nonce-001',
    clock: fn (): int => 1781690400,
);

$response = $client->createPayment([
    'out_order_no' => 'OUT001',
    'title' => '测试订单',
    'amount' => 9900,
    'mode' => 'CASHIER',
]);

assertSameValue('PH001', $response['data']['payment_no'], 'Client should parse API response.');
assertSameValue('POST', $captured['method'], 'Client method mismatch.');
assertSameValue('https://payhub.example.test/api/payments', $captured['url'], 'Client URL mismatch.');
assertSameValue('app-key', $captured['headers']['X-App-Key'], 'Client app key header mismatch.');
assertSameValue('nonce-001', $captured['headers']['X-Nonce'], 'Client nonce header mismatch.');
assertTrueValue(is_file(__DIR__.'/../examples/web/index.php'), 'PHP web demo should exist.');

echo "PHP SDK tests passed.\n";
