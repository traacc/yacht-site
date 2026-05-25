<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Yacht\UpdateYachtRequiredDocumentsAction;
use App\Enums\YachtDocumentType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Страница настроек обязательных документов для яхты.
 *
 * Доступна только администраторам. Позволяет включать/отключать
 * обязательность каждого типа документа через переключатели (Toggle).
 * Настройки применяются как в Admin-, так и в User-панелях.
 */
class YachtDocumentSettings extends Page
{
    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Документы яхт';

    protected static ?string $title = 'Обязательные документы яхт';

    protected static ?int $navigationSort = 50;

    protected static string|UnitEnum|null $navigationGroup = 'Яхты';

    /** @var array<string, bool> */
    public array $data = [];

    // ──────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────

    public function mount(UpdateYachtRequiredDocumentsAction $action): void
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
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Обязательные документы')
                    ->description(
                        'Отметьте типы документов, которые будут обязательными для каждой яхты. '
                        . 'Пользователи должны будут загрузить эти документы при регистрации или редактировании яхты. '
                        . 'Изменения применяются мгновенно во всех панелях.'
                    )
                    ->schema(
                        array_map(
                            fn (YachtDocumentType $type) => Toggle::make($type->value)
                                ->label($type->label())
                                ->helperText($this->helperTextFor($type))
                                ->default(false),
                            YachtDocumentType::configurable(),
                        ),
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

    public function save(UpdateYachtRequiredDocumentsAction $action): void
    {
        $data = $this->form->getState();

        $this->validate([
            'data.*' => ['boolean'],
        ]);

        $action->save($data);

        Notification::make()
            ->title('Настройки обязательных документов сохранены')
            ->body('Изменения применены. Теперь пользователи будут видеть обновлённый список обязательных документов.')
            ->success()
            ->send();
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function helperTextFor(YachtDocumentType $type): string
    {
        return match ($type) {
            YachtDocumentType::OrcCertificate   => 'Сертификат ORC с гоночным баллом и параметрами яхты.',
            YachtDocumentType::ShipTicket       => 'Судовой билет или свидетельство о регистрации.',
            YachtDocumentType::Insurance        => 'Действующий страховой полис яхты.',
            YachtDocumentType::Regulation       => 'Положение о соревнованиях.',
            YachtDocumentType::RaceInstructions => 'Гоночная инструкция.',
            YachtDocumentType::Charter          => 'Устав организации или клуба.',
            YachtDocumentType::Protocol         => 'Протокол результатов.',
            YachtDocumentType::Other            => 'Прочие документы.',
        };
    }
}