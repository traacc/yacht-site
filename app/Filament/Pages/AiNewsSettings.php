<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Jobs\DiscoverSailingNews;
use App\Services\WorldNews\WorldNewsSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

class AiNewsSettings extends Page
{
    use HasUnsavedDataChangesAlert;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $navigationLabel = 'Настройки AI-новостей';

    protected static ?string $title = 'Настройки AI-новостей';

    protected static ?int $navigationSort = 14;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $settings = app(WorldNewsSettings::class);
        $values = $settings->all();

        $this->form->fill([
            'enabled' => (bool) ($values['enabled'] ?? false),
            'auto_publish' => (bool) ($values['auto_publish'] ?? false),
            'interval_minutes' => (int) ($values['interval_minutes'] ?? 360),
            'lookback_days' => (int) ($values['lookback_days'] ?? 7),
            'max_items' => (int) ($values['max_items'] ?? 5),
            'min_relevance' => (int) ($values['min_relevance'] ?? 70),
            'system_prompt' => (string) ($values['system_prompt'] ?? ''),
            'search_prompt' => (string) ($values['search_prompt'] ?? ''),
        ]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('runDiscovery')
                ->label('Запустить поиск сейчас')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Запустить поиск новостей?')
                ->modalDescription('Задание будет поставлено в очередь и выполнено с сохранёнными настройками.')
                ->modalSubmitActionLabel('Запустить')
                ->action(function (): void {
                    if (! app(WorldNewsSettings::class)->isProviderConfigured()) {
                        Notification::make()
                            ->title('AI-провайдер не настроен')
                            ->body('Задайте API-ключ в переменных окружения перед запуском поиска.')
                            ->warning()
                            ->send();

                        return;
                    }

                    DiscoverSailingNews::dispatch(force: true);

                    Notification::make()
                        ->title('Поиск поставлен в очередь')
                        ->body('Новые кандидаты появятся в разделе «AI-новости».')
                        ->success()
                        ->send();
                }),
        ];
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
                Section::make('Состояние AI-провайдера')
                    ->description('Секретный API-ключ задаётся только через переменные окружения и никогда не выводится в панели.')
                    ->schema([
                        Placeholder::make('provider_status')
                            ->label('API-ключ')
                            ->content(fn (): string => app(WorldNewsSettings::class)->isProviderConfigured()
                                ? 'Настроен'
                                : 'Не настроен'),

                        Placeholder::make('provider_model')
                            ->label('Модель')
                            ->content(fn (): string => app(WorldNewsSettings::class)->model()),

                        Placeholder::make('last_run')
                            ->label('Последний запуск')
                            ->content(fn (): string => app(WorldNewsSettings::class)->lastRunSummary())
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Поиск и публикация')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Включить автоматический поиск')
                            ->default(false),

                        Toggle::make('auto_publish')
                            ->label('Автоматически публиковать отобранные новости')
                            ->helperText('Если выключено, найденные материалы ожидают ручной проверки в разделе «AI-новости».')
                            ->default(false),

                        TextInput::make('interval_minutes')
                            ->label('Интервал запуска, минут')
                            ->numeric()
                            ->integer()
                            ->minValue(15)
                            ->maxValue(10080)
                            ->required(),

                        TextInput::make('lookback_days')
                            ->label('Глубина поиска, дней')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(30)
                            ->required(),

                        TextInput::make('max_items')
                            ->label('Максимум материалов за запуск')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required(),

                        TextInput::make('min_relevance')
                            ->label('Минимальная релевантность')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Инструкции для AI')
                    ->description('Системная инструкция задаёт редакционные правила, поисковая — темы и критерии отбора материалов.')
                    ->schema([
                        Textarea::make('system_prompt')
                            ->label('Системная инструкция')
                            ->rows(12)
                            ->required()
                            ->maxLength(20000)
                            ->columnSpanFull(),

                        Textarea::make('search_prompt')
                            ->label('Поисковая инструкция')
                            ->helperText('Доступные подстановки: {{from_date}}, {{to_date}}, {{max_items}}.')
                            ->rows(8)
                            ->required()
                            ->maxLength(10000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** @return array<Action> */
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
            'data.enabled' => ['required', 'boolean'],
            'data.auto_publish' => ['required', 'boolean'],
            'data.interval_minutes' => ['required', 'integer', 'min:15', 'max:10080'],
            'data.lookback_days' => ['required', 'integer', 'min:1', 'max:30'],
            'data.max_items' => ['required', 'integer', 'min:1', 'max:10'],
            'data.min_relevance' => ['required', 'integer', 'between:0,100'],
            'data.system_prompt' => ['required', 'string', 'max:20000'],
            'data.search_prompt' => ['required', 'string', 'max:10000'],
        ]);

        app(WorldNewsSettings::class)->save($data);

        Notification::make()
            ->title('Настройки AI-новостей сохранены')
            ->success()
            ->send();

        // Состояние формы теперь совпадает с сохранённым — сбрасываем базу сравнения,
        // иначе уход со страницы после сохранения будет считаться потерей изменений.
        $this->rememberData();
    }
}
