<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Actions\Auth\SendEmailVerificationLinkAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Подтверждение e-mail: статус + кнопки «Отправить письмо / Проверить».
 *
 * Общий для «Профиля» (рядом с полем e-mail) и «Настроек уведомлений»,
 * чтобы кнопки и тексты не разъезжались между страницами.
 *
 * @see SendEmailVerificationLinkAction
 */
trait VerifiesEmail
{
    /**
     * Статус и кнопки одной строкой — для belowContent() поля «Email».
     *
     * @param  string  $returnUrl  куда вернуть пользователя после проверки
     * @return array<Text|Action>
     */
    protected function emailVerificationContent(User $user, string $returnUrl): array
    {
        // helperText() пишет в тот же слот belowContent, поэтому подсказка едет здесь же,
        // иначе поле осталось бы вообще без пояснения.
        return [
            $this->emailVerificationBadge($user),
            ...$this->emailVerificationActions($user, $returnUrl),
            Text::make($this->emailVerificationHint($user)),
        ];
    }

    /** Подсказка под полем: что произойдёт при смене адреса. */
    protected function emailVerificationHint(User $user): string
    {
        if ($user->hasTechnicalEmail()) {
            return 'Укажите настоящий адрес — на него придёт письмо со ссылкой для подтверждения.';
        }

        return 'При смене адреса подтверждение сбрасывается — на новый адрес придёт письмо со ссылкой.';
    }

    /** Короткий статус для строки под полем; полный адрес виден в самом поле. */
    protected function emailVerificationBadge(User $user): Text
    {
        return Text::make(match (true) {
            $user->hasTechnicalEmail() => 'Адрес не указан',
            $user->hasVerifiedEmail() => 'Подтверждён',
            default => 'Не подтверждён',
        })
            ->badge()
            ->color($user->hasVerifiedEmail() ? 'success' : 'warning')
            ->icon($user->hasVerifiedEmail() ? Heroicon::OutlinedCheckBadge : Heroicon::OutlinedExclamationTriangle);
    }

    /**
     * @param  string  $returnUrl  куда вернуть пользователя после проверки
     * @return array<Action>
     */
    protected function emailVerificationActions(User $user, string $returnUrl): array
    {
        // Технический адрес подтверждать бессмысленно: письмо просто некуда слать.
        $canVerify = ! $user->hasTechnicalEmail() && ! $user->hasVerifiedEmail();

        return [
            Action::make('resendVerification')
                ->label('Отправить письмо повторно')
                ->icon(Heroicon::OutlinedEnvelope)
                ->visible($canVerify)
                ->action(function (Action $action) use ($user) {
                    try {
                        // Экшен уже троттлит отправки (3 за 10 минут).
                        app(SendEmailVerificationLinkAction::class)->handle($user);
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Не удалось отправить письмо')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        $action->halt();

                        return null;
                    }

                    Notification::make()
                        ->title('Письмо отправлено')
                        ->body('Перейдите по ссылке из письма, затем вернитесь и обновите страницу.')
                        ->success()
                        ->send();

                    return null;
                }),

            Action::make('refreshEmail')
                ->label('Проверить подтверждение')
                ->color('gray')
                ->visible($canVerify)
                ->action(fn () => redirect($returnUrl)),
        ];
    }
}
