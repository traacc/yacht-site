<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\AiNewsProvider;
use App\Exceptions\AiNewsProviderException;
use App\Services\Ai\Data\AiNewsArticle;
use App\Services\Ai\Data\AiNewsBatch;
use App\Services\Ai\Data\AiNewsRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Поиск и редакционная обработка через OpenAI Responses API + web_search.
 *
 * Используется REST-клиент Laravel: отдельный SDK проекту не требуется.
 */
final class OpenAiNewsProvider implements AiNewsProvider
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $configuredModel = null,
        private readonly ?string $baseUrl = null,
        private readonly ?int $timeoutSeconds = null,
    ) {}

    public function isConfigured(): bool
    {
        return trim($this->key()) !== '' && trim($this->model()) !== '';
    }

    public function model(): string
    {
        return $this->configuredModel
            ?? (string) config('services.openai.news_model', 'gpt-5-mini');
    }

    public function discover(AiNewsRequest $request): AiNewsBatch
    {
        if (! $this->isConfigured()) {
            throw new AiNewsProviderException('AI-провайдер не настроен: задайте OPENAI_API_KEY и OPENAI_NEWS_MODEL.');
        }

        try {
            $response = Http::acceptJson()
                ->withToken($this->key())
                ->connectTimeout(10)
                ->timeout($this->timeout())
                ->retry(2, 1000, throw: false)
                ->post($this->endpoint(), $this->payload($request));
        } catch (ConnectionException $exception) {
            throw new AiNewsProviderException('Не удалось подключиться к AI-провайдеру.', previous: $exception);
        }

        if ($response->failed()) {
            throw new AiNewsProviderException($this->errorMessage($response));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new AiNewsProviderException('AI-провайдер вернул некорректный JSON-ответ.');
        }

        return $this->parseResponse($payload);
    }

    /**
     * Разбор вынесен в публичный метод, чтобы формат внешнего API можно было
     * проверять unit-тестом без HTTP и секретов.
     *
     * @param  array<string, mixed>  $response
     */
    public function parseResponse(array $response): AiNewsBatch
    {
        $status = (string) ($response['status'] ?? 'completed');
        if ($status !== 'completed') {
            $reason = data_get($response, 'incomplete_details.reason', $status);

            throw new AiNewsProviderException("AI-провайдер вернул незавершённый ответ: {$reason}");
        }

        $text = $this->outputText($response);

        try {
            $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new AiNewsProviderException('AI-провайдер вернул некорректный JSON.', previous: $exception);
        }

        if (! is_array($decoded) || ! isset($decoded['articles']) || ! is_array($decoded['articles'])) {
            throw new AiNewsProviderException('В AI-ответе отсутствует массив articles.');
        }

        try {
            $articles = array_map(
                static fn (mixed $article): AiNewsArticle => is_array($article)
                    ? AiNewsArticle::fromArray($article)
                    : throw new InvalidArgumentException('Элемент articles должен быть объектом.'),
                array_values($decoded['articles']),
            );
        } catch (InvalidArgumentException $exception) {
            throw new AiNewsProviderException($exception->getMessage(), previous: $exception);
        }

        return new AiNewsBatch(
            responseId: (string) ($response['id'] ?? ''),
            model: (string) ($response['model'] ?? $this->model()),
            articles: $articles,
        );
    }

    /** @return array<string, mixed> */
    private function payload(AiNewsRequest $request): array
    {
        return [
            'model' => $this->model(),
            'store' => false,
            'instructions' => $request->systemPrompt,
            'input' => $request->searchPrompt,
            'tools' => [[
                'type' => 'web_search',
                'search_context_size' => 'high',
            ]],
            'tool_choice' => 'auto',
            'include' => ['web_search_call.action.sources'],
            'max_output_tokens' => (int) config('services.openai.news_max_output_tokens', 12000),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'sailing_news_discovery',
                    'strict' => true,
                    'schema' => $this->schema($request->maxItems),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function schema(int $maxItems): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'articles' => [
                    'type' => 'array',
                    'maxItems' => max(1, min(10, $maxItems)),
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'summary' => ['type' => 'string'],
                            'article_html' => ['type' => 'string'],
                            'source_name' => ['type' => 'string'],
                            'source_url' => ['type' => 'string'],
                            'source_published_at' => ['type' => 'string'],
                            'relevance_score' => [
                                'type' => 'integer',
                                'minimum' => 0,
                                'maximum' => 100,
                            ],
                            'selection_reason' => ['type' => 'string'],
                        ],
                        'required' => [
                            'title',
                            'summary',
                            'article_html',
                            'source_name',
                            'source_url',
                            'source_published_at',
                            'relevance_score',
                            'selection_reason',
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['articles'],
            'additionalProperties' => false,
        ];
    }

    /** @param array<string, mixed> $response */
    private function outputText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }

        foreach ((array) ($response['output'] ?? []) as $output) {
            if (! is_array($output) || ($output['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($output['content'] ?? []) as $content) {
                if (! is_array($content)) {
                    continue;
                }

                if (($content['type'] ?? null) === 'refusal') {
                    throw new AiNewsProviderException('AI-провайдер отказался выполнять запрос.');
                }

                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new AiNewsProviderException('AI-провайдер не вернул текстовый результат.');
    }

    private function errorMessage(Response $response): string
    {
        $providerMessage = $response->json('error.message');
        $suffix = is_string($providerMessage) && $providerMessage !== ''
            ? ': '.Str::limit($providerMessage, 500)
            : '';

        return "Ошибка AI-провайдера HTTP {$response->status()}{$suffix}";
    }

    private function key(): string
    {
        return $this->apiKey ?? (string) config('services.openai.api_key', '');
    }

    private function endpoint(): string
    {
        $baseUrl = $this->baseUrl
            ?? (string) config('services.openai.base_url', 'https://api.openai.com/v1');

        return rtrim($baseUrl, '/').'/responses';
    }

    private function timeout(): int
    {
        return $this->timeoutSeconds
            ?? (int) config('services.openai.news_timeout', 120);
    }
}
