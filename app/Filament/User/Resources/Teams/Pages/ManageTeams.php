<?php

namespace App\Filament\User\Resources\Teams\Pages;

use App\Actions\Team\AddMembersToTeamAction;
use App\Actions\Team\CreateTeamAction;
use App\Filament\User\Resources\Teams\TeamResource;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;

class ManageTeams extends ManageRecords
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Кнопка «Добавить участников» — только для капитана (организатора команды)
            Action::make('addMembers')
                ->label('Добавить участников')
                ->icon('heroicon-o-user-plus')
                ->color('white')
                ->visible(fn (): bool => auth()->user()->isCaptain())
                ->form(function (): array {
                    /** @var User $captain */
                    $captain = auth()->user();

                    // Команда капитана (первая активная)
                    $team = Team::where('organizer_id', $captain->id)->first();

                    if (!$team) {
                        return [
                            Select::make('user_ids')
                                ->label('Свободные участники')
                                ->disabled()
                                ->helperText('У вас нет зарегистрированной команды.'),
                        ];
                    }

                    $currentCount = $team->activeMembers()->count();
                    $available    = Team::MAX_MEMBERS - $currentCount;

                    // Свободные пользователи (не состоящие ни в одной активной команде),
                    // исключая самого капитана
                    $freeUsers = User::freeUsers()
                        ->where('id', '!=', $captain->id)
                        ->orderBy('last_name')
                        ->orderBy('first_name')
                        ->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name]);

                    return [
                        Select::make('team_id')
                            ->label('Команда')
                            ->options(
                                Team::where('organizer_id', $captain->id)
                                    ->pluck('name', 'id')
                            )
                            ->default($team->id)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) use ($captain) {
                                // При смене команды сбрасываем выбор участников
                                $set('user_ids', []);
                            }),

                        Select::make('user_ids')
                            ->label('Свободные участники')
                            ->options($freeUsers)
                            ->multiple()
                            ->searchable()
                            ->required()
                            ->minItems(1)
                            ->helperText(
                                $freeUsers->isEmpty()
                                    ? 'Нет свободных участников.'
                                    : "Доступно мест: {$available} из " . Team::MAX_MEMBERS . '.'
                            )
                            ->disabled($freeUsers->isEmpty() || $available <= 0),
                    ];
                })
                ->action(function (array $data): void {
                    /** @var User $captain */
                    $captain = auth()->user();

                    $team = Team::find($data['team_id']);

                    if (!$team || $team->organizer_id !== $captain->id) {
                        Notification::make()
                            ->title('Ошибка')
                            ->body('Вы не являетесь капитаном выбранной команды.')
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        $added = app(AddMembersToTeamAction::class)->handle(
                            $team,
                            $data['user_ids'],
                            $captain,
                        );

                        Notification::make()
                            ->title('Участники добавлены')
                            ->body("Добавлено участников: {$added->count()}.")
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        $message = collect($e->errors())->flatten()->first();

                        Notification::make()
                            ->title('Ошибка')
                            ->body($message)
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make()
                ->label('Зарегистрировать команду')
                ->modalHeading('Зарегистрировать команду')
                ->using(function (array $data): Team {
                    $data['approval_status'] = 'approved';

                    return app(CreateTeamAction::class)->handle(
                        $data,
                        auth()->user(),
                    );
                }),
        ];
    }
}
