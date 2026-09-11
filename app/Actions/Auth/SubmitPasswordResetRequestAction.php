<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Filament\Resources\PasswordResetRequests\PasswordResetRequestResource;
use App\Mail\PasswordResetRequested;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Services\Notifications\AdminRecipients;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

/**
 * Посетитель оставил запрос на восстановление пароля в форме входа.
 *
 * Пароль сбрасывает не сам пользователь: обращение фиксируется в панели
 * (раздел «Обращения»), администрация связывается по указанным контактам,
 * отвечает письмом или отправляет ссылку на смену пароля прямо из панели.
 * Сбой отправки письма не должен ронять запрос — обращение уже сохранено.
 */
class SubmitPasswordResetRequestAction
{
    public function __construct(
        private readonly AdminRecipients $recipients,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  User|null  $user  Пользователь, которого заявитель сам выбрал в списке;
     *                           без выбора ищем профиль по контактам.
     */
    public function handle(string $email, string $phone, ?User $user = null): PasswordResetRequest
    {
        $user ??= PasswordResetRequest::matchUser($email, $phone);

        $request = PasswordResetRequest::create([
            'user_id' => $user?->getKey(),
            'email' => $email,
            'phone' => $phone,
        ]);

        $this->mailAdmins($request);
        $this->notifyPanel($request);

        return $request;
    }

    /** Письмо на общий адрес администрации. */
    private function mailAdmins(PasswordResetRequest $request): void
    {
        $emails = $this->settings->adminNotificationEmails();

        if ($emails === []) {
            return;
        }

        try {
            Mail::to($emails)->send(new PasswordResetRequested($request, $this->panelUrl()));
        } catch (\Exception $e) {
            report($e);
        }
    }

    /** Колокольчик в админ-панели — тем, кому открыт раздел. */
    private function notifyPanel(PasswordResetRequest $request): void
    {
        $admins = $this->recipients->forSection(PasswordResetRequestResource::class);

        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Запрос на восстановление пароля')
            ->body(($request->requesterName() ?? 'Не найден в базе').' — '.$request->email.', '.$request->phone
                .(($accountEmail = $request->differingAccountEmail()) !== null ? '; e-mail аккаунта: '.$accountEmail : ''))
            ->icon('heroicon-o-key')
            ->actions([
                Action::make('open')
                    ->label('Открыть')
                    ->url($this->panelUrl())
                    ->markAsRead(),
            ])
            ->sendToDatabase($admins);
    }

    /**
     * Панель указываем явно: обращение создаётся в контексте публичного сайта,
     * где текущей панели нет.
     */
    private function panelUrl(): string
    {
        return PasswordResetRequestResource::getUrl(panel: 'admin');
    }
}
