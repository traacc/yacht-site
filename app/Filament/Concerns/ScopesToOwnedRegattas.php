<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Ограничивает записи ресурса регатами, доступными текущему пользователю.
 *
 * Реально сужает выборку только для роли «Админ-разработчик» — см.
 *
 * @see \App\Models\Regatta::scopeVisibleForUser(). Для остальных ролей это no-op,
 * поэтому подключение трейта не меняет поведение существующей админки.
 *
 * getEloquentQuery() намеренно НЕ определён в трейте: часть ресурсов уже
 * переопределяет его сама, а метод класса молча побеждает метод трейта —
 * такие ресурсы остались бы без ограничения и без единой ошибки. Вместо этого
 * каждый ресурс вызывает scopeToOwnedRegattas() в своём override.
 */
trait ScopesToOwnedRegattas
{
    /**
     * Путь связи от модели ресурса до регаты; null — ресурс самой регаты.
     *
     * Метод, а не свойство: PHP запрещает переопределять свойство трейта
     * с другим значением по умолчанию.
     */
    protected static function regattaRelationPath(): ?string
    {
        return 'regatta';
    }

    /**
     * Ограничивает запрос регатами текущего пользователя.
     */
    public static function scopeToOwnedRegattas(Builder $query): Builder
    {
        $path = static::regattaRelationPath();

        if ($path === null) {
            return $query->visibleForUser();
        }

        return $query->whereHas(
            $path,
            fn (Builder $q) => $q->visibleForUser(),
        );
    }

    /**
     * Для modifyQueryUsing() у Select/SelectFilter, выбирающих регату.
     */
    public static function modifyRegattaQuery(Builder $query): Builder
    {
        return $query->visibleForUser();
    }
}
