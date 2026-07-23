<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Teams;

use App\Actions\Team\InviteMemberFromOtherTeamAction;
use App\Enums\TeamMemberInvitationStatus;
use App\Enums\TeamMemberRole;
use App\Filament\User\Resources\Teams\Pages\ManageTeams;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamMemberInvitation;
use App\Models\User;
use App\Models\Yacht;
use App\Services\TeamRoleGuard;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

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

    /**
     * Бейдж на пункте меню «Мои команды» — число входящих приглашений
     * сменить главную команду, ожидающих ответа текущего пользователя.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::pendingInvitationsCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::pendingInvitationsCount() > 0 ? 'warning' : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Приглашения, ожидающие вашего ответа';
    }

    private static function pendingInvitationsCount(): int
    {
        return TeamMemberInvitation::query()
            ->where('user_id', auth()->id())
            ->where('status', TeamMemberInvitationStatus::Pending->value)
            ->count();
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
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'])
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
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'])
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
                                'user_id' => (string) auth()->id(),
                                'role' => TeamMemberRole::Organizer->value,
                                'status' => 'active',
                                'is_permanent' => true,
                            ],
                        ];
                    })
                    // Участников нельзя удалять — только исключать (статус «Покинул команду»),
                    // чтобы сохранить историю участия.
                    ->deletable(false)
                    // Если добавляют пользователя, который уже является постоянным участником
                    // другой команды, его нельзя добавить напрямую — вместо создания записи
                    // участия отправляется запрос на смену главной команды, который он должен
                    // подтвердить (см. incomingInvitationActions в ManageTeams).
                    ->mutateRelationshipDataBeforeCreateUsing(
                        fn (array $data, $component): ?array => static::interceptPermanentMemberInvitation($data, $component),
                    )
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
                                    // Постоянных участников других команд НЕ прячем: их можно выбрать,
                                    // но при сохранении вместо прямого добавления будет отправлен
                                    // запрос на смену главной команды (см. mutateRelationshipDataBeforeCreateUsing).

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
                            // К именам постоянных участников других команд добавляем пометку,
                            // чтобы капитан видел, что выбор запустит процедуру приглашения.
                            ->getOptionLabelFromRecordUsing(function (User $record, $livewire): string {
                                $teamName = static::permanentElsewhereMap(
                                    static::currentTeamId($livewire),
                                )[$record->getKey()] ?? null;

                                return $teamName !== null
                                    ? "{$record->name} — уже в команде «{$teamName}»"
                                    : (string) $record->name;
                            })
                            ->live()
                            ->searchable()
                            ->preload()
                            ->required(),
                        // Предупреждение появляется, когда выбранный пользователь уже является
                        // постоянным участником другой команды.
                        Placeholder::make('permanent_elsewhere_warning')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(fn (Get $get, $livewire): bool => isset(
                                static::permanentElsewhereMap(static::currentTeamId($livewire))[$get('user_id')],
                            ))
                            ->content(function (Get $get, $livewire): string {
                                $teamName = static::permanentElsewhereMap(
                                    static::currentTeamId($livewire),
                                )[$get('user_id')] ?? '';

                                return "Этот пользователь уже является постоянным участником команды «{$teamName}». "
                                    .'При сохранении ему будет отправлен запрос на смену главной команды; '
                                    .'он станет участником вашей команды только после подтверждения.';
                            }),
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
                                'left' => 'Покинул команду',
                            ])
                            ->default('active')
                            ->selectablePlaceholder(false)
                            ->required(),
                        // Признак «постоянный участник» пользователю не показываем —
                        // он управляется автоматически (создатель = постоянный, остальные
                        // становятся постоянными только через подтверждённое приглашение).
                        Hidden::make('is_permanent')
                            ->default(false),
                    ]),

                // Пользователи, которым отправлено приглашение сменить главную команду
                // и которые ещё не ответили. В составе участников они появятся только
                // после подтверждения, поэтому показываем их отдельно.
                Placeholder::make('pending_invitations')
                    ->label('Отправленные приглашения')
                    ->columnSpanFull()
                    ->visible(fn (?Team $record): bool => $record !== null
                        && TeamMemberInvitation::query()
                            ->where('team_id', $record->getKey())
                            ->where('status', TeamMemberInvitationStatus::Pending->value)
                            ->exists())
                    ->content(function (?Team $record): HtmlString {
                        $lines = TeamMemberInvitation::query()
                            ->with('user:id,name')
                            ->where('team_id', $record->getKey())
                            ->where('status', TeamMemberInvitationStatus::Pending->value)
                            ->latest()
                            ->get()
                            ->map(fn (TeamMemberInvitation $invitation): string => e(
                                ($invitation->user?->name ?? '—').' — ожидает подтверждения',
                            ));

                        return new HtmlString($lines->implode('<br>'));
                    }),

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
     * ID редактируемой команды из смонтированного табличного действия (EditAction), либо null.
     */
    private static function currentTeamId($livewire): ?string
    {
        if ($livewire && method_exists($livewire, 'getMountedTableActionRecord')) {
            return $livewire->getMountedTableActionRecord()?->getKey();
        }

        return null;
    }

    /**
     * Карта [user_id => название главной команды] для пользователей, являющихся
     * постоянными участниками какой-либо команды, кроме $exceptTeamId.
     *
     * Кэшируется в пределах запроса, чтобы не плодить запросы при отрисовке опций Select.
     *
     * @return array<string, string>
     */
    private static array $permanentElsewhereCache = [];

    private static function permanentElsewhereMap(?string $exceptTeamId): array
    {
        $key = $exceptTeamId ?? 'null';

        if (! array_key_exists($key, static::$permanentElsewhereCache)) {
            static::$permanentElsewhereCache[$key] = TeamMember::query()
                ->where('is_permanent', true)
                ->when($exceptTeamId, fn (Builder $q): Builder => $q->where('team_id', '!=', $exceptTeamId))
                ->with('team:id,name')
                ->get()
                ->keyBy('user_id')
                ->map(fn (TeamMember $member): string => $member->team?->name ?? '')
                ->all();
        }

        return static::$permanentElsewhereCache[$key];
    }

    /**
     * Перехватывает добавление нового участника в Repeater.
     *
     * Если выбранный пользователь уже является постоянным участником другой команды,
     * запись участия НЕ создаётся: вместо этого отправляется запрос на смену главной
     * команды, который пользователь должен подтвердить. Возврат null сообщает Repeater'у
     * пропустить создание строки (см. Repeater::saveRelationships).
     */
    private static function interceptPermanentMemberInvitation(array $data, $component): ?array
    {
        $userId = $data['user_id'] ?? null;

        if (! $userId) {
            return $data;
        }

        $team = $component->getRelationship()?->getParent();

        if (! $team instanceof Team) {
            return $data;
        }

        $isPermanentElsewhere = TeamMember::query()
            ->where('user_id', $userId)
            ->where('is_permanent', true)
            ->where('team_id', '!=', $team->getKey())
            ->exists();

        if (! $isPermanentElsewhere) {
            // Свободный пользователь (не постоянный ни в одной другой команде)
            // добавляется сразу как постоянный участник этой команды.
            $data['is_permanent'] = true;

            return $data;
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return null;
        }

        try {
            app(InviteMemberFromOtherTeamAction::class)->handle($team, $user, auth()->user());

            Notification::make()
                ->success()
                ->title('Запрос отправлен')
                ->body("Участнику «{$user->name}» отправлен запрос на смену главной команды. "
                    .'Он станет участником команды после подтверждения.')
                ->send();
        } catch (ValidationException $e) {
            Notification::make()
                ->warning()
                ->title('Не удалось отправить запрос')
                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->warning()
                ->title('Не удалось отправить запрос')
                ->body("Участника «{$user->name}» не удалось пригласить.")
                ->send();
        }

        // Строку участия не создаём — участник появится только после подтверждения.
        return null;
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
