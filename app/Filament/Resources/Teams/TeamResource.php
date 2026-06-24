<?php

namespace App\Filament\Resources\Teams;

use App\Enums\TeamMemberRole;
use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\Pages\ManageTeams;
use App\Models\Team;
use App\Exports\TeamExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;

class TeamResource extends Resource
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = 'team';

    public static function getModelLabel(): string
    {
        return 'Команда'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Команды'; // Название во множественном числе
    }

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('picture')
                    ->label('Добавить фотографию')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->avatar()
                    ->directory('owners')
                    ->imageEditor()
                    ->imageEditorViewportWidth(2380)
                    ->imageEditorViewportHeight(1785)
                    ->imageEditorAspectRatios([
                        '4:3',
                        null,
                    ])
                    ->disk('public')->columnSpanFull(),
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Введите названия команды')
                    ->required()
                    ->unique(table: 'teams', column: 'name', ignorable: fn (?Team $record) => $record)
                    ->columnSpanFull(),
                TextInput::make('formatted_external_id')
                    ->label('ID')
                    ->readOnly()
                    ->columnSpanFull()
                    ->formatStateUsing(fn (?Team $record) => $record?->formatted_external_id ?? '—'),
                Hidden::make('organizer_id')
                    ->dehydrateStateUsing(function (Get $get) {
                        $organizer = collect($get('teamMembers'))->first(
                            fn ($member) => ($member['role'] ?? null) === TeamMemberRole::Organizer->value,
                        );

                        return $organizer['user_id'] ?? null;
                    }),
                Textarea::make('description')
                    ->label('Описание')
                    ->placeholder('Описание команды')
                    ->columnSpanFull(),
                Select::make('approval_status')
                    ->label('Статус одобрения')
                    ->placeholder('Выберите статус')
                    ->options(['pending' => 'На рассмотрении', 'approved' => 'Одобрена', 'rejected' => 'Отклонена'])
                    ->default('pending')
                    ->required(),

                Repeater::make('teamMembers')
                    ->label('Участники')
                    ->relationship('teamMembers')
                    ->addActionLabel('Добавить участника')
                    ->columns(3)
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->rules([
                        fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                            if (! is_array($value)) {
                                return;
                            }

                            $organizerCount = collect($value)
                                ->filter(fn (array $member): bool => ($member['role'] ?? null) === TeamMemberRole::Organizer->value)
                                ->count();

                            if ($organizerCount === 0) {
                                $fail('Необходимо назначить рулевого команды.');
                            }

                            if ($organizerCount > 1) {
                                $fail('В команде может быть только один Капитан.');
                            }
                        },
                    ])
                    ->schema([
                        Select::make('user_id')
                            ->label('Пользователь')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, $component) {
                                        // Текущая редактируемая команда (если есть) — её постоянных участников не исключаем
                                        $currentTeamId = null;
                                        $livewire = $component?->getLivewire();
                                        if ($livewire && method_exists($livewire, 'getMountedTableActionRecord')) {
                                            $currentTeamId = $livewire->getMountedTableActionRecord()?->getKey();
                                        }

                                        // Показываем только пользователей, не являющихся постоянными участниками других команд
                                        $query->withoutPermanentInOtherTeams($currentTeamId);

                                        // Исключаем пользователей, уже добавленных в других строках Repeater'а
                                        $statePath = $component->getStatePath();
                                        $segments = explode('.', $statePath);
                                        array_pop($segments);              // убираем 'user_id'
                                        $currentUuid = array_pop($segments); // UUID текущей строки
                                        $repeaterPath = implode('.', $segments);

                                        $repeaterData = data_get($component->getLivewire(), $repeaterPath, []);
                                        $currentValue = $component->getState();
                                        $selectedIds = collect($repeaterData)
                                            ->except([$currentUuid])
                                            ->pluck('user_id')
                                            ->filter()
                                            ->reject(fn ($id) => $id === $currentValue)
                                            ->values();

                                        if ($selectedIds->isNotEmpty()) {
                                            $query->whereNotIn('id', $selectedIds->toArray());
                                        }

                                        return $query;
                                    },
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('role')
                            ->label('Роль')
                            ->options(collect(TeamMemberRole::cases())->mapWithKeys(
                                fn (TeamMemberRole $role) => [$role->value => $role->label()],
                            ))
                            ->default(TeamMemberRole::Member->value)
                            ->required(),
                        Select::make('status')
                            ->label('Статус')
                            ->options([
                                'invited'  => 'Приглашён',
                                'active'   => 'Активен',
                                'declined' => 'Отказался',
                                'left'     => 'Покинул команду',
                            ])
                            ->default('active')
                            ->required(),
                        Toggle::make('is_permanent')
                            ->label('Постоянный участник')
                            ->default(false),
                    ]),

                SpatieMediaLibraryFileUpload::make('gallery')
                    ->label('Галерея')
                    ->collection('gallery')
                    ->multiple()
                    ->reorderable()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->imageEditor()
                    ->disk('public')
                    ->visibility('public')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('activeMembers'))
            ->columns([
                TextColumn::make('formatted_external_id')
                    ->label('ID')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('external_id', $search)),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                TextColumn::make('organizer.name')
                    ->label('Организатор')
                    ->searchable()->toggleable(),
                TextColumn::make('approval_status')
                    ->label('Статус')
                    ->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending' => 'На рассмотрении',
                    'approved' => 'Одобрена',
                    'rejected' => 'Отклонена',
                    'withdrawn' => 'Отозвана',
                    default => $state,
                })->toggleable()
                ->color(fn (string $state): string => match ($state) {
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'withdrawn' => 'gray',
                    default => 'gray',
                })->toggleable(),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                SelectFilter::make('approval_status')
                ->label('Статус') // Красивое название для пользователя
                ->options([
                    'pending' => 'На рассмотрении',
                    'approved' => 'Одобрена',
                    'rejected' => 'Отклонена',
                    'withdrawn' => 'Отозвана',
                ]),
                TrashedFilter::make(),
            ], layout: FiltersLayout::AboveContent)->filtersFormColumns(3)->deferFilters(false)
            ->defaultSort('name')
            ->headerActions([
                Action::make('exportXlsx')
                    ->label('Экспорт в Excel')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('white')
                    ->action(fn ($livewire) => (new TeamExport)->download(
                        $livewire->getFilteredSortedTableQuery(),
                        'teams_'.now()->format('Y-m-d').'.xlsx',
                    )),
            ])
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать команду'),
                DeleteAction::make()
                    ->label('Удалить')
                    ->modalHeading('Удалить команду')
                    ->hidden(false) // показывать и для уже удалённых записей
                    ->using(fn (Team $record, DeleteAction $action) => \App\Support\SafeDelete::single($record, $action, 'команду')),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Удалить')
                        ->using(fn (\Illuminate\Support\Collection $records, DeleteBulkAction $action) => \App\Support\SafeDelete::bulk($records, $action, 'команды')),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTeams::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
