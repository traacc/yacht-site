<?php

declare(strict_types=1);

namespace App\Filament\Resources\PasswordResetRequests;

use App\Actions\Auth\SubmitPasswordResetRequestAction;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\PasswordResetRequests\Pages\ManagePasswordResetRequests;
use App\Mail\PasswordResetRequestAnswered;
use App\Models\PasswordResetRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use UnitEnum;

/**
 * Обращения из формы «Забыли пароль».
 *
 * Автоматического сброса на сайте нет: заявка попадает сюда, и администратор
 * либо отвечает письмом на указанный email, либо отправляет пользователю
 * ссылку на смену пароля (тот же механизм, что и на публичной форме).
 *
 * @see SubmitPasswordResetRequestAction
 */
class PasswordResetRequestResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = PasswordResetRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Восстановление пароля';

    protected static ?int $navigationSort = 17;

    protected static string|UnitEnum|null $navigationGroup = 'Обращения';

    public static function getModelLabel(): string
    {
        return 'запрос на восстановление пароля';
    }

    public static function getPluralModelLabel(): string
    {
        return 'запросы на восстановление пароля';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PasswordResetRequest::pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Пользователь')
                    // Заявку может оставить кто угодно: email из формы может не
                    // принадлежать зарегистрированному пользователю.
                    ->placeholder('не найден в базе')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email скопирован'),

                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Телефон скопирован'),
                /*
                IconColumn::make('answer')
                    ->label('Отвечено')
                    ->boolean()
                    ->state(fn (PasswordResetRequest $record): bool => $record->isAnswered()),

                IconColumn::make('reset_link_sent_at')
                    ->label('Ссылка отправлена')
                    ->boolean()
                    ->state(fn (PasswordResetRequest $record): bool => $record->resetLinkSent()),
                */
                TextColumn::make('answeredBy.name')
                    ->label('Ответил')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Получен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('processed')
                    ->label('Статус обработки')
                    ->placeholder('Все')
                    ->trueLabel('Обработанные')
                    ->falseLabel('Необработанные')
                    ->queries(
                        true: fn (Builder $query) => $query->processed(),
                        false: fn (Builder $query) => $query->pending(),
                    ),
            ])
            ->emptyStateHeading('Запросов на восстановление пароля пока нет')
            ->recordActions([
                Action::make('answer')
                    ->label('Ответить')
                    ->icon(Heroicon::PencilSquare)
                    ->form([
                        Placeholder::make('requester')
                            ->label('Заявитель')
                            ->content(fn (PasswordResetRequest $record): string => trim(
                                ($record->requesterName() ?? 'Не найден в базе').' · '.$record->email.' · '.$record->phone,
                            )),

                        Textarea::make('answer')
                            ->label('Ответ')
                            ->placeholder('Текст письма заявителю')
                            ->rows(6)
                            ->required()
                            ->default(fn (PasswordResetRequest $record): ?string => $record->answer)
                            ->columnSpanFull(),
                    ])
                    ->modalHeading('Ответ на запрос восстановления пароля')
                    ->modalSubmitActionLabel('Отправить ответ')
                    ->action(function (PasswordResetRequest $record, array $data): void {
                        $record->update([
                            'answer' => $data['answer'],
                            'answered_at' => now(),
                            'answered_by' => auth()->id(),
                        ]);

                        try {
                            Mail::to($record->email)->send(new PasswordResetRequestAnswered($record));
                        } catch (\Exception $e) {
                            report($e);

                            // Ответ сохранён — сообщаем, что до заявителя он не дошёл,
                            // чтобы связались по телефону из заявки.
                            Notification::make()
                                ->title('Ответ сохранён, но письмо не отправлено')
                                ->body('Свяжитесь с заявителем по телефону: '.$record->phone)
                                ->warning()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Ответ отправлен на '.$record->email)
                            ->success()
                            ->send();
                    }),

                Action::make('sendResetLink')
                    ->label('Отправить ссылку')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('success')
                    // Ссылку шлёт стандартный брокер паролей — он работает только
                    // с зарегистрированным email.
                    ->visible(fn (PasswordResetRequest $record): bool => $record->user !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Отправить ссылку на смену пароля?')
                    ->modalDescription(fn (PasswordResetRequest $record): string => 'Письмо со ссылкой уйдёт на '.$record->email.'. Ссылка действует ограниченное время.')
                    ->modalSubmitActionLabel('Отправить')
                    ->action(function (PasswordResetRequest $record): void {
                        $status = Password::sendResetLink(['email' => $record->email]);

                        if ($status !== Password::RESET_LINK_SENT) {
                            Notification::make()
                                ->title('Не удалось отправить ссылку')
                                ->body(__($status))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['reset_link_sent_at' => now()]);

                        Notification::make()
                            ->title('Ссылка отправлена на '.$record->email)
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->modalHeading('Удалить запрос')
                    ->modalDescription('Вы уверены, что хотите удалить это обращение?')
                    ->modalSubmitActionLabel('Удалить')
                    ->hiddenLabel(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'answeredBy']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePasswordResetRequests::route('/'),
        ];
    }
}
