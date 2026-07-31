<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Advert;
use App\Models\News;
use App\Models\Regatta;
use App\Models\RepairCase;
use App\Models\Tour;
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
        'carter30.history',
        'carter30.regulations',
        'carter30.repair',
        'carter30.technical-help',
        'carter30.marketplace',
        'carter30.yacht-sale',
        'services.index',
        'services.fleet-rental',
        'services.events',
        'services.training',
        'services.tours',
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
                    'loc' => route('news-details', $news),
                    'lastmod' => $news->updated_at?->toAtomString(),
                ];
            });

        // Кейсы ремонта раздела «Carter 30»
        RepairCase::published()
            ->ordered()
            ->get()
            ->each(function (RepairCase $case) use (&$urls): void {
                $urls[] = [
                    'loc' => route('carter30.repair-case', $case),
                    'lastmod' => $case->updated_at?->toAtomString(),
                ];
            });

        // Походы раздела «Услуги». Прошедшие тоже включаем: их страницы живут
        // на витрине как подтверждение опыта.
        Tour::published()
            ->recentFirst()
            ->get()
            ->each(function (Tour $tour) use (&$urls): void {
                $urls[] = [
                    'loc' => $tour->publicUrl(),
                    'lastmod' => $tour->updated_at?->toAtomString(),
                ];
            });

        // Объявления досок раздела «Carter 30»
        Advert::query()
            ->visible()
            ->orderByDesc('published_at')
            ->get()
            ->each(function (Advert $advert) use (&$urls): void {
                $urls[] = [
                    'loc' => $advert->publicUrl(),
                    'lastmod' => $advert->updated_at?->toAtomString(),
                ];
            });

        // Регаты
        Regatta::query()
            ->orderByDesc('date_start')
            ->get()
            ->each(function (Regatta $regatta) use (&$urls): void {
                $urls[] = [
                    'loc' => route('competition-details', $regatta),
                    'lastmod' => $regatta->updated_at?->toAtomString(),
                ];
            });

        file_put_contents(public_path('sitemap.xml'), $this->render($urls));

        return count($urls);
    }

    /**
     * Собирает XML карты сайта.
     *
     * @param  list<array{loc: string, lastmod?: string|null}>  $urls
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
            $lines[] = '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>';
            $lines[] = '    <lastmod>'.($url['lastmod'] ?? $now).'</lastmod>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }
}
