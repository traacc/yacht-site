<?php

declare(strict_types=1);

namespace App\Filament\Resources\Adverts;

use App\Enums\AdvertType;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\Adverts\Pages\ManageAdvertCategories;
use App\Models\AdvertCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Справочник категорий объявлений.
 *
 * В меню не показывается: открывается ссылкой со страницы объявлений и из
 * createOptionForm в форме объявления — как категории помощи.
 */
class AdvertCategoryResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = AdvertCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    public static function getModelLabel(): string
    {
        return 'Категория объявлений';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Категории объявлений';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::formComponents());
    }

    /**
     * Вынесено отдельно: этот же набор полей используется в createOptionForm
     * селекта категории в форме объявления.
     *
     * @return list<Component>
     */
    public static function formComponents(): array
    {
        return [
            Select::make('type')
                ->label('Раздел')
                ->options(AdvertType::options())
                ->default(AdvertType::Marketplace->value)
                ->required(),

            TextInput::make('title')
                ->label('Название')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', $state ? Str::slug($state) : '')),

            TextInput::make('slug')
                ->label('Slug')
                ->placeholder('avtomaticheski-zapolnyaetsya')
                ->required()
                ->maxLength(255)
                ->unique('advert_categories', 'slug', ignoreRecord: true),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),

                TextColumn::make('title')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Раздел')
                    ->badge()
                    ->formatStateUsing(fn (AdvertType $state): string => $state->label()),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('adverts_count')
                    ->label('Объявлений')
                    ->counts('adverts')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->filters([
                SelectFilter::make('type')
                    ->label('Раздел')
                    ->options(AdvertType::options()),
            ])
            ->emptyStateHeading('Категорий пока нет')
            ->emptyStateDescription('Добавьте категории — по ним пользователи фильтруют объявления на витрине.')
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать категорию'),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdvertCategories::route('/'),
        ];
    }
}
