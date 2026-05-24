<?php

declare(strict_types=1);

namespace App\Enums;

enum TeamMemberRole: string
{
    case Organizer = 'organizer';
    case TeamAdmin = 'team_admin';
    case Member    = 'member';

    // ──────────────────────────────────────────────
    // Действия, для которых проверяются права
    // ──────────────────────────────────────────────

    /** Добавление / удаление участников команды */
    public const string ACTION_MANAGE_MEMBERS = 'manage_members';

    /** Подача заявки на регату */
    public const string ACTION_SUBMIT_ENTRY = 'submit_entry';

    /** Редактирование данных команды (название, описание, яхта) */
    public const string ACTION_EDIT_TEAM = 'edit_team';

    /** Архивирование / восстановление команды */
    public const string ACTION_ARCHIVE_TEAM = 'archive_team';

    // ──────────────────────────────────────────────
    // Матрица прав: действие → допустимые роли
    // ──────────────────────────────────────────────

    /**
     * Возвращает список ролей, которым разрешено выполнять действие.
     *
     * @return list<self>
     */
    public static function allowedRolesFor(string $action): array
    {
        return match ($action) {
            self::ACTION_MANAGE_MEMBERS => [self::Organizer, self::TeamAdmin],
            self::ACTION_SUBMIT_ENTRY   => [self::Organizer, self::TeamAdmin],
            self::ACTION_EDIT_TEAM      => [self::Organizer, self::TeamAdmin],
            self::ACTION_ARCHIVE_TEAM   => [self::Organizer],
            default                     => [],
        };
    }

    /**
     * Проверяет, может ли данная роль выполнить указанное действие.
     */
    public function canPerform(string $action): bool
    {
        return in_array($this, self::allowedRolesFor($action), strict: true);
    }

    // ──────────────────────────────────────────────
    // Удобные алиасы (обратная совместимость)
    // ──────────────────────────────────────────────

    public function canManageTeam(): bool
    {
        return $this->canPerform(self::ACTION_MANAGE_MEMBERS);
    }

    public function canSubmitEntry(): bool
    {
        return $this->canPerform(self::ACTION_SUBMIT_ENTRY);
    }

    // ──────────────────────────────────────────────
    // Метаданные
    // ──────────────────────────────────────────────

    public function label(): string
    {
        return match ($this) {
            self::Organizer => 'Организатор',
            self::TeamAdmin => 'Администратор',
            self::Member    => 'Участник',
        };
    }
}
