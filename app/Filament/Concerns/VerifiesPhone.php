<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Actions\Auth\RequestPhoneVerificationCallAction;
use App\Actions\Auth\VerifyPhoneCodeAction;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Блок подтверждения телефона звонком: статус + кнопки «Позвонить / Ввести код».
 *
 * Звонок поступает на сохранённый в профиле номер, поэтому изменённый, но не
 * сохранённый номер сюда не попадает — об этом сказано в описании секции.
 *
 * @see RequestPhoneVerificationCallAction
 * @see VerifyPhoneCodeAction
 */
trait VerifiesPhone
{
    /**
     * @param  string  $returnUrl  куда вернуть пользователя после подтверждения
     */
    protected function phoneVerificationSection(User $user, string $returnUrl): Section
    {
        $verified = $user->hasVerifiedPhone();
        $hasPhone = $user->normalizedPhone() !== null;

        $pending = $verified ? null : $user->phoneVerificationCodes()->first();
        $pending = $pending?->isUsable() === true ? $pending : null;

        return Section::make('Телефон')
            ->description($verified
                ? 'Номер подтверждён звонком.'
                : 'Подтвердите номер звонком: мы позвоним на сохранённый в профиле номер, отвечать не нужно. Код — последние '
                    .PhoneVerificationCode::CODE_LENGTH.' цифры номера, с которого поступит звонок. Новый номер сначала сохраните.')
            ->schema([
                Placeholder::make('phone_status')
                    ->label('Статус')
                    ->content(match (true) {
                        $verified => 'Подтверждён '.$user->phone_verified_at->format('d.m.Y H:i').' — '.(PhoneNumber::format($user->phone) ?? $user->phone),
                        ! $hasPhone => 'Номер не указан',
                        $pending !== null => 'Не подтверждён. Звонок заказан, код действует до '.$pending->expires_at->format('H:i'),
                        default => 'Не подтверждён',
                    }),

                Actions::make([
                    Action::make('requestPhoneCall')
                        ->label($pending !== null ? 'Позвонить ещё раз' : 'Позвонить для подтверждения')
                        ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                        ->visible(! $verified && $hasPhone)
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
                        ->visible(! $verified && $hasPhone)
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
                ])->key('phone-verification-actions'),
            ]);
    }
}
