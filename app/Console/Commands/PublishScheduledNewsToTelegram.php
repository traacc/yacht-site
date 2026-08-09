<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PublishNewsToTelegram;
use App\Models\News;
use App\Services\SettingsService;
use Illuminate\Console\Command;

class PublishScheduledNewsToTelegram extends Command
{
    protected $signature = 'news:publish-to-telegram';

    protected $description = 'Публикует в Telegram новости, у которых наступила дата публикации и которые ещё не были отправлены.';

    public function handle(SettingsService $settings): int
    {
        if (! $settings->get('home.telegram_autopublish', true)) {
            return self::SUCCESS;
        }

        $news = News::query()
            ->manual()
            ->published()
            ->where('published_to_tg', false)
            ->get();

        foreach ($news as $item) {
            PublishNewsToTelegram::dispatch($item->id);
        }

        if ($news->isNotEmpty()) {
            $this->info("Поставлено в очередь новостей: {$news->count()}");
        }

        return self::SUCCESS;
    }
}
