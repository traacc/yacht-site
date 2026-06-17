<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RegulationsPageSettings extends Page
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Регламент';

    protected static ?string $title = 'Управление документами регламента';

    protected static ?int $navigationSort = 23;

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

        $documents = $settings->get('regulations.documents', []);
        $before_note = $settings->get('regulations.before_note', '');

        // Нормализуем file каждого документа — преобразуем в массив для FileUpload
        $documents = collect((array) $documents)
            ->map(function (array $document): array {
                $file = $document['file'] ?? null;
                if (is_string($file) && $file !== '') {
                    $document['file'] = [$file];
                } else {
                    $document['file'] = [];
                }
                return $document;
            })
            ->values()
            ->all();

        $this->form->fill([
            'documents' => $documents,
            'before_note' => $before_note,
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
                Section::make('Документы регламента')
                    ->description('Загрузите документы (PDF), которые будут отображаться на странице «Технический регламент яхт». Перетаскивайте записи для изменения порядка отображения.')
                    ->schema([
                        RichEditor::make('before_note')->label('Текст перед документами'),
                        Repeater::make('documents')
                            ->label('Документы')
                            ->addActionLabel('Добавить документ')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Название документа')
                                    ->placeholder('Введите название документа')
                                    ->required()
                                    ->maxLength(255)
                                    ->rules(['required', 'string', 'max:255']),

                                TextInput::make('desc')
                                    ->label('Описание')
                                    ->placeholder('Например: Актуальная редакция от 12 мая 2025')
                                    ->nullable()
                                    ->maxLength(255)
                                    ->rules(['nullable', 'string', 'max:255']),

                                FileUpload::make('file')
                                    ->label('Файл')
                                    ->helperText('Допустимые форматы: PDF. Максимальный размер: 10 МБ.')
                                    ->disk('public')
                                    ->directory('regulations')
                                    ->visibility('public')
                                    ->maxSize(10240)
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->nullable()
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

        $documents = collect((array) ($data['documents'] ?? []))
            ->filter(fn (array $document) => ! empty($document['title']))
            ->map(function (array $document): array {
                $file = $document['file'] ?? [];
                if (is_array($file)) {
                    $file = ! empty($file) ? reset($file) : null;
                }
                if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                    $file = null;
                }

                return [
                    'title' => $document['title'] ?? '',
                    'desc'  => $document['desc'] ?? '',
                    'file'  => $file,
                ];
            })
            ->values()
            ->all();

        $before_note = $data['before_note'] ?? '';

        $settings->set('regulations.before_note', $before_note, 'regulations');
        $settings->set('regulations.documents', $documents, 'regulations');
        $settings->forgetGroup('regulations');

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}