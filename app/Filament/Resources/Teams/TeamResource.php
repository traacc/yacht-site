<?php

namespace App\Filament\Resources\Teams;

use App\Enums\TeamMemberRole;
use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\Pages\ManageTeams;
use App\Models\Team;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
                Select::make('organizer_id')
                    ->label('Капитан')
                    ->relationship('organizer', 'name')
                    ->placeholder('Выберите капитана')
                    ->columnSpanFull(),
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

                            if ($organizerCount > 1) {
                                $fail('В команде может быть только один капитан.');
                            }
                        },
                    ])
                    ->schema([
                        Select::make('user_id')
                            ->label('Пользователь')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, $component) => $query
                                    ->where(function (Builder $q) use ($component) {
                                        $q->freeUsers();

                                        $livewire = $component?->getLivewire();
                                        if ($livewire && method_exists($livewire, 'getMountedTableActionRecord')) {
                                            $record = $livewire->getMountedTableActionRecord();
                                            if ($record) {
                                                $q->orWhereHas('teamMemberships', fn (Builder $q2) => $q2->where('team_id', $record->getKey()));
                                            }
                                        }
                                    }),
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
                            ])
                            ->default('active')
                            ->required(),
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
                    ->searchable(),
                TextColumn::make('approval_status')
                    ->label('Статус')
                    ->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending' => 'На рассмотрении',
                    'approved' => 'Одобрена',
                    'rejected' => 'Отклонена',
                    'withdrawn' => 'Отозвана',
                    default => $state,
                })
                ->color(fn (string $state): string => match ($state) {
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'withdrawn' => 'gray',
                    default => 'gray',
                }),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                SelectFilter::make('approval_status')
                ->label('Статус') // Красивое название для пользователя
                ->options([
                    'pending' => 'На рассмотрении',
                    'approved' => 'Одобрена',
                    'rejected' => 'Отклонена',
                    'withdrawn' => 'Отозвана',
                ])
            ], layout: FiltersLayout::AboveContent)->filtersFormColumns(3)->deferFilters(false)
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать команду'),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
