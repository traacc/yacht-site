<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Безопасное удаление записей: вместо системной ошибки БД
 * (Integrity constraint violation: 1451) показывает понятное предупреждение.
 */
class SafeDelete
{
    /**
     * Удалить одну запись. При нарушении внешнего ключа показывает
     * предупреждение и останавливает действие.
     *
     * @param  string  $label  Название записи в винительном падеже, напр. «яхту».
     */
    public static function single(Model $record, Action $action, string $label = 'запись'): void
    {
        try {
            $record->forceDelete();
        } catch (QueryException $e) {
            if (! self::isForeignKeyViolation($e)) {
                throw $e;
            }

            self::notify(
                'Невозможно удалить',
                "Нельзя удалить эту {$label}: она связана с другими данными в системе. "
                . 'Сначала удалите или отвяжите связанные записи.'
            );

            $action->halt();
        }
    }

    /**
     * Массовое удаление. Записи без связей удаляются, для остальных
     * показывается предупреждение.
     *
     * @param  Collection<int, Model>  $records
     * @param  string  $labelPlural  Название записей во множественном числе, напр. «яхты».
     */
    public static function bulk(Collection $records, Action $action, string $labelPlural = 'записи'): void
    {
        $blocked = 0;

        foreach ($records as $record) {
            try {
                $record->forceDelete();
            } catch (QueryException $e) {
                if (! self::isForeignKeyViolation($e)) {
                    throw $e;
                }

                $blocked++;
            }
        }

        if ($blocked > 0) {
            self::notify(
                'Удалены не все записи',
                "Не удалось удалить {$blocked} ({$labelPlural} связаны с другими данными). "
                . 'Остальные записи удалены.'
            );

            $action->halt();
        }
    }

    /**
     * Является ли ошибка нарушением внешнего ключа (SQLSTATE 23000, MySQL 1451).
     */
    protected static function isForeignKeyViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000';
    }

    protected static function notify(string $title, string $body): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->persistent()
            ->send();
    }
}
