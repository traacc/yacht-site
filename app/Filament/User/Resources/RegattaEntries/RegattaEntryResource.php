<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\RegattaEntries;

use App\Enums\SystemRole;
use App\Filament\User\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Models\RegattaEntry;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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

    protected static string|BackedEnum|null $navigationIcon = 'cup';

    public static function getModelLabel(): string
    {
        return 'Заявка на соревнование';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки на соревнования';
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->whereHas('team', fn (Builder $q) => $q->visibleForUser($user))
            ->whereHas('regatta', fn (Builder $q) => $q->whereIn(
                'regatta_status',
                [
                    \App\Enums\RegattaStatus::Closest->value,
                    \App\Enums\RegattaStatus::Upcoming->value,
                    \App\Enums\RegattaStatus::Active->value,
                ],
            ))
            ->orderBy(
                \App\Models\Regatta::select('date_start')
                    ->whereColumn('regattas.id', 'regatta_entries.regatta_id'),
                'asc'
            );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('regatta_id')
                    ->relationship(
                        'regatta',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('date_end', '>=', now()->toDateString())
                            ->orderBy('date_start'),
                    )
                    ->label('Регата')
                    ->required()
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
                    ->relationship('team', 'name', modifyQueryUsing: function (Builder $query) {
                        /** @var User $user */
                        $user = auth()->user();

                        if ($user->system_role !== SystemRole::User) {
                            return; // staff видят все команды
                        }

                        $query->where(function (Builder $q) use ($user) {
                            $q->where('organizer_id', $user->id)
                                ->orWhereHas('members', fn (Builder $q) => $q->where('user_id', $user->id));
                        });
                    })
                    ->label('Команда')
                    ->required()
                    ->live(),
                Select::make('yacht_id')
                    ->label('Яхта')
                    ->options(fn (Get $get, ?\App\Models\RegattaEntry $record = null) => \App\Models\Yacht::query()
                        //->where('user_id', auth()->id())
                        ->when(
                            $get('regatta_id'),
                            fn (Builder $query, string $regattaId) => $query->whereDoesntHave(
                                'regattaEntries',
                                fn (Builder $q) => $q->where('regatta_id', $regattaId)
                                    ->when($record, fn (Builder $q) => $q->where('id', '!=', $record->id)),
                            ),
                        )
                        ->pluck('name', 'id'),
                    )
                    ->live()
                    ->searchable(),

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
                                $fail('В экипаже может быть только один капитан.');
                            }
                        },
                    ])
                    ->itemLabel(fn (array $state): string => ($state['member_name'] ?? 'Участник')
                        . (($state['is_captain'] ?? false) ? ' ⭐ Капитан' : '')
                        . ' — ' . match ($state['role'] ?? '') {
                        'main'              => 'Основной',
                        'reserve'           => 'Запасной',
                        'captain'           => 'Капитан',
                        'not_participating' => 'Не участвует',
                        default             => '—',
                    })
                    ->schema([
                        Select::make('team_member_id')
                            ->label('Участник')
                            ->options(function (Get $get): array {
                                $teamId = $get('../../team_id');
                                if (! $teamId) {
                                    return [];
                                }

                                $team = Team::find($teamId);
                                if (! $team) {
                                    return [];
                                }

                                return $team->members()
                                    ->wherePivot('status', 'active')
                                    ->get()
                                    ->mapWithKeys(fn (\App\Models\User $user): array => [
                                        $user->pivot->id => $user->name,
                                    ])
                                    ->all();
                            })
                            ->required()
                            ->live()
                            ->searchable()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                if (! $state) {
                                    $set('member_name', '');
                                    return;
                                }

                                $teamId = $get('../../../team_id');
                                if (! $teamId) {
                                    return;
                                }

                                $team = Team::find($teamId);
                                $member = $team?->members()
                                    ->wherePivot('status', 'active')
                                    ->wherePivot('id', $state)
                                    ->first();

                                $set('member_name', $member?->name ?? '');
                            }),
                        Hidden::make('member_name'),
                        Hidden::make('is_captain')
                            ->default(false),
                        \Filament\Forms\Components\Placeholder::make('captain_badge')
                            ->label('')
                            ->content(fn (Get $get): string => $get('is_captain') ? ' Капитан команды' : '')
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => (bool) $get('is_captain')),
                        Select::make('role')
                            ->label('Роль')
                            ->options([
                                'main'              => 'Основной',
                                'reserve'           => 'Запасной',
                                'captain'           => 'Капитан',
                                'not_participating' => 'Не участвует',
                            ])
                            ->required(),
                    ]),

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
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(20480)
                            ->maxFiles(config('documents.max_files_per_type', 10))
                            ->downloadable()
                            ->helperText(fn () => 'Можно загрузить до ' . config('documents.max_files_per_type', 10) . ' файлов'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('regatta.name')->label('Регата')
                    ->searchable(),
                TextColumn::make('team.name')->label('Команда')
                    ->searchable(),
                TextColumn::make('yacht.name')->label('Яхта')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()->label('Статус')->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending'  => 'На проверке',
                    'approved' => 'Активная',
                    'rejected' => 'Отклонена',
                    default    => $state,
                })->color(fn (string $state): string => match ($state) {
                    'pending'  => 'gray',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default    => 'gray',
                }),
                TextColumn::make('crew')
                    ->label('Экипаж')
                    ->state(fn (RegattaEntry $record): string => (string) $record->crew()
                        ->whereIn('role', ['main', 'reserve', 'captain'])
                        ->count()
                    ),

                TextColumn::make('created_at')
                    ->dateTime()
                    //->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    //->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->filters([
                //
            ])->emptyStateHeading('Записей пока нет')
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать заявку на регату')
                    ->mountUsing(function (Schema $form, RegattaEntry $record): void {
                        $data = $record->toArray();
                        $data['required_documents'] = app(\App\Actions\Document\SyncDocumentFilesAction::class)
                            ->load($record, ManageRegattaEntries::getRequiredDocuments($record->regatta_id));
                        $data['crew'] = static::loadCrew($record);
                        $form->fill($data);
                    })
                    ->using(function (RegattaEntry $record, array $data): RegattaEntry {
                        $docs = $data['required_documents'] ?? [];
                        $crew = $data['crew'] ?? [];
                        unset($data['required_documents'], $data['crew']);

                        // Проверка дубликата: та же команда уже подала заявку на эту регату?
                        $exists = RegattaEntry::where('regatta_id', $data['regatta_id'])
                            ->where('team_id', $data['team_id'])
                            ->where('id', '!=', $record->id)
                            ->exists();

                        if ($exists) {
                            Notification::make()
                                ->title('Заявка уже существует')
                                ->body('Эта команда уже подала заявку на выбранную регату.')
                                ->danger()
                                ->send();

                            $this->halt();
                        }

                        $record->update($data);

                        app(\App\Actions\Document\SyncDocumentFilesAction::class)
                            ->execute($record, $docs);

                        static::syncCrew($record, $crew);

                        return $record;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegattaEntries::route('/'),
        ];
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
        return [];
    }

    /**
     * Загружает существующий экипаж для формы редактирования.
     *
     * @return array<int, array{team_member_id: string, member_name: string, role: string}>
     */
    public static function loadCrew(RegattaEntry $record): array
    {
        return $record->crew()
            ->with('teamMember.user')
            ->get()
            ->map(fn (\App\Models\RegattaEntryCrew $crew): array => [
                'team_member_id' => $crew->team_member_id,
                'member_name'    => $crew->teamMember?->user?->name ?? 'Неизвестный',
                'is_captain'     => $crew->teamMember?->role === 'organizer',
                'role'           => $crew->role,
            ])
            ->all();
    }

    /**
     * Синхронизирует экипаж после сохранения формы.
     *
     * @param array<int, array{team_member_id: string, role: string}> $crew
     */
    public static function syncCrew(RegattaEntry $record, array $crew): void
    {
        // Удаляем записи, которых нет в новом наборе
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
}
