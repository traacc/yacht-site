<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictsAccessByRole;
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

/**
 * Обзорная страница подраздела «Ремонт и модернизация» раздела «Carter 30».
 *
 * Сами кейсы — отдельный ресурс (RepairCaseResource); здесь только вводный
 * текст и общие чертежи с документами.
 */
class Carter30RepairPageSettings extends Page
{
    use HasUnsavedDataChangesAlert;
    use RestrictsAccessByRole;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Carter 30: Ремонт';

    protected static ?string $title = 'Настройки страницы «Ремонт и модернизация»';

    protected static ?int $navigationSort = 28;

    protected static string|UnitEnum|null $navigationGroup = 'Яхты';

    public array $data = [];

    public function mount(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        // Нормализуем file каждого документа — преобразуем в массив для FileUpload
        $documents = collect((array) $settings->get('carter30.repair.documents', []))
            ->map(function (array $document): array {
                $file = $document['file'] ?? null;
                $document['file'] = is_string($file) && $file !== '' ? [$file] : [];

                return $document;
            })
            ->values()
            ->all();

        $this->form->fill([
            'intro' => $settings->get('carter30.repair.intro', ''),
            'documents' => $documents,
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
                Section::make('Описание раздела')
                    ->description('Текст с картинками в теле. Картинки добавляйте кнопкой «Прикрепить файлы»: при копировании из Word переносится только текст.')
                    ->schema([
                        RichEditor::make('intro')
                            ->label('Содержание')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('carter30/repair')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsMaxSize(5120)
                            ->columnSpanFull(),
                    ]),

                Section::make('Чертежи и документы')
                    ->description('Файлы для скачивания. Перетаскивайте записи для изменения порядка отображения.')
                    ->schema([
                        Repeater::make('documents')
                            ->label('Документы')
                            ->addActionLabel('Добавить документ')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Название документа')
                                    ->required()
                                    ->maxLength(255)
                                    ->rules(['required', 'string', 'max:255']),

                                TextInput::make('desc')
                                    ->label('Описание')
                                    ->placeholder('Например: Чертёж усиления вант-путенсов')
                                    ->nullable()
                                    ->maxLength(255)
                                    ->rules(['nullable', 'string', 'max:255']),

                                FileUpload::make('file')
                                    ->label('Файл')
                                    ->helperText('PDF, изображения или файлы чертежей. Максимальный размер: 20 МБ.')
                                    ->disk('public')
                                    ->directory('carter30/repair')
                                    ->visibility('public')
                                    ->maxSize(20480)
                                    // Чертежи приходят не только в PDF, поэтому список
                                    // шире, чем на странице регламента.
                                    ->acceptedFileTypes([
                                        'application/pdf',
                                        'image/jpeg',
                                        'image/png',
                                        'image/webp',
                                        'application/acad',
                                        'image/vnd.dwg',
                                        'application/dxf',
                                        'image/vnd.dxf',
                                    ])
                                    ->nullable()
                                    ->columnSpanFull(),
                            ]),
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

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $documents = collect((array) ($data['documents'] ?? []))
            ->filter(fn (array $document) => ! empty($document['title']))
            ->map(function (array $document): array {
                $file = $document['file'] ?? [];
                if (is_array($file)) {
                    $file = ! empty($file) ? reset($file) : null;
                }
                if ($file instanceof TemporaryUploadedFile) {
                    $file = null;
                }

                return [
                    'title' => $document['title'] ?? '',
                    'desc' => $document['desc'] ?? '',
                    'file' => $file,
                ];
            })
            ->values()
            ->all();

        $settings->set('carter30.repair.intro', $data['intro'] ?? '', 'carter30');
        $settings->set('carter30.repair.documents', $documents, 'carter30');
        $settings->forgetGroup('carter30');

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();

        // Состояние формы теперь совпадает с сохранённым — сбрасываем базу сравнения,
        // иначе уход со страницы после сохранения будет считаться потерей изменений.
        $this->rememberData();
    }
}
