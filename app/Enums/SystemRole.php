<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SystemRole: string implements HasLabel
{
    case User = 'user';
    case Admin = 'admin';
    case Judge = 'judge';
    case Secretary = 'secretary';
    case Accountant = 'accountant';
    case DeveloperAdmin = 'developer_admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::User => 'Участник',
            self::Admin => 'Администратор',
            self::Judge => 'Судья',
            self::Secretary => 'Секретарь',
            self::Accountant => 'Бухгалтер',
            self::DeveloperAdmin => 'Админ-разработчик',
        };
    }

    public function canManageRegattas(): bool
    {
        return in_array($this, [self::Admin, self::Secretary, self::DeveloperAdmin]);
    }

    public function canEnterResults(): bool
    {
        return in_array($this, [self::Admin, self::Judge, self::Secretary, self::DeveloperAdmin]);
    }

    public function canPublishNews(): bool
    {
        return in_array($this, [self::Admin, self::Secretary]);
    }

    public function canApproveEntries(): bool
    {
        return in_array($this, [self::Admin, self::Secretary, self::DeveloperAdmin]);
    }

    /**
     * Доступ к финансовому контуру: реестр платежей, подтверждение прихода,
     * журнал изменений и его выгрузка.
     */
    public function canManagePayments(): bool
    {
        return in_array($this, [self::Admin, self::Accountant]);
    }
}
