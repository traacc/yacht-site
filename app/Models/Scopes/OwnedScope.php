<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Скрывает яхты без владельца (user_id IS NULL) во всех запросах модели.
 *
 * Такие записи появляются при импорте из реестра Ассоциации, пока их не
 * «забрал» конкретный пользователь. Чтобы показать их в каком-то месте,
 * снимите scope через ->withoutGlobalScope(OwnedScope::class).
 */
class OwnedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNotNull($model->qualifyColumn('user_id'));
    }
}
