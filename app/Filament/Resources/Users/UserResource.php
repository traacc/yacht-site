<?php

namespace App\Filament\Resources\Users;

use App\Actions\Auth\SendEmailVerificationLinkAction;
use App\Enums\CreationSource;
use App\Enums\SportCategory;
use App\Enums\SystemRole;
use App\Enums\TeamMemberRole;
use App\Exports\UserExport;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Regatta;
use App\Models\User;
use App\Support\SafeDelete;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class UserResource extends Resource
{
    use RestrictsAccessByRole;

    public static function getModelLabel(): string
    {
        return 'Пользователь'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Пользователи'; // Название во множественном числе
    }

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'user';

    protected static ?int $navigationSort = 5;

    protected static string|UnitEnum|null $navigationGroup = 'Команды и участники';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('photo_url')
                    ->label('Изменить фотографию')
                    ->avatar()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif'])
                    ->imageEditor()
                    ->disk('public')
                    ->directory('avatars')
                    ->columnSpanFull()
                    ->visibility('public')
                    ->extraFieldWrapperAttributes(['class' => 'photo_wrapper']),
                TextInput::make('formatted_external_id')
                    ->label('ID')
                    ->readOnly()
                    ->columnSpanFull()
                    ->formatStateUsing(fn (?User $record) => $record?->formatted_external_id ?? '—'),
                TextInput::make('name')
                    ->label('ФИО')
                    ->placeholder('ФИО')
                    ->required()
                    ->rules([
                        function (Get $get, ?User $record): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                                $birthDate = $get('birth_date');

                                if (blank($value) || blank($birthDate)) {
                                    return;
                                }

                                $duplicateExists = User::query()
                                    ->where('name', $value)
                                    ->whereDate('birth_date', $birthDate)
                                    ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey()))
                                    ->exists();

                                if ($duplicateExists) {
                                    $fail('Пользователь с таким ФИО и датой рождения уже зарегистрирован');
                                }
                            };
                        },
                    ]),
                /*
                TextInput::make('first_name')
                    ->label('Имя')
                    ->placeholder('Имя')
                    ->required(),
                TextInput::make('last_name')
                    ->label('Фамилия')
                    ->placeholder('Фамилия')
                    ->required(),
                TextInput::make('patronymic')
                    ->label('Отчество')
                    ->maxLength(255),
                */
                DatePicker::make('birth_date')
                    ->minDate(now()->subYears(100))
                    ->maxDate(now()->addYears(100))
                    ->displayFormat('d.m.Y')
                    ->native(false)
                    ->label('Дата рождения')
                    ->required(),
                TextInput::make('email')
                    ->label('Email')
                    ->placeholder('email@example.com')
                    ->email()
                    ->required()
                    ->rules([
                        fn ($record) => Rule::unique('users', 'email')->ignore($record?->id),
                    ])
                    ->validationMessages([
                        'unique' => 'Пользователь с таким email уже зарегистрирован',
                    ]),
                TextInput::make('password')
                    ->label('Пароль')
                    ->placeholder('Новый пароль')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => bcrypt($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                TextInput::make('phone')
                    ->label('Телефон')
                    ->mask('+7 (999) 999-99-99')
                    ->placeholder('+7 (___) ___-__-__')
                    ->required()
                    ->telRegex('/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/')
                    ->rules([
                        fn ($record) => Rule::unique('users', 'phone')->ignore($record?->id),
                    ])
                    ->validationMessages([
                        'unique' => 'Пользователь с таким телефоном уже зарегистрирован',
                    ]),
                Select::make('sport_category')
                    ->label('Спортивный разряд')
                    ->placeholder('Спортивный разряд')
                    ->options(SportCategory::class),

                Textarea::make('about')
                    ->label('О себе')
                    ->placeholder('О себе')
                    ->rows(4)
                    ->maxLength(2000)
                    ->columnSpanFull(),

                Select::make('system_role')
                    ->label('Системная роль')
                    ->placeholder('Выберите роль')
                    ->options(SystemRole::class)
                    ->default(SystemRole::User->value)
                    ->required(),
                Toggle::make('is_banned')
                    ->label('Забанен'),
                Textarea::make('ban_reason')
                    ->label('Причина бана')
                    ->placeholder('Причина бана')
                    ->columnSpanFull(),
                Textarea::make('ban_comment')
                    ->label('Комментарий к бану')
                    ->placeholder('Комментарий к бану')
                    ->columnSpanFull(),

                Repeater::make('teamMemberships')
                    ->label('Команды')
                    ->relationship('teamMemberships')
                    ->addActionLabel('Добавить в команду')
                    ->columns(3)
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->schema([
                        Select::make('team_id')
                            ->label('Команда')
                            ->relationship('team', 'name')
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
                                'invited' => 'Приглашён',
                                'active' => 'Активен',
                                'declined' => 'Отказался',
                                'left' => 'Покинул команду',
                            ])
                            ->default('active')
                            ->required(),
                        Toggle::make('is_permanent')
                            ->label('Постоянный участник')
                            ->default(false),
                    ]),

                Select::make('crew_team_id')
                    ->label('Отправить в экипаж ближайшей регаты')
                    ->helperText('Пользователь будет добавлен в заявку выбранной команды. В списке — только команды, уже записанные на ближайшую регату (пользователя нужно добавить в эту команду выше).')
                    ->searchable()
                    ->preload()
                    ->visibleOn('create')
                    ->options(fn (): array => static::closestRegattaTeamOptions())
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Команды, уже записанные (pending/approved) на ближайшую регату.
     *
     * @return array<string, string> team_id => team name
     */
    public static function closestRegattaTeamOptions(): array
    {
        $regatta = Regatta::closestUpcoming();

        if (! $regatta) {
            return [];
        }

        return $regatta->entries()
            ->whereIn('status', ['pending', 'approved'])
            ->with('team:id,name')
            ->get()
            ->pluck('team.name', 'team_id')
            ->filter()
            ->all();
    }

    /**
     * Добавляет пользователя запасным в экипаж ближайшей регаты — в заявку
     * выбранной команды, при условии что команда записана (pending/approved)
     * и пользователь действительно состоит в этой команде.
     *
     * @return bool Была ли создана запись экипажа
     */
    public static function addToClosestRegattaCrew(User $user, string $teamId): bool
    {
        $regatta = Regatta::closestUpcoming();

        if (! $regatta) {
            return false;
        }

        $user->loadMissing('teamMemberships');

        $membership = $user->teamMemberships->firstWhere('team_id', $teamId);

        if (! $membership) {
            return false;
        }

        $entry = $regatta->entries()
            ->where('team_id', $teamId)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if (! $entry) {
            return false;
        }

        $crew = $entry->crew()->firstOrCreate(
            ['team_member_id' => $membership->id],
            ['role' => 'main'],
        );

        return $crew->wasRecentlyCreated;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->columns([
                TextColumn::make('name')
                    ->label('ФИО')
                    ->searchable()->sortable(),
                TextColumn::make('birth_date')
                    ->label('ДР')
                    ->date('d.m.Y')
                    ->sortable()->toggleable(),
                TextColumn::make('sport_category')
                    ->label('Разряд')
                    ->formatStateUsing(fn ($state) => $state instanceof SportCategory ? $state->getLabel() : '—')
                    ->badge()->toggleable(),
                TextColumn::make('system_role')
                    ->label('Роль')
                    ->badge()
                    ->formatStateUsing(fn (SystemRole $state): string => $state->getLabel())->toggleable(),
                TextColumn::make('creation_source')
                    ->label('Источник')
                    ->badge()
                    ->formatStateUsing(fn (CreationSource $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('email_verified_at')
                    ->label('E-mail подтверждён')
                    ->boolean()
                    ->tooltip(fn (User $record): ?string => $record->email_verified_at?->format('d.m.Y H:i'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Рег.')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Изм.')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()->toggleable(),
            ])->defaultSort('name', 'asc')->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                SelectFilter::make('system_role')
                    ->label('Роль')
                    ->options(SystemRole::class),
                SelectFilter::make('sport_category')
                    ->label('Спортивный разряд')
                    ->options(SportCategory::class),
                SelectFilter::make('creation_source')
                    ->label('Источник создания')
                    ->options(
                        collect(CreationSource::cases())
                            ->reject(fn ($case) => $case === CreationSource::Unknown)
                            ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                    ),
                TernaryFilter::make('email_verified')
                    ->label('E-mail подтверждён')
                    ->placeholder('Все')
                    ->trueLabel('Подтверждён')
                    ->falseLabel('Не подтверждён')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('email_verified_at'),
                        blank: fn (Builder $query) => $query,
                    ),
                TrashedFilter::make(),
            ], layout: FiltersLayout::AboveContent)->filtersFormColumns(3)->deferFilters(false)
            ->headerActions([
                Action::make('exportXlsx')
                    ->label('Экспорт в Excel')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('white')
                    ->action(fn ($livewire) => (new UserExport)->download(
                        $livewire->getFilteredSortedTableQuery(),
                        'users_'.now()->format('Y-m-d').'.xlsx',
                    )),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('sendEmailVerification')
                        ->label('Письмо для подтверждения')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->visible(fn (User $record): bool => ! $record->hasVerifiedEmail() && ! $record->hasTechnicalEmail())
                        ->requiresConfirmation()
                        ->modalHeading('Отправить письмо для подтверждения e-mail?')
                        ->modalDescription(fn (User $record): string => 'Письмо со ссылкой будет отправлено на '.$record->email.'.')
                        ->action(function (User $record): void {
                            try {
                                app(SendEmailVerificationLinkAction::class)->handle($record, throttle: false);
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->title('Не удалось отправить письмо')
                                    ->body(collect($e->errors())->flatten()->first())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()->title('Письмо отправлено')->success()->send();
                        }),
                    Action::make('verifyEmailManually')
                        ->label('Подтвердить вручную')
                        ->icon(Heroicon::OutlinedCheckBadge)
                        ->color('warning')
                        // Нужно, когда участник платит офлайн и не имеет доступа к почте.
                        ->visible(fn (User $record): bool => ! $record->hasVerifiedEmail()
                            && auth()->user()?->system_role === SystemRole::Admin)
                        ->requiresConfirmation()
                        ->modalHeading('Подтвердить e-mail вручную?')
                        ->modalDescription('Пользователь получит доступ к онлайн-оплате без перехода по ссылке из письма. Убедитесь, что адрес принадлежит именно ему.')
                        ->action(function (User $record): void {
                            $record->markEmailAsVerified();

                            Notification::make()->title('E-mail подтверждён')->success()->send();
                        }),
                ])->label('E-mail')->icon(Heroicon::OutlinedEnvelope)->button()->color('gray')
                    ->visible(fn (User $record): bool => ! $record->hasVerifiedEmail()),
                EditAction::make()->modalHeading('Редактировать пользователя'),
                DeleteAction::make()->label('Удалить')
                    ->modalHeading('Удалить пользователя')
                    ->hidden(false) // показывать и для уже удалённых записей
                    ->using(fn (User $record, DeleteAction $action) => SafeDelete::single($record, $action, 'пользователя')),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Удалить')
                        ->using(fn (Collection $records, DeleteBulkAction $action) => SafeDelete::bulk($records, $action, 'пользователи')),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
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
