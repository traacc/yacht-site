<?php

declare(strict_types=1);

namespace App\Actions\RegattaEntry;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentRegistry;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use Illuminate\Validation\ValidationException;

/**
 * Счёт за береговую программу заявки: цена за человека × число человек.
 *
 * Сбор живёт отдельной записью реестра (назначение «Береговая программа»),
 * чтобы не смешиваться со стартовым взносом ни в отметке fee_paid, ни в отчётах.
 * Число человек можно менять и после подачи, поэтому счёт пересчитывается:
 *
 *  — пока не оплачено — меняется сумма открытой записи;
 *  — после оплаты добавили людей — выставляется доплата на разницу;
 *  — людей стало меньше после оплаты — ничего не делаем: порядок возврата
 *    не определён, деньги разбирает бухгалтер вручную.
 *
 * Запись, по которой плательщик уже ушёл на страницу банка (есть pending-
 * транзакция), не редактируется: банк спишет прежнюю сумму, и запись должна
 * ей соответствовать. Её сумма считается внесённой, разница — новой записью.
 *
 * Вызывается из RegattaEntryShoreProgramObserver при смене числа человек.
 */
final class SyncShoreProgramPaymentAction
{
    public function handle(RegattaEntry $entry): void
    {
        $regatta = $entry->regatta;
        $persons = (int) $entry->shore_program_persons;
        $target = $regatta?->shoreProgramPrice($persons) ?? 0.0;

        $registries = $entry->paymentRegistries()
            ->where('purpose', PaymentPurpose::ShoreProgram->value)
            ->with('transactions')
            ->oldest()
            ->get();

        [$editable, $locked] = $registries
            ->filter(fn (PaymentRegistry $registry): bool => $registry->status !== PaymentStatus::Canceled)
            ->partition(fn (PaymentRegistry $registry): bool => $this->isAwaiting($registry)
                && ! $this->hasPaymentInProgress($registry));

        // Оплачено (полностью или частично) либо оплачивается прямо сейчас.
        $committed = (float) $locked->sum(fn (PaymentRegistry $registry): float => (float) $registry->amount);
        $due = round($target - $committed, 2);

        $current = $editable->shift();

        // Лишние открытые записи (например, после ручных правок) не нужны —
        // к оплате всегда одна сумма.
        foreach ($editable as $extra) {
            $extra->update(['status' => PaymentStatus::Canceled]);
        }

        if ($due <= 0) {
            $current?->update(['status' => PaymentStatus::Canceled]);

            return;
        }

        $name = $this->registryName($entry, $regatta, $persons, isTopUp: $committed > 0);

        if ($current === null) {
            $entry->paymentRegistries()->create([
                'name' => $name,
                'amount' => $due,
                'purpose' => PaymentPurpose::ShoreProgram,
                'status' => PaymentStatus::Pending,
            ]);

            return;
        }

        $current->fill(['name' => $name, 'amount' => $due]);

        if ($current->isDirty()) {
            $current->save();
        }
    }

    /**
     * Проверка числа человек при подаче и правке заявки.
     *
     * Если у регаты есть сбор — нужен хотя бы один человек и не больше,
     * чем людей в экипаже. Без сбора значение не хранится.
     *
     * @param  int  $crewSize  Сколько людей в заявке (для индивидуальной — мест).
     *
     * @throws ValidationException
     */
    public static function validatePersons(Regatta $regatta, mixed $persons, int $crewSize, string $field = 'shoreProgramPersons'): ?int
    {
        if (! $regatta->hasShoreProgramFee()) {
            return null;
        }

        $persons = is_numeric($persons) ? (int) $persons : 0;

        if ($persons < 1) {
            throw ValidationException::withMessages([
                $field => 'Укажите, за сколько человек оплачивается береговая программа — хотя бы за одного.',
            ]);
        }

        if ($persons > max($crewSize, 1)) {
            throw ValidationException::withMessages([
                $field => "Береговая программа — не больше, чем людей в экипаже ({$crewSize}).",
            ]);
        }

        return $persons;
    }

    private function isAwaiting(PaymentRegistry $registry): bool
    {
        return in_array($registry->status, [PaymentStatus::Pending, PaymentStatus::Overdue], true);
    }

    private function hasPaymentInProgress(PaymentRegistry $registry): bool
    {
        return $registry->transactions->contains(
            fn ($transaction): bool => $transaction->status === PaymentTransactionStatus::Pending,
        );
    }

    private function registryName(RegattaEntry $entry, ?Regatta $regatta, int $persons, bool $isTopUp): string
    {
        $what = $isTopUp
            ? "Береговая программа, доплата (всего {$persons} чел.)"
            : "Береговая программа ({$persons} чел.)";

        return "{$what} — {$regatta?->name} ({$entry->participantName()})";
    }
}
