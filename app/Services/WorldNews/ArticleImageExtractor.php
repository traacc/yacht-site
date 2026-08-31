<?php

declare(strict_types=1);

namespace App\Services\WorldNews;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Достаёт URL превью-картинки со страницы новости.
 *
 * AI-модели выдумывают ссылки на изображения, поэтому картинку берём не из
 * ответа модели, а из разметки самой статьи: og:image и его аналоги.
 */
final class ArticleImageExtractor
{
    /** Разметка head находится в начале документа — качать страницу целиком незачем. */
    private const MAX_HTML_BYTES = 512000;

    /** Порядок важен: сначала самые надёжные теги. */
    private const META_PROPERTIES = [
        'og:image:secure_url',
        'og:image:url',
        'og:image',
        'twitter:image',
        'twitter:image:src',
        'vk:image',
    ];

    public function __construct(private readonly UrlCanonicalizer $urls) {}

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
        try {
            $response = Http::withHeaders([
                // Без внятного User-Agent часть новостных сайтов отдаёт заглушку.
                'User-Agent' => 'Mozilla/5.0 (compatible; YachtAssociationBot/1.0)',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
                ->connectTimeout(5)
                ->timeout((int) config('services.openai.news_image_timeout', 15))
                ->get($pageUrl);
        } catch (Throwable) {
            return null;
        }

        if ($response->failed()) {
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

        foreach ($xpath->query('//link[@rel="image_src"]') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $found[] = trim($node->getAttribute('href'));
            }
        }

        return array_values(array_filter($found, static fn (string $value): bool => $value !== ''));
    }

    /**
     * og:image часто указывают относительным путём или протокол-относительной
     * ссылкой (`//cdn.example/pic.jpg`) — приводим к абсолютному виду.
     */
    private function absolutize(string $pageUrl, string $candidate): ?string
    {
        $candidate = trim(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($candidate === '' || str_starts_with($candidate, 'data:')) {
            return null;
        }

        $base = parse_url($pageUrl);

        if (! is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }

        $authority = $base['host'].(isset($base['port']) ? ':'.$base['port'] : '');

        $absolute = match (true) {
            str_starts_with($candidate, '//') => $base['scheme'].':'.$candidate,
            str_starts_with($candidate, '/') => "{$base['scheme']}://{$authority}{$candidate}",
            (bool) preg_match('~^https?://~i', $candidate) => $candidate,
            default => "{$base['scheme']}://{$authority}/".ltrim($candidate, '/'),
        };

        return $this->urls->canonicalize($absolute) !== null ? $absolute : null;
    }
}
