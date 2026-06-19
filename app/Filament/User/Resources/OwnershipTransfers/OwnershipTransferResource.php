<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\OwnershipTransfers;

use App\Filament\User\Resources\OwnershipTransfers\Pages\ManageOwnershipTransfers;
use App\Models\Yacht;
use App\Models\YachtOwnershipTransfer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class OwnershipTransferResource extends Resource
{
    protected static ?string $model = YachtOwnershipTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'Заявка на передачу яхты';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Передача яхты';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formComponents());
    }

    /**
     * Компоненты формы заявки. Используются как в ресурсе, так и в
     * быстром действии «Запросить передачу» на странице «Мои Яхты».
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function formComponents(): array
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

        return [
            Placeholder::make('note_form')
                ->hiddenLabel()
                ->content(new HtmlString(
                    'Выберите яхту, владение которой хотите получить, и приложите документ, '
                    . 'подтверждающий ваше право собственности. Администратор рассмотрит заявку '
                    . 'и при подтверждении передаст яхту вам.'
                ))
                ->columnSpanFull(),

            Select::make('yacht_id')
                ->label('Яхта')
                ->placeholder('Номер ВФПС или название яхты')
                ->columnSpanFull()
                ->required()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => static::ownedByOthersQuery()
                    ->where(function (Builder $q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('vfps_number', 'like', "%{$search}%");
                    })
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (Yacht $yacht) => [$yacht->id => static::yachtLabel($yacht)])
                    ->toArray())
                ->getOptionLabelUsing(function ($value): ?string {
                    $yacht = static::ownedByOthersQuery()->find($value);

                    return $yacht ? static::yachtLabel($yacht) : null;
                })
                ->rules([
                    // Нельзя дублировать активную заявку на ту же яхту
                    fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        $exists = YachtOwnershipTransfer::query()
                            ->where('yacht_id', $value)
                            ->where('requester_id', auth()->id())
                            ->where('status', 'pending')
                            ->exists();

                        if ($exists) {
                            $fail('У вас уже есть заявка на эту яхту, ожидающая рассмотрения.');
                        }
                    },
                ]),

            FileUpload::make('proof_files')
                ->label('Документ, подтверждающий владение')
                ->columnSpanFull()
                ->required()
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

            Textarea::make('comment')
                ->label('Комментарий')
                ->placeholder('Дополнительная информация для администратора (необязательно)')
                ->columnSpanFull()
                ->rows(3),
        ];
    }

    /**
     * Создать заявку на передачу яхты из данных формы и привязать документы.
     */
    public static function createTransfer(array $data): YachtOwnershipTransfer
    {
        $proofFiles = array_values(array_filter((array) ($data['proof_files'] ?? [])));

        $yacht = Yacht::query()->findOrFail($data['yacht_id']);

        $transfer = YachtOwnershipTransfer::create([
            'yacht_id'          => $yacht->id,
            'requester_id'      => auth()->id(),
            'previous_owner_id' => $yacht->user_id,
            'status'            => 'pending',
            'comment'           => $data['comment'] ?? null,
        ]);

        app(\App\Actions\Document\SyncDocumentFilesAction::class)->execute($transfer, [[
            'doc_type' => YachtOwnershipTransfer::PROOF_DOC_TYPE,
            'title'    => 'Подтверждение владения',
            'files'    => $proofFiles,
        ]]);

        return $transfer;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('yacht.name')
                    ->label('Яхта')
                    ->searchable(),
                TextColumn::make('previousOwner.name')
                    ->label('Текущий владелец')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label('Подана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->emptyStateHeading('Заявок пока нет')
            ->recordActions([
                Action::make('withdraw')
                    ->label('Отозвать')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (YachtOwnershipTransfer $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Отозвать заявку?')
                    ->action(function (YachtOwnershipTransfer $record): void {
                        $record->delete();
                        Notification::make()
                            ->title('Заявка отозвана')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('requester_id', auth()->id())
            ->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOwnershipTransfers::route('/'),
        ];
    }

    /**
     * Яхты, принадлежащие другим пользователям (доступные для запроса передачи).
     */
    public static function ownedByOthersQuery(): Builder
    {
        return Yacht::query()->where('user_id', '!=', auth()->id());
    }

    public static function yachtLabel(Yacht $yacht): string
    {
        return trim(($yacht->name ?? '') . ($yacht->vfps_number ? " ({$yacht->vfps_number})" : ''));
    }
}
