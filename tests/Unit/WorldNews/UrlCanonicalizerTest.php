<?php

declare(strict_types=1);

namespace Tests\Unit\WorldNews;

use App\Services\WorldNews\UrlCanonicalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlCanonicalizerTest extends TestCase
{
    private UrlCanonicalizer $canonicalizer;

    protected function setUp(): void
    {
        $this->canonicalizer = new UrlCanonicalizer;
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function canonicalUrls(): iterable
    {
        yield 'normalizes origin and removes tracking data' => [
            '  HTTPS://WWW.Example.COM:443/news/story?utm_source=telegram&b=2&a=1#comments  ',
            'https://example.com/news/story?a=1&b=2',
        ];

        yield 'uses a slash for an empty path and removes a default http port' => [
            'http://EXAMPLE.com:80',
            'http://example.com/',
        ];

        yield 'preserves a non-default port and removes a path trailing slash' => [
            'https://Example.com:8443/archive/',
            'https://example.com:8443/archive',
        ];

        yield 'removes known click identifiers case-insensitively' => [
            'https://example.com/article?id=42&FBCLID=facebook&Gclid=google&yclid=yandex&_OPENSTAT=openstat&UTM_Medium=social',
            'https://example.com/article?id=42',
        ];

        yield 'does not leave an empty query delimiter' => [
            'https://example.com/article?utm_campaign=summer#top',
            'https://example.com/article',
        ];
    }

    #[DataProvider('canonicalUrls')]
    public function test_it_canonicalizes_absolute_http_urls(string $url, string $expected): void
    {
        self::assertSame($expected, $this->canonicalizer->canonicalize($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'relative' => ['/news/story'];
        yield 'host without scheme' => ['example.com/news/story'];
        yield 'unsupported scheme' => ['ftp://example.com/news/story'];
        yield 'missing host' => ['https:///news/story'];
    }

    #[DataProvider('invalidUrls')]
    public function test_it_rejects_invalid_or_unsupported_urls(string $url): void
    {
        self::assertNull($this->canonicalizer->canonicalize($url));
        self::assertNull($this->canonicalizer->fingerprint($url));
    }

    public function test_fingerprint_is_sha256_of_the_canonical_url(): void
    {
        $url = 'HTTPS://Example.com:443/story?utm_source=rss&id=7#read';
        $canonicalUrl = 'https://example.com/story?id=7';

        self::assertSame(
            hash('sha256', $canonicalUrl),
            $this->canonicalizer->fingerprint($url),
        );
    }

    public function test_equivalent_url_variants_have_the_same_fingerprint(): void
    {
        $plain = 'https://example.com/story?a=1&b=2';
        $tracked = ' HTTPS://EXAMPLE.COM:443/story?b=2&utm_campaign=race&a=1#results ';

        self::assertSame(
            $this->canonicalizer->fingerprint($plain),
            $this->canonicalizer->fingerprint($tracked),
        );
    }
}
