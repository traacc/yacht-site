<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'yandex_map' => [
        'api_key' => env('YANDEX_MAP_API_KEY', ''),
        'suggest_api_key' => env('YANDEX_MAP_SUGGEST_API_KEY', ''),
        'lang' => 'ru_RU',
        'center' => [55.7558, 37.6173],
        'zoom' => 10,
    ],
    // config/services.php
    'yandex_captcha' => [
        'site_key' => env('YANDEX_SMARTCAPTCHA_SITE_KEY'),
        'server_key' => env('YANDEX_SMARTCAPTCHA_SERVER_KEY'),
    ],

    'telegram' => [
        // Токен бота из @BotFather.
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        // ID канала/группы (например, @my_channel или -1001234567890).
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        // Прокси для обхода блокировки API, например:
        //   socks5://user:pass@host:1080  или  http://host:3128
        'proxy' => env('TELEGRAM_PROXY'),
        // Имя бота без @ — нужно для deep-link привязки (t.me/<bot>?start=<токен>).
        // Если не задано, определяется через getMe и кешируется на сутки.
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        // Секрет webhook: Telegram вернёт его в X-Telegram-Bot-Api-Secret-Token.
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    'vk' => [
        // Приложение VK ID (настройки приложения на id.vk.com).
        'client_id' => env('VK_CLIENT_ID'),
        'client_secret' => env('VK_CLIENT_SECRET'),
        // Стартовый refresh-токен. VK ротирует его при каждом обновлении,
        // поэтому актуальное значение хранится в таблице settings (группа vk),
        // а это — значение для «холодного» старта.
        'refresh_token' => env('VK_REFRESH_TOKEN'),
        // device_id из VK ID (если требуется вашим приложением).
        'device_id' => env('VK_DEVICE_ID'),
        // Необязательный статический access token в обход refresh-flow.
        'access_token' => env('VK_ACCESS_TOKEN'),
        // ID сообщества (положительное число, без минуса).
        'group_id' => env('VK_GROUP_ID'),
        // Версия VK API.
        'api_version' => env('VK_API_VERSION', '5.199'),
        // Прокси для VK API (если требуется): socks5://... или http://...
        'proxy' => env('VK_PROXY'),
    ],

    'openai' => [
        // Ключ хранится только в окружении, в таблицу settings не попадает.
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'news_model' => env('OPENAI_NEWS_MODEL', 'gpt-5-mini'),
        'news_timeout' => (int) env('OPENAI_NEWS_TIMEOUT', 120),
        'news_max_output_tokens' => (int) env('OPENAI_NEWS_MAX_OUTPUT_TOKENS', 12000),
    ],
];
