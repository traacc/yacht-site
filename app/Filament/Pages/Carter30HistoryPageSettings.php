<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
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
 * Подраздел «История» раздела «Carter 30».
 *
 * Отдельной модели не заводим: страница — один документ, который заказчик
 * переносит из Word.
 */
class Carter30HistoryPageSettings extends Page
{
    use HasUnsavedDataChangesAlert;
    use RestrictsAccessByRole;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Carter 30: История';

    protected static ?string $title = 'Настройки страницы «История Carter 30»';

    protected static ?int $navigationSort = 27;

    protected static string|UnitEnum|null $navigationGroup = 'Яхты';

    public array $data = [];

    public function mount(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $this->form->fill([
            'content' => $settings->get('carter30.history', ''),
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
                Section::make('История класса')
                    ->description('Текст с картинками в теле. Картинки добавляйте кнопкой «Прикрепить файлы» или перетаскиванием: при копировании из Word переносится только текст.')
                    ->schema([
                        RichEditor::make('content')
                            ->label('Содержание раздела')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('carter30')
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

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $settings->set('carter30.history', $data['content'] ?? '', 'carter30');
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
