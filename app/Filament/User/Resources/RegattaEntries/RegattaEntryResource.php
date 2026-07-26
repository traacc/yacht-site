<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\RegattaEntries;

use App\Actions\Auth\SendEmailVerificationLinkAction;
use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\Payment\StartOnlinePaymentAction;
use App\Enums\PaymentStatus;
use App\Enums\RegattaStatus;
use App\Enums\SystemRole;
use App\Filament\User\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\RegattaEntryCrew;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Yacht;
use App\Models\YachtDocumentType;
use App\Services\Payments\PaymentManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

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
                    RegattaStatus::Closest->value,
                    RegattaStatus::Upcoming->value,
                    RegattaStatus::Active->value,
                ],
            ))
            ->orderBy(
                Regatta::select('date_start')
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
                                'doc_type' => $doc['doc_type'],
                                'title' => $doc['title'],
                                'is_required' => $doc['is_required'] ?? false,
                                'files' => [],
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
                    ->live()
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        $set('crew', static::buildCrewDefaults($state, $get('regatta_id')));
                    }),
                Select::make('yacht_id')
                    ->label('Яхта')
                    ->options(fn (Get $get, ?RegattaEntry $record = null) => Yacht::query()
                        // ->where('user_id', auth()->id())
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
                    ->required()
                    ->live()
                    ->searchable(),

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
                                    ->sort(fn (string $a, string $b): int => strnatcasecmp($a, $b))
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
                                'main' => 'Основной',
                                'reserve' => 'Запасной',
                                'captain' => 'Рулевой',
                                // 'not_participating' => 'Не участвует',
                            ])
                            ->required(),
                    ])->columns(3)
                    ->itemLabel(null)
                    ->addActionLabel('Добавить члена экипажа')
                    ->deleteAction(
                        fn (Action $action) => $action
                            ->icon('heroicon-m-x-mark')
                            ->color('danger')
                            ->iconButton()
                    )->extraAttributes(['class' => 'hide-repeater-header-label']),

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
                        ManageRegattaEntries::getRequiredDocuments($get('regatta_id')),
                    ))
                    ->columns(1)
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->helperText('Заявку можно подать без документов — недостающие обязательные документы будут отмечены и их можно загрузить позже.')
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
                            ->helperText(fn () => 'Можно загрузить до '.config('documents.max_files_per_type', 10).' файлов'),
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
                TextColumn::make('regatta.name')->label('Регата')
                    ->searchable(),
                TextColumn::make('team.name')->label('Команда')
                    ->searchable(),
                TextColumn::make('yacht.name')->label('Яхта')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()->label('Статус')->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'На проверке',
                        'approved' => 'Активная',
                        'rejected' => 'Отклонена',
                        default => $state,
                    })->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('crew')
                    ->label('Экипаж')
                    ->state(fn (RegattaEntry $record): string => (string) $record->crew()
                        ->whereIn('role', ['main', 'reserve', 'captain'])
                        ->count()
                    ),
                IconColumn::make('documents_complete')
                    ->label('Документы')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (RegattaEntry $record): ?string => $record->hasMissingDocuments()
                        ? 'Поданы не все обязательные документы'
                        : null),

                TextColumn::make('created_at')
                    ->dateTime()
                    // ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    // ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->filters([
                //
            ])->emptyStateHeading('Записей пока нет')
            ->recordActions([
                Action::make('payOnline')
                    ->label('Оплатить взнос')
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->color('success')
                    ->visible(fn (RegattaEntry $record): bool => static::feeIsPayable($record)
                        && (bool) auth()->user()?->hasVerifiedEmail())
                    ->action(function (RegattaEntry $record) {
                        $registry = $record->paymentRegistries()
                            ->where('status', '!=', PaymentStatus::Paid->value)
                            ->latest()
                            ->first();

                        // Заявка могла быть создана до внедрения сборов — реестра нет.
                        $registry ??= $record->paymentRegistries()->create([
                            'name' => "Сбор за участие — {$record->regatta->name} ({$record->team?->name})",
                            'amount' => $record->regatta->entry_fee_amount ?? 0,
                            'status' => PaymentStatus::Pending,
                        ]);

                        try {
                            $transaction = app(StartOnlinePaymentAction::class)
                                ->handle($registry, auth()->user());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Оплата недоступна')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return null;
                        }

                        return redirect()->away($transaction->confirmation_url);
                    }),
                // Пока e-mail не подтверждён, вместо оплаты предлагаем подтвердить его.
                Action::make('verifyEmailFirst')
                    ->label('Подтвердите e-mail')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('warning')
                    ->visible(fn (RegattaEntry $record): bool => static::feeIsPayable($record)
                        && ! auth()->user()?->hasVerifiedEmail())
                    ->requiresConfirmation()
                    ->modalHeading('Подтверждение e-mail')
                    ->modalDescription(fn (): string => 'Онлайн-оплата доступна только после подтверждения e-mail. '
                        .'Отправить письмо со ссылкой на '.auth()->user()?->email.'?')
                    ->modalSubmitActionLabel('Отправить письмо')
                    ->action(function (): void {
                        try {
                            app(SendEmailVerificationLinkAction::class)->handle(auth()->user());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Не удалось отправить письмо')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Письмо отправлено')
                            ->body('Перейдите по ссылке из письма — после этого можно будет оплатить взнос.')
                            ->success()
                            ->send();
                    }),
                EditAction::make()->modalHeading('Редактировать заявку на регату')
                    ->mountUsing(function (Schema $form, RegattaEntry $record): void {
                        $data = $record->toArray();
                        $data['required_documents'] = app(SyncDocumentFilesAction::class)
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

                        $data['documents_complete'] = static::documentsComplete($docs);
                        $record->update($data);

                        app(SyncDocumentFilesAction::class)
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
     * Есть ли по заявке неоплаченный взнос, который можно оплатить онлайн
     * (без учёта подтверждения e-mail — оно проверяется отдельно).
     */
    protected static function feeIsPayable(RegattaEntry $record): bool
    {
        return (bool) $record->regatta?->entry_fee_required
            && ! $record->fee_paid
            && app(PaymentManager::class)->isEnabled();
    }

    /**
     * Определяет читаемую метку для документа в Repeater.
     */
    /**
     * Все ли обязательные документы загружены среди элементов repeater'а.
     *
     * @param  array<int, array{doc_type?: string, is_required?: bool, files?: array}>  $docs
     */
    public static function documentsComplete(array $docs): bool
    {
        foreach ($docs as $doc) {
            if (! ($doc['is_required'] ?? false)) {
                continue;
            }

            if (array_filter((array) ($doc['files'] ?? [])) === []) {
                return false;
            }
        }

        return true;
    }

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
            ->map(fn (TeamMember $member): array => [
                'team_member_id' => $member->id,
                'member_name' => $member->user?->name ?? 'Неизвестный',
                'is_captain' => false,
                'role' => 'main',
            ])
            ->all();
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
            ->map(fn (RegattaEntryCrew $crew): array => [
                'team_member_id' => $crew->team_member_id,
                'member_name' => $crew->teamMember?->user?->name ?? 'Неизвестный',
                'is_captain' => $crew->role === 'captain',
                'role' => $crew->role,
            ])
            ->all();
    }

    /**
     * Синхронизирует экипаж после сохранения формы.
     *
     * @param  array<int, array{team_member_id: string, role: string}>  $crew
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
                ['role' => $item['role'] ?? 'main'],
            );
        }
    }
}
