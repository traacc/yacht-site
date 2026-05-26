<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Teams\Pages;

use App\Actions\Team\AddMembersToTeamAction;
use App\Actions\Team\CreateTeamAction;
use App\Enums\TeamMemberRole;
use App\Exceptions\InsufficientTeamRoleException;
use App\Filament\User\Resources\Teams\TeamResource;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleGuard;
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
            // Кнопка «Добавить участников» — для Organizer и TeamAdmin
            Action::make('addMembers')
                ->label('Добавить участников')
                ->icon('heroicon-o-user-plus')
                ->color('white')
                ->visible(fn (): bool => $this->userCanManageMembers())
                ->form(function (): array {
                    /** @var User $actor */
                    $actor = auth()->user();

                    // Команды, в которых пользователь имеет право управлять участниками
                    $manageableTeams = Team::query()
                        ->whereHas('teamMembers', fn ($q) => $q
                            ->where('user_id', $actor->id)
                            ->where('status', 'active')
                            ->whereIn('role', [
                                TeamMemberRole::Organizer->value,
                                TeamMemberRole::TeamAdmin->value,
                            ])
                        )
                        ->pluck('name', 'id');

                    if ($manageableTeams->isEmpty()) {
                        return [
                            Select::make('user_ids')
                                ->label('Свободные участники')
                                ->disabled()
                                ->helperText('У вас нет команды с правами управления участниками.'),
                        ];
                    }

                    $firstTeamId  = $manageableTeams->keys()->first();
                    $firstTeam    = Team::find($firstTeamId);
                    $currentCount = $firstTeam?->activeMembers()->count() ?? 0;
                    $available    = Team::MAX_MEMBERS - $currentCount;

                    $freeUsers = User::freeUsers()
                        ->where('id', '!=', $actor->id)
                        ->orderBy('last_name')
                        ->orderBy('first_name')
                        ->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name]);

                    return [
                        Select::make('team_id')
                            ->label('Команда')
                            ->options($manageableTeams)
                            ->default($firstTeamId)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('user_ids', [])),

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
                    /** @var User $actor */
                    $actor = auth()->user();
                    $team  = Team::find($data['team_id']);

                    if (! $team) {
                        Notification::make()
                            ->title('Ошибка')
                            ->body('Команда не найдена.')
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        $added = app(AddMembersToTeamAction::class)->handle(
                            $team,
                            $data['user_ids'],
                            $actor,
                        );

                        Notification::make()
                            ->title('Участники добавлены')
                            ->body("Добавлено участников: {$added->count()}.")
                            ->success()
                            ->send();
                    } catch (InsufficientTeamRoleException $e) {
                        Notification::make()
                            ->title('Недостаточно прав')
                            ->body($e->getMessage())
                            ->danger()
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
                })->createAnother(false),
        ];
    }

    /**
     * Проверяет, есть ли у текущего пользователя хотя бы одна команда,
     * в которой он имеет право управлять участниками.
     */
    private function userCanManageMembers(): bool
    {
        $userId = auth()->id();

        return Team::query()
            ->whereHas('teamMembers', fn ($q) => $q
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->whereIn('role', [
                    TeamMemberRole::Organizer->value,
                    TeamMemberRole::TeamAdmin->value,
                ])
            )
            ->exists();
    }
}
