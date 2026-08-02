<?php

declare(strict_types=1);

namespace App\Actions\Advert;

use App\Filament\Resources\Adverts\AdvertResource;
use App\Mail\AdvertSubmitted;
use App\Models\Advert;
use App\Services\Notifications\AdminRecipients;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Объявление подано и ждёт модерации.
 *
 * ТЗ 3-го этапа: «Объявления вначале проходят модерацию – уведомлять в
 * дашборде и на почту» — отсюда ровно два канала. Сбой отправки письма не
 * должен ронять подачу: объявление уже сохранено и видно в админке.
 */
class SubmitAdvertAction
{
    public function __construct(
        private readonly AdminRecipients $recipients,
        private readonly SettingsService $settings,
    ) {}

    public function handle(Advert $advert): Advert
    {
        $advert->loadMissing(['author', 'category', 'yacht', 'regattas']);

        $this->mailModerators($advert);
        $this->notifyPanel($advert);

        return $advert;
    }

    /** Письмо на общий адрес администрации («почта info» в терминах ТЗ). */
    private function mailModerators(Advert $advert): void
    {
        $emails = $this->settings->adminNotificationEmails();

        if ($emails === []) {
            return;
        }

        try {
            Mail::to($emails)->send(new AdvertSubmitted($advert, $this->moderationUrl()));
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Колокольчик в админ-панели.
     *
     * Намеренно не через UserNotification: тот фильтрует каналы личными
     * настройками категорий пользователя, а служебное уведомление модератору
     * не должно глушиться галочкой в личном кабинете.
     */
    private function notifyPanel(Advert $advert): void
    {
        $moderators = $this->recipients->forSection(AdvertResource::class);

        if ($moderators->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Новое объявление на модерацию')
            ->body($advert->type->label().': '.Str::limit($advert->title, 120))
            ->icon('heroicon-o-megaphone')
            ->actions([
                Action::make('open')
                    ->label('Открыть')
                    ->url($this->moderationUrl())
                    ->markAsRead(),
            ])
            ->sendToDatabase($moderators);
    }

    /** Панель указываем явно: объявление подаётся из ЛК, а модерация живёт в админке. */
    private function moderationUrl(): string
    {
        return AdvertResource::getUrl(panel: 'admin');
    }
}
