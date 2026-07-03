<?php

namespace App\Services;

use App\Models\News;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VkService
{
    // Через прокси и при загрузке картинки соединение бывает медленным.
    private const TIMEOUT_SECONDS = 25;
    private const CONNECT_TIMEOUT_SECONDS = 8;

    // VK позволяет очень длинные посты; ограничиваем тело новости разумным запасом.
    private const TEXT_LIMIT = 4000;

    // Эндпоинт VK ID для обмена refresh-токена на access-токен.
    private const TOKEN_ENDPOINT = 'https://id.vk.com/oauth2/auth';

    // Ключи в таблице settings (группа «vk»), где хранится ротируемый токен.
    private const SETTING_ACCESS_TOKEN = 'vk.access_token';
    private const SETTING_ACCESS_EXPIRES = 'vk.access_token_expires_at';
    private const SETTING_REFRESH_TOKEN = 'vk.refresh_token';

    public function __construct(
        private readonly ?string $accessToken = null,
        private readonly ?string $groupId = null,
        private readonly ?string $apiVersion = null,
        private readonly ?string $proxy = null,
        private ?SettingsService $settings = null,
    ) {
        // Значения по умолчанию берём из config/services.php.
    }

    private function settings(): SettingsService
    {
        return $this->settings ??= app(SettingsService::class);
    }

    /**
     * Статический access token (в обход refresh-flow), если он задан вручную.
     */
    private function staticToken(): ?string
    {
        return $this->accessToken ?? config('services.vk.access_token') ?: null;
    }

    private function group(): ?string
    {
        return $this->groupId ?? config('services.vk.group_id');
    }

    private function version(): string
    {
        return $this->apiVersion ?? config('services.vk.api_version', '5.199');
    }

    private function proxy(): ?string
    {
        return $this->proxy ?? config('services.vk.proxy');
    }

    public function isConfigured(): bool
    {
        if (empty($this->group())) {
            return false;
        }

        // Либо задан статический токен, либо есть всё для refresh-flow.
        if (! empty($this->staticToken())) {
            return true;
        }

        return ! empty(config('services.vk.client_id'))
            && ! empty(config('services.vk.client_secret'))
            && ! empty($this->currentRefreshToken());
    }

    /**
     * Публикует новость на стене сообщества VK.
     * Если есть обложка — сначала загружает фото и прикрепляет его к посту,
     * иначе отправляет только текст со ссылкой.
     */
    public function publishNews(News $news): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('VK is not configured, skipping news publication', [
                'news_id' => $news->id,
            ]);

            return false;
        }

        $coverPath  = $this->coverPath($news);
        $attachment = $coverPath !== null ? $this->uploadWallPhoto($coverPath, $news) : null;

        return $this->wallPost($this->caption($news), $attachment, $news);
    }

    // ──────────────────────────────────────────────
    // Access token (VK ID, refresh-token flow)
    // ──────────────────────────────────────────────

    /**
     * Возвращает действующий access token: статический (если задан) либо
     * полученный/обновлённый по refresh-токену.
     */
    private function accessToken(News $news): ?string
    {
        $static = $this->staticToken();

        if (! empty($static)) {
            return $static;
        }

        return $this->accessTokenViaRefresh($news);
    }

    /**
     * Отдаёт кэшированный access token, а при истечении срока — обновляет его.
     * Обновление сериализуется блокировкой, чтобы параллельные процессы не
     * ротировали refresh-токен дважды (второй раз он уже недействителен).
     */
    private function accessTokenViaRefresh(News $news): ?string
    {
        if (($token = $this->cachedAccessToken()) !== null) {
            return $token;
        }

        $lock = Cache::lock('vk:token-refresh', 20);

        try {
            $lock->block(15);
        } catch (LockTimeoutException) {
            // Токен обновляет другой процесс — берём то, что уже сохранено.
            return $this->cachedAccessToken();
        }

        try {
            // Повторная проверка: токен мог обновить сосед, пока мы ждали.
            return $this->cachedAccessToken() ?? $this->refreshAccessToken($news);
        } finally {
            $lock->release();
        }
    }

    /**
     * Действующий access token из настроек либо null, если он истёк
     * (запас 60 секунд на выполнение запросов).
     */
    private function cachedAccessToken(): ?string
    {
        $token     = $this->settings()->get(self::SETTING_ACCESS_TOKEN);
        $expiresAt = (int) $this->settings()->get(self::SETTING_ACCESS_EXPIRES, 0);

        return (! empty($token) && $expiresAt > time() + 60) ? (string) $token : null;
    }

    /**
     * Обменивает refresh-токен на новый access-токен и сохраняет обновлённые
     * значения (включая новый refresh-токен — VK ротирует его при каждом обмене).
     */
    private function refreshAccessToken(News $news): ?string
    {
        $refreshToken = $this->currentRefreshToken();

        if (empty($refreshToken)) {
            Log::warning('VK refresh token is not configured', ['news_id' => $news->id]);

            return null;
        }

        $params = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => config('services.vk.client_id'),
            'client_secret' => config('services.vk.client_secret'),
        ];

        // device_id требуется некоторым приложениям VK ID — передаём, если задан.
        if (! empty($deviceId = config('services.vk.device_id'))) {
            $params['device_id'] = $deviceId;
        }

        try {
            $response = $this->baseRequest()->asForm()->post(self::TOKEN_ENDPOINT, $params);
        } catch (ConnectionException | RequestException $e) {
            Log::warning('VK token refresh connection failed', [
                'news_id' => $news->id,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || empty($json['access_token'])) {
            Log::warning('VK token refresh failed', [
                'news_id' => $news->id,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            return null;
        }

        $accessToken = (string) $json['access_token'];
        $expiresIn   = (int) ($json['expires_in'] ?? 3600);

        $this->settings()->set(self::SETTING_ACCESS_TOKEN, $accessToken, 'vk');
        $this->settings()->set(self::SETTING_ACCESS_EXPIRES, time() + $expiresIn, 'vk');
        // Сохраняем новый refresh-токен: без этого следующее обновление упадёт.
        $this->settings()->set(self::SETTING_REFRESH_TOKEN, (string) ($json['refresh_token'] ?? $refreshToken), 'vk');

        return $accessToken;
    }

    /**
     * Актуальный refresh-токен: сначала из настроек (ротируемый),
     * затем стартовое значение из конфига/.env.
     */
    private function currentRefreshToken(): ?string
    {
        $stored = $this->settings()->get(self::SETTING_REFRESH_TOKEN);

        if (! empty($stored)) {
            return (string) $stored;
        }

        return config('services.vk.refresh_token') ?: null;
    }

    // ──────────────────────────────────────────────
    // Публикация
    // ──────────────────────────────────────────────

    /**
     * Загружает обложку на стену сообщества и возвращает строку вложения
     * вида «photo{owner_id}_{id}» для wall.post, либо null при неудаче
     * (тогда новость уйдёт текстом со ссылкой).
     */
    private function uploadWallPhoto(string $coverPath, News $news): ?string
    {
        // 1. Получаем адрес сервера для загрузки.
        $server = $this->apiCall('photos.getWallUploadServer', [
            'group_id' => $this->group(),
        ], $news);

        if ($server === null || empty($server['upload_url'])) {
            return null;
        }

        // 2. Загружаем файл картинки на полученный сервер (multipart).
        $contents = Storage::disk('public')->get($coverPath);

        try {
            $uploaded = $this->baseRequest()
                ->attach('photo', $contents, basename($coverPath))
                ->post($server['upload_url'])
                ->json();
        } catch (ConnectionException | RequestException $e) {
            Log::warning('VK photo upload connection failed', [
                'news_id' => $news->id,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }

        // Пустой массив фото (строка «[]») означает, что загрузить не удалось.
        if (! is_array($uploaded) || empty($uploaded['photo']) || $uploaded['photo'] === '[]') {
            Log::warning('VK photo upload returned no photo', [
                'news_id'  => $news->id,
                'response' => $uploaded,
            ]);

            return null;
        }

        // 3. Сохраняем загруженное фото в сообществе.
        $saved = $this->apiCall('photos.saveWallPhoto', [
            'group_id' => $this->group(),
            'server'   => $uploaded['server'],
            'photo'    => $uploaded['photo'],
            'hash'     => $uploaded['hash'],
        ], $news);

        if ($saved === null || empty($saved[0])) {
            return null;
        }

        $photo = $saved[0];

        return 'photo' . $photo['owner_id'] . '_' . $photo['id'];
    }

    /**
     * Публикует запись на стене сообщества от его имени.
     */
    private function wallPost(string $message, ?string $attachment, News $news): bool
    {
        $params = [
            'owner_id'   => '-' . $this->group(),
            'from_group' => 1,
            'message'    => $message,
        ];

        if ($attachment !== null) {
            $params['attachments'] = $attachment;
        }

        $result = $this->apiCall('wall.post', $params, $news);

        return $result !== null && isset($result['post_id']);
    }

    /**
     * Базовый запрос с таймаутами, ретраями и прокси (если задан).
     */
    private function baseRequest(): PendingRequest
    {
        $request = Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(2, 1000, throw: false);

        if (! empty($this->proxy())) {
            $request->withOptions(['proxy' => $this->proxy()]);
        }

        return $request;
    }

    private function endpoint(string $method): string
    {
        return "https://api.vk.com/method/{$method}";
    }

    /**
     * Вызывает метод VK API и единообразно обрабатывает ответ/ошибки.
     * Возвращает содержимое ключа «response» либо null при ошибке.
     * При ошибке авторизации (код 5) один раз сбрасывает access-токен и
     * повторяет запрос — на случай, если токен протух между вызовами.
     *
     * @param  array<string, mixed>  $params
     * @return array<mixed>|null
     */
    private function apiCall(string $method, array $params, News $news, bool $allowRetry = true): ?array
    {
        $token = $this->accessToken($news);

        if ($token === null) {
            Log::warning('VK access token unavailable', [
                'news_id' => $news->id,
                'method'  => $method,
            ]);

            return null;
        }

        $params['access_token'] = $token;
        $params['v']            = $this->version();

        try {
            $response = $this->baseRequest()->asForm()->post($this->endpoint($method), $params);
            $json     = $response->json();

            if ($response->successful() && is_array($json) && array_key_exists('response', $json)) {
                return (array) $json['response'];
            }

            // Код 5 — «User authorization failed»: токен протух. Сбрасываем кэш
            // и повторяем один раз (только если работаем через refresh-flow).
            if ($allowRetry
                && ($json['error']['error_code'] ?? null) === 5
                && empty($this->staticToken())
            ) {
                $this->settings()->set(self::SETTING_ACCESS_EXPIRES, 0, 'vk');

                return $this->apiCall($method, $params, $news, allowRetry: false);
            }

            Log::warning('VK API responded with error', [
                'news_id' => $news->id,
                'method'  => $method,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            return null;
        } catch (ConnectionException | RequestException $e) {
            Log::warning('VK API connection failed', [
                'news_id' => $news->id,
                'method'  => $method,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Формирует текст поста: заголовок, краткое содержимое и ссылку на новость.
     * VK не поддерживает HTML, поэтому отправляем чистый текст — ссылку VK
     * подхватит и оформит превью автоматически.
     */
    private function caption(News $news): string
    {
        $url   = route('news-details', $news);
        $title = trim((string) $news->title);

        $body = Str::limit($this->plainContent($news), self::TEXT_LIMIT, '…');

        $parts = array_filter([
            $title,
            $body !== '' ? $body : null,
            $url,
        ]);

        return implode("\n\n", $parts);
    }

    /**
     * Превращает HTML-содержимое новости в чистый текст.
     */
    private function plainContent(News $news): string
    {
        $text = preg_replace('/<\/(p|div|br|li|h[1-6])\s*\/?>/i', "\n", (string) $news->content);

        return trim(preg_replace("/\n{3,}/", "\n\n", strip_tags(html_entity_decode($text))));
    }

    /**
     * Относительный путь к обложке на публичном диске, если файл существует.
     * Если файла нет локально — вернётся null, и новость уйдёт текстом со ссылкой.
     */
    private function coverPath(News $news): ?string
    {
        if (empty($news->cover_image_url)) {
            return null;
        }

        return Storage::disk('public')->exists($news->cover_image_url)
            ? $news->cover_image_url
            : null;
    }
}
