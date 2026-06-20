<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

use Anhao\PaymentHub\Client;
use Anhao\PaymentHub\Webhook;

$baseUrl = env('PAYMENT_HUB_BASE_URL', 'https://payhub.alapi.cn');
$appKey = env('PAYMENT_HUB_APP_KEY', '');
$appSecret = env('PAYMENT_HUB_APP_SECRET', '');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/webhook' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $body = file_get_contents('php://input') ?: '';

    if (! Webhook::verifySignature(getallheaders(), $body, $appSecret)) {
        http_response_code(401);
        echo 'invalid signature';
        return;
    }

    header('Content-Type: application/json');
    echo json_encode(['code' => 0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

if ($path === '/pay' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $client = new Client($baseUrl, $appKey, $appSecret);

    try {
        $response = $client->createPayment([
            'out_order_no' => 'PHP_DEMO_'.date('YmdHis'),
            'title' => trim((string) ($_POST['title'] ?? 'PHP SDK Demo')),
            'amount' => max(1, (int) ($_POST['amount'] ?? 1)),
            'mode' => 'CASHIER',
            'return_url' => absoluteUrl('/'),
        ]);

        renderPage('Payment Created', paymentResultHtml($response));
    } catch (Throwable $e) {
        renderPage('Create Payment Failed', '<pre>'.h($e->getMessage()).'</pre>');
    }

    return;
}

renderPage('Payment Hub PHP Demo', formHtml($baseUrl, $appKey));

function formHtml(string $baseUrl, string $appKey): string
{
    return '
        <p>Base URL: <code>'.h($baseUrl).'</code></p>
        <p>App Key: <code>'.h($appKey !== '' ? $appKey : 'not set').'</code></p>
        <form method="post" action="/pay">
            <label>Title <input name="title" value="PHP SDK Demo"></label>
            <label>Amount cents <input name="amount" type="number" min="1" value="1"></label>
            <button type="submit">Create Cashier Payment</button>
        </form>
        <p>Webhook URL for this demo: <code>'.h(absoluteUrl('/webhook')).'</code></p>
    ';
}

/** @param array<string, mixed> $response */
function paymentResultHtml(array $response): string
{
    $data = is_array($response['data'] ?? null) ? $response['data'] : [];
    $paymentUrl = (string) ($data['payment_url'] ?? '');

    return '
        <p>Payment No: <code>'.h((string) ($data['payment_no'] ?? '')).'</code></p>
        <p><a href="'.h($paymentUrl).'" target="_blank" rel="noreferrer">Open cashier</a></p>
        <pre>'.h(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '').'</pre>
        <p><a href="/">Back</a></p>
    ';
}

function renderPage(string $title, string $body): void
{
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>'.h($title).'</title><style>
        body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;max-width:760px;margin:40px auto;padding:0 20px;line-height:1.5}
        form{display:grid;gap:12px;max-width:360px}
        input{display:block;width:100%;box-sizing:border-box;padding:8px;margin-top:4px}
        button{padding:10px 14px}
        pre{background:#f6f8fa;padding:16px;overflow:auto}
    </style></head><body><h1>'.h($title).'</h1>'.$body.'</body></html>';
}

function absoluteUrl(string $path): string
{
    $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8081';

    return $scheme.'://'.$host.$path;
}

function env(string $key, string $default): string
{
    $value = getenv($key);

    return is_string($value) && $value !== '' ? $value : $default;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
