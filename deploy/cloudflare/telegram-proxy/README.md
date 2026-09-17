# Telegram Bot API через Cloudflare Worker

Обход блокировки `api.telegram.org`: сайт шлёт запросы Bot API на Worker, тот пересылает их в Telegram.
Входящий webhook (Telegram → `/api/telegram/webhook`) идёт напрямую на сайт, Worker его не касается.

## Развёртывание

```bash
cd deploy/cloudflare/telegram-proxy
npx wrangler login
npx wrangler deploy
npx wrangler secret put PROXY_SECRET   # случайная строка, например: openssl rand -hex 32
```

Привяжите свой домен (раскомментируйте `routes` в `wrangler.toml` или Workers → Settings → Domains & Routes):
`*.workers.dev` из РФ часто недоступен.

## Настройка сайта

В `.env`:

```
TELEGRAM_API_URL=https://tg.example.com
TELEGRAM_API_SECRET=<тот же PROXY_SECRET>
TELEGRAM_PROXY=
```

Затем:

```bash
docker exec yacht-site-laravel.worker-1 php artisan config:clear
docker restart yacht-site-laravel.worker-1   # воркер очереди читает .env только при старте
```

## Проверка

```bash
curl -H "X-Proxy-Secret: <секрет>" https://tg.example.com/bot<TOKEN>/getMe
docker exec yacht-site-laravel.worker-1 php artisan telegram:set-webhook
```
