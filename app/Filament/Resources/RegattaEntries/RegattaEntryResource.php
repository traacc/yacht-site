<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegattaEntries;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Enums\RegattaEntrySource;
use App\Enums\RegattaStatus;
use App\Enums\TeamMemberRole;
use App\Filament\Concerns\ResolvesCrewMembers;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Concerns\ScopesToOwnedRegattas;
use App\Filament\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Models\RegattaEntry;
use App\Models\RegattaEntryCrew;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\YachtDocumentType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RegattaEntryResource extends Resource
{
    use ResolvesCrewMembers;
    use RestrictsAccessByRole;
    use ScopesToOwnedRegattas;

    protected static ?string $model = RegattaEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'results';

    protected static ?int $navigationSort = 3;

    protected static string|UnitEnum|null $navigationGroup = 'Регаты';

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
        return static::scopeToOwnedRegattas(parent::getEloquentQuery())
            // Заявитель собирается из команды, автора и экипажа — грузим пачкой,
            // иначе колонки «Заявитель» и «Рулевой» дают N+1.
            ->with(['team', 'applicant', 'crew.user', 'crew.teamMember.user'])
            ->whereHas('regatta', fn (Builder $q) => $q->whereIn(
                'regatta_status',
                [
                    RegattaStatus::Closest->value,
                    RegattaStatus::Upcoming->value,
                    RegattaStatus::Active->value,
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

    /**
     * Строит дефолтный набор элементов Repeater'а документов для заявки.
     *
     * Используется и при заполнении формы дефолтами, и при подстановке
     * регаты (afterStateUpdated / fillForm экшена).
     *
     * @return array<int, array{doc_type: string, title: string, is_required: bool, files: array}>
     */
    public static function defaultRequiredDocuments(?string $regattaId): array
    {
        return array_map(
            fn (array $doc): array => [
                'doc_type' => $doc['doc_type'],
                'title' => $doc['title'],
                'is_required' => $doc['is_required'] ?? false,
                'files' => [],
            ],
            ManageRegattaEntries::getRequiredDocuments($regattaId),
        );
    }

    public static function form(Schema $schema): Schema
    {
        $maxFiles = (int) config('documents.max_files_per_type', 10);
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
                        modifyQueryUsing: fn (Builder $query) => $query->visibleForUser()->orderBy('date_start'),
                    )
                    ->required()
                    ->columnSpanFull()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $set('required_documents', static::defaultRequiredDocuments($state));
                    }),
                Select::make('team_id')
                    ->label('Команда')
                    ->relationship('team', 'name')
                    ->required()
                    ->columnSpanFull()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        $set('crew', static::buildCrewDefaults($state, $get('regatta_id')));
                    }),
                Select::make('yacht_id')
                    ->label('Яхта')
                    ->relationship('yacht', 'name')
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('submitted_at')
                    ->label('Дата рассмотрения')
                    ->displayFormat('d.m.Y')
                    ->native(false)
                    ->default(now())
                    ->required(),
                Select::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'На рассмотрении',
                        'approved' => 'Одобрена',
                        'rejected' => 'Отклонена',
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
                    // ->deletable(false)
                    ->reorderable(false)
                    ->default([])
                    ->rules([
                        fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                            $crew = collect($value);

                            if ($crew->isEmpty()) {
                                return;
                            }

                            $captainCount = $crew->filter(fn (array $item): bool => ($item['role'] ?? '') === 'captain')->count();

                            if ($captainCount === 0) {
                                $fail('В экипаже должен быть Рулевой.');
                            } elseif ($captainCount > 1) {
                                $fail('В экипаже может быть только один Рулевой.');
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
                        Hidden::make('id'),
                        // Участник сборного экипажа: команды за ним нет, выбирать
                        // из членств нечего — показываем имя из строки экипажа.
                        Placeholder::make('crew_member_name')
                            ->label('Участник')
                            ->content(fn (Get $get): string => (string) ($get('member_name') ?: '—'))
                            ->visible(fn (Get $get): bool => static::isCrewGuestRow($get)),
                        Select::make('team_member_id')
                            ->label('Участник')
                            ->options(fn (Get $get, ?RegattaEntry $record): array => static::crewMemberOptions(
                                regattaId: $get('../../regatta_id'),
                                record: $record,
                            ))
                            ->getSearchResultsUsing(fn (string $search, Get $get, ?RegattaEntry $record): array => static::crewMemberSearchResults(
                                search: $search,
                                regattaId: $get('../../regatta_id'),
                                record: $record,
                            ))
                            ->getOptionLabelUsing(fn (?string $value): ?string => static::crewMemberOptionLabel($value))
                            ->visible(fn (Get $get): bool => ! static::isCrewGuestRow($get))
                            ->required(fn (Get $get): bool => ! static::isCrewGuestRow($get))
                            ->live()
                            ->searchable()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $set('member_name', static::crewMemberName($state));
                            }),
                        Hidden::make('member_name'),
                        Hidden::make('is_captain')
                            ->default(false),
                        Select::make('role')
                            ->label('Роль')
                            ->options([
                                'main' => 'Основной',
                                'reserve' => 'Запасной',
                                'captain' => 'Рулевой',
                                // 'not_participating' => 'Не участвует',
                            ])
                            ->required(),
                    ])
                    ->columns(3)
                    ->itemLabel(null)
                    ->addActionLabel('Добавить члена экипажа')
                    ->deleteAction(
                        fn (Action $action) => $action
                            ->icon('heroicon-m-x-mark')
                            ->color('danger')
                            ->iconButton()
                    )->extraAttributes(['class' => 'hide-repeater-header-label']),

                // ── Документы заявки ──────────────────
                Repeater::make('required_documents')
                    ->label('Документы')
                    ->columnSpanFull()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->default(fn (Get $get) => static::defaultRequiredDocuments($get('regatta_id')))
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
                                    $fail('Загрузите следующие обязательные документы: '.implode(', ', $missing).'.');
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
                            ->helperText('Можно загрузить до '.$maxFiles.' файлов'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Подсвечиваем жёлтым заявки с неполными документами
            ->recordClasses(fn (RegattaEntry $record): ?string => $record->hasMissingDocuments()
                ? 'entry-incomplete-docs-row'
                : null)
            ->columns([
                TextColumn::make('team.name')
                    ->label('Заявитель')
                    // У индивидуальных и сборных заявок команды нет — показываем,
                    // кто подал заявку и как с ним связаться, иначе строка пустая.
                    ->state(fn (RegattaEntry $record): string => $record->participantName())
                    ->description(fn (RegattaEntry $record): ?string => $record->team
                        ? null
                        : trim($record->participationSummary()
                            .($record->applicantContacts() ? ' · '.$record->applicantContacts() : '')))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(fn (Builder $q) => $q
                            ->whereHas('team', fn (Builder $t) => $t->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('applicant', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('crew', fn (Builder $c) => $c->where('full_name', 'like', "%{$search}%"))))
                    ->sortable(),
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('captain')
                    ->label('Рулевой')
                    ->state(fn (RegattaEntry $record): string => $record->captainCrew()?->displayName() ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('crew', function (Builder $q) use ($search): void {
                            $q->where('role', 'captain')
                                ->where(function (Builder $q) use ($search): void {
                                    $q->where('full_name', 'like', "%{$search}%")
                                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))
                                        ->orWhereHas('teamMember.user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"));
                                });
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            User::select('name')
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
                    ->dateTime()->dateTime('d.m.Y')
                    ->sortable(),
                */
                TextColumn::make('status')
                    ->label('Статус')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
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
                    })->toggleable(),
                IconColumn::make('documents_complete')
                    ->label('Документы')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (RegattaEntry $record): ?string => $record->hasMissingDocuments()
                        ? 'Поданы не все обязательные документы'
                        : null)
                    ->sortable()
                    ->toggleable(),
                ToggleColumn::make('fee_paid')
                    ->label('Сбор оплачен')
                    ->visible(fn (): bool => RegattaEntry::query()
                        ->whereHas('regatta', fn ($q) => $q->where('entry_fee_required', true))
                        ->exists())
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('source')
                    ->label('Источник')
                    ->badge()
                    ->formatStateUsing(fn (RegattaEntrySource $state): string => $state->label())
                    ->sortable()
                    ->toggleable(),
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
            ->filters([
                SelectFilter::make('regatta_id')
                    ->label('Регата')
                    ->relationship(
                        'regatta',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->visibleForUser()
                            ->whereIn('regatta_status', [
                                RegattaStatus::Upcoming,
                                RegattaStatus::Closest,
                                RegattaStatus::Active,
                            ])
                            ->orderBy('date_start'),
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('source')
                    ->label('Источник')
                    ->options(
                        collect(RegattaEntrySource::cases())
                            ->reject(fn ($case) => $case === RegattaEntrySource::Unknown)
                            ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать заявку на регату')
                    ->mountUsing(function (Schema $form, RegattaEntry $record): void {
                        $sync = app(SyncDocumentFilesAction::class);
                        $requiredDocs = ManageRegattaEntries::getRequiredDocuments($record->regatta_id);

                        $data = $record->toArray();
                        $data['required_documents'] = $sync->load($record, $requiredDocs);
                        $data['crew'] = static::loadCrew($record);

                        $form->fill($data);
                    })
                    ->using(function (RegattaEntry $record, array $data, Action $action): RegattaEntry {
                        $requiredDocs = $data['required_documents'] ?? [];
                        $crew = $data['crew'] ?? [];
                        unset($data['required_documents'], $data['crew']);

                        // Проверка дубликата: та же команда уже подала заявку на эту регату?
                        $conflict = RegattaEntry::where('regatta_id', $data['regatta_id'])
                            ->where('team_id', $data['team_id'])
                            ->where('id', '!=', $record->id)
                            ->first();

                        if ($conflict) {
                            // Сохраняем данные формы, чтобы кнопка «Перезаписать» могла их применить.
                            session()->put("regatta_entry_overwrite:{$record->id}", [
                                'data' => $data,
                                'docs' => $requiredDocs,
                                'crew' => $crew,
                                'conflict_id' => $conflict->id,
                            ]);

                            Notification::make()
                                ->title('Заявка уже существует')
                                ->body('Эта команда уже подала заявку на эту регату. Можно перезаписать существующую заявку данными из формы.')
                                ->danger()
                                ->persistent()
                                ->actions([
                                    Action::make('overwrite')
                                        ->label('Перезаписать')
                                        ->color('danger')
                                        ->button()
                                        ->close()
                                        ->dispatch('overwriteRegattaEntry', ['recordId' => $record->id]),
                                ])
                                ->send();

                            $action->halt();
                        }

                        $record->update($data);

                        app(SyncDocumentFilesAction::class)
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
     * Исключает участников команды, которые уже заявлены в экипаже
     * любой заявки на эту же регату.
     *
     * @return array<int, array{team_member_id: string, member_name: string, role: string}>
     */
    public static function buildCrewDefaults(?string $teamId, ?string $regattaId = null): array
    {
        if (! $teamId) {
            return [];
        }

        $query = TeamMember::query()
            ->where('team_id', $teamId)
            ->where('status', 'active');

        if ($regattaId) {
            $participatingIds = RegattaEntryCrew::query()
                ->whereHas('regattaEntry', fn (Builder $q) => $q->where('regatta_id', $regattaId))
                ->pluck('team_member_id');

            $query->whereNotIn('id', $participatingIds);
        }

        return $query
            ->with('user')
            ->get()
            ->map(function (TeamMember $member): array {
                $isCaptain = $member->role === TeamMemberRole::Organizer->value;

                return [
                    'team_member_id' => $member->id,
                    'member_name' => $member->user?->name ?? 'Неизвестный',
                    'is_captain' => $isCaptain,
                    'role' => $isCaptain ? 'captain' : 'main',
                ];
            })
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
        $team = Team::find($record->team_id);
        $organizerId = $team?->organizer_id;

        $existing = $record->crew()
            ->with(['teamMember.user', 'user'])
            ->get()
            ->map(fn (RegattaEntryCrew $crew): array => static::crewRowData($crew));

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
        // return $existing->concat($newMembers)->all();
        return $existing->all();
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

        $model = YachtDocumentType::cachedAll()
            ->first(fn (YachtDocumentType $t) => $t->key === $docType);

        $label = $model?->label ?? ($state['title'] ?? null);
        $isRequired = (bool) ($state['is_required'] ?? false);

        return $label ? ($label.($isRequired ? ' *' : ' (необязательный)')) : null;
    }
}
