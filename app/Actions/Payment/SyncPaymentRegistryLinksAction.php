<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentPurpose;
use App\Models\PaymentRegistry;
use App\Models\RegattaEntry;
use App\Models\Team;

/**
 * Заполняет денормализованные поля платежа (регата, яхта, команда, назначение,
 * плательщик) по связанному источнику — для группировки и фильтров в реестре.
 *
 * Модель НЕ сохраняется: вызывающий сам решает, писать через save() (с журналом)
 * или saveQuietly() (технический бэкфилл).
 */
final class SyncPaymentRegistryLinksAction
{
    /**
     * @param  bool  $force  Перезаписать назначение и плательщика, даже если уже заполнены.
     * @return bool Были ли изменены атрибуты.
     */
    public function handle(PaymentRegistry $registry, bool $force = false): bool
    {
        $payable = $registry->payable;

        // Ручной платёж без источника: связи под ручным управлением из формы,
        // иначе нельзя было бы отнести наличные к регате.
        if ($payable === null) {
            return false;
        }

        $before = $registry->only(['regatta_id', 'yacht_id', 'team_id', 'purpose', 'payer_name']);

        if ($payable instanceof RegattaEntry) {
            // Берём колонки заявки, не загружая модель яхты, — глобальный
            // OwnedScope на Yacht тогда вообще не при делах.
            $registry->regatta_id = $payable->regatta_id;
            $registry->yacht_id = $payable->yacht_id;
            $registry->team_id = $payable->team_id;
        } elseif ($payable instanceof Team) {
            $registry->regatta_id = null;
            $registry->yacht_id = null;
            $registry->team_id = $payable->getKey();
        }

        if ($force || $registry->purpose === null) {
            $registry->purpose = PaymentPurpose::defaultForPayable($payable) ?? $registry->purpose;
        }

        if ($force || blank($registry->payer_name)) {
            $payerName = $this->resolvePayerName($registry, $payable);

            if ($payerName !== null) {
                $registry->payer_name = $payerName;
            }
        }

        return $before !== $registry->only(['regatta_id', 'yacht_id', 'team_id', 'purpose', 'payer_name']);
    }

    /** ФИО плательщика: организатор команды, иначе — пользователь успешной онлайн-оплаты. */
    private function resolvePayerName(PaymentRegistry $registry, object $payable): ?string
    {
        $team = match (true) {
            $payable instanceof RegattaEntry => $payable->team,
            $payable instanceof Team => $payable,
            default => null,
        };

        if ($team?->organizer?->name) {
            return $team->organizer->name;
        }

        return $registry->transactions()
            ->whereNotNull('user_id')
            ->latest()
            ->first()?->user?->name;
    }
}
