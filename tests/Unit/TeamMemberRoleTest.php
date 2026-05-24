<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TeamMemberRole;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тесты для TeamMemberRole.
 * Не требуют БД — тестируют только логику enum.
 */
final class TeamMemberRoleTest extends TestCase
{
    // ──────────────────────────────────────────────
    // allowedRolesFor
    // ──────────────────────────────────────────────

    public function test_manage_members_allowed_for_organizer_and_team_admin(): void
    {
        $allowed = TeamMemberRole::allowedRolesFor(TeamMemberRole::ACTION_MANAGE_MEMBERS);

        $this->assertContains(TeamMemberRole::Organizer, $allowed);
        $this->assertContains(TeamMemberRole::TeamAdmin, $allowed);
        $this->assertNotContains(TeamMemberRole::Member, $allowed);
    }

    public function test_submit_entry_allowed_for_organizer_and_team_admin(): void
    {
        $allowed = TeamMemberRole::allowedRolesFor(TeamMemberRole::ACTION_SUBMIT_ENTRY);

        $this->assertContains(TeamMemberRole::Organizer, $allowed);
        $this->assertContains(TeamMemberRole::TeamAdmin, $allowed);
        $this->assertNotContains(TeamMemberRole::Member, $allowed);
    }

    public function test_edit_team_allowed_for_organizer_and_team_admin(): void
    {
        $allowed = TeamMemberRole::allowedRolesFor(TeamMemberRole::ACTION_EDIT_TEAM);

        $this->assertContains(TeamMemberRole::Organizer, $allowed);
        $this->assertContains(TeamMemberRole::TeamAdmin, $allowed);
        $this->assertNotContains(TeamMemberRole::Member, $allowed);
    }

    public function test_archive_team_allowed_only_for_organizer(): void
    {
        $allowed = TeamMemberRole::allowedRolesFor(TeamMemberRole::ACTION_ARCHIVE_TEAM);

        $this->assertContains(TeamMemberRole::Organizer, $allowed);
        $this->assertNotContains(TeamMemberRole::TeamAdmin, $allowed);
        $this->assertNotContains(TeamMemberRole::Member, $allowed);
    }

    public function test_unknown_action_returns_empty_allowed_list(): void
    {
        $allowed = TeamMemberRole::allowedRolesFor('non_existent_action');

        $this->assertEmpty($allowed);
    }

    // ──────────────────────────────────────────────
    // canPerform — Organizer
    // ──────────────────────────────────────────────

    public function test_organizer_can_perform_manage_members(): void
    {
        $this->assertTrue(TeamMemberRole::Organizer->canPerform(TeamMemberRole::ACTION_MANAGE_MEMBERS));
    }

    public function test_organizer_can_perform_submit_entry(): void
    {
        $this->assertTrue(TeamMemberRole::Organizer->canPerform(TeamMemberRole::ACTION_SUBMIT_ENTRY));
    }

    public function test_organizer_can_perform_edit_team(): void
    {
        $this->assertTrue(TeamMemberRole::Organizer->canPerform(TeamMemberRole::ACTION_EDIT_TEAM));
    }

    public function test_organizer_can_perform_archive_team(): void
    {
        $this->assertTrue(TeamMemberRole::Organizer->canPerform(TeamMemberRole::ACTION_ARCHIVE_TEAM));
    }

    // ──────────────────────────────────────────────
    // canPerform — TeamAdmin
    // ──────────────────────────────────────────────

    public function test_team_admin_can_perform_manage_members(): void
    {
        $this->assertTrue(TeamMemberRole::TeamAdmin->canPerform(TeamMemberRole::ACTION_MANAGE_MEMBERS));
    }

    public function test_team_admin_can_perform_submit_entry(): void
    {
        $this->assertTrue(TeamMemberRole::TeamAdmin->canPerform(TeamMemberRole::ACTION_SUBMIT_ENTRY));
    }

    public function test_team_admin_can_perform_edit_team(): void
    {
        $this->assertTrue(TeamMemberRole::TeamAdmin->canPerform(TeamMemberRole::ACTION_EDIT_TEAM));
    }

    public function test_team_admin_cannot_perform_archive_team(): void
    {
        $this->assertFalse(TeamMemberRole::TeamAdmin->canPerform(TeamMemberRole::ACTION_ARCHIVE_TEAM));
    }

    // ──────────────────────────────────────────────
    // canPerform — Member
    // ──────────────────────────────────────────────

    public function test_member_cannot_perform_manage_members(): void
    {
        $this->assertFalse(TeamMemberRole::Member->canPerform(TeamMemberRole::ACTION_MANAGE_MEMBERS));
    }

    public function test_member_cannot_perform_submit_entry(): void
    {
        $this->assertFalse(TeamMemberRole::Member->canPerform(TeamMemberRole::ACTION_SUBMIT_ENTRY));
    }

    public function test_member_cannot_perform_edit_team(): void
    {
        $this->assertFalse(TeamMemberRole::Member->canPerform(TeamMemberRole::ACTION_EDIT_TEAM));
    }

    public function test_member_cannot_perform_archive_team(): void
    {
        $this->assertFalse(TeamMemberRole::Member->canPerform(TeamMemberRole::ACTION_ARCHIVE_TEAM));
    }

    public function test_member_cannot_perform_unknown_action(): void
    {
        $this->assertFalse(TeamMemberRole::Member->canPerform('non_existent_action'));
    }

    // ──────────────────────────────────────────────
    // Алиасы обратной совместимости
    // ──────────────────────────────────────────────

    public function test_can_manage_team_alias_matches_can_perform(): void
    {
        foreach (TeamMemberRole::cases() as $role) {
            $this->assertSame(
                $role->canPerform(TeamMemberRole::ACTION_MANAGE_MEMBERS),
                $role->canManageTeam(),
                "canManageTeam() не совпадает с canPerform() для роли {$role->value}",
            );
        }
    }

    public function test_can_submit_entry_alias_matches_can_perform(): void
    {
        foreach (TeamMemberRole::cases() as $role) {
            $this->assertSame(
                $role->canPerform(TeamMemberRole::ACTION_SUBMIT_ENTRY),
                $role->canSubmitEntry(),
                "canSubmitEntry() не совпадает с canPerform() для роли {$role->value}",
            );
        }
    }

    // ──────────────────────────────────────────────
    // label()
    // ──────────────────────────────────────────────

    public function test_labels_are_non_empty_strings(): void
    {
        foreach (TeamMemberRole::cases() as $role) {
            $this->assertNotEmpty($role->label());
        }
    }
}
