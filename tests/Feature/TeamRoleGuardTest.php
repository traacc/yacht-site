<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TeamMemberRole;
use App\Exceptions\InsufficientTeamRoleException;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamRoleGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-тесты для TeamRoleGuard.
 * Требуют БД — проверяют реальные запросы через Eloquent.
 */
final class TeamRoleGuardTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // roleOf()
    // ──────────────────────────────────────────────

    public function test_role_of_returns_organizer_for_active_organizer(): void
    {
        [$team, $user] = $this->makeTeamWithMember('organizer');

        $this->assertSame(TeamMemberRole::Organizer, TeamRoleGuard::roleOf($team, $user));
    }

    public function test_role_of_returns_team_admin_for_active_team_admin(): void
    {
        [$team, $user] = $this->makeTeamWithMember('team_admin');

        $this->assertSame(TeamMemberRole::TeamAdmin, TeamRoleGuard::roleOf($team, $user));
    }

    public function test_role_of_returns_member_for_active_member(): void
    {
        [$team, $user] = $this->makeTeamWithMember('member');

        $this->assertSame(TeamMemberRole::Member, TeamRoleGuard::roleOf($team, $user));
    }

    public function test_role_of_returns_null_for_non_member(): void
    {
        $team      = Team::factory()->create();
        $outsider  = User::factory()->create();

        $this->assertNull(TeamRoleGuard::roleOf($team, $outsider));
    }

    public function test_role_of_returns_null_for_invited_but_not_active_member(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();

        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role'    => 'member',
            'status'  => 'invited',
        ]);

        $this->assertNull(TeamRoleGuard::roleOf($team, $user));
    }

    public function test_role_of_returns_null_for_declined_member(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();

        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role'    => 'member',
            'status'  => 'declined',
        ]);

        $this->assertNull(TeamRoleGuard::roleOf($team, $user));
    }

    // ──────────────────────────────────────────────
    // check()
    // ──────────────────────────────────────────────

    public function test_check_returns_true_for_organizer_manage_members(): void
    {
        [$team, $user] = $this->makeTeamWithMember('organizer');

        $this->assertTrue(TeamRoleGuard::check($team, $user, TeamMemberRole::ACTION_MANAGE_MEMBERS));
    }

    public function test_check_returns_true_for_team_admin_submit_entry(): void
    {
        [$team, $user] = $this->makeTeamWithMember('team_admin');

        $this->assertTrue(TeamRoleGuard::check($team, $user, TeamMemberRole::ACTION_SUBMIT_ENTRY));
    }

    public function test_check_returns_false_for_member_manage_members(): void
    {
        [$team, $user] = $this->makeTeamWithMember('member');

        $this->assertFalse(TeamRoleGuard::check($team, $user, TeamMemberRole::ACTION_MANAGE_MEMBERS));
    }

    public function test_check_returns_false_for_team_admin_archive_team(): void
    {
        [$team, $user] = $this->makeTeamWithMember('team_admin');

        $this->assertFalse(TeamRoleGuard::check($team, $user, TeamMemberRole::ACTION_ARCHIVE_TEAM));
    }

    public function test_check_returns_false_for_non_member(): void
    {
        $team     = Team::factory()->create();
        $outsider = User::factory()->create();

        $this->assertFalse(TeamRoleGuard::check($team, $outsider, TeamMemberRole::ACTION_MANAGE_MEMBERS));
    }

    // ──────────────────────────────────────────────
    // authorize() — успешные сценарии
    // ──────────────────────────────────────────────

    public function test_authorize_does_not_throw_for_organizer_manage_members(): void
    {
        [$team, $user] = $this->makeTeamWithMember('organizer');

        $this->expectNotToPerformAssertions();
        TeamRoleGuard::authorize($team, $user, TeamMemberRole::ACTION_MANAGE_MEMBERS);
    }

    public function test_authorize_does_not_throw_for_team_admin_submit_entry(): void
    {
        [$team, $user] = $this->makeTeamWithMember('team_admin');

        $this->expectNotToPerformAssertions();
        TeamRoleGuard::authorize($team, $user, TeamMemberRole::ACTION_SUBMIT_ENTRY);
    }

    public function test_authorize_does_not_throw_for_organizer_archive_team(): void
    {
        [$team, $user] = $this->makeTeamWithMember('organizer');

        $this->expectNotToPerformAssertions();
        TeamRoleGuard::authorize($team, $user, TeamMemberRole::ACTION_ARCHIVE_TEAM);
    }

    // ──────────────────────────────────────────────
    // authorize() — сценарии отказа
    // ──────────────────────────────────────────────

    public function test_authorize_throws_for_member_manage_members(): void
    {
        [$team, $user] = $this->makeTeamWithMember('member');

        $this->expectException(InsufficientTeamRoleException::class);
        TeamRoleGuard::authorize($team, $user, TeamMemberRole::ACTION_MANAGE_MEMBERS);
    }

    public function test_authorize_throws_for_member_submit_entry(): void
    {
        [$team, $user] = $this->makeTeamWithMember('member');

        $this->expectException(InsufficientTeamRoleException::class);
        TeamRoleGuard::authorize($team, $user, TeamMemberRole::ACTION_SUBMIT_ENTRY);
    }

    public function test_authorize_throws_for_team_admin_archive_team(): void
    {
        [$team, $user] = $this->makeTeamWithMember('team_admin');

        $this->expectException(InsufficientTeamRoleException::class);
        TeamRoleGuard::authorize($team, $user, TeamMemberRole::ACTION_ARCHIVE_TEAM);
    }

    public function test_authorize_throws_for_non_member(): void
    {
        $team     = Team::factory()->create();
        $outsider = User::factory()->create();

        $this->expectException(InsufficientTeamRoleException::class);
        TeamRoleGuard::authorize($team, $outsider, TeamMemberRole::ACTION_MANAGE_MEMBERS);
    }

    public function test_authorize_exception_carries_action_and_actual_role(): void
    {
        [$team, $user] = $this->makeTeamWithMember('member');

        try {
            TeamRoleGuard::authorize($team, $user, TeamMemberRole::ACTION_ARCHIVE_TEAM);
            $this->fail('Ожидалось исключение InsufficientTeamRoleException');
        } catch (InsufficientTeamRoleException $e) {
            $this->assertSame(TeamMemberRole::ACTION_ARCHIVE_TEAM, $e->action);
            $this->assertSame(TeamMemberRole::Member, $e->actualRole);
        }
    }

    public function test_authorize_exception_carries_null_role_for_non_member(): void
    {
        $team     = Team::factory()->create();
        $outsider = User::factory()->create();

        try {
            TeamRoleGuard::authorize($team, $outsider, TeamMemberRole::ACTION_MANAGE_MEMBERS);
            $this->fail('Ожидалось исключение InsufficientTeamRoleException');
        } catch (InsufficientTeamRoleException $e) {
            $this->assertSame(TeamMemberRole::ACTION_MANAGE_MEMBERS, $e->action);
            $this->assertNull($e->actualRole);
        }
    }

    // ──────────────────────────────────────────────
    // Вспомогательные методы
    // ──────────────────────────────────────────────

    /**
     * Создаёт команду и активного участника с указанной ролью.
     *
     * @return array{Team, User}
     */
    private function makeTeamWithMember(string $role): array
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();

        TeamMember::factory()->create([
            'team_id'   => $team->id,
            'user_id'   => $user->id,
            'role'      => $role,
            'status'    => 'active',
            'joined_at' => now(),
        ]);

        return [$team, $user];
    }
}
