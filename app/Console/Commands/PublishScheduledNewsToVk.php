<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PublishNewsToVk;
use App\Models\News;
use App\Services\SettingsService;
use Illuminate\Console\Command;

class PublishScheduledNewsToVk extends Command
{
    protected $signature = 'news:publish-to-vk';

    protected $description = 'Публикует во ВКонтакте новости, у которых наступила дата публикации и которые ещё не были отправлены.';

    public function handle(SettingsService $settings): int
    {
        if (! $settings->get('home.vk_autopublish', true)) {
            return self::SUCCESS;
        }

        $news = News::query()
            ->manual()
            ->published()
            ->where('published_to_vk', false)
            ->get();

        foreach ($news as $item) {
            PublishNewsToVk::dispatch($item->id);
        }

        if ($news->isNotEmpty()) {
            $this->info("Поставлено в очередь новостей: {$news->count()}");
        }

        return self::SUCCESS;
    }
}
