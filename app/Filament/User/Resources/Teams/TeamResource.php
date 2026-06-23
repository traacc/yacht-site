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
use Filament\Forms\Components\Toggle;
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
                    ->required()
                    ->unique(table: 'teams', column: 'name', ignorable: fn (?Team $record) => $record)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Описание')
                    ->placeholder('Описание команды')
                    ->columnSpanFull(),

                Select::make('default_yacht_id')
                    ->label('Яхта по умолчанию')
                    ->placeholder('Выберите яхту')
                    ->options(fn () => Yacht::where('approval_status', 'approved')
                        ->where('user_id', auth()->id())
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->default(function (): ?string {
                        $yachts = Yacht::where('approval_status', 'approved')
                            ->where('user_id', auth()->id())
                            ->get();

                        return $yachts->count() === 1 ? $yachts->first()->id : null;
                    })
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
                    // При создании команды первым участником автоматически добавляется
                    // текущий пользователь с ролью Капитана (Organizer).
                    ->default(function (string $operation): array {
                        if ($operation !== 'create') {
                            return [];
                        }

                        return [
                            [
                                'user_id'      => (string) auth()->id(),
                                'role'         => TeamMemberRole::Organizer->value,
                                'status'       => 'active',
                                'is_permanent' => true,
                            ],
                        ];
                    })
                    // Участников нельзя удалять — только исключать (статус «Покинул команду»),
                    // чтобы сохранить историю участия.
                    ->deletable(false)
                    ->rules([
                        fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                            if (! is_array($value)) {
                                return;
                            }

                            $organizers = collect($value)
                                ->filter(fn (array $member): bool => ($member['role'] ?? null) === TeamMemberRole::Organizer->value);

                            $organizerCount = $organizers->count();

                            if ($organizerCount === 0) {
                                $fail('Необходимо назначить капитана команды');
                            }

                            if ($organizerCount > 1) {
                                $fail('В команде может быть только один Капитан');
                            }

                            // Капитана нельзя исключать из команды.
                            if ($organizers->contains(fn (array $member): bool => ($member['status'] ?? null) === 'left')) {
                                $fail('Капитана нельзя исключить из команды');
                            }

                            // Создатель команды обязан быть в составе участников.
                            $organizerId = auth()->id();
                            $organizerIncluded = collect($value)
                                ->contains(fn (array $member): bool => (string) ($member['user_id'] ?? '') === (string) $organizerId);

                            if (! $organizerIncluded) {
                                $fail('Вы должны добавить себя в состав команды');
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

                                    // Показываем пользователей, не являющихся постоянными участниками других команд,
                                    // плюс самого создателя команды (он обязан быть в составе).
                                    $query->where(function (Builder $q) use ($currentTeamId) {
                                        $q->withoutPermanentInOtherTeams($currentTeamId)
                                            ->orWhere('id', auth()->id());
                                    });

                                    // Исключаем пользователей, уже добавленных в других строках Repeater'а.
                                    // Важно: применяем как отдельный AND на верхнем уровне запроса,
                                    // иначе из-за приоритета AND над OR исключение затронет только
                                    // последнюю ветку OR-группы выше.
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
                                'active' => 'Активен',
                                'left'   => 'Покинул команду',
                            ])
                            ->default('active')
                            ->selectablePlaceholder(false)
                            ->required(),
                        Toggle::make('is_permanent')
                            ->label('Постоянный участник')
                            ->default(false),
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
                    ->width('150px')
                    ->searchable()->label('Команда'),
                TextColumn::make('organizer.name')
                    ->width('50px')
                    ->searchable()->label('Капитан'),
                TextColumn::make('active_members_count')
                    ->label('Участники')
                    ->width('50px')
                    ->sortable(),
                // Роль текущего пользователя в команде
                /*
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
                */
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
                    ->visible(fn (Team $record): bool => auth()->user()?->can('archiveTeam', $record) ?? false)
                    ->modalHeading('Удалить команду')
                    ->hidden(false) // показывать и для уже удалённых записей
                    ->using(fn (Team $record): bool => (bool) $record->forceDelete()),
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

        $organizers = collect($data['teamMembers'])
            ->filter(fn (array $member): bool => ($member['role'] ?? null) === TeamMemberRole::Organizer->value);

        $organizerCount = $organizers->count();

        if ($organizerCount === 0) {
            throw ValidationException::withMessages([
                'teamMembers' => 'Необходимо назначить капитана команды',
            ]);
        }

        if ($organizerCount > 1) {
            throw ValidationException::withMessages([
                'teamMembers' => 'В команде может быть только один капитан',
            ]);
        }

        if ($organizers->contains(fn (array $member): bool => ($member['status'] ?? null) === 'left')) {
            throw ValidationException::withMessages([
                'teamMembers' => 'Капитана нельзя исключить из команды',
            ]);
        }
    }
}
