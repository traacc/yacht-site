<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Help\SaveHelpPageSettingsAction;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Forms\Components\PdfRichEditor;
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
use UnitEnum;

class HelpPageSettings extends Page
{
    use HasUnsavedDataChangesAlert;
    use RestrictsAccessByRole;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    /** Лейбл «Помощь» занят HelpResource (справочник специалистов). */
    protected static ?string $navigationLabel = 'Страница «Помощь»';

    protected static ?string $title = 'Настройки страницы «Помощь»';

    /** Сразу после HelpResource (25). */
    protected static ?int $navigationSort = 26;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    public array $data = [];

    public function mount(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $documents = collect((array) $settings->get('help.site_guide_documents', []))
            ->filter(fn (mixed $document): bool => is_array($document))
            ->map(function (array $document): array {
                $file = $document['file'] ?? null;
                $document['file'] = is_string($file) && $file !== '' ? [$file] : [];

                return $document;
            })
            ->values()
            ->all();

        $this->form->fill([
            'before_note' => $settings->get('help.before_note', ''),
            'site_guide' => $settings->get('help.site_guide', ''),
            'site_guide_documents' => $documents,
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
                Section::make('Помощь по сайту')
                    ->description('Описание разделов сайта и как ими пользоваться. Отображается в первой вкладке страницы «Помощь». Скриншоты добавляйте кнопкой «Прикрепить файлы» или перетаскиванием: при копировании из Word переносится только текст.')
                    ->schema([
                        PdfRichEditor::make('site_guide')
                            ->label('Содержание раздела')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('help')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsMaxSize(5120)
                            ->pdfAttachmentsDisk('public')
                            ->pdfAttachmentsDirectory('help/site-guide')
                            ->pdfAttachmentsVisibility('public')
                            ->pdfAttachmentsMaxSize(10240)
                            ->columnSpanFull(),

                        Repeater::make('site_guide_documents')
                            ->label('PDF-документы')
                            ->addActionLabel('Добавить PDF-документ')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->columns(3)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Название документа')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('desc')
                                    ->label('Описание')
                                    ->maxLength(255),

                                FileUpload::make('file')
                                    ->label('PDF-файл')
                                    ->helperText('Максимальный размер: 10 МБ.')
                                    ->disk('public')
                                    ->directory('help/site-guide')
                                    ->visibility('public')
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->maxSize(10240)
                                    ->openable()
                                    ->downloadable()
                                    ->required(),
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Вводный текст')
                    ->description('Текст отображается во вкладке «Для владельцев яхт» перед списком специалистов.')
                    ->schema([
                        RichEditor::make('before_note')
                            ->label('Текст перед контентом')
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

    public function save(SaveHelpPageSettingsAction $saveSettings): void
    {
        $saveSettings->execute($this->form->getState());

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();

        // Состояние формы теперь совпадает с сохранённым — сбрасываем базу сравнения,
        // иначе уход со страницы после сохранения будет считаться потерей изменений.
        $this->rememberData();
    }
}
