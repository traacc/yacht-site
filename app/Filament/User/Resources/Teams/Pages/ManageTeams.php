<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Teams\Pages;

use App\Actions\Team\InviteMemberFromOtherTeamAction;
use App\Enums\TeamMemberInvitationStatus;
use App\Enums\TeamMemberRole;
use App\Filament\User\Resources\Teams\TeamResource;
use App\Models\Team;
use App\Models\TeamMemberInvitation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

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

                    // Исключаем teamMembers — участники уже создаются в CreateTeamAction,
                    // иначе Filament повторно сохранит relationship-данные из Repeater.
                    unset($data['teamMembers']);

                    return app(\App\Actions\Team\CreateTeamAction::class)->handle(
                        $data,
                        auth()->user(),
                    );
                })->createAnother(false)->successNotification(
                Notification::make()
                    ->success()
                    ->title('Готово!')
                    ->body('Поздравляем с успешной регистрацией команды')),

            $this->inviteMemberAction(),

            ...$this->incomingInvitationActions(),
        ];
    }

    /**
     * Действие капитана: запросить приглашение постоянного участника из другой команды.
     */
    private function inviteMemberAction(): Action
    {
        return Action::make('inviteMember')
            ->label('Пригласить участника из другой команды')
            ->icon('heroicon-o-user-plus')
            ->color('white')
            ->visible(fn (): bool => $this->managedTeamsQuery()->exists())
            ->modalHeading('Пригласить участника из другой команды')
            ->modalSubmitActionLabel('Отправить запрос')
            ->schema([
                Placeholder::make('invite_note')
                    ->hiddenLabel()
                    ->content(
                        'Выберите свою команду и участника, который сейчас является постоянным '
                        . 'участником другой команды. Запрос придёт ему на одобрение; после '
                        . 'согласия ваша команда станет его главной командой.'
                    )
                    ->columnSpanFull(),

                Select::make('team_id')
                    ->label('Ваша команда')
                    ->options(fn (): array => $this->managedTeamsQuery()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn () => $this->managedTeamsQuery()->count() === 1
                        ? $this->managedTeamsQuery()->value('id')
                        : null)
                    ->required()
                    ->live()
                    ->searchable()
                    ->columnSpanFull(),

                Select::make('user_id')
                    ->label('Участник')
                    ->placeholder('Выберите участника другой команды')
                    ->options(fn (Get $get): array => $this->invitableUsersQuery($get('team_id'))
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => $this
                        ->invitableUsersQuery($get('team_id'))
                        ->where('name', 'like', "%{$search}%")
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => User::query()->whereKey($value)->value('name'))
                    ->required()
                    ->searchable()
                    ->columnSpanFull(),

                Textarea::make('message')
                    ->label('Сообщение участнику')
                    ->placeholder('Необязательно')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $team = Team::query()->findOrFail($data['team_id']);
                $user = User::query()->findOrFail($data['user_id']);

                app(InviteMemberFromOtherTeamAction::class)->handle(
                    $team,
                    $user,
                    auth()->user(),
                    $data['message'] ?? null,
                );

                Notification::make()
                    ->success()
                    ->title('Запрос отправлен')
                    ->body("Участник «{$user->name}» получит запрос на смену главной команды.")
                    ->send();
            });
    }

    /**
     * Действия участника: ответить на входящие приглашения сменить главную команду.
     *
     * @return array<int, Action>
     */
    private function incomingInvitationActions(): array
    {
        $invitations = TeamMemberInvitation::query()
            ->with(['team', 'requester'])
            ->where('user_id', auth()->id())
            ->where('status', TeamMemberInvitationStatus::Pending->value)
            ->latest()
            ->get();

        return $invitations->map(function (TeamMemberInvitation $invitation): Action {
            $teamName = $invitation->team?->name ?? 'команду';

            return Action::make('invitation_' . $invitation->getKey())
                ->label("Приглашение: {$teamName}")
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->badge('новое')
                ->modalHeading("Приглашение в команду «{$teamName}»")
                ->modalSubmitActionLabel('Подтвердить')
                ->schema([
                    Placeholder::make('invitation_info')
                        ->hiddenLabel()
                        ->content(
                            "Капитан «{$invitation->requester?->name}» приглашает вас в команду «{$teamName}». "
                            . 'Если вы одобрите запрос, эта команда станет вашей главной командой.'
                            . ($invitation->message ? "\n\nСообщение: {$invitation->message}" : '')
                        )
                        ->columnSpanFull(),

                    Radio::make('decision')
                        ->hiddenLabel()
                        ->options([
                            'approve' => 'Одобрить и сменить главную команду',
                            'reject'  => 'Отклонить',
                        ])
                        ->default('approve')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) use ($invitation): void {
                    // Перечитываем актуальный статус — приглашение могло быть отозвано/обработано
                    $fresh = TeamMemberInvitation::query()->find($invitation->getKey());

                    if ($fresh === null || ! $fresh->isPending()) {
                        Notification::make()
                            ->warning()
                            ->title('Приглашение уже неактуально')
                            ->send();

                        return;
                    }

                    if (($data['decision'] ?? null) === 'approve') {
                        $fresh->approve();

                        Notification::make()
                            ->success()
                            ->title('Главная команда изменена')
                            ->body("Теперь ваша главная команда — «{$fresh->team?->name}».")
                            ->send();

                        return;
                    }

                    $fresh->reject();

                    Notification::make()
                        ->title('Приглашение отклонено')
                        ->send();
                });
        })->all();
    }

    /**
     * Команды, в которых текущий пользователь может управлять участниками
     * (роль Organizer или TeamAdmin среди активных участников).
     */
    private function managedTeamsQuery(): Builder
    {
        $userId = auth()->id();

        return Team::query()
            ->whereHas('teamMembers', fn (Builder $q): Builder => $q
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->whereIn('role', [
                    TeamMemberRole::Organizer->value,
                    TeamMemberRole::TeamAdmin->value,
                ]));
    }

    /**
     * Пользователи, которых можно пригласить: постоянные участники другой команды
     * (не целевой) и не текущий капитан.
     */
    private function invitableUsersQuery(?string $targetTeamId): Builder
    {
        return User::query()
            ->whereKeyNot(auth()->id())
            ->whereHas('teamMemberships', fn (Builder $q): Builder => $q
                ->where('is_permanent', true)
                ->when($targetTeamId, fn (Builder $q): Builder => $q->where('team_id', '!=', $targetTeamId)));
    }
}
