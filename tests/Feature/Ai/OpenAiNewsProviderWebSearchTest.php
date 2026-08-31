<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\AiWebSearchMode;
use App\Services\Ai\Data\AiNewsRequest;
use App\Services\Ai\OpenAiNewsProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OpenAiNewsProviderWebSearchTest extends TestCase
{
    public function test_openai_models_get_the_native_web_search_tool(): void
    {
        $this->fakeEmptyResponse();

        $this->discoverWith('openai/gpt-5-mini');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return data_get($payload, 'tools.0.type') === 'web_search'
                && data_get($payload, 'include.0') === 'web_search_call.action.sources'
                && ! array_key_exists('plugins', $payload);
        });
    }

    public function test_other_vendors_get_the_router_web_plugin(): void
    {
        $this->fakeEmptyResponse();

        $this->discoverWith('deepseek/deepseek-chat-v3.1');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return data_get($payload, 'plugins.0.id') === 'web'
                && data_get($payload, 'plugins.0.max_results') === 5
                && ! array_key_exists('tools', $payload);
        });
    }

    public function test_bare_model_ids_are_treated_as_direct_openai_calls(): void
    {
        $this->fakeEmptyResponse();

        $this->discoverWith('gpt-5-mini');

        Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'tools.0.type') === 'web_search');
    }

    public function test_the_mode_can_be_forced_regardless_of_the_model(): void
    {
        $this->fakeEmptyResponse();

        $this->discoverWith('openai/gpt-5-mini', AiWebSearchMode::Plugin);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return data_get($payload, 'plugins.0.id') === 'web'
                && ! array_key_exists('tools', $payload);
        });
    }

    public function test_search_can_be_disabled_entirely(): void
    {
        $this->fakeEmptyResponse();

        $this->discoverWith('deepseek/deepseek-chat-v3.1', AiWebSearchMode::Off);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return ! array_key_exists('tools', $payload)
                && ! array_key_exists('plugins', $payload)
                && data_get($payload, 'text.format.type') === 'json_schema';
        });
    }

    private function fakeEmptyResponse(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.example.test/v1/responses' => Http::response([
                'id' => 'resp_web_search_test',
                'status' => 'completed',
                'output_text' => json_encode(['articles' => []], JSON_THROW_ON_ERROR),
            ]),
        ]);
    }

    private function discoverWith(string $model, ?AiWebSearchMode $mode = null): void
    {
        $provider = new OpenAiNewsProvider(
            apiKey: 'test-key',
            configuredModel: $model,
            baseUrl: 'https://api.example.test/v1',
            timeoutSeconds: 10,
            webSearchMode: $mode,
        );

        $provider->discover(new AiNewsRequest(
            systemPrompt: 'Editorial rules',
            searchPrompt: 'Find recent sailing news',
            maxItems: 3,
        ));
    }
}
