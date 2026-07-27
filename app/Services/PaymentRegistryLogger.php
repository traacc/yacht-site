<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRegistryLogEvent;
use App\Enums\PaymentStatus;
use App\Models\PaymentRegistry;
use App\Models\PaymentRegistryLog;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Ведение журнала изменений реестра платежей.
 *
 * Регистрируется синглтоном в AppServiceProvider: withoutAutoLog() опирается
 * на состояние экземпляра, и без синглтона глушение не сработает.
 */
class PaymentRegistryLogger
{
    /** Поля реестра, попадающие в журнал: колонка → подпись в логе. */
    public const TRACKED = [
        'name' => 'Название',
        'amount' => 'Сумма',
        'status' => 'Статус оплаты',
        'payment_method' => 'Способ оплаты',
        'paid_at' => 'Дата оплаты',
        'document' => 'Документ',
        'payable_type' => 'Источник платежа',
        'payable_id' => 'Источник платежа',
        'confirmed_at' => 'Подтверждение прихода',
        'confirmed_by' => 'Подтвердил',
        'deleted_at' => 'Удаление',
    ];

    /** Автоматическое логирование из обсервера временно отключено. */
    private bool $muted = false;

    // ──────────────────────────────────────────────
    // События
    // ──────────────────────────────────────────────

    public function created(PaymentRegistry $registry): PaymentRegistryLog
    {
        return $this->record($registry, PaymentRegistryLogEvent::Created);
    }

    /** Возвращает null, если значимых изменений не было. */
    public function updated(PaymentRegistry $registry): ?PaymentRegistryLog
    {
        $changes = $this->diff($registry);

        if ($changes === []) {
            return null;
        }

        return $this->record($registry, PaymentRegistryLogEvent::Updated, $changes);
    }

    /**
     * Записать изменение, выполненное «тихо» (updateQuietly/saveQuietly или
     * Query Builder). События модели в таких случаях не срабатывают, а
     * getOriginal() к моменту вызова уже содержит новые значения — поэтому
     * старые значения передаются явно.
     *
     * @param  array<string, mixed>  $before  [колонка => значение до изменения]
     */
    public function updatedQuietly(PaymentRegistry $registry, array $before): ?PaymentRegistryLog
    {
        $changes = [];

        foreach ($before as $field => $old) {
            if (! array_key_exists($field, self::TRACKED)) {
                continue;
            }

            $new = $registry->getAttribute($field);

            if ($this->rawValue($old) === $this->rawValue($new)) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => self::TRACKED[$field],
                'old' => $this->rawValue($old),
                'new' => $this->rawValue($new),
                'old_label' => $this->presentValue($field, $old),
                'new_label' => $this->presentValue($field, $new),
            ];
        }

        if ($changes === []) {
            return null;
        }

        return $this->record($registry, PaymentRegistryLogEvent::Updated, $changes);
    }

    public function deleting(PaymentRegistry $registry): PaymentRegistryLog
    {
        return $this->record($registry, PaymentRegistryLogEvent::Deleted);
    }

    public function restored(PaymentRegistry $registry): PaymentRegistryLog
    {
        return $this->record($registry, PaymentRegistryLogEvent::Restored);
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    public function record(
        PaymentRegistry $registry,
        PaymentRegistryLogEvent $event,
        array $changes = [],
    ): PaymentRegistryLog {
        $actor = $this->actor();

        return PaymentRegistryLog::create([
            'payment_registry_id' => $registry->getKey(),
            'registry_name' => (string) $registry->name,
            'registry_amount' => $registry->amount,
            'user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'event' => $event,
            'changed_fields' => $changes !== [] ? $changes : null,
            'ip' => $this->ip(),
        ]);
    }

    // ──────────────────────────────────────────────
    // Diff
    // ──────────────────────────────────────────────

    /**
     * Изменения отслеживаемых полей: и сырые значения (переживают
     * переименование подписей), и подписи (переживают удаление кейса enum).
     *
     * @return list<array{field: string, label: string, old: mixed, new: mixed, old_label: string, new_label: string}>
     */
    public function diff(PaymentRegistry $registry): array
    {
        $changed = array_intersect_key($registry->getChanges(), self::TRACKED);

        $result = [];

        foreach ($changed as $field => $new) {
            $old = $registry->getOriginal($field);

            // Сравниваем нормализованные значения: getChanges() может отдать
            // enum/Carbon, а getOriginal() — сырую строку из БД.
            if ($this->rawValue($old) === $this->rawValue($new)) {
                continue;
            }

            $result[] = [
                'field' => $field,
                'label' => self::TRACKED[$field],
                'old' => $this->rawValue($old),
                'new' => $this->rawValue($new),
                'old_label' => $this->presentValue($field, $old),
                'new_label' => $this->presentValue($field, $new),
            ];
        }

        return $result;
    }

    // ──────────────────────────────────────────────
    // Глушение авто-лога
    // ──────────────────────────────────────────────

    /**
     * Выполнить колбэк без автоматической записи из обсервера — для действий,
     * которые пишут собственное семантическое событие (например подтверждение).
     */
    public function withoutAutoLog(Closure $callback): mixed
    {
        $previous = $this->muted;
        $this->muted = true;

        try {
            return $callback();
        } finally {
            $this->muted = $previous;
        }
    }

    public function isMuted(): bool
    {
        return $this->muted;
    }

    // ──────────────────────────────────────────────
    // Internals
    // ──────────────────────────────────────────────

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function ip(): ?string
    {
        // В консоли и очередях request()->ip() вернёт мусор.
        return app()->runningInConsole() ? null : request()->ip();
    }

    /**
     * Приведение к скаляру: getChanges() может отдать enum-объект или Carbon,
     * если каст уже применён к атрибуту.
     */
    private function rawValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        return is_scalar($value) || $value === null ? $value : (string) $value;
    }

    /** Человекочитаемое значение поля. */
    private function presentValue(string $field, mixed $value): string
    {
        $value = $this->rawValue($value);

        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field) {
            'status' => PaymentStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            'payment_method' => $this->methodLabel((string) $value),
            'amount' => number_format((float) $value, 2, ',', ' ').' ₽',
            'paid_at', 'confirmed_at', 'deleted_at' => $this->dateLabel($value),
            'confirmed_by' => User::query()->find($value)?->name ?? (string) $value,
            'payable_type' => class_basename((string) $value),
            default => (string) $value,
        };
    }

    private function methodLabel(string $value): string
    {
        $method = PaymentMethod::tryFrom($value);

        if ($method === null) {
            return $value;
        }

        // В логе сразу видно, наличные это или безнал.
        return $method->label().' ('.mb_strtolower($method->settlement()->label()).')';
    }

    private function dateLabel(mixed $value): string
    {
        try {
            return Carbon::parse($value)->format('d.m.Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
