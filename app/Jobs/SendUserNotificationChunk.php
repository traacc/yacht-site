<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * Рассылает уведомление порции пользователей. Общий шаг для всех массовых
 * рассылок — сами продюсеры только режут получателей на порции.
 */
class SendUserNotificationChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  list<string>  $userIds
     */
    public function __construct(
        public array $userIds,
        public UserNotification $notification,
    ) {}

    public function handle(): void
    {
        User::query()
            ->whereIn('id', $this->userIds)
            // Обязательно: via() дёргает настройки и привязку на каждом получателе.
            ->with(['notificationPreferences', 'telegramAccount'])
            // chunkById, а не chunk: без явной сортировки постраничная выборка
            // может продублировать или пропустить получателей.
            ->chunkById(100, function (Collection $users): void {
                Notification::send($users, $this->notification);
            });
    }
}
