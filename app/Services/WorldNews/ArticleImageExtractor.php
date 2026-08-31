<?php

declare(strict_types=1);

namespace App\Services\WorldNews;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

/**
 * Достаёт URL превью-картинки со страницы новости.
 *
 * AI-модели выдумывают ссылки на изображения, поэтому картинку берём не из
 * ответа модели, а из разметки самой статьи: og:image и его аналоги.
 */
final class ArticleImageExtractor
{
    /**
     * Раньше хватало 512 КБ на один <head>, но последняя ступень цепочки
     * ищет картинку в теле статьи, поэтому запас увеличен.
     */
    private const MAX_HTML_BYTES = 1048576;

    /** Меньше этого по любой стороне — заведомо иконка, а не иллюстрация. */
    private const MIN_IMAGE_SIDE = 200;

    /** Порядок важен: сначала самые надёжные теги. */
    private const META_PROPERTIES = [
        'og:image:secure_url',
        'og:image:url',
        'og:image',
        'twitter:image',
        'twitter:image:src',
        'vk:image',
    ];

    public function __construct(
        private readonly UrlCanonicalizer $urls,
        private readonly PublicUrlFetcher $fetcher,
    ) {}

    public function extract(string $pageUrl): ?string
    {
        if ($this->urls->canonicalize($pageUrl) === null) {
            return null;
        }

        $html = $this->fetch($pageUrl);

        if ($html === null) {
            return null;
        }

        foreach ($this->candidates($html) as $candidate) {
            $absolute = $this->absolutize($pageUrl, $candidate);

            if ($absolute !== null) {
                return $absolute;
            }
        }

        return null;
    }

    private function fetch(string $pageUrl): ?string
    {
        $response = $this->fetcher->get(
            $pageUrl,
            headers: [
                // Без внятного User-Agent часть новостных сайтов отдаёт заглушку.
                'User-Agent' => 'Mozilla/5.0 (compatible; YachtAssociationBot/1.0)',
                'Accept' => 'text/html,application/xhtml+xml',
            ],
            timeout: (int) config('services.openai.news_image_timeout', 15),
        );

        if ($response === null || $response->failed()) {
            return null;
        }

        if (! str_contains(strtolower($response->header('Content-Type')), 'html')) {
            return null;
        }

        return substr($response->body(), 0, self::MAX_HTML_BYTES);
    }

    /** @return list<string> */
    private function candidates(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            // Явный meta charset нужен, чтобы кириллица в alt/title не ломала парсер.
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (Throwable) {
            return [];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);

        // Порядок = убывание надёжности: соцсетевые теги ведут редакторы,
        // разметку в теле страницы — движок сайта, а <img> уже догадка.
        $found = [
            ...$this->fromMetaTags($xpath),
            ...$this->fromImageSrcLink($xpath),
            ...$this->fromJsonLd($xpath),
            ...$this->fromMicrodata($xpath),
            ...$this->fromArticleBody($xpath),
        ];

        return array_values(array_filter($found, static fn (string $value): bool => $value !== ''));
    }

    /** @return list<string> */
    private function fromMetaTags(DOMXPath $xpath): array
    {
        $found = [];

        foreach (self::META_PROPERTIES as $property) {
            $query = sprintf(
                '//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")=%1$s]'
                .'|//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")=%1$s]',
                sprintf('"%s"', $property),
            );

            foreach ($xpath->query($query) ?: [] as $node) {
                if ($node instanceof DOMElement) {
                    $found[] = trim($node->getAttribute('content'));
                }
            }
        }

        return $found;
    }

    /** @return list<string> */
    private function fromImageSrcLink(DOMXPath $xpath): array
    {
        $found = [];

        foreach ($xpath->query('//link[@rel="image_src"]') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $found[] = trim($node->getAttribute('href'));
            }
        }

