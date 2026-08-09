<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DiscoverSailingNews as DiscoverSailingNewsJob;
use App\Services\WorldNews\WorldNewsSettings;
use Illuminate\Console\Command;
use Throwable;

class DiscoverSailingNews extends Command
{
    protected $signature = 'news:discover-sailing
        {--force : Запустить независимо от переключателя и интервала}
        {--sync : Выполнить в текущем процессе вместо очереди}';

    protected $description = 'Ищет и обрабатывает новости парусного спорта через AI-движок.';

    public function handle(WorldNewsSettings $settings): int
    {
        $force = (bool) $this->option('force');

        if (! $force && ! $settings->enabled()) {
            $this->line('AI-поиск новостей выключен.');

            return self::SUCCESS;
        }

        if (! $force && ! $settings->shouldRun()) {
            $this->line('Интервал с прошлого запуска ещё не истёк.');

            return self::SUCCESS;
        }

        if (! $settings->isProviderConfigured()) {
            $this->error('AI-провайдер не настроен: проверьте OPENAI_API_KEY и OPENAI_NEWS_MODEL.');

            return self::FAILURE;
        }

        try {
            if ((bool) $this->option('sync')) {
                DiscoverSailingNewsJob::dispatchSync($force);
                $this->info($settings->lastRunSummary());
            } else {
                DiscoverSailingNewsJob::dispatch($force);
                $this->info('Поиск новостей поставлен в очередь.');
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
