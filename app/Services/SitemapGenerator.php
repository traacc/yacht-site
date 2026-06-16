<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\News;
use App\Models\Regatta;
use Illuminate\Support\Carbon;

class SitemapGenerator
{
    /**
     * Имена статических маршрутов публичного сайта для включения в sitemap.
     *
     * @var list<string>
     */
    private const STATIC_ROUTES = [
        'home',
        'charter',
        'management',
        'trustees',
        'policy',
        'rules',
        'regulations',
        'decisions',
        'votings',
        'competitions',
        'teams',
        'yachts',
        'ratings',
        'gallery',
        'help',
        'news',
    ];

    /**
     * Генерирует sitemap.xml и сохраняет его в public/sitemap.xml.
     *
     * @return int Количество URL в карте сайта.
     */
    public function generate(): int
    {
        $urls = [];

        // Статические страницы
        foreach (self::STATIC_ROUTES as $name) {
            $urls[] = ['loc' => route($name)];
        }

        // Опубликованные новости
        News::published()
            ->orderByDesc('published_at')
            ->get()
            ->each(function (News $news) use (&$urls): void {
                $urls[] = [
                    'loc'     => route('news-details', $news),
                    'lastmod' => $news->updated_at?->toAtomString(),
                ];
            });

        // Регаты
        Regatta::query()
            ->orderByDesc('date_start')
            ->get()
            ->each(function (Regatta $regatta) use (&$urls): void {
                $urls[] = [
                    'loc'     => route('competition-details', $regatta),
                    'lastmod' => $regatta->updated_at?->toAtomString(),
                ];
            });

        file_put_contents(public_path('sitemap.xml'), $this->render($urls));

        return count($urls);
    }

    /**
     * Собирает XML карты сайта.
     *
     * @param list<array{loc: string, lastmod?: string|null}> $urls
     */
    private function render(array $urls): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        $now = Carbon::now()->toAtomString();

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . '</loc>';
            $lines[] = '    <lastmod>' . ($url['lastmod'] ?? $now) . '</lastmod>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines) . "\n";
    }
}
