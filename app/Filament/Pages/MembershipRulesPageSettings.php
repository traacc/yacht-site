<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Services\MembershipFees;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
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

/**
 * Раздел «Правила вступления»: размер членского взноса и текст о порядке его уплаты.
 *
 * По ТЗ 3-го этапа взнос устанавливается администратором именно в профильном
 * разделе, и там же информация публикуется — страница пишет настройки группы
 * `membership`, которые читает @see \App\Services\MembershipFees и выводит
 * `/association/rules`.
 */
class MembershipRulesPageSettings extends Page
{
    use HasUnsavedDataChangesAlert;
    use RestrictsAccessByRole;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Правила вступления';

    protected static ?string $title = 'Правила вступления: членский взнос';

    protected static ?int $navigationSort = 25;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        /** @var MembershipFees $fees */
        $fees = app(MembershipFees::class);

        $this->form->fill([
            'fee_published' => $fees->isPublished(),
            'fee_unit' => $fees->unit(),
            'fee_intro' => $fees->intro(),
            'fee_rates' => collect($fees->rates())
                ->map(fn (array $rate): array => [
                    'year' => $rate['year'],
                    'amount' => $rate['amount'],
                    'note' => $rate['note'],
                ])
                ->all(),
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
                Section::make('Размер членского взноса')
                    ->description('Взнос берётся с яхты и устанавливается на год. Ставку на новый год добавляйте отдельной записью — прошлые остаются в истории, на сайте показывается действующая.')
                    ->schema([
                        Repeater::make('fee_rates')
                            ->label('Ставки по годам')
                            ->addActionLabel('Добавить год')
                            ->reorderable(false)
                            ->collapsible()
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state): ?string => filled($state['year'] ?? null)
                                ? $state['year'].' — '.(filled($state['amount'] ?? null)
                                    ? MembershipFees::format((float) $state['amount'])
                                    : 'сумма не указана')
                                : null)
                            ->schema([
                                TextInput::make('year')
                                    ->label('Год')
                                    ->numeric()
                                    ->minValue(2000)
                                    ->maxValue(2100)
                                    ->required()
                                    ->default((int) now()->year)
                                    ->rules(['required', 'integer', 'min:2000', 'max:2100'])
                                    ->helperText('Если год указан дважды, останется последняя запись.'),

                                TextInput::make('amount')
                                    ->label('Сумма взноса')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->suffix('₽')
                                    ->required()
                                    ->rules(['required', 'numeric', 'min:0']),

                                TextInput::make('note')
                                    ->label('Примечание')
                                    ->placeholder('Например: при вступлении после 1 июля — половина суммы')
                                    ->maxLength(255)
                                    ->rules(['nullable', 'string', 'max:255'])
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        TextInput::make('fee_unit')
                            ->label('Подпись к сумме')
                            ->placeholder(MembershipFees::DEFAULT_UNIT)
                            ->maxLength(255)
                            ->helperText('Показывается под суммой на сайте: с чего и за какой период берётся взнос.')
                            ->rules(['nullable', 'string', 'max:255']),
                    ]),

                Section::make('Публикация на странице «Правила вступления»')
                    ->description('Блок выводится на /association/rules. Если ставок нет и текст пуст, блок не отображается.')
                    ->schema([
                        Toggle::make('fee_published')
                            ->label('Показывать блок о членском взносе на сайте')
                            ->default(true),

                        RichEditor::make('fee_intro')
                            ->label('Порядок уплаты взноса')
                            ->helperText('Текст рядом с суммой: как, куда и в какие сроки платить.')
                            // Диск задаём явно: FILESYSTEM_DISK=local, а картинки из текста
                            // должны лежать там же, откуда их отдаёт публичная страница.
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('membership')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsMaxSize(5120)
                            ->columnSpanFull(),
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

        $rates = collect((array) ($data['fee_rates'] ?? []))
            ->filter(fn ($rate): bool => is_array($rate) && filled($rate['year'] ?? null) && filled($rate['amount'] ?? null))
            ->map(fn (array $rate): array => [
                'year' => (int) $rate['year'],
                'amount' => round((float) $rate['amount'], 2),
                'note' => trim((string) ($rate['note'] ?? '')),
            ])
            // Год — ключ ставки: дубли схлопываем здесь, чтобы в настройках лежали чистые данные.
            ->keyBy('year')
            ->sortKeysDesc()
            ->values()
            ->all();

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $settings->setMany([
            MembershipFees::RATES_KEY => $rates,
            MembershipFees::UNIT_KEY => trim((string) ($data['fee_unit'] ?? '')),
            MembershipFees::INTRO_KEY => (string) ($data['fee_intro'] ?? ''),
            MembershipFees::PUBLISH_KEY => (bool) ($data['fee_published'] ?? false),
        ], MembershipFees::SETTING_GROUP);

        $settings->forgetGroup(MembershipFees::SETTING_GROUP);

        $this->form->fill([
            'fee_published' => (bool) ($data['fee_published'] ?? false),
            'fee_unit' => trim((string) ($data['fee_unit'] ?? '')),
            'fee_intro' => (string) ($data['fee_intro'] ?? ''),
            'fee_rates' => $rates,
        ]);

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();

        // Состояние формы теперь совпадает с сохранённым — сбрасываем базу сравнения,
        // иначе уход со страницы после сохранения будет считаться потерей изменений.
        $this->rememberData();
    }
}
