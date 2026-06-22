<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class ManagementPageSettings extends Page
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Руководство';

    protected static ?string $title = 'Управление составом руководства';

    protected static ?int $navigationSort = 20;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    /**
     * Единый массив состояния формы — стандартный паттерн Filament для Page с FileUpload.
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

        $members = $settings->get('management.members', []);

        // Нормализуем photo каждого участника — преобразуем в массив для FileUpload
        $members = collect((array) $members)
            ->map(function (array $member): array {
                $photo = $member['photo'] ?? null;
                // FileUpload ожидает массив (даже для одного файла) при использовании image()
                if (is_string($photo) && $photo !== '') {
                    $member['photo'] = [$photo];
                } else {
                    $member['photo'] = [];
                }
                return $member;
            })
            ->values()
            ->all();

        $this->form->fill([
            'members' => $members,
        ]);
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
                Section::make('Состав руководства')
                    ->description('Добавьте, отсортируйте и настройте информацию о членах руководства Ассоциации. Перетаскивайте записи для изменения порядка отображения на сайте.')
                    ->schema([
                        Repeater::make('members')
                            ->label('Члены руководства')
                            ->addActionLabel('Добавить члена руководства')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Имя')
                                    ->placeholder('Введите полное имя')
                                    ->required()
                                    ->maxLength(255)
                                    ->autocomplete('off')
                                    ->extraInputAttributes([
                                        'autocomplete' => 'off',
                                        'data-1p-ignore' => 'true',
                                        'data-lpignore' => 'true',
                                        'data-form-type' => 'other',
                                    ])
                                    ->rules(['required', 'string', 'max:255']),

                                TextInput::make('position')
                                    ->label('Должность')
                                    ->placeholder('Введите должность')
                                    ->required()
                                    ->maxLength(255)
                                    ->rules(['required', 'string', 'max:255']),

                                RichEditor::make('description')
                                    ->label('Описание')
                                    ->placeholder('Введите описание руководителя')
                                    ->nullable()
                                    ->columnSpanFull(),

                                FileUpload::make('photo')
                                    ->label('Фотография')
                                    ->helperText('Допустимые форматы: JPEG, PNG, WebP. Максимальный размер: 2 МБ.')
                                    ->image()
                                    ->imagePreviewHeight('200')
                                    ->disk('public')
                                    ->directory('management')
                                    ->visibility('public')
                                    ->maxSize(2048)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->nullable()
                                    ->columnSpanFull(),

                                Repeater::make('responsibilities')
                                    ->label('Зоны ответственности')
                                    ->addActionLabel('Добавить зону ответственности')
                                    ->defaultItems(0)
                                    ->schema([
                                        TextInput::make('item')
                                            ->label('Формулировка')
                                            ->placeholder('Введите зону ответственности')
                                            ->required()
                                            ->maxLength(500)
                                            ->rules(['required', 'string', 'max:500']),
                                    ])
                                    ->columnSpanFull(),
                            ]),
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
        // getState() вызывает callBeforeStateDehydrated → saveUploadedFiles()
        $data = $this->form->getState();

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        // Нормализуем данные участников
        $members = collect((array) ($data['members'] ?? []))
            ->filter(fn (array $member) => ! empty($member['name']) && ! empty($member['position']))
            ->map(function (array $member): array {
                // Извлекаем путь к фото из массива FileUpload
                $photo = $member['photo'] ?? [];
                if (is_array($photo)) {
                    $photo = ! empty($photo) ? reset($photo) : null;
                }
                // Если это объект TemporaryUploadedFile — пропускаем (не должно случиться после getState)
                if ($photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                    $photo = null;
                }

                // Нормализуем responsibilities: каждый элемент может быть строкой или ['item' => '...']
                $responsibilities = collect((array) ($member['responsibilities'] ?? []))
                    ->map(fn ($item) => is_array($item) ? ($item['item'] ?? '') : (string) $item)
                    ->filter(fn (string $item) => $item !== '')
                    ->values()
                    ->all();

                return [
                    'name'            => $member['name'] ?? '',
                    'position'        => $member['position'] ?? '',
                    'description'     => $member['description'] ?? '',
                    'photo'           => $photo,
                    'responsibilities' => $responsibilities,
                ];
            })
            ->values()
            ->all();

        $settings->set('management.members', $members, 'management');
        $settings->forgetGroup('management');

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}