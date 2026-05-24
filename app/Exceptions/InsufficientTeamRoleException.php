<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TeamMemberRole;
use RuntimeException;

final class InsufficientTeamRoleException extends RuntimeException
{
    public function __construct(
        public readonly string $action,
        public readonly ?TeamMemberRole $actualRole,
        string $message = '',
    ) {
        parent::__construct(
            $message ?: self::buildMessage($action, $actualRole),
        );
    }

    private static function buildMessage(string $action, ?TeamMemberRole $actualRole): string
    {
        $allowed = array_map(
            fn (TeamMemberRole $r) => $r->label(),
            TeamMemberRole::allowedRolesFor($action),
        );

        $allowedStr = implode(', ', $allowed) ?: 'никому';
        $actualStr  = $actualRole?->label() ?? 'нет роли';

        return "Действие «{$action}» доступно только: {$allowedStr}. Ваша роль: {$actualStr}.";
    }
}
