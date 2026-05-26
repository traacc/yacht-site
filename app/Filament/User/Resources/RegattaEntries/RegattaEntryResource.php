<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\RegattaEntries;

use App\Filament\User\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Models\RegattaEntry;
use App\Models\Document;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RegattaEntryResource extends Resource
{
    protected static ?string $model = RegattaEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'cup';

    public static function getModelLabel(): string
    {
        return 'Заявка на соревнование'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки на соревнования'; // Название во множественном числе
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->whereHas('team', fn (Builder $q) => $q->visibleForUser($user));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('regatta_id')
                    ->relationship(
                        'regatta',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('date_end', '>=', now()->toDateString()),
                    )
                    ->label('Регата')
                    ->required(),
                Select::make('team_id')
                    ->relationship('team', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('organizer_id', auth()->id()))->label('Команда')
                    ->required(),
                Select::make('yacht_id')
                    ->relationship('yacht', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('user_id', auth()->id()))->label('Яхта'),

                Repeater::make('documents')
                    ->relationship()
                    ->label('Документы')
                    ->columnSpanFull()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->default(fn () => array_map(
                        fn (array $doc) => [
                            'doc_type' => $doc['doc_type'],
                            'title'    => $doc['title'],
                            'url'      => null,
                        ],
                        ManageRegattaEntries::getRequiredDocuments(),
                    ))
                    ->columns(1)
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->rules([
                        function (): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                $requiredDocs = ManageRegattaEntries::getRequiredDocuments();
                                $missing = [];

                                foreach ($requiredDocs as $required) {
                                    $docType = $required['doc_type'];
                                    $uploaded = collect((array) $value)->first(
                                        fn (array $doc): bool => ($doc['doc_type'] ?? '') === $docType
                                    );
                                    if ($uploaded === null || empty($uploaded['url'])) {
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
                        FileUpload::make('url')
                            ->label('Файл')
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
                            ->downloadable(),
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
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->stackedOnMobile()
            ->filters([
                //
            ])->emptyStateHeading('Записей пока нет')
            ->recordActions([
                EditAction::make()
                    ->mountUsing(function (Schema $form, RegattaEntry $record): void {
                        ManageRegattaEntries::ensureRequiredDocuments($record);
                        $form->fill($record->toArray());
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                /*
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
                */
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegattaEntries::route('/'),
        ];
    }

    /**
     * Определяет читаемую метку для документа в Repeater.
     * Читает название из таблицы yacht_document_types, для неизвестных —
     * возвращает title из состояния.
     */
    public static function resolveDocumentLabel(array $state): ?string
    {
        $docType = $state['doc_type'] ?? null;

        if ($docType === null) {
            return $state['title'] ?? null;
        }

        $model = \App\Models\YachtDocumentType::cachedAll()
            ->first(fn (\App\Models\YachtDocumentType $t) => $t->key === $docType);

        return $model?->label ?? ($state['title'] ?? null);
    }
}
