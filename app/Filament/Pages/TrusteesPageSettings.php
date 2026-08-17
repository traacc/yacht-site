<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Services\ImageConverter;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class TrusteesPageSettings extends Page
{
    use HasUnsavedDataChangesAlert;
    use RestrictsAccessByRole;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Попечительский совет';

    protected static ?string $title = 'Управление составом попечительского совета';

    protected static ?int $navigationSort = 21;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    /**
     * Единый массив состояния формы.
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

        $members = $settings->get('trustees.members', []);

        $members = collect((array) $members)
            ->map(function (array $member): array {
                $photo = $member['photo'] ?? null;
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
                Section::make('Состав попечительского совета')
                    ->description('Добавьте, отсортируйте и настройте информацию о членах попечительского совета Ассоциации.')
                    ->schema([
                        Repeater::make('members')
                            ->label('Члены попечительского совета')
                            ->addActionLabel('Добавить члена совета')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Имя')
                                    ->placeholder('Введите полное имя')
                                    ->required()
                                    ->maxLength(255)
                                    ->rules(['required', 'string', 'max:255']),

                                TextInput::make('position')
                                    ->label('Должность')
                                    ->placeholder('Введите должность')
                                    ->required()
                                    ->maxLength(255)
                                    ->rules(['required', 'string', 'max:255']),

                                RichEditor::make('description')
                                    ->label('Описание')
                                    ->placeholder('Введите описание')
                                    ->nullable()
                                    ->columnSpanFull(),

                                FileUpload::make('photo')
                                    ->label('Фотография')
                                    ->helperText('Допустимые форматы: JPEG, PNG, WebP. Максимальный размер: 2 МБ.')
                                    ->image()
                                    ->imagePreviewHeight('200')
                                    ->disk('public')
                                    ->directory('trustees')
                                    ->visibility('public')
                                    ->maxSize(2048)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif'])
                                    ->nullable()
                                    ->imageEditor()
                                    ->imageEditorViewportWidth(1710)
                                    ->imageEditorViewportHeight(2280)
                                    ->imageEditorAspectRatios([
                                        '3:4',
                                        '1:1',
                                        null,
                                    ])
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
        $data = $this->form->getState();

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $members = collect((array) ($data['members'] ?? []))
            ->filter(fn (array $member) => ! empty($member['name']) && ! empty($member['position']))
            ->map(function (array $member): array {
                $photo = $member['photo'] ?? [];
                if (is_array($photo)) {
                    $photo = ! empty($photo) ? reset($photo) : null;
                }
                if ($photo instanceof TemporaryUploadedFile) {
                    $photo = null;
                }
                // HEIC-фото нормализуем в webp (браузер heic не покажет).
                if (is_string($photo) && $photo !== '') {
                    $photo = app(ImageConverter::class)->normalizeHeicToWebp($photo, 'public');
                }

                $responsibilities = collect((array) ($member['responsibilities'] ?? []))
                    ->map(fn ($item) => is_array($item) ? ($item['item'] ?? '') : (string) $item)
                    ->filter(fn (string $item) => $item !== '')
                    ->values()
                    ->all();

                return [
                    'name' => $member['name'] ?? '',
                    'position' => $member['position'] ?? '',
                    'description' => $member['description'] ?? '',
                    'photo' => $photo,
                    'responsibilities' => $responsibilities,
                ];
            })
            ->values()
            ->all();

        $settings->set('trustees.members', $members, 'trustees');
        $settings->forgetGroup('trustees');

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();

        // Состояние формы теперь совпадает с сохранённым — сбрасываем базу сравнения,
        // иначе уход со страницы после сохранения будет считаться потерей изменений.
        $this->rememberData();
    }
}
