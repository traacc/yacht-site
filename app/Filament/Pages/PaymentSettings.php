<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\PaymentProviderCode;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Настройки эквайринга (онлайн-оплаты). Хранятся в settings, группа
 * «payments». Сюда же добавятся секции с креденшелами реальных провайдеров
 * (ЮKassa, Т-Банк) при их подключении.
 */
class PaymentSettings extends Page
{
    use HasUnsavedDataChangesAlert;
    use RestrictsAccessByRole;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Онлайн-оплата';

    protected static ?string $title = 'Настройки онлайн-оплаты';

    protected static ?int $navigationSort = 12;

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $this->form->fill([
            'enabled' => (bool) $settings->get('payments.enabled', false),
            'provider' => (string) $settings->get('payments.provider', ''),
            'test_enabled' => (bool) $settings->get('payments.test_enabled', false),
            'test_allow_production' => (bool) $settings->get('payments.test_allow_production', false),
        ]);
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
        return $schema
            ->statePath('data')
            ->components([

                // ── Общие настройки ───────────────────────────
                Section::make('Онлайн-оплата')
                    ->description('Приём онлайн-платежей на сайте (стартовые взносы и другие платежи реестра). Пока не выбран банк-эквайер, доступен только тестовый провайдер.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Включить онлайн-оплату')
                            ->helperText('Если выключено — кнопки «Оплатить онлайн» не отображаются.')
                            ->default(false),

                        Select::make('provider')
                            ->label('Активный провайдер')
                            ->options(PaymentProviderCode::class)
                            ->native(false)
                            ->placeholder('Не выбран'),
                    ]),

                // ── Тестовый провайдер ────────────────────────
                Section::make('Тестовый провайдер')
                    ->description('Симулятор оплаты для проверки полного цикла без реального списания средств. Работает в окружениях local и staging.')
                    ->schema([
                        Toggle::make('test_enabled')
                            ->label('Разрешить тестовый провайдер')
                            ->default(false),

                        Toggle::make('test_allow_production')
                            ->label('Разрешить тестовый провайдер в продакшене')
                            ->helperText('Внимание: «оплаченные» через симулятор платежи выглядят как настоящие. Включайте только осознанно и на короткое время.')
                            ->default(false),
                    ]),

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
        $data = $this->form->getState();

        $this->validate([
            'data.enabled' => ['boolean'],
            'data.provider' => ['nullable', 'string'],
            'data.test_enabled' => ['boolean'],
            'data.test_allow_production' => ['boolean'],
        ]);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        // Select с options(Enum::class) возвращает enum-объект, а не строку.
        $provider = $data['provider'] ?? null;

        $settings->setMany([
            'payments.enabled' => (bool) ($data['enabled'] ?? false),
            'payments.provider' => $provider instanceof PaymentProviderCode ? $provider->value : (string) ($provider ?? ''),
            'payments.test_enabled' => (bool) ($data['test_enabled'] ?? false),
            'payments.test_allow_production' => (bool) ($data['test_allow_production'] ?? false),
        ], 'payments');

        $settings->forgetGroup('payments');

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();

        // Состояние формы теперь совпадает с сохранённым — сбрасываем базу сравнения,
        // иначе уход со страницы после сохранения будет считаться потерей изменений.
        $this->rememberData();
    }
}
