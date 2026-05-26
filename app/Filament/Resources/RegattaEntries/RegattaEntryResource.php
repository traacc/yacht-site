<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegattaEntries;

use App\Filament\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Models\RegattaEntry;
use BackedEnum;
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
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegattaEntryResource extends Resource
{
    protected static ?string $model = RegattaEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'results';

    public static function getModelLabel(): string
    {
        return 'Заявка на регату';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки на регату';
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
                    ->relationship('regatta', 'name')
                    ->required()
                    ->columnSpanFull()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $docs = array_map(
                            fn (array $doc) => [
                                'doc_type' => $doc['doc_type'],
                                'title'    => $doc['title'],
                                'files'    => [],
                            ],
                            ManageRegattaEntries::getRequiredDocuments($state),
                        );
                        $set('required_documents', $docs);
                    }),
                Select::make('team_id')
                    ->label('Команда')
                    ->relationship('team', 'name')
                    ->required()
                    ->columnSpanFull(),
                Select::make('yacht_id')
                    ->label('Яхта')
                    ->relationship('yacht', 'name')
                    ->columnSpanFull(),
                Placeholder::make('team.organizer.name')
                    ->label('Капитан')
                    ->columnSpanFull(),
                DatePicker::make('submitted_at')
                    ->label('Дата')
                    ->displayFormat('d.m.Y')
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

                // ── Обязательные документы ──────────────────
                Repeater::make('required_documents')
                    ->label('Обязательные документы')
                    ->columnSpanFull()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->default(fn (Get $get) => array_map(
                        fn (array $doc) => [
                            'doc_type' => $doc['doc_type'],
                            'title'    => $doc['title'],
                            'files'    => [],
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
                    ->searchable(),
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable(),
                TextColumn::make('team.organizer.name')
                    ->label('Капитан')
                    ->searchable(),
                TextColumn::make('submitted_at')
                    ->label('Дата')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
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
                    }),
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
            ->emptyStateHeading('Записей пока нет')
            ->filters([])
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать заявку на регату')
                    ->mountUsing(function (Schema $form, RegattaEntry $record): void {
                        $sync = app(\App\Actions\Document\SyncDocumentFilesAction::class);
                        $requiredDocs = ManageRegattaEntries::getRequiredDocuments($record->regatta_id);

                        $data = $record->toArray();
                        $data['required_documents'] = $sync->load($record, $requiredDocs);

                        $form->fill($data);
                    })
                    ->using(function (RegattaEntry $record, array $data): RegattaEntry {
                        $requiredDocs = $data['required_documents'] ?? [];
                        unset($data['required_documents']);

                        $record->update($data);

                        app(\App\Actions\Document\SyncDocumentFilesAction::class)
                            ->execute($record, $requiredDocs);

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

        return $model?->label ?? ($state['title'] ?? null);
    }
}
