<?php

namespace App\Filament\Resources\Users;

use App\Enums\SportCategory;
use App\Enums\TeamMemberRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Regatta;
use App\Models\User;
use BackedEnum;
use App\Exports\UserExport;
use Filament\Actions\Action;
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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
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

class UserResource extends Resource
{
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('photo_url')
                    ->label('Изменить фотографию')
                    ->avatar()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
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
                    ->label('Дата рождения')
                    ->required(),
                TextInput::make('email')
                    ->label('Email')
                    ->placeholder('email@example.com')
                    ->email()
                    ->required()
                    ->rules([
                        fn ($record) => \Illuminate\Validation\Rule::unique('users', 'email')->ignore($record?->id),
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
                        fn ($record) => \Illuminate\Validation\Rule::unique('users', 'phone')->ignore($record?->id),
                    ])
                    ->validationMessages([
                        'unique' => 'Пользователь с таким телефоном уже зарегистрирован',
                    ]),
                Select::make('sport_category')
                    ->label('Спортивный разряд')
                    ->placeholder('Спортивный разряд')
                    ->options(SportCategory::class),



                Select::make('system_role')
                    ->label('Системная роль')
                    ->placeholder('Выберите роль')
                    ->options(\App\Enums\SystemRole::class)
                    ->default(\App\Enums\SystemRole::User->value)
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
                                'invited'  => 'Приглашён',
                                'active'   => 'Активен',
                                'declined' => 'Отказался',
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
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\SportCategory ? $state->getLabel() : '—')
                    ->badge()->toggleable(),
                TextColumn::make('system_role')
                    ->label('Роль')
                    ->badge()
                    ->formatStateUsing(fn (\App\Enums\SystemRole $state): string => $state->getLabel())->toggleable(),
                TextColumn::make('creation_source')
                    ->label('Источник')
                    ->badge()
                    ->formatStateUsing(fn (\App\Enums\CreationSource $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->options(\App\Enums\SystemRole::class),
                SelectFilter::make('sport_category')
                    ->label('Спортивный разряд')
                    ->options(SportCategory::class),
                SelectFilter::make('creation_source')
                    ->label('Источник создания')
                    ->options(\App\Enums\CreationSource::class),
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
                EditAction::make()->modalHeading('Редактировать пользователя'),
                DeleteAction::make()->label('Удалить')
                    ->modalHeading('Удалить пользователя')
                    ->hidden(false) // показывать и для уже удалённых записей
                    ->using(fn (User $record, DeleteAction $action) => \App\Support\SafeDelete::single($record, $action, 'пользователя')),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Удалить')
                        ->using(fn (\Illuminate\Support\Collection $records, DeleteBulkAction $action) => \App\Support\SafeDelete::bulk($records, $action, 'пользователи')),
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
