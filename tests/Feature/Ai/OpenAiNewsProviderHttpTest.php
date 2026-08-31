<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Services\Ai\Data\AiNewsRequest;
use App\Services\Ai\OpenAiNewsProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OpenAiNewsProviderHttpTest extends TestCase
{
    public function test_it_sends_a_structured_web_search_request_to_the_responses_api(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.example.test/v1/responses' => Http::response([
                'id' => 'resp_http_test',
                'status' => 'completed',
                'model' => 'test-model',
                'output' => [['type' => 'web_search_call', 'id' => 'ws_http_test']],
                'output_text' => json_encode(['articles' => []], JSON_THROW_ON_ERROR),
            ]),
        ]);

        $provider = new OpenAiNewsProvider(
            apiKey: 'test-key',
            configuredModel: 'test-model',
            baseUrl: 'https://api.example.test/v1',
            timeoutSeconds: 10,
        );

        $batch = $provider->discover(new AiNewsRequest(
            systemPrompt: 'Editorial rules',
            searchPrompt: 'Find recent sailing news',
            maxItems: 3,
        ));

        self::assertSame('resp_http_test', $batch->responseId);
        self::assertSame([], $batch->articles);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.example.test/v1/responses'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && data_get($payload, 'model') === 'test-model'
                && data_get($payload, 'instructions') === 'Editorial rules'
                && data_get($payload, 'input') === 'Find recent sailing news'
                && data_get($payload, 'tools.0.type') === 'web_search'
                && data_get($payload, 'text.format.type') === 'json_schema'
                && data_get($payload, 'text.format.strict') === true
                && data_get($payload, 'text.format.schema.properties.articles.maxItems') === 3;
        });
        Http::assertSentCount(1);
    }
}
