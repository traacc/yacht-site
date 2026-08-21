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
        // Быстрые повторы внутри одного HTTP-запроса, когда соединение с
        // api.telegram.org не установилось (прокси моргнул, таймаут, 5xx).
        'retry_times' => (int) env('TELEGRAM_RETRY_TIMES', 3),
        'retry_delay' => (int) env('TELEGRAM_RETRY_DELAY', 2000), // мс
        // Повторы публикации новости на уровне очереди: сколько раз джоба
        // PublishNewsToTelegram вернётся в очередь, если Telegram недоступен.
        'publish_tries' => (int) env('TELEGRAM_PUBLISH_TRIES', 5),
        // Пауза перед каждым следующим заходом, сек. Последнее значение
        // используется для всех оставшихся попыток.
        'publish_backoff' => [60, 300, 900, 1800],
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

    /*
     * SMS-провайдер «i-digital direct» (direct.i-dgtl.ru).
     * Используется для подтверждения телефона (см. App\Services\SmsService).
     *
     * api_key — готовая строка Basic-авторизации из ЛК (Разработчикам → API-ключи),
     * это уже base64 от <key_id>:<password>, дополнительно кодировать не нужно.
     * Нужен ключ типа TOKEN_1 («Одиночные сообщения и рассылки»).
     *
     * sender_name — одобренное имя отправителя. Для тестов провайдер разрешает
     * зарезервированное имя sms_promo.
     */
    'i_dgtl' => [
        'base_url' => env('IDGTL_BASE_URL', 'https://direct.i-dgtl.ru/api/v1'),
        'api_key' => env('IDGTL_API_KEY'),
        'sender_name' => env('IDGTL_SENDER_NAME'),
        // Вендор рекомендует ждать ответ до 70 секунд: обычно приходит за секунды,
        // но под нагрузкой ответ может задержаться.
        'timeout' => (int) env('IDGTL_TIMEOUT', 70),
        'connect_timeout' => (int) env('IDGTL_CONNECT_TIMEOUT', 8),
        // Повторы внутри запроса — только на обрыв связи и 5xx.
        'retry_times' => (int) env('IDGTL_RETRY_TIMES', 2),
        'retry_delay' => (int) env('IDGTL_RETRY_DELAY', 1000), // мс
        // Время жизни SMS у оператора: доставлять код позже его срока бессмысленно.
        'ttl' => (int) env('IDGTL_SMS_TTL', 600), // сек, 60 ≤ ttl ≤ 86400
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
