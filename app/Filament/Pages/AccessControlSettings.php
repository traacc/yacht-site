<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\SettingsService;
use App\Support\AccessControl;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Страница управления доступом к разделам админ-панели по системной роли.
 *
 * Для каждой настраиваемой роли (Судья, Секретарь, Бухгалтер) можно включить
 * или отключить доступ к каждому ресурсу/странице. Администратор всегда имеет
 * полный доступ и в матрице не отображается.
 *
 * @see AccessControl
 */
class AccessControlSettings extends Page
{
    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $navigationLabel = 'Права доступа';

    protected static ?string $title = 'Права доступа по ролям';

    protected static ?int $navigationSort = 90;

    protected static string|UnitEnum|null $navigationGroup = 'Администрирование';

    /** @var array<string, array<string, bool>> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $matrix = AccessControl::matrix();

        $data = [];

        foreach (AccessControl::configurableRoles() as $role) {
            foreach (AccessControl::manageableItems() as $items) {
                foreach ($items as $item) {
                    $data[$role->value][$item['key']] =
                        (bool) ($matrix[$role->value][$item['key']] ?? true);
                }
            }
        }

        $this->form->fill($data);
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
        $grouped = AccessControl::manageableItems();

        $sections = [];

        foreach (AccessControl::configurableRoles() as $role) {
            $groupSchemas = [];

            foreach ($grouped as $groupName => $items) {
                $toggles = array_map(
                    fn (array $item) => Toggle::make($role->value.'.'.$item['key'])
                        ->label($item['label'])
                        ->default(true)
                        ->inline(false),
                    $items,
                );

                $groupSchemas[] = Fieldset::make($groupName)
                    ->schema($toggles)
                    ->columns(2);
            }

            $sections[] = Section::make($role->getLabel())
                ->description('Разделы, доступные роли «'.$role->getLabel().'».')
                ->schema($groupSchemas)
                ->collapsible();
        }

        return $schema
            ->statePath('data')
            ->components($sections);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить права')
                ->color('primary')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $settings->set(AccessControl::SETTING_KEY, $data, AccessControl::SETTING_GROUP);
        $settings->forgetGroup(AccessControl::SETTING_GROUP);

        Notification::make()
            ->title('Права доступа сохранены')
            ->body('Изменения применяются сразу. Администратор всегда имеет полный доступ.')
            ->success()
            ->send();
    }
}
