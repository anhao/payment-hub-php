# PHP Web Demo

Run from `sdks/php`:

```bash
composer dump-autoload
PAYMENT_HUB_BASE_URL=https://payhub.alapi.cn \
PAYMENT_HUB_APP_KEY=your_app_key \
PAYMENT_HUB_APP_SECRET=your_app_secret \
php -S 127.0.0.1:8081 -t examples/web examples/web/index.php
```

Open `http://127.0.0.1:8081`.

Use `http://127.0.0.1:8081/webhook` as the demo Webhook endpoint when testing through a tunnel.
