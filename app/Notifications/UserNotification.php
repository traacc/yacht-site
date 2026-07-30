<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\User;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Telegram\TelegramMessage;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * База для всех уведомлений центра уведомлений.
 *
 * Наследник описывает категорию и содержимое, а набор каналов вычисляется из
 * настроек пользователя — см. via(). Каждый канал уходит отдельной job'ой,
 * поэтому сбой Telegram не мешает доставке письма.
 *
 * Важно: в конструктор наследника передавайте только скаляры и идентификаторы,
 * а не Eloquent-модели — уведомление сериализуется в очередь.
 */
abstract class UserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    abstract public function category(): NotificationCategory;

    abstract public function title(): string;

    abstract public function body(): string;

    /** Ссылка «Открыть» — общая для письма, Telegram и колокольчика. */
    public function url(): ?string
    {
        return null;
    }

    /**
     * Абсолютный URL картинки-обложки для письма (например, обложка новости).
     *
     * Именно абсолютный: относительные пути в почтовых клиентах не работают.
     * Наследник обязан отдавать null, если файла нет, — иначе получатель
     * увидит «битую» картинку.
     */
    public function imageUrl(): ?string
    {
        return null;
    }

    public function icon(): string
    {
        return 'heroicon-o-bell-alert';
    }

    /**
     * Каналы доставки берутся из настроек пользователя.
     *
     * Вызывается синхронно в момент отправки, поэтому при массовой рассылке
     * связи notificationPreferences и telegramAccount обязаны быть загружены
     * заранее — иначе получим N+1.
     *
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return array_map(
            static fn (NotificationChannel $channel): string => $channel->driver(),
            app(NotificationPreferences::class)->channelsFor($notifiable, $this->category()),
        );
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->markdown('mail.notification', [
                'user' => $notifiable,
                'title' => $this->title(),
                'body' => $this->body(),
                'url' => $this->url(),
                'imageUrl' => $this->imageUrl(),
                'category' => $this->category(),
                'unsubscribeUrl' => $this->unsubscribeUrl($notifiable),
            ]);
    }

    public function toTelegram(User $notifiable): TelegramMessage
    {
        $text = '<b>'.e($this->title()).'</b>'."\n\n".e($this->body());

        return new TelegramMessage(
            text: $text,
            buttonUrl: $this->url(),
            buttonText: 'Открыть на сайте',
        );
    }

    /**
     * Формат Filament обязателен: колокольчик читает записи через
     * Filament\Notifications\Notification::fromDatabase().
     *
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title())
            ->body($this->body())
            ->icon($this->icon())
            ->iconColor('primary');

        if ($this->url() !== null) {
            $notification->actions([
                FilamentAction::make('open')
                    ->label('Открыть')
                    ->url($this->url())
                    ->markAsRead(),
            ]);
        }

        return $notification->getDatabaseMessage();
    }

    /** Ссылка отписки от этой категории по e-mail (подписанная, живёт 30 дней). */
    protected function unsubscribeUrl(User $notifiable): string
    {
        return URL::temporarySignedRoute('notifications.unsubscribe', now()->addDays(30), [
            'user' => $notifiable->getKey(),
            'category' => $this->category()->value,
            'channel' => NotificationChannel::Email->value,
        ]);
    }
}
