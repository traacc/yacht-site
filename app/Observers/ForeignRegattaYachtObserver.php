<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Service\AssignCharterYachtModelCountry;
use App\Models\ForeignRegattaDivision;
use App\Models\ForeignRegattaYacht;

/**
 * Достраивает данные лодки, которые админ не вводит руками.
 *
 * Регату — когда лодку добавляют репитером внутри дивизиона: там известен
 * только дивизион, а колонка обязательная.
 *
 * Страну базирования модели — от регаты, во флот которой лодку завели впервые
 * (@see AssignCharterYachtModelCountry).
 *
 * Регистрируется в AppServiceProvider:
 *   ForeignRegattaYacht::observe(ForeignRegattaYachtObserver::class);
 */
class ForeignRegattaYachtObserver
{
    public function __construct(private readonly AssignCharterYachtModelCountry $assignCountry) {}

    /** Лодку добавили в дивизион: регату берём у него, её в форме не спрашивают. */
    public function creating(ForeignRegattaYacht $yacht): void
    {
        if ($yacht->foreign_regatta_id !== null || $yacht->division_id === null) {
            return;
        }

        $yacht->foreign_regatta_id = ForeignRegattaDivision::query()
            ->whereKey($yacht->division_id)
            ->value('foreign_regatta_id');
    }

    public function saved(ForeignRegattaYacht $yacht): void
    {
        if ($yacht->yacht_model_id === null) {
            return;
        }

        $this->assignCountry->handle($yacht->yachtModel, $yacht->regatta?->country);
    }
}
