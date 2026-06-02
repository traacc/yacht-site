<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Teams;

use App\Enums\TeamMemberRole;
use App\Filament\User\Resources\Teams\Pages\ManageTeams;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleGuard;
use BackedEnum;
use Illuminate\Validation\ValidationException;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use App\Models\Yacht;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = 'team';

    public static function getRelations(): array
    {
        return [];
    }

    public static function getModelLabel(): string
    {
        return 'Моя команда';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Мои команды';
    }

    // ──────────────────────────────────────────────
    // Авторизация через TeamPolicy
    // ──────────────────────────────────────────────

    /**
     * Создать команду может любой авторизованный пользователь.
     */
    public static function canCreate(): bool
    {
        return auth()->check();
    }

    /**
     * Редактировать команду могут только Organizer и TeamAdmin.
     */
    public static function canEdit(Model $record): bool
    {
        /** @var Team $record */
        return auth()->user()?->can('editTeam', $record) ?? false;
    }

    /**
     * Удалять (soft-delete) команду может только Organizer.
     */
    public static function canDelete(Model $record): bool
    {
        /** @var Team $record */
        return auth()->user()?->can('archiveTeam', $record) ?? false;
    }

    // ──────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────

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
                    ->required()->columnSpanFull(),
                Textarea::make('description')
                    ->label('Описание')
                    ->placeholder('Описание команды')
                    ->columnSpanFull(),

                Select::make('default_yacht_id')
                    ->label('Яхта по умолчанию')
                    ->placeholder('Выберите яхту')
                    ->options(fn () => Yacht::where('approval_status', 'approved')
                        //->where('user_id', auth()->id())
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->columnSpanFull(),

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

                Repeater::make('teamMembers')
                    ->label('Участники')
                    ->relationship('teamMembers')
                    ->addActionLabel('Добавить участника')
                    ->columns(2)
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
                                $fail('В команде может быть только один капитан');
                            }
                        },
                    ])
                    ->schema([
                        Select::make('user_id')
                            ->label('Пользователь')
                            ->relationship(name:'user', titleAttribute:'name', modifyQueryUsing: fn (Builder $query) => $query->freeUsers())
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
                        Hidden::make('status')
                            ->default('active'),
                    ]),

                Hidden::make('organizer_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    // ──────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('activeMembers'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()->label('Команда'),
                TextColumn::make('organizer.name')
                    ->searchable()->label('Капитан'),
                TextColumn::make('active_members_count')
                    ->label('Участники')
                    ->sortable(),
                // Роль текущего пользователя в команде
                TextColumn::make('current_user_role')
                    ->label('Ваша роль')
                    ->state(function (Team $record): string {
                        $role = TeamRoleGuard::roleOf($record, auth()->user());
                        return $role?->label() ?? '—';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Организатор' => 'success',
                        'Администратор' => 'info',
                        'Участник' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('approval_status')
                    ->label('Статус')
                    ->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'На рассмотрении',
                        'approved'  => 'Одобрена',
                        'rejected'  => 'Отклонена',
                        'withdrawn' => 'Отозвана',
                        default     => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'approved'  => 'success',
                        'rejected'  => 'danger',
                        'withdrawn' => 'gray',
                        default     => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])->emptyStateHeading('Записей пока нет')
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать команду')
                    ->hiddenLabel()
                    ->visible(fn (Team $record): bool => auth()->user()?->can('editTeam', $record) ?? false),
                /*
                RestoreAction::make()
                    ->visible(fn (Team $record): bool => auth()->user()?->can('archiveTeam', $record) ?? false),
                */
                DeleteAction::make()
                    ->hiddenLabel()
                    ->visible(fn (Team $record): bool => auth()->user()?->can('archiveTeam', $record) ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ])->stackedOnMobile();
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

    /**
     * Показываем команды, в которых текущий пользователь:
     *   — является организатором (organizer_id), ИЛИ
     *   — является активным участником (через team_members).
     */
    public static function getEloquentQuery(): Builder
    {
        $userId = auth()->id();

        return parent::getEloquentQuery()
            ->where(function (Builder $query) use ($userId): void {
                $query
                    ->where('organizer_id', $userId)
                    ->orWhereHas('teamMembers', fn (Builder $q) => $q
                        ->where('user_id', $userId)
                        ->where('status', 'active')
                    );
            });
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organizer_id'] = auth()->id();

        static::validateUniqueOrganizer($data);

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        // organizer_id не меняется при редактировании
        unset($data['organizer_id']);

        static::validateUniqueOrganizer($data);

        return $data;
    }

    /**
     * Проверяет, что в переданных данных формы не более одного участника с ролью organizer.
     */
    private static function validateUniqueOrganizer(array $data): void
    {
        if (! isset($data['teamMembers'])) {
            return;
        }

        $organizerCount = collect($data['teamMembers'])
            ->filter(fn (array $member): bool => ($member['role'] ?? null) === TeamMemberRole::Organizer->value)
            ->count();

        if ($organizerCount > 1) {
            throw ValidationException::withMessages([
                'teamMembers' => 'В команде может быть только один капитан',
            ]);
        }
    }
}
