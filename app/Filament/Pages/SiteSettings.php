<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Models\ApiClient;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\SitemapGenerator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\HtmlString;
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

    /** Plaintext только что выпущенного API-токена — показывается один раз. */
    public ?string $newApiToken = null;

    /** Имя клиента, для которого выпущен токен (для подписи в UI). */
    public ?string $newApiTokenName = null;

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
            // Отдел заказов: заявки на услуги, аренду и ремонт
            'order_email' => $settings->orderEmail(),
            // Автопубликация новостей в Telegram
            'telegram_autopublish' => (bool) $settings->get('home.telegram_autopublish', true),
            // Автопубликация новостей в VK
            'vk_autopublish' => (bool) $settings->get('home.vk_autopublish', true),
            // Рассылка уведомлений о новостях пользователям сайта
            'news_notifications' => (bool) $settings->get('home.news_notifications', true),
            // Слать письма центра уведомлений только на подтверждённые адреса
            'notify_verified_emails_only' => $settings->notifyVerifiedEmailsOnly(),
        ]);
    }

    /**
     * Сводка по подтверждённым адресам — чтобы администратор видел последствия
     * включения галочки до того, как её включит.
     */
    private function verifiedEmailsSummary(): string
    {
        $real = User::query()->where('email', 'not like', '%@noemail.local')->count();
        $verified = User::query()
            ->where('email', 'not like', '%@noemail.local')
            ->whereNotNull('email_verified_at')
            ->count();
        $unverified = $real - $verified;

        $summary = "подтверждено {$verified} из {$real} реальных адресов.";

        return $unverified > 0
            ? $summary." При включённой галочке письма центра уведомлений не будут приходить {$unverified} пользователям."
            : $summary;
    }

    /**
     * Кнопки в шапке страницы.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('issueApiToken')
                ->label('Выпустить API-токен')
                ->icon(Heroicon::OutlinedKey)
                ->color('primary')
                ->modalHeading('Выпуск API-токена')
                ->modalDescription('Токен для доступа внешней программы к API (участники и результаты). Значение показывается один раз.')
                ->modalSubmitActionLabel('Выпустить')
                ->schema([
                    TextInput::make('name')
                        ->label('Название клиента')
                        ->placeholder('Судейская программа')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    [$client, $plain] = ApiClient::issue(trim((string) $data['name']));

                    $this->newApiToken = $plain;
                    $this->newApiTokenName = $client->name;

                    Notification::make()
                        ->title('Токен выпущен')
                        ->body('Скопируйте значение — оно показывается один раз.')
                        ->success()
                        ->send();
                }),

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
                    ->description('E-mail-адреса администраторов, на которые отправляются системные уведомления: новые заявки на регату, регистрация команд, яхт и пользователей, вопросы и сообщения в поддержку. Можно указать несколько адресов.')
                    ->schema([
                        TagsInput::make('admin_notification_emails')
                            ->label('E-mail администраторов')
                            ->placeholder('admin@example.com')
                            ->helperText('Введите адрес и нажмите Enter. Если список пуст — уведомления не отправляются.')
                            ->nestedRecursiveRules(['email'])
                            ->columnSpanFull(),
                    ]),

                // ── Отдел заказов ─────────────────────────────
                Section::make('Отдел заказов')
                    ->description('Единый адрес для коммерческих запросов с сайта: заявки раздела «Услуги», запросы на аренду яхт и заявки на ремонт.')
                    ->schema([
                        TextInput::make('order_email')
                            ->label('E-mail отдела заказов')
                            ->email()
                            ->placeholder('order@carter-pro.ru')
                            ->helperText('Если поле пустое, письма уходят на order@carter-pro.ru.')
                            ->maxLength(255)
                            ->rules(['nullable', 'email', 'max:255'])
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

                // ── Центр уведомлений ─────────────────────────
                Section::make('Уведомления пользователям')
                    ->description('Настройки центра уведомлений. Способ получения (e-mail, Telegram, личный кабинет) каждый пользователь выбирает сам в своём кабинете.')
                    ->schema([
                        Toggle::make('news_notifications')
                            ->label('Уведомлять пользователей о новых новостях')
                            ->helperText('Отключите, чтобы временно приостановить массовую рассылку по сайту.')
                            ->default(true),

                        Toggle::make('notify_verified_emails_only')
                            ->label('Отправлять письма только на подтверждённые e-mail')
                            ->helperText('Касается только уведомлений центра уведомлений. Письма подтверждения адреса, восстановления пароля и системные письма по заявкам отправляются всегда.')
                            ->default(false),

                        Placeholder::make('verified_emails_stats')
                            ->label('Сейчас в базе')
                            ->content(fn (): string => $this->verifiedEmailsSummary()),
                    ]),

                // ── API для внешней программы ─────────────────
                Section::make('API для внешней программы')
                    ->description('Токены доступа к API (экспорт участников, импорт и чтение результатов). Кнопка выпуска — в шапке страницы. Токен хранится только хешем и показывается один раз.')
                    ->schema([
                        Placeholder::make('api_clients')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => new HtmlString(
                                view('filament.pages.partials.api-clients', [
                                    'clients' => ApiClient::orderByDesc('created_at')->get(),
                                    'newToken' => $this->newApiToken,
                                    'newTokenName' => $this->newApiTokenName,
                                ])->render(),
                            ))
                            ->columnSpanFull(),
                    ]),

            ]);
    }

    // ──────────────────────────────────────────────
    // API-токены (действия из партиала)
    // ──────────────────────────────────────────────

    /** Отозвать токен: доступ прекращается, запись сохраняется для истории. */
    public function revokeApiClient(string $id): void
    {
        $client = ApiClient::find($id);

        if ($client && $client->revoked_at === null) {
            $client->forceFill(['revoked_at' => now()])->save();

            Notification::make()
                ->title("Токен «{$client->name}» отозван")
                ->success()
                ->send();
        }
    }

    /** Удалить токен безвозвратно. */
    public function deleteApiClient(string $id): void
    {
        $client = ApiClient::find($id);

        if ($client) {
            $name = $client->name;
            $client->delete();

            Notification::make()
                ->title("Токен «{$name}» удалён")
                ->success()
                ->send();
        }
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
            'data.order_email' => ['nullable', 'email', 'max:255'],
            'data.telegram_autopublish' => ['boolean'],
            'data.vk_autopublish' => ['boolean'],
            'data.news_notifications' => ['boolean'],
            'data.notify_verified_emails_only' => ['boolean'],
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

        // Отдел заказов. Пустое значение допустимо: orderEmail() подставит дефолт.
        $settings->set('site.order_email', trim((string) ($data['order_email'] ?? '')), 'site');
        $settings->forgetGroup('site');

        // Автопубликация новостей в Telegram
        $settings->set('home.telegram_autopublish', (bool) ($data['telegram_autopublish'] ?? true), 'home');

        // Автопубликация новостей в VK
        $settings->set('home.vk_autopublish', (bool) ($data['vk_autopublish'] ?? true), 'home');

        // Рассылка уведомлений о новостях пользователям сайта
        $settings->set('home.news_notifications', (bool) ($data['news_notifications'] ?? true), 'home');

        // Слать письма центра уведомлений только на подтверждённые адреса
        $settings->set('home.notify_verified_emails_only', (bool) ($data['notify_verified_emails_only'] ?? false), 'home');

        $settings->forgetGroup('home');

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
