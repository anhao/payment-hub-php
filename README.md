# Payment Hub PHP SDK

Composer 包名：`anhao/payment-hub-client`

开源仓库：`https://github.com/anhao/payment-hub-php`

许可证：MIT

## 安装

```bash
composer require anhao/payment-hub-client
```

本仓库内开发测试：

```bash
composer dump-autoload
composer test
```

## 创建收银台支付

```php
use Anhao\PaymentHub\Client;

$client = new Client(
    baseUrl: 'https://payhub.alapi.cn',
    appKey: 'your_app_key',
    appSecret: 'your_app_secret',
);

$response = $client->createPayment([
    'out_order_no' => 'WPUSH202606180001',
    'title' => 'WPUSH 年费会员',
    'amount' => 9900,
    'mode' => 'CASHIER',
    // 微信 JSAPI / 小程序收银台支付时传入；普通扫码支付可不传。
    'extra' => Client::wechatPayContext('wx-mini-appid', 'openid-from-miniapp'),
]);

header('Location: '.$response['data']['payment_url']);
```

`Client::wechatPayContext()` 会生成 `extra.pay_context.wechat`，Hub 会用其中的 `appid` 过滤支付账户，并在微信 JSAPI / 小程序下单时传递 `openid`。

## 直连支付宝当面付

```php
$response = $client->createPayment([
    'out_order_no' => 'WPUSH202606180002',
    'title' => 'WPUSH 年费会员',
    'amount' => 9900,
    'mode' => 'DIRECT',
    'provider' => 'alipay',
    'method' => 'FACE_TO_FACE',
]);

$codeUrl = $response['data']['code_url'];
```

## 查询和退款

```php
$payment = $client->getPayment('PH20260618ABC123XYZ0');

$refund = $client->createRefund([
    'payment_no' => 'PH20260618ABC123XYZ0',
    'out_refund_no' => 'WPUSH_REFUND_001',
    'amount' => 9900,
    'reason' => '用户申请退款',
]);
```

## Webhook 验签

```php
use Anhao\PaymentHub\Webhook;

$body = file_get_contents('php://input') ?: '';

if (! Webhook::verifySignature(getallheaders(), $body, 'your_app_secret')) {
    http_response_code(401);
    exit('invalid signature');
}

$payload = json_decode($body, true);

// 幂等处理 payment.success / refund.success ...

echo json_encode(['code' => 0]);
```

## 支付完成跳转验签

```php
use Anhao\PaymentHub\Signature;

if (! Signature::verifyReturnUrl($_GET, 'your_app_secret')) {
    http_response_code(401);
    exit('invalid signature');
}

// 仅用于页面展示或定位订单；最终发货仍以 Webhook / 主动查询为准。
$paymentNo = $_GET['payment_no'] ?? '';
```
