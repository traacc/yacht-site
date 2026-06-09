<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegattaEntries;

use App\Filament\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\RegattaEntry;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegattaEntryResource extends Resource
{
    protected static ?string $model = RegattaEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'results';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'Заявка на регату';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки на регату';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('regatta', fn (Builder $q) => $q->whereIn(
                'regatta_status',
                [
                    \App\Enums\RegattaStatus::Closest->value,
                    \App\Enums\RegattaStatus::Upcoming->value,
                    \App\Enums\RegattaStatus::Active->value,
                ],
            ));
            /*
            ->orderBy(
                \App\Models\Regatta::select('date_start')
                    ->whereColumn('regattas.id', 'regatta_entries.regatta_id'),
                'asc'
            );
            */
    }

    public static function form(Schema $schema): Schema
    {
        $maxFiles      = (int) config('documents.max_files_per_type', 10);
        $acceptedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        return $schema
            ->components([
                Select::make('regatta_id')
                    ->label('Регата')
                    ->relationship(
                        'regatta',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('date_start'),
                    )
                    ->required()
                    ->columnSpanFull()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $docs = array_map(
                            fn (array $doc) => [
                                'doc_type'    => $doc['doc_type'],
                                'title'       => $doc['title'],
                                'is_required' => $doc['is_required'] ?? false,
                                'files'       => [],
                            ],
                            ManageRegattaEntries::getRequiredDocuments($state),
                        );
                        $set('required_documents', $docs);
                    }),
                Select::make('team_id')
                    ->label('Команда')
                    ->relationship('team', 'name')
                    ->required()
                    ->columnSpanFull()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $set('crew', static::buildCrewDefaults($state));
                    }),
                Select::make('yacht_id')
                    ->label('Яхта')
                    ->relationship('yacht', 'name')
                    ->columnSpanFull(),
                DatePicker::make('submitted_at')
                    ->label('Дата рассмотрения')
                    ->displayFormat('d.m.Y')
                    ->default(now())
                    ->required(),
                Select::make('status')
                    ->label('Статус')
                    ->options([
                        'pending'   => 'На рассмотрении',
                        'approved'  => 'Одобрена',
                        'rejected'  => 'Отклонена',
                        'withdrawn' => 'Отозвана',
                    ])
                    ->default('pending')
                    ->required()
                    ->columnSpanFull(),

                // ── Экипаж ────────────────────────────
                Repeater::make('crew')
                    ->label('Экипаж')
                    ->columnSpanFull()
                    ->addable(true)
                    //->deletable(false)
                    ->reorderable(false)
                    ->default([])
                    ->rules([
                        fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                            $captainCount = collect($value)->filter(fn (array $item): bool => ($item['role'] ?? '') === 'captain')->count();
                            if ($captainCount > 1) {
                                $fail('В экипаже может быть только один капитан.');
                            }
                        },
                    ])
                    /*
                    ->itemLabel(fn (array $state): string => ($state['member_name'] ?? 'Участник')
                        . (($state['is_captain'] ?? false) ? ' ⭐ Капитан' : '')
                        . ' — ' . match ($state['role'] ?? '') {
                        'main'              => 'Основной',
                        'reserve'           => 'Запасной',
                        'captain'           => 'Капитан',
                        //'not_participating' => 'Не участвует',
                        default             => '—',
                    })*/
                    ->schema([
                        Select::make('team_member_id')
                            ->label('Участник')
                            ->options(function (Get $get): array {
                                $teamId = $get('../../team_id');

                                if (! $teamId) {
                                    return [];
                                }

                                return TeamMember::query()
                                    ->where('team_id', $teamId)
                                    ->where('status', 'active')
                                    ->with('user')
                                    ->get()
                                    ->mapWithKeys(fn (TeamMember $member): array => [
                                        $member->id => $member->user?->name ?? 'Неизвестный',
                                    ])
                                    ->all();
                            })
                            ->required()
                            ->live()
                            ->searchable()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (! $state) {
                                    $set('member_name', '');
                                    return;
                                }

                                $member = TeamMember::with('user')->find($state);
                                $set('member_name', $member?->user?->name ?? '');
                            }),
                        Hidden::make('member_name'),
                        Hidden::make('is_captain')
                            ->default(false),
                        Select::make('role')
                            ->label('Роль')
                            ->options([
                                'main'              => 'Основной',
                                'reserve'           => 'Запасной',
                                'captain'           => 'Капитан',
                                //'not_participating' => 'Не участвует',
                            ])
                            ->required(),
                    ])
                    ->columns(3)
                    ->itemLabel(null)
                    ->deleteAction(
                        fn (Action $action) => $action
                            ->icon('heroicon-m-x-mark')
                            ->color('danger')
                            ->iconButton()
                    )->extraAttributes(['class' => 'hide-repeater-header-label'])
                    ,

                // ── Документы заявки ──────────────────
                Repeater::make('required_documents')
                    ->label('Документы')
                    ->columnSpanFull()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->default(fn (Get $get) => array_map(
                        fn (array $doc) => [
                            'doc_type'    => $doc['doc_type'],
                            'title'       => $doc['title'],
                            'is_required' => $doc['is_required'] ?? false,
                            'files'       => [],
                        ],
                        ManageRegattaEntries::getRequiredDocuments($get('regatta_id')),
                    ))
                    ->columns(1)
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->rules([
                        function (Get $get): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                $requiredDocs = ManageRegattaEntries::getRequiredDocuments($get('regatta_id'));
                                $missing = [];

                                foreach ($requiredDocs as $required) {
                                    if (! ($required['is_required'] ?? false)) {
                                        continue;
                                    }

                                    $docType = $required['doc_type'];
                                    $item = collect((array) $value)->first(
                                        fn (array $doc): bool => ($doc['doc_type'] ?? '') === $docType
                                    );

                                    $files = array_filter((array) ($item['files'] ?? []));

                                    if ($item === null || $files === []) {
                                        $missing[] = $required['title'];
                                    }
                                }

                                if ($missing !== []) {
                                    $fail('Загрузите следующие обязательные документы: ' . implode(', ', $missing) . '.');
                                }
                            };
                        },
                    ])
                    ->schema([
                        Hidden::make('doc_type'),
                        Hidden::make('title'),
                        Hidden::make('is_required'),
                        FileUpload::make('files')
                            ->label('Файлы')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->directory('documents')
                            ->disk('public')
                            ->acceptedFileTypes($acceptedTypes)
                            ->maxSize(20480)
                            ->maxFiles($maxFiles)
                            ->downloadable()
                            ->helperText('Можно загрузить до ' . $maxFiles . ' файлов'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('team.name')
                    ->label('Команда')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('captain')
                    ->label('Капитан')
                    ->state(fn (RegattaEntry $record): string => $record->crew()
                        ->where('role', 'captain')
                        ->first()?->teamMember?->user?->name ?? '—'
                    )
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('crew', function (Builder $q) use ($search): void {
                            $q->where('role', 'captain')
                                ->whereHas('teamMember.user', function (Builder $q) use ($search): void {
                                    $q->where('name', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\User::select('name')
                                ->join('team_members', 'team_members.user_id', '=', 'users.id')
                                ->join('regatta_entry_crew', function ($join): void {
                                    $join->on('regatta_entry_crew.team_member_id', '=', 'team_members.id')
                                        ->where('regatta_entry_crew.role', '=', 'captain');
                                })
                                ->whereColumn('regatta_entry_crew.regatta_entry_id', 'regatta_entries.id')
                                ->limit(1),
                            $direction
                        );
                    })->toggleable(),
                TextColumn::make('crew')
                    ->label('Экипаж')
                    ->state(fn (RegattaEntry $record): string => (string) $record->crew()
                        ->whereIn('role', ['main', 'reserve', 'captain'])
                        ->count()
                    )
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount([
                            'crew as crew_count' => fn (Builder $q) => $q->whereIn('role', ['main', 'reserve', 'captain']),
                        ])->orderBy('crew_count', $direction);
                    })->toggleable(),
                /*
                TextColumn::make('submitted_at')
                    ->label('Дата рассмотрения')
                    ->dateTime()->dateTime('d M Y')
                    ->sortable(),
                */
                TextColumn::make('status')
                    ->label('Статус')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
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
                    })->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Зарегистрирован')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Обновлен')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Записей пока нет')
            ->filters([])
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать заявку на регату')
                    ->mountUsing(function (Schema $form, RegattaEntry $record): void {
                        $sync = app(\App\Actions\Document\SyncDocumentFilesAction::class);
                        $requiredDocs = ManageRegattaEntries::getRequiredDocuments($record->regatta_id);

                        $data = $record->toArray();
                        $data['required_documents'] = $sync->load($record, $requiredDocs);
                        $data['crew'] = static::loadCrew($record);

                        $form->fill($data);
                    })
                    ->using(function (RegattaEntry $record, array $data): RegattaEntry {
                        $requiredDocs = $data['required_documents'] ?? [];
                        $crew = $data['crew'] ?? [];
                        unset($data['required_documents'], $data['crew']);

                        // Проверка дубликата: та же команда уже подала заявку на эту регату?
                        $exists = RegattaEntry::where('regatta_id', $data['regatta_id'])
                            ->where('team_id', $data['team_id'])
                            ->where('id', '!=', $record->id)
                            ->exists();

                        if ($exists) {
                            \Filament\Notifications\Notification::make()
                                ->title('Заявка уже существует')
                                ->body('Эта команда уже подала заявку на выбранную регату.')
                                ->danger()
                                ->send();

                            $this->halt();
                        }

                        $record->update($data);

                        app(\App\Actions\Document\SyncDocumentFilesAction::class)
                            ->execute($record, $requiredDocs);

                        static::syncCrew($record, $crew);

                        return $record;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegattaEntries::route('/'),
        ];
    }

    // ──────────────────────────────────────────────
    // Crew helpers
    // ──────────────────────────────────────────────

    /**
     * Строит дефолтный список экипажа для формы создания.
     *
     * @return array<int, array{team_member_id: string, member_name: string, role: string}>
     */
    public static function buildCrewDefaults(?string $teamId): array
    {
        if (! $teamId) {
            return [];
        }

        return TeamMember::query()
            ->where('team_id', $teamId)
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->map(fn (TeamMember $member): array => [
                'team_member_id' => $member->id,
                'member_name'    => $member->user?->name ?? 'Неизвестный',
                'is_captain'     => false,
                'role'           => 'main',
            ])
            ->all();
    }

    /**
     * Загружает существующий экипаж для формы редактирования.
     * Добавляет новых активных участников команды, которых ещё нет в заявке.
     *
     * @return array<int, array{team_member_id: string, member_name: string, role: string}>
     */
    public static function loadCrew(RegattaEntry $record): array
    {
        $team = \App\Models\Team::find($record->team_id);
        $organizerId = $team?->organizer_id;

        $existing = $record->crew()
            ->with('teamMember.user')
            ->get()
            ->map(fn (\App\Models\RegattaEntryCrew $crew): array => [
                'team_member_id' => $crew->team_member_id,
                'member_name'    => $crew->teamMember?->user?->name ?? 'Неизвестный',
                'is_captain'     => $crew->role === 'captain',
                'role'           => $crew->role,
            ]);

        $existingMemberIds = $existing->pluck('team_member_id')->all();

        /*
        $newMembers = $team
            ?->members()
            ->wherePivot('status', 'active')
            ->get()
            ->filter(fn (\App\Models\User $user): bool => ! in_array($user->pivot->id, $existingMemberIds, true))
            ->map(fn (\App\Models\User $user): array => [
                'team_member_id' => $user->pivot->id,
                'member_name'    => $user->name,
                'is_captain'     => $user->id === $organizerId,
                'role'           => 'not_participating',
            ])
            ?? collect();
        */
        //return $existing->concat($newMembers)->all();
        return $existing->all();
    }

    /**
     * Синхронизирует экипаж после сохранения формы.
     *
     * @param array<int, array{team_member_id: string, role: string}> $crew
     */
    public static function syncCrew(RegattaEntry $record, array $crew): void
    {
        $incomingIds = collect($crew)->pluck('team_member_id')->filter()->toArray();

        $record->crew()->whereNotIn('team_member_id', $incomingIds)->delete();

        foreach ($crew as $item) {
            if (empty($item['team_member_id'])) {
                continue;
            }

            $record->crew()->updateOrCreate(
                ['team_member_id' => $item['team_member_id']],
                ['role'           => $item['role'] ?? 'main'],
            );
        }
    }

    /**
     * Определяет читаемую метку для документа в Repeater.
     */
    public static function resolveDocumentLabel(array $state): ?string
    {
        $docType = $state['doc_type'] ?? null;

        if ($docType === null) {
            return $state['title'] ?? null;
        }

        $model = \App\Models\YachtDocumentType::cachedAll()
            ->first(fn (\App\Models\YachtDocumentType $t) => $t->key === $docType);

        $label = $model?->label ?? ($state['title'] ?? null);
        $isRequired = (bool) ($state['is_required'] ?? false);

        return $label ? ($label . ($isRequired ? ' *' : ' (необязательный)')) : null;
    }
}
