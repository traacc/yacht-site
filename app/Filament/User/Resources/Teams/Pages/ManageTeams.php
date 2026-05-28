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
            CreateAction::make()
                ->label('Зарегистрировать команду')
                ->modalHeading('Зарегистрировать команду')
                ->using(function (array $data): Team {
                    $data['approval_status'] = 'approved';

                    return app(CreateTeamAction::class)->handle(
                        $data,
                        auth()->user(),
                    );
                })->createAnother(false)->successNotification(
                Notification::make()
                    ->success()
                    ->title('Готово!')
                    ->body('Поздравляем с успешной регистрацией команды')),
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
