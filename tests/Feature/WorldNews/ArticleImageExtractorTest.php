<?php

declare(strict_types=1);

namespace Tests\Feature\WorldNews;

use App\Services\WorldNews\ArticleImageExtractor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ArticleImageExtractorTest extends TestCase
{
    public function test_it_prefers_open_graph_over_twitter_tags(): void
    {
        $this->fakePage(<<<'HTML'
            <html><head>
                <meta name="twitter:image" content="https://cdn.example.test/twitter.jpg">
                <meta property="og:image" content="https://cdn.example.test/og.jpg">
            </head><body></body></html>
            HTML);

        self::assertSame(
            'https://cdn.example.test/og.jpg',
            $this->extract('https://news.example.test/article'),
        );
    }

    public function test_it_resolves_a_root_relative_path(): void
    {
        $this->fakePage('<html><head><meta property="og:image" content="/media/cover.jpg"></head></html>');

        self::assertSame(
            'https://news.example.test/media/cover.jpg',
            $this->extract('https://news.example.test/sport/article'),
        );
    }

    public function test_it_resolves_a_protocol_relative_url(): void
    {
        $this->fakePage('<html><head><meta property="og:image" content="//cdn.example.test/cover.jpg"></head></html>');

        self::assertSame(
            'https://cdn.example.test/cover.jpg',
            $this->extract('https://news.example.test/article'),
        );
    }

    public function test_it_falls_back_to_the_image_src_link(): void
    {
        $this->fakePage('<html><head><link rel="image_src" href="https://cdn.example.test/link.jpg"></head></html>');

        self::assertSame(
            'https://cdn.example.test/link.jpg',
            $this->extract('https://news.example.test/article'),
        );
    }

    public function test_it_keeps_cyrillic_markup_parseable(): void
    {
        $this->fakePage(<<<'HTML'
            <html><head><title>Регата в Сочи</title>
                <meta property="og:image" content="https://cdn.example.test/cover.jpg">
            </head><body><p>Гонки при переменчивом ветре</p></body></html>
            HTML);

        self::assertSame(
            'https://cdn.example.test/cover.jpg',
            $this->extract('https://news.example.test/article'),
        );
    }

    public function test_it_returns_null_without_any_image_tag(): void
    {
        $this->fakePage('<html><head><title>Без картинки</title></head></html>');

        self::assertNull($this->extract('https://news.example.test/article'));
    }

    public function test_it_ignores_inline_data_uris(): void
    {
        $this->fakePage('<html><head><meta property="og:image" content="data:image/gif;base64,R0lGOD"></head></html>');

        self::assertNull($this->extract('https://news.example.test/article'));
    }

    public function test_it_gives_up_on_a_failing_page(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('nope', 404)]);

        self::assertNull($this->extract('https://news.example.test/article'));
    }

    public function test_it_skips_non_html_responses(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('{}', 200, ['Content-Type' => 'application/json'])]);

        self::assertNull($this->extract('https://news.example.test/article'));
    }

    public function test_it_rejects_a_malformed_source_url_without_any_request(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        self::assertNull($this->extract('not-a-url'));
        Http::assertNothingSent();
    }

    private function fakePage(string $html): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html; charset=utf-8'])]);
    }

    private function extract(string $url): ?string
    {
        return app(ArticleImageExtractor::class)->extract($url);
    }
}
