<?php

declare(strict_types=1);

namespace App\Filament\User\Pages;

use App\Actions\Notifications\GenerateTelegramLinkTokenAction;
use App\Actions\Notifications\SaveNotificationPreferencesAction;
use App\Actions\Notifications\UnlinkTelegramAccountAction;
use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\User;
use App\Services\Notifications\NotificationPreferences;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * Центр уведомлений: матрица «категория × канал» и привязка Telegram.
 *
 * Отсутствие сохранённых настроек означает «включено всё», поэтому страница
 * при первом открытии показывает все галочки отмеченными.
 *
 * @see NotificationPreferences
 */
class NotificationSettings extends Page
{
    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'Уведомления';

    protected static ?string $title = 'Настройки уведомлений';

    protected static ?int $navigationSort = 0;

    /** @var array<string, array<string, bool>> */
    public array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Аккаунт';
    }

    public function mount(): void
    {
        $this->form->fill(app(NotificationPreferences::class)->matrix($this->user()));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make($this->getFormActions())
                        ->key('form-actions'),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $hasTelegram = $this->user()->hasLinkedTelegram();

        $categories = array_map(
            fn (NotificationCategory $category) => Fieldset::make($category->getLabel())
                ->schema(array_map(
                    fn (NotificationChannel $channel) => Checkbox::make("{$category->value}.{$channel->value}")
                        ->label($channel->getLabel())
                        ->default(true)
                        ->disabled($channel === NotificationChannel::Telegram && ! $hasTelegram)
                        // Отключённые поля Filament по умолчанию не дегидрируются:
                        // без этого галочки Telegram сбрасывались бы при сохранении.
                        ->dehydrated(true)
                        ->helperText($channel === NotificationChannel::Telegram && ! $hasTelegram
                            ? 'Сначала привяжите Telegram ниже'
                            : null),
                    NotificationChannel::available(),
                ))
                ->columns(3),
            NotificationCategory::cases(),
        );

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Способы получения уведомлений')
                    ->description('Отметьте, какие уведомления и куда вы хотите получать. Снятая галочка — отписка от рассылки.')
                    ->schema($categories),

                $this->telegramSection(),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить настройки')
                ->color('primary')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        app(SaveNotificationPreferencesAction::class)->handle($this->user(), $this->form->getState());

        Notification::make()
            ->title('Настройки уведомлений сохранены')
            ->success()
            ->send();
    }

    private function telegramSection(): Section
    {
        $account = $this->user()->telegramAccount;

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
                        ->action(function () {
                            try {
                                // Токен создаём по клику, а не при каждом рендере страницы.
                                $link = app(GenerateTelegramLinkTokenAction::class)->handle($this->user());
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
                        ->action(function () {
                            app(UnlinkTelegramAccountAction::class)->handle($this->user());

                            Notification::make()
                                ->title('Telegram отвязан')
                                ->success()
                                ->send();

                            return redirect(static::getUrl());
                        }),

                    Action::make('refreshTelegram')
                        ->label('Проверить привязку')
                        ->color('gray')
                        ->action(fn () => redirect(static::getUrl())),
                ])->key('telegram-actions'),
            ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
