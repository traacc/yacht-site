<?php

declare(strict_types=1);

namespace Tests\Feature\WorldNews;

use App\Services\WorldNews\CoverImageDownloader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CoverImageDownloaderTest extends TestCase
{
    public function test_it_stores_the_image_next_to_manual_covers(): void
    {
        Storage::fake('public');
        $this->fakeImage('image/jpeg', 'binary-jpeg');

        $path = $this->store('https://cdn.example.test/cover.jpg');

        self::assertNotNull($path);
        self::assertStringStartsWith('news/covers/', $path);
        self::assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
        self::assertSame('binary-jpeg', Storage::disk('public')->get($path));
    }

    public function test_it_derives_the_extension_from_the_content_type(): void
    {
        Storage::fake('public');
        $this->fakeImage('image/webp; charset=binary', 'binary-webp');

        self::assertStringEndsWith('.webp', (string) $this->store('https://cdn.example.test/cover'));
    }

    public function test_it_refuses_a_non_image_content_type(): void
    {
        Storage::fake('public');
        $this->fakeImage('text/html', '<html></html>');

        self::assertNull($this->store('https://cdn.example.test/not-an-image'));
        self::assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_it_gives_up_on_a_failing_download(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 500)]);

        self::assertNull($this->store('https://cdn.example.test/cover.jpg'));
    }

    public function test_it_skips_an_empty_url(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake();

        self::assertNull($this->store(null));
        self::assertNull($this->store('  '));
        Http::assertNothingSent();
    }

    private function fakeImage(string $contentType, string $body): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response($body, 200, ['Content-Type' => $contentType])]);
    }

    private function store(?string $url): ?string
    {
        return app(CoverImageDownloader::class)->store($url);
    }
}
