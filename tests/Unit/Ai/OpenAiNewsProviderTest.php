<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Exceptions\AiNewsProviderException;
use App\Services\Ai\Data\AiNewsBatch;
use App\Services\Ai\OpenAiNewsProvider;
use PHPUnit\Framework\TestCase;

final class OpenAiNewsProviderTest extends TestCase
{
    private OpenAiNewsProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new OpenAiNewsProvider(
            apiKey: 'test-key',
            configuredModel: 'configured-model',
            baseUrl: 'https://api.example.test/v1',
            timeoutSeconds: 10,
        );
    }

    public function test_it_parses_output_text_from_a_responses_message_into_dtos(): void
    {
        $response = [
            'id' => 'resp_123',
            'status' => 'completed',
            'model' => 'gpt-5-mini-2026-08-07',
            'output' => [
                [
                    'type' => 'web_search_call',
                    'id' => 'ws_123',
                ],
                [
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => self::json([
                            'articles' => [self::articlePayload()],
                        ]),
                    ]],
                ],
            ],
        ];

        $batch = $this->provider->parseResponse($response);

        self::assertInstanceOf(AiNewsBatch::class, $batch);
        self::assertSame('resp_123', $batch->responseId);
        self::assertSame('gpt-5-mini-2026-08-07', $batch->model);
        self::assertCount(1, $batch->articles);

        $article = $batch->articles[0];
        self::assertSame('World Sailing announces a new series', $article->title);
        self::assertSame('The international federation announced a new offshore series.', $article->summary);
        self::assertSame('<p>The series starts next season.</p>', $article->content);
        self::assertSame('World Sailing', $article->sourceName);
        self::assertSame('https://www.sailing.org/news/new-series', $article->sourceUrl);
        self::assertSame('2026-08-08T12:00:00+00:00', $article->sourcePublishedAt);
        self::assertSame(91, $article->relevanceScore);
        self::assertSame('Relevant international sailing competition.', $article->selectionReason);
    }

    public function test_it_parses_top_level_output_text_and_uses_the_configured_model_fallback(): void
    {
        $batch = $this->provider->parseResponse([
            'id' => 'resp_top_level',
            'status' => 'completed',
            'output_text' => self::json(['articles' => []]),
        ]);

        self::assertSame('resp_top_level', $batch->responseId);
        self::assertSame('configured-model', $batch->model);
        self::assertSame([], $batch->articles);
    }

    public function test_it_rejects_malformed_json(): void
    {
        $this->expectException(AiNewsProviderException::class);
        $this->expectExceptionMessage('AI-провайдер вернул некорректный JSON.');

        $this->provider->parseResponse([
            'status' => 'completed',
            'output_text' => '{"articles": [}',
        ]);
    }

    public function test_it_rejects_json_without_an_articles_array(): void
    {
        $this->expectException(AiNewsProviderException::class);
        $this->expectExceptionMessage('В AI-ответе отсутствует массив articles.');

        $this->provider->parseResponse([
            'status' => 'completed',
            'output_text' => self::json(['result' => []]),
        ]);
    }

    public function test_it_rejects_a_refusal(): void
    {
        $this->expectException(AiNewsProviderException::class);
        $this->expectExceptionMessage('AI-провайдер отказался выполнять запрос.');

        $this->provider->parseResponse([
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'refusal',
                    'refusal' => 'Unable to comply.',
                ]],
            ]],
        ]);
    }

    public function test_it_rejects_an_incomplete_response_with_its_reason(): void
    {
        $this->expectException(AiNewsProviderException::class);
        $this->expectExceptionMessage('AI-провайдер вернул незавершённый ответ: max_output_tokens');

        $this->provider->parseResponse([
            'status' => 'incomplete',
            'incomplete_details' => [
                'reason' => 'max_output_tokens',
            ],
        ]);
    }

    /** @return array<string, string|int> */
    private static function articlePayload(): array
    {
        return [
            'title' => 'World Sailing announces a new series',
            'summary' => 'The international federation announced a new offshore series.',
            'article_html' => '<p>The series starts next season.</p>',
            'source_name' => 'World Sailing',
            'source_url' => 'https://www.sailing.org/news/new-series',
            'source_published_at' => '2026-08-08T12:00:00+00:00',
            'relevance_score' => 91,
            'selection_reason' => 'Relevant international sailing competition.',
        ];
    }

    /** @param array<string, mixed> $value */
    private static function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
