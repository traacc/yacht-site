<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\WorldNews\DiscoverSailingNewsAction;
use App\Services\WorldNews\WorldNewsSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DiscoverSailingNews implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 900;

    public function __construct(public bool $force = false) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function uniqueId(): string
    {
        return 'sailing-news-discovery';
    }

    public function handle(
        WorldNewsSettings $settings,
        DiscoverSailingNewsAction $discover,
    ): void {
        if (! $this->force) {
            if (! $settings->enabled()) {
                return;
            }

            // Повтор очереди должен снова выполнить запрос после временной ошибки.
            // markRunStarted() уже обновил last_run_at, поэтому обычная проверка
            // интервала на второй попытке преждевременно завершила бы job.
            if ($this->attempts() === 1 && ! $settings->shouldRun()) {
                return;
            }
        }

        if (! $settings->isProviderConfigured()) {
            throw new RuntimeException('AI-провайдер новостей не настроен.');
        }

        $settings->markRunStarted();

        try {
            $result = $discover->handle();
            $settings->markRunFinished($result->summary());
        } catch (Throwable $exception) {
            $settings->markRunFailed($exception->getMessage());

            Log::error('Sailing news discovery failed', [
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
