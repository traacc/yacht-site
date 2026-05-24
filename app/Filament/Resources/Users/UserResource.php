<?php

namespace App\Filament\Resources\Users;

use App\Enums\SportCategory;
use App\Enums\TeamMemberRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('photo_url')
                    ->label('Изменить фотографию')
                    ->avatar()
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('avatars')
                    ->columnSpanFull()
                    ->visibility('public')
                    ->extraFieldWrapperAttributes(['class' => 'photo_wrapper']),
                TextInput::make('first_name')
                    ->label('Имя')
                    ->placeholder('Имя')
                    ->required(),
                TextInput::make('last_name')
                    ->label('Фамилия')
                    ->placeholder('Фамилия')
                    ->required(),
                DatePicker::make('birth_date')
                    ->displayFormat('d.m.Y')
                    ->label('Дата рождения'),
                TextInput::make('email')
                    ->label('Email')
                    ->placeholder('email@example.com')
                    ->email()
                    ->required(),
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
                    ->mask('+7 (999) 999-99-99')
                    ->telRegex('/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/'),
                Select::make('sport_category')
                    ->label('Спортивный разряд')
                    ->placeholder('Спортивный разряд')
                    ->options(SportCategory::class),



                Select::make('system_role')
                    ->label('Системная роль')
                    ->placeholder('Выберите роль')
                    ->options([
                        'user' => 'Пользователь',
                        'admin' => 'Администратор',
                    ])
                    ->default('user')
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
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Имя пользователя')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Дата рождения')
                    ->date()
                    ->sortable(),
                TextColumn::make('sport_category')
                    ->label('Разряд')
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\SportCategory ? $state->getLabel() : '—')
                    ->badge(),
                TextColumn::make('system_role')
                    ->label('Роль')
                    ->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                        'user' => 'Пользователь',
                        'admin' => 'Администратор',
                    }),

            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                SelectFilter::make('system_role')
                ->label('Роль') // Красивое название для пользователя
                ->options([
                    'user' => 'Пользователь',
                    'admin' => 'Администратор',
                ])
            ], layout: FiltersLayout::AboveContent)->filtersFormColumns(3)->deferFilters(false)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
