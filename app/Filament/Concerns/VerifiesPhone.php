<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Actions\Auth\RequestPhoneVerificationCallAction;
use App\Actions\Auth\VerifyPhoneCodeAction;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Подтверждение телефона звонком: статус + кнопки «Позвонить / Ввести код».
 *
 * Звонок поступает на сохранённый в профиле номер, поэтому изменённый, но не
 * сохранённый номер сюда не попадает — об этом сказано в подсказке поля.
 *
 * @see RequestPhoneVerificationCallAction
 * @see VerifyPhoneCodeAction
 */
trait VerifiesPhone
{
    /**
     * Статус и кнопки одной строкой — для belowContent() поля «Телефон».
     *
     * @param  string  $returnUrl  куда вернуть пользователя после подтверждения
     * @return array<Text|Action>
     */
    protected function phoneVerificationContent(User $user, string $returnUrl): array
    {
        // helperText() пишет в тот же слот belowContent, поэтому подсказка едет здесь же,
        // иначе поле осталось бы вообще без пояснения.
        return [
            $this->phoneVerificationBadge($user),
            ...$this->phoneVerificationActions($user, $returnUrl),
            Text::make($this->phoneVerificationHint($user)),
        ];
    }

    /** Подсказка под полем: правила подтверждения зависят от текущего статуса. */
    protected function phoneVerificationHint(User $user): string
    {
        if ($user->hasVerifiedPhone()) {
            return 'При смене номера подтверждение сбрасывается — новый номер нужно подтвердить звонком.';
        }

        return 'Мы позвоним на сохранённый в профиле номер, отвечать не нужно: код — последние '
            .PhoneVerificationCode::CODE_LENGTH.' цифры номера, с которого поступит звонок. Новый номер сначала сохраните.';
    }

    /** Короткий статус для строки под полем; сам номер виден в поле. */
    protected function phoneVerificationBadge(User $user): Text
    {
        $verified = $user->hasVerifiedPhone();
        $pending = $this->pendingPhoneVerification($user);

        return Text::make(match (true) {
            $verified => 'Подтверждён '.$user->phone_verified_at->format('d.m.Y H:i'),
            $user->normalizedPhone() === null => 'Номер не указан',
            $pending !== null => 'Звонок заказан, код действует до '.$pending->expires_at->format('H:i'),
            default => 'Не подтверждён',
        })
            ->badge()
            ->color($verified ? 'success' : 'warning')
            ->icon($verified ? Heroicon::OutlinedCheckBadge : Heroicon::OutlinedExclamationTriangle);
    }

    /**
     * @param  string  $returnUrl  куда вернуть пользователя после подтверждения
     * @return array<Action>
     */
    protected function phoneVerificationActions(User $user, string $returnUrl): array
    {
        $canVerify = ! $user->hasVerifiedPhone() && $user->normalizedPhone() !== null;
        $pending = $this->pendingPhoneVerification($user);

        return [
            Action::make('requestPhoneCall')
                ->label($pending !== null ? 'Позвонить ещё раз' : 'Позвонить для подтверждения')
                ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                ->visible($canVerify)
                ->action(function (Action $action) use ($user, $returnUrl) {
                    try {
                        app(RequestPhoneVerificationCallAction::class)->handle($user);
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Не удалось заказать звонок')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        $action->halt();

                        return null;
                    }

                    Notification::make()
                        ->title('Звонок заказан')
                        ->body('Дождитесь звонка — отвечать не нужно. Введите последние '
                            .PhoneVerificationCode::CODE_LENGTH.' цифры номера, с которого позвонили. Код действует '
                            .PhoneVerificationCode::TTL_MINUTES.' мин.')
                        ->success()
                        ->send();

                    return redirect($returnUrl);
                }),

            Action::make('confirmPhoneCode')
                ->label('Ввести код')
                ->color('gray')
                ->visible($canVerify)
                ->modalHeading('Подтверждение телефона')
                ->modalSubmitActionLabel('Подтвердить')
                ->schema([
                    TextInput::make('code')
                        ->label('Последние '.PhoneVerificationCode::CODE_LENGTH.' цифры номера, с которого позвонили')
                        ->required()
                        ->numeric()
                        ->minLength(PhoneVerificationCode::CODE_LENGTH)
                        ->maxLength(PhoneVerificationCode::CODE_LENGTH)
                        ->autocomplete('one-time-code')
                        ->placeholder(str_repeat('0', PhoneVerificationCode::CODE_LENGTH)),
                ])
                ->action(function (array $data, Action $action) use ($user, $returnUrl) {
                    try {
                        app(VerifyPhoneCodeAction::class)->handle($user, (string) $data['code']);
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Код не принят')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        $action->halt();

                        return null;
                    }

                    Notification::make()
                        ->title('Телефон подтверждён')
                        ->success()
                        ->send();

                    return redirect($returnUrl);
                }),
        ];
    }

    /** Действующий заказанный звонок, если он есть. */
    private function pendingPhoneVerification(User $user): ?PhoneVerificationCode
    {
        if ($user->hasVerifiedPhone()) {
            return null;
        }

        $pending = $user->phoneVerificationCodes()->first();

        return $pending?->isUsable() === true ? $pending : null;
    }
}
