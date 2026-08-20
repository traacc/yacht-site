<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Actions\Notifications\GenerateTelegramLinkTokenAction;
use App\Actions\Notifications\UnlinkTelegramAccountAction;
use App\Models\User;
use App\Services\Telegram\TelegramUpdateHandler;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * Блок привязки Telegram-бота: статус + кнопки «Привязать / Отвязать / Проверить».
 *
 * Общий для «Профиля» и «Настроек уведомлений», чтобы кнопки и тексты
 * не разъезжались между страницами.
 *
 * @see TelegramUpdateHandler
 */
trait LinksTelegramAccount
{
    /**
     * @param  string  $returnUrl  куда вернуть пользователя после привязки/отвязки
     */
    protected function telegramLinkSection(User $user, string $returnUrl): Section
    {
        $account = $user->telegramAccount;

        return Section::make('Telegram')
            ->description('Привяжите Telegram, чтобы получать отмеченные уведомления в мессенджере.')
            ->schema([
                Placeholder::make('telegram_status')
                    ->label('Статус')
                    ->content(match (true) {
                        $account === null => 'Не привязан',
                        $account->isBlocked() => 'Бот заблокирован в Telegram — отправьте боту команду /start, чтобы возобновить получение уведомлений',
                        default => 'Привязан: '.$account->displayName(),
                    }),

                Actions::make([
                    Action::make('linkTelegram')
                        ->label($account === null ? 'Привязать Telegram' : 'Перепривязать')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->action(function () use ($user) {
                            try {
                                // Токен создаём по клику, а не при каждом рендере страницы.
                                $link = app(GenerateTelegramLinkTokenAction::class)->handle($user);
                            } catch (Throwable $e) {
                                report($e);

                                Notification::make()
                                    ->title('Не удалось создать ссылку')
                                    ->body('Telegram-бот не настроен. Обратитесь к администратору сайта.')
                                    ->danger()
                                    ->send();

                                return null;
                            }

                            return redirect()->away($link);
                        }),

                    Action::make('unlinkTelegram')
                        ->label('Отвязать')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Отвязать Telegram?')
                        ->modalDescription('Уведомления в Telegram перестанут приходить. Настройки категорий сохранятся.')
                        ->visible($account !== null)
                        ->action(function () use ($user, $returnUrl) {
                            app(UnlinkTelegramAccountAction::class)->handle($user);

                            Notification::make()
                                ->title('Telegram отвязан')
                                ->success()
                                ->send();

                            return redirect($returnUrl);
                        }),

                    Action::make('refreshTelegram')
                        ->label('Проверить привязку')
                        ->color('gray')
                        ->action(fn () => redirect($returnUrl)),
                ])->key('telegram-actions'),
            ]);
    }
}
