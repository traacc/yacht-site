<?php

declare(strict_types=1);

namespace Tests\Feature\WorldNews;

use App\Services\WorldNews\CoverImageDownloader;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesHostResolution;
use Tests\TestCase;

final class CoverImageDownloaderTest extends TestCase
{
    use FakesHostResolution;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeHostResolution();
    }

    public function test_it_stores_the_image_next_to_manual_covers(): void
    {
        Storage::fake('public');
        $this->fakeImage('image/jpeg', self::imageBytes('jpeg'));

        $path = $this->store('https://cdn.example.test/cover.jpg');

        self::assertNotNull($path);
        self::assertStringStartsWith('news/covers/', $path);
        self::assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
        self::assertSame(self::imageBytes('jpeg'), Storage::disk('public')->get($path));
    }

    public function test_it_derives_the_extension_from_the_content_type(): void
    {
        Storage::fake('public');
        $this->fakeImage('image/webp; charset=binary', self::imageBytes('webp'));

        self::assertStringEndsWith('.webp', (string) $this->store('https://cdn.example.test/cover'));
    }

    public function test_it_sends_the_source_page_as_referer(): void
    {
        Storage::fake('public');
        $this->fakeImage('image/jpeg', self::imageBytes('jpeg'));

        app(CoverImageDownloader::class)->store(
            'https://cdn.example.test/cover.jpg',
            'https://news.example.test/article',
        );

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Referer', 'https://news.example.test/article'));
    }

    public function test_it_omits_the_referer_when_no_page_is_given(): void
    {
        Storage::fake('public');
        $this->fakeImage('image/jpeg', self::imageBytes('jpeg'));

        $this->store('https://cdn.example.test/cover.jpg');

        Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Referer'));
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

    public function test_it_refuses_a_tracking_pixel(): void
    {
        Storage::fake('public');
        $this->fakeImage('image/gif', self::imageBytes('gif', 1, 1));

        self::assertNull($this->store('https://cdn.example.test/pixel.gif'));
        self::assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_it_refuses_an_image_smaller_than_the_threshold(): void
    {
        Storage::fake('public');
        $this->fakeImage('image/png', self::imageBytes('png', 150, 150));

        self::assertNull($this->store('https://cdn.example.test/logo.png'));
    }

    public function test_it_refuses_bytes_that_are_not_an_image(): void
    {
        Storage::fake('public');
        $this->fakeImage('image/jpeg', 'на самом деле это не картинка');

        self::assertNull($this->store('https://cdn.example.test/broken.jpg'));
        self::assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_it_accepts_a_small_engine_generated_preview(): void
    {
        Storage::fake('public');
        // Drupal отдаёт в og:image derivative такого размера — это фото статьи.
        $this->fakeImage('image/png', self::imageBytes('png', 220, 147));

        self::assertNotNull($this->store('https://cdn.example.test/preview.png'));
    }

    public function test_it_refuses_a_wide_thin_banner(): void
    {
        Storage::fake('public');
        $this->fakeImage('image/png', self::imageBytes('png', 728, 90));

        self::assertNull($this->store('https://cdn.example.test/banner.png'));
    }

    /** Настоящие байты картинки: проверка размеров работает только на них. */
    private static function imageBytes(string $format, int $width = 800, int $height = 600): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 40, 90, 160));

        ob_start();

        match ($format) {
            'jpeg' => imagejpeg($image),
            'png' => imagepng($image),
            'webp' => imagewebp($image),
            'gif' => imagegif($image),
        };

        return (string) ob_get_clean();
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