        return $found;
    }

    /**
     * Schema.org в JSON-LD: у Article/NewsArticle картинка лежит в `image`,
     * произвольно вложенном — строкой, массивом или объектом ImageObject.
     *
     * @return list<string>
     */
    private function fromJsonLd(DOMXPath $xpath): array
    {
        $found = [];

        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $node) {
            $decoded = json_decode((string) $node->textContent, true);

            if (is_array($decoded)) {
                $this->collectJsonLdImages($decoded, $found);
            }
        }

        return $found;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $found
     */
    private function collectJsonLdImages(array $data, array &$found): void
    {
        foreach ($data as $key => $value) {
            // `logo` намеренно не трогаем: это эмблема издания, а не иллюстрация.
            if ($key === 'image' || $key === 'thumbnailUrl') {
                foreach ($this->flattenJsonLdImage($value) as $url) {
                    $found[] = $url;
                }

                continue;
            }

            if (is_array($value)) {
                $this->collectJsonLdImages($value, $found);
            }
        }
    }

    /** @return list<string> */
    private function flattenJsonLdImage(mixed $value): array
    {
        if (is_string($value)) {
            return [trim($value)];
        }

        if (! is_array($value)) {
            return [];
        }

        if (isset($value['url']) && is_string($value['url'])) {
            return [trim($value['url'])];
        }

        $urls = [];

        foreach ($value as $item) {
            foreach ($this->flattenJsonLdImage($item) as $url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /** @return list<string> */
    private function fromMicrodata(DOMXPath $xpath): array
    {
        $found = [];

        foreach ($xpath->query('//*[@itemprop="image"]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            foreach (['content', 'src', 'href'] as $attribute) {
                $value = trim($node->getAttribute($attribute));

                if ($value !== '') {
                    $found[] = $value;

                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Последняя ступень: первая содержательная картинка в теле статьи.
     * Служебную мелочь (логотипы, иконки, счётчики) отсеиваем по имени файла
     * и по указанным в разметке размерам.
     *
     * @return list<string>
     */
    private function fromArticleBody(DOMXPath $xpath): array
    {
        $found = [];
        $query = '//article//img|//*[@itemprop="articleBody"]//img|//main//img';

        foreach ($xpath->query($query) ?: [] as $node) {
            if (! $node instanceof DOMElement || $this->looksLikeChrome($node)) {
                continue;
            }

            $source = $this->widestSrcSetEntry($node->getAttribute('srcset'))
                ?? trim($node->getAttribute('src'));

            if ($source !== '') {
                $found[] = $source;
            }
        }

        return $found;
    }

    private function looksLikeChrome(DOMElement $image): bool
    {
        foreach (['width', 'height'] as $attribute) {
            $value = (int) $image->getAttribute($attribute);

            if ($value > 0 && $value < self::MIN_IMAGE_SIDE) {
                return true;
            }
        }

        $haystack = strtolower($image->getAttribute('src').' '.$image->getAttribute('class'));

        return (bool) preg_match('~logo|icon|sprite|avatar|placeholder|spacer|pixel|counter|banner~', $haystack);
    }

    /** Из srcset берём самый крупный вариант: `url 320w, url 1200w`. */
    private function widestSrcSetEntry(string $srcset): ?string
    {
        $best = null;
        $bestWidth = 0;

        foreach (explode(',', $srcset) as $entry) {
            $parts = preg_split('~\s+~', trim($entry)) ?: [];
            $url = trim((string) ($parts[0] ?? ''));

            if ($url === '') {
                continue;
            }

            $width = (int) rtrim((string) ($parts[1] ?? '0'), 'w');

            if ($best === null || $width > $bestWidth) {
                $best = $url;
                $bestWidth = $width;
            }
        }

        return $best;
    }

    /**
     * og:image часто указывают относительным путём или протокол-относительной
     * ссылкой (`//cdn.example/pic.jpg`) — приводим к абсолютному виду и
     * проверяем, что картинка не уводит во внутреннюю сеть.
     */
    private function absolutize(string $pageUrl, string $candidate): ?string
    {
        $absolute = $this->urls->resolve(
            $pageUrl,
            html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );

        if ($absolute === null || ! $this->fetcher->isPublic($absolute)) {
            return null;
        }

        return $absolute;
    }
}
