<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
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

/**
 * Страница настроек обязательных документов для заявок на регату.
 *
 * Переключатели формируются динамически из таблицы yacht_document_types
 * (только типы с is_configurable = true). Типы управляются через
 * YachtDocumentTypeResource в админ-панели.
 */
class RegattaEntryDocumentSettings extends Page
{
    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Документы заявок';

    protected static ?string $title = 'Обязательные документы для заявок на регату';

    protected static ?int $navigationSort = 60;

    protected static string|UnitEnum|null $navigationGroup = 'Регаты';

    /** @var array<string, bool> */
    public array $data = [];

    // ──────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────

    public function mount(UpdateRegattaEntryRequiredDocumentsAction $action): void
    {
        $this->form->fill($action->get());
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->isAdmin() ?? false;
    }

    // ──────────────────────────────────────────────
    // Content & Form schemas
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
        $types = \App\Models\YachtDocumentType::cachedConfigurable();

        if ($types->isEmpty()) {
            return $schema
                ->statePath('data')
                ->components([
                    Section::make('Обязательные документы')
                        ->description('Настраиваемые типы документов отсутствуют.')
                        ->schema([
                            Placeholder::make('empty')
                                ->hiddenLabel()
                                ->content(new HtmlString(
                                    'Добавьте типы документов через раздел '
                                    . '«<strong>Яхты → Типы документов</strong>» в боковом меню.'
                                )),
                        ]),
                ]);
        }

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Обязательные документы')
                    ->description('Документы, которые требуются при подаче заявки на регату.')
                    ->schema(
                        $types->map(
                            fn (\App\Models\YachtDocumentType $type) => Toggle::make($type->key)
                                ->label($type->label)
                                ->helperText($type->description)
                                ->default(false),
                        )->all(),
                    ),
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

    public function save(UpdateRegattaEntryRequiredDocumentsAction $action): void
    {
        $data = $this->form->getState();

        $this->validate([
            'data.*' => ['boolean'],
        ]);

        $action->save($data);

        Notification::make()
            ->title('Настройки обязательных документов сохранены')
            ->body('Изменения применены. Теперь пользователи будут видеть обновлённый список обязательных документов при подаче заявки.')
            ->success()
            ->send();
    }
}