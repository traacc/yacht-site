<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Services\SettingsService;
use App\Services\SitemapGenerator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SiteSettings extends Page
{
    use RestrictsAccessByRole;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Настройки сайта';

    protected static ?string $title = 'Настройки сайта';

    protected static ?int $navigationSort = 11;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    /**
     * Единый массив состояния формы — стандартный паттерн Filament для Page.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    // ──────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────

    public function mount(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $this->form->fill([
            // Режим обновления сайта
            'maintenance_mode' => (bool) $settings->get('home.maintenance_mode', false),
            'maintenance_message' => $settings->get('home.maintenance_message', 'Сайт в процессе обновления'),
            // E-mail'ы администраторов для уведомлений
            'admin_notification_emails' => $settings->adminNotificationEmails(),
            // Автопубликация новостей в Telegram
            'telegram_autopublish' => (bool) $settings->get('home.telegram_autopublish', true),
            // Автопубликация новостей в VK
            'vk_autopublish' => (bool) $settings->get('home.vk_autopublish', true),
        ]);
    }

    /**
     * Кнопки в шапке страницы.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateSitemap')
                ->label('Сгенерировать sitemap.xml')
                ->icon(Heroicon::OutlinedMap)
                ->color('gray')
                ->action(function (): void {
                    $count = app(SitemapGenerator::class)->generate();

                    Notification::make()
                        ->title('Карта сайта обновлена')
                        ->body("Записано URL: {$count}. Файл: /sitemap.xml")
                        ->success()
                        ->send();
                }),
        ];
    }

    // ──────────────────────────────────────────────
    // Content schema (Filament 4)
    // ──────────────────────────────────────────────

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
        return $schema
            ->statePath('data')
            ->components([

                // ── Режим обновления сайта ────────────────────
                Section::make('Режим обновления')
                    ->description('Если включено — посетители видят заглушку вместо содержимого сайта. Панель администратора остаётся доступной.')
                    ->schema([
                        Toggle::make('maintenance_mode')
                            ->label('Скрыть содержимое сайта')
                            ->helperText('Включите, чтобы временно закрыть публичный сайт для посетителей.')
                            ->default(false)
                            ->live(),

                        TextInput::make('maintenance_message')
                            ->label('Текст заглушки')
                            ->placeholder('Сайт в процессе обновления')
                            ->default('Сайт в процессе обновления')
                            ->maxLength(255)
                            ->visible(fn ($get) => (bool) $get('maintenance_mode'))
                            ->rules(['nullable', 'string', 'max:255']),
                    ]),

                // ── Уведомления администраторам ───────────────
                Section::make('Уведомления администраторам')
                    ->description('E-mail-адреса администраторов, на которые отправляются системные уведомления: новые заявки на регату, регистрация команд, яхт и пользователей. Можно указать несколько адресов.')
                    ->schema([
                        TagsInput::make('admin_notification_emails')
                            ->label('E-mail администраторов')
                            ->placeholder('admin@example.com')
                            ->helperText('Введите адрес и нажмите Enter. Если список пуст — уведомления не отправляются.')
                            ->nestedRecursiveRules(['email'])
                            ->columnSpanFull(),
                    ]),

                // ── Публикация новостей в Telegram ────────────
                Section::make('Публикация в Telegram')
                    ->description('Если включено — новости автоматически публикуются в Telegram-канал при наступлении даты публикации. Если выключено — посты в Telegram не создаются.')
                    ->schema([
                        Toggle::make('telegram_autopublish')
                            ->label('Автопубликация новостей в Telegram')
                            ->helperText('Отключите, чтобы временно приостановить автопостинг новостей в канал.')
                            ->default(true),
                    ]),

                // ── Публикация новостей в VK ──────────────────
                Section::make('Публикация в VK')
                    ->description('Если включено — новости автоматически публикуются в сообщество VK при наступлении даты публикации. Если выключено — посты в VK не создаются.')
                    ->schema([
                        Toggle::make('vk_autopublish')
                            ->label('Автопубликация новостей в VK')
                            ->helperText('Отключите, чтобы временно приостановить автопостинг новостей в сообщество.')
                            ->default(true),
                    ]),

            ]);
    }

    // ──────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────

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
        $data = $this->form->getState();

        $this->validate([
            'data.maintenance_mode' => ['boolean'],
            'data.maintenance_message' => ['nullable', 'string', 'max:255'],
            'data.admin_notification_emails' => ['nullable', 'array'],
            'data.admin_notification_emails.*' => ['email'],
            'data.telegram_autopublish' => ['boolean'],
            'data.vk_autopublish' => ['boolean'],
        ]);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        // Режим обновления сайта
        $settings->set('home.maintenance_mode', (bool) ($data['maintenance_mode'] ?? false), 'home');
        $settings->set(
            'home.maintenance_message',
            trim((string) ($data['maintenance_message'] ?? '')) ?: 'Сайт в процессе обновления',
            'home',
        );

        // E-mail'ы администраторов для системных уведомлений
        $adminEmails = collect((array) ($data['admin_notification_emails'] ?? []))
            ->flatten()
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        $settings->set('home.admin_notification_emails', $adminEmails, 'home');

        // Автопубликация новостей в Telegram
        $settings->set('home.telegram_autopublish', (bool) ($data['telegram_autopublish'] ?? true), 'home');

        // Автопубликация новостей в VK
        $settings->set('home.vk_autopublish', (bool) ($data['vk_autopublish'] ?? true), 'home');

        $settings->forgetGroup('home');

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
