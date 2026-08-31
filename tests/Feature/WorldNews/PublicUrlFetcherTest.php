<?php

declare(strict_types=1);

namespace Tests\Feature\WorldNews;

use App\Services\WorldNews\PublicUrlFetcher;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesHostResolution;
use Tests\TestCase;

final class PublicUrlFetcherTest extends TestCase
{
    use FakesHostResolution;

    /** @return array<string, array{string}> */
    public static function blockedAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'частная 10/8' => ['10.0.0.5'],
            'частная 172.16/12' => ['172.16.0.1'],
            'частная 192.168/16' => ['192.168.1.1'],
            'link-local, метаданные облака' => ['169.254.169.254'],
            'CGNAT' => ['100.64.0.1'],
            'IPv6 loopback' => ['::1'],
            'IPv6 unique-local' => ['fd00::1'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv4-mapped loopback' => ['::ffff:127.0.0.1'],
        ];
    }

    #[DataProvider('blockedAddresses')]
    public function test_it_refuses_hosts_resolving_into_internal_networks(string $address): void
    {
        $this->fakeHostResolution(['internal.example.test' => [$address]]);
        Http::preventStrayRequests();
        Http::fake();

        self::assertFalse($this->fetcher()->isPublic('https://internal.example.test/'));
        self::assertNull($this->fetcher()->get('https://internal.example.test/'));
        Http::assertNothingSent();
    }

    public function test_it_allows_a_public_address(): void
    {
        $this->fakeHostResolution(['news.example.test' => ['93.184.216.34']]);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('ok')]);

        self::assertTrue($this->fetcher()->isPublic('https://news.example.test/article'));
        self::assertSame('ok', $this->fetcher()->get('https://news.example.test/article')?->body());
    }

    public function test_it_refuses_a_host_with_one_internal_address_among_several(): void
    {
        $this->fakeHostResolution(['mixed.example.test' => ['93.184.216.34', '127.0.0.1']]);
        Http::preventStrayRequests();
        Http::fake();

        self::assertNull($this->fetcher()->get('https://mixed.example.test/'));
        Http::assertNothingSent();
    }

    public function test_it_refuses_a_non_http_scheme(): void
    {
        $this->fakeHostResolution();
        Http::preventStrayRequests();
        Http::fake();

        self::assertFalse($this->fetcher()->isPublic('file:///etc/passwd'));
        self::assertFalse($this->fetcher()->isPublic('gopher://news.example.test/'));
    }

    public function test_it_refuses_credentials_in_the_url(): void
    {
        $this->fakeHostResolution();

        self::assertFalse($this->fetcher()->isPublic('https://user:pass@news.example.test/'));
    }

    public function test_it_refuses_an_unresolvable_host(): void
    {
        $this->fakeHostResolution(['nowhere.example.test' => []]);

        self::assertFalse($this->fetcher()->isPublic('https://nowhere.example.test/'));
    }

    public function test_it_follows_a_public_redirect(): void
    {
        $this->fakeHostResolution();
        Http::preventStrayRequests();
        Http::fake([
            'news.example.test/*' => Http::response('', 301, ['Location' => 'https://cdn.example.test/final']),
            'cdn.example.test/*' => Http::response('прибыли', 200),
        ]);

        self::assertSame('прибыли', $this->fetcher()->get('https://news.example.test/article')?->body());
    }

    public function test_it_stops_a_redirect_into_the_internal_network(): void
    {
        $this->fakeHostResolution([
            'news.example.test' => ['93.184.216.34'],
            'internal.example.test' => ['169.254.169.254'],
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'news.example.test/*' => Http::response('', 302, ['Location' => 'http://internal.example.test/latest/meta-data/']),
            'internal.example.test/*' => Http::response('секреты', 200),
        ]);

        self::assertNull($this->fetcher()->get('https://news.example.test/article'));
        Http::assertSentCount(1);
    }

    public function test_it_gives_up_on_a_redirect_loop(): void
    {
        $this->fakeHostResolution();
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://news.example.test/loop'])]);

        self::assertNull($this->fetcher()->get('https://news.example.test/loop'));
    }

    private function fetcher(): PublicUrlFetcher
    {
        return app(PublicUrlFetcher::class);
    }
}
