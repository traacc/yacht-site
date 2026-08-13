<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArchivedRegattaEntries;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Enums\RegattaStatus;
use App\Filament\Concerns\ResolvesCrewMembers;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Concerns\ScopesToOwnedRegattas;
use App\Filament\Resources\ArchivedRegattaEntries\Pages\ManageArchivedRegattaEntries;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\RegattaEntryCrew;
use App\Models\Team;
use App\Models\User;
use App\Models\YachtDocumentType;
use App\Services\RatingCalculator;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ArchivedRegattaEntryResource extends Resource
{
    use ResolvesCrewMembers;
    use RestrictsAccessByRole;
    use ScopesToOwnedRegattas;

    protected static ?string $model = RegattaEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'results';

    protected static ?string $navigationLabel = 'Архивные заявки';

    protected static ?int $navigationSort = 5;

    protected static string|UnitEnum|null $navigationGroup = 'Регаты';

    public static function getModelLabel(): string
    {
        return 'Архивная заявка на регату';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Архивные заявки на регату';
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeToOwnedRegattas(parent::getEloquentQuery())
            ->whereHas('regatta', fn (Builder $q) => $q->whereIn(
                'regatta_status',
                [
                    RegattaStatus::Finished->value,
                    RegattaStatus::Cancelled->value,
                    RegattaStatus::Postponed->value,
                ],
            ))
            ->orderByDesc(
                Regatta::select('date_start')
                    ->whereColumn('regattas.id', 'regatta_entries.regatta_id'),
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
                        $docs = array_map(
                            fn (array $doc) => [
                                'doc_type' => $doc['doc_type'],
                                'title' => $doc['title'],
                                'is_required' => $doc['is_required'] ?? false,
                                'files' => [],
                            ],
                            ManageArchivedRegattaEntries::getRequiredDocuments($state),
                        );
                        $set('required_documents', $docs);
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
                    ->columnSpanFull(),
                DatePicker::make('submitted_at')
                    ->label('Дата рассмотрения')
                    ->displayFormat('d.m.Y')
                    ->native(false)
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
                    ->deletable(true)
                    ->reorderable(false)
                    ->default([])
                    ->columns(1)
                    ->rules([
                        fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                            $captainCount = collect($value)->filter(fn (array $item): bool => ($item['role'] ?? '') === 'captain')->count();
                            if ($captainCount > 1) {
                                $fail('В экипаже может быть только один Рулевой.');
                            }
                        },
                    ])
                    ->itemLabel(fn (array $state): string => ($state['member_name'] ?? 'Участник')
                        .(($state['is_captain'] ?? false) ? ' ⭐ Рулевой' : '')
                        .' — '.match ($state['role'] ?? '') {
                            'main' => 'Основной',
                            'reserve' => 'Запасной',
                            'captain' => 'Рулевой',
                            // 'not_participating' => 'Не участвует',
                            default => '—',
                        })
                    ->schema([
                        Hidden::make('id'),
                        // Сборный экипаж: участник заявлен без команды, выбирать не из чего.
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
                    ])->columns(2)->inset()->addActionLabel('Добавить члена экипажа'),

                // ── Документы заявки ──────────────────
                Repeater::make('required_documents')
                    ->label('Документы')
                    ->columnSpanFull()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->default(fn (Get $get) => array_map(
                        fn (array $doc) => [
                            'doc_type' => $doc['doc_type'],
                            'title' => $doc['title'],
                            'is_required' => $doc['is_required'] ?? false,
                            'files' => [],
                        ],
                        ManageArchivedRegattaEntries::getRequiredDocuments($get('regatta_id')),
                    ))
                    ->columns(1)
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->rules([
                        function (Get $get): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                $requiredDocs = ManageArchivedRegattaEntries::getRequiredDocuments($get('regatta_id'));
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
            ->columns([
                TextColumn::make('team.name')
                    ->label('Заявитель')
                    // Индивидуальные и сборные заявки идут без команды.
                    ->state(fn (RegattaEntry $record): string => $record->participantName())
                    ->description(fn (RegattaEntry $record): ?string => $record->team
                        ? null
                        : $record->participation_kind->getLabel())
                    ->searchable(),
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable(),
                TextColumn::make('regatta.regatta_status')
                    ->label('Статус регаты')
                    ->badge()
                    ->sortable()->toggleable(),
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
                    })->toggleable(),
                TextColumn::make('crew')
                    ->label('Экипаж')
                    ->state(fn (RegattaEntry $record): string => (string) $record->crew()
                        ->whereIn('role', ['main', 'reserve', 'captain'])
                        ->count()
                    )->toggleable(),
                /*
                TextColumn::make('submitted_at')
                    ->label('Дата рассмотрения')
                    ->dateTime()->dateTime('d.m.Y'),
                */
                TextColumn::make('status')
                    ->label('Статус')
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
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Архивных записей пока нет')
            ->filters([])
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать архивную заявку')
                    ->mountUsing(function (Schema $form, RegattaEntry $record): void {
                        $sync = app(SyncDocumentFilesAction::class);
                        $requiredDocs = ManageArchivedRegattaEntries::getRequiredDocuments($record->regatta_id);

                        $data = $record->toArray();
                        $data['required_documents'] = $sync->load($record, $requiredDocs);
                        $data['crew'] = static::loadCrew($record);

                        $form->fill($data);
                    })
                    ->using(function (RegattaEntry $record, array $data): RegattaEntry {
                        $requiredDocs = $data['required_documents'] ?? [];
                        $crew = $data['crew'] ?? [];
                        unset($data['required_documents'], $data['crew']);

                        $record->update($data);

                        app(SyncDocumentFilesAction::class)
                            ->execute($record, $requiredDocs);

                        static::syncCrew($record, $crew);

                        static::recalculateSeasonRatings($record);

                        return $record;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageArchivedRegattaEntries::route('/'),
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
    public static function buildCrewDefaults(?string $teamId, ?string $regattaId = null): array
    {
        if ($teamId === null) {
            return [];
        }

        $team = Team::find($teamId);
        if (! $team) {
            return [];
        }

        $members = $team->members()
            ->wherePivot('status', 'active');

        if ($regattaId) {
            $participatingIds = RegattaEntryCrew::query()
                ->whereHas('regattaEntry', fn (Builder $q) => $q->where('regatta_id', $regattaId))
                ->pluck('team_member_id');

            $members->wherePivotNotIn('id', $participatingIds->all());
        }

        return $members
            ->get()
            ->map(fn (User $user): array => [
                'team_member_id' => $user->pivot->id,
                'member_name' => $user->name,
                'is_captain' => false,
                'role' => 'main',
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
                'is_captain'     => false,
                'role'           => 'not_participating',
            ])
            ?? collect();
        */
        // return $existing->concat($newMembers)->all();

        return $existing->all();
    }

    /**
     * Пересчитывает личный и командный рейтинг сезона после правки архивной заявки.
     *
     * Состав экипажа, команда и статус заявки участвуют в расчёте рейтингов
     * (RatingCalculator::crewByRegattaTeam), поэтому их изменение должно
     * отражаться в рейтингах. Пересчёт читает уже готовые итоговые строки
     * (regatta_result_items) и свежий состав экипажа.
     *
     * Важно: итоговые строки результатов (в т.ч. «замороженные» с team_id = null)
     * намеренно НЕ пересчитываются — их total_points и место историчны, а
     * recomputeItemTotals обнулил бы замороженные строки и сдвинул число лодок.
     */
    public static function recalculateSeasonRatings(RegattaEntry $record): void
    {
        $record->loadMissing('regatta.season');

        if ($record->regatta?->season === null) {
            return;
        }

        app(RatingCalculator::class)
            ->recalculateAfterRegatta($record->regatta);
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
