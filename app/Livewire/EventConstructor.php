<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Service\SubmitServiceRequestAction;
use App\Enums\ServiceForm;
use App\Enums\ServiceType;
use App\Models\Yacht;
use App\Services\EventPlanner;
use App\Support\Plural;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * «Конструктор мероприятия» на /services/events (ТЗ 3-го этапа, п. 7).
 *
 * Три шага: параметры ивента → подбор флота и расчёт → контакты. Заказчик
 * перечисляет возможные даты, конструктор считает по каждой свой вариант и
 * предлагает самый дешёвый из тех, где яхт хватает; если яхт меньше, чем
 * нужно, он об этом говорит, но заявку принять не мешает — подберём вручную.
 *
 * Логики подбора и цен здесь нет: этим занят @see EventPlanner, а отправкой —
 * общий SubmitServiceRequestAction. Компонент только ведёт по шагам и
 * складывает результат в payload заявки — набор его полей описан там же, где
 * поля остальных форм услуг (@see ServiceType::payloadFields()).
 */
class EventConstructor extends Component
{
    /** Свой вариант активности вместо списка из админки. */
    public const ACTIVITY_OTHER = 'other';

    /** Текущий шаг: params → fleet → contacts → done. */
    public string $step = 'params';

    // ── Параметры мероприятия (12 пунктов ТЗ) ──

    public string $eventName = '';

    public string $format = '';

    public string $details = '';

    /** Активность на воде: заголовок из списка либо ACTIVITY_OTHER. */
    public string $activity = '';

    public string $activityOther = '';

    /** @var array<int, string> Возможные даты проведения. */
    public array $dates = [''];

    public ?int $guestsTotal = null;

    public ?int $guestsAfloat = null;

    public string $startTime = '';

    public ?int $hoursAfloat = 3;

    public string $media = 'none';

    public bool $needsVenue = false;

    /** Площадка: пусто — подобрать самую дешёвую подходящую. */
    public string $venue = '';

    public string $catering = 'none';

    // ── Подбор ──

    /** Выбранная дата в формате Y-m-d. */
    public string $selectedDate = '';

    /** @var array<int, string> id выбранных яхт. */
    public array $selectedYachts = [];

    // ── Контакты ──

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $comment = '';

    public bool $privacy = false;

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user?->name ?? '';
        $this->phone = $user?->phone ?? '';
        $this->email = $user?->email ?? '';
    }

    // ──────────────────────────────────────────────
    // Шаг 1. Параметры
    // ──────────────────────────────────────────────

    public function addDate(): void
    {
        if (count($this->dates) < EventPlanner::MAX_DATES) {
            $this->dates[] = '';
        }
    }

    public function removeDate(int $index): void
    {
        unset($this->dates[$index]);

        $this->dates = array_values($this->dates);

        if ($this->dates === []) {
            $this->dates = [''];
        }
    }

    /** Параметры заполнены — считаем подбор и переходим к флоту. */
    public function plan(): void
    {
        $this->validate($this->paramRules(), $this->paramMessages());

        unset($this->variants, $this->quote);

        if ($this->variants === []) {
            $this->addError('dates.0', 'Ни одна из дат не подходит: укажите будущие даты.');

            return;
        }

        // Стартовый вариант — самый дешёвый из тех, где яхт хватает. Если не
        // хватает нигде, показываем дату с наибольшим числом свободных лодок:
        // с ней разговор с менеджером начинается с меньшего дефицита.
        $variants = collect($this->variants);

        $best = $variants->firstWhere('enough', true)
            ?? $variants->sortByDesc('available')->first();

        $this->selectedDate = (string) ($best['key'] ?? '');
        $this->selectedYachts = (array) ($best['suggested'] ?? []);

        $this->step = 'fleet';
    }

    /** @return array<string, list<string>> */
    private function paramRules(): array
    {
        return [
            'eventName' => ['required', 'string', 'max:255'],
            'format' => ['required', 'string', 'in:'.implode(',', $this->optionKeys('event_format'))],
            'details' => ['nullable', 'string', 'max:2000'],
            'activity' => ['nullable', 'string', 'max:255'],
            'activityOther' => ['nullable', 'string', 'max:255', 'required_if:activity,'.self::ACTIVITY_OTHER],
            'dates' => ['required', 'array', 'min:1', 'max:'.EventPlanner::MAX_DATES],
            'dates.*' => ['nullable', 'date'],
            'guestsTotal' => ['required', 'integer', 'min:1', 'max:500'],
            'guestsAfloat' => ['required', 'integer', 'min:1', 'max:500', 'lte:guestsTotal'],
            'startTime' => ['nullable', 'date_format:H:i'],
            'hoursAfloat' => ['required', 'integer', 'min:1', 'max:12'],
            'media' => ['required', 'string', 'in:'.implode(',', $this->optionKeys('media'))],
            'needsVenue' => ['boolean'],
            'venue' => ['nullable', 'string', 'max:255'],
            'catering' => ['required', 'string', 'in:'.implode(',', $this->optionKeys('catering'))],
        ];
    }

    /** @return array<string, string> */
    private function paramMessages(): array
    {
        return [
            'eventName.required' => 'Назовите мероприятие.',
            'format.required' => 'Выберите, что это за мероприятие.',
            'dates.required' => 'Укажите хотя бы одну возможную дату.',
            'guestsTotal.required' => 'Укажите общее количество участников.',
            'guestsAfloat.required' => 'Укажите, сколько участников выйдет на воду.',
            'guestsAfloat.lte' => 'На воде не может быть больше участников, чем всего.',
            'hoursAfloat.required' => 'Укажите, сколько времени проведёте на воде.',
            'activityOther.required_if' => 'Опишите активность на воде.',
        ];
    }

    // ──────────────────────────────────────────────
    // Шаг 2. Флот и расчёт
    // ──────────────────────────────────────────────

    public function chooseDate(string $key): void
    {
        $variant = collect($this->variants)->firstWhere('key', $key);

        if ($variant === null) {
            return;
        }

        $this->selectedDate = $key;
        // Набор яхт свой на каждую дату — прежний выбор к новой дате не
        // относится, поэтому возвращаемся к предложенному конструктором.
        $this->selectedYachts = $variant['suggested'];

        unset($this->quote);
    }

    public function toggleYacht(string $id): void
    {
        $this->selectedYachts = in_array($id, $this->selectedYachts, true)
            ? array_values(array_diff($this->selectedYachts, [$id]))
            : [...$this->selectedYachts, $id];

        unset($this->quote);
    }

    public function toContacts(): void
    {
        $this->step = 'contacts';
    }

    public function back(): void
    {
        $this->step = match ($this->step) {
            'contacts' => 'fleet',
            default => 'params',
        };
    }

    /** Начать заново — после отправленной заявки. */
    public function restart(): void
    {
        $this->reset(['eventName', 'details', 'activity', 'activityOther', 'guestsTotal',
            'guestsAfloat', 'startTime', 'needsVenue', 'venue', 'comment', 'privacy',
            'selectedDate', 'selectedYachts']);
        $this->dates = [''];
        $this->resetValidation();
        $this->step = 'params';
    }

    // ──────────────────────────────────────────────
    // Данные шагов
    // ──────────────────────────────────────────────

    public function planner(): EventPlanner
    {
        return app(EventPlanner::class);
    }

    /** Сколько яхт нужно под заявленное число гостей на воде. */
    public function needed(): int
    {
        return $this->planner()->yachtsNeeded((int) $this->guestsAfloat);
    }

    /**
     * Варианты по каждой из перечисленных дат.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function variants(): array
    {
        $dates = $this->planner()->parseDates($this->dates);

        return $dates === []
            ? []
            : $this->planner()->availability($dates, $this->needed());
    }

    /** @return array<string, mixed>|null */
    public function variant(): ?array
    {
        return collect($this->variants)->firstWhere('key', $this->selectedDate);
    }

    /** @return Collection<int, Yacht> */
    public function selectedFleet(): Collection
    {
        $variant = $this->variant();

        if ($variant === null) {
            return collect();
        }

        return $variant['yachts']->filter(
            fn (Yacht $yacht): bool => in_array((string) $yacht->getKey(), $this->selectedYachts, true),
        )->values();
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function quote(): ?array
    {
        $variant = $this->variant();

        if ($variant === null) {
            return null;
        }

        return $this->planner()->quote($variant['date'], $this->selectedFleet(), [
            'hours' => (int) $this->hoursAfloat,
            'guests_total' => (int) $this->guestsTotal,
            'guests_afloat' => (int) $this->guestsAfloat,
            'activity' => $this->activityTitle(),
            'media' => $this->media,
            'needs_venue' => $this->needsVenue,
            'venue' => $this->venue,
            'catering' => $this->catering,
        ]);
    }

    /** Активность своими словами важнее выбранной в списке. */
    public function activityTitle(): string
    {
        return $this->activity === self::ACTIVITY_OTHER
            ? trim($this->activityOther)
            : trim($this->activity);
    }

    // ──────────────────────────────────────────────
    // Шаг 3. Заявка
    // ──────────────────────────────────────────────

    public function submit(SubmitServiceRequestAction $action): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'privacy' => ['accepted'],
        ], [
            'privacy.accepted' => 'Подтвердите согласие с политикой обработки персональных данных.',
        ]);

        // Форма живёт вне маршрута с throttle, поэтому ограничение своё —
        // такое же, как у остальных заявок на услуги.
        $key = 'event-constructor:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('privacy', 'Слишком много заявок подряд. Попробуйте через минуту.');

            return;
        }

        RateLimiter::hit($key, 60);

        $variant = $this->variant();

        $action->handle(
            type: ServiceType::Event,
            data: [
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email,
                'comment' => $this->comment,
                // Водная часть укладывается в один день: дата начала и
                // окончания совпадают, как и у однодневной аренды.
                'date_start' => $variant['date']->toDateString(),
                'date_end' => $variant['date']->toDateString(),
                'quantity' => $this->guestsTotal,
                'payload' => $this->payload(),
                'source' => 'event-constructor',
            ],
            user: auth()->user(),
            form: ServiceForm::EventConstructor,
        );

        $this->step = 'done';
    }

    /**
     * Параметры ивента и итог конструктора — в payload заявки.
     *
     * Подобранный флот и смету складываем текстом: менеджер видит в письме и
     * в карточке заявки ровно то, что видел заказчик на экране, а живой
     * пересчёт к этому моменту уже неповторим — лодки могли занять.
     *
     * @return array<string, string>
     */
    private function payload(): array
    {
        $quote = $this->quote;
        $variant = $this->variant();

        return [
            'event_name' => $this->eventName,
            'event_format' => $this->format,
            'event_details' => $this->details,
            'water_activity' => $this->activityTitle(),
            'dates' => collect($this->planner()->parseDates($this->dates))
                ->map(fn (CarbonImmutable $date): string => $date->format('d.m.Y'))
                ->implode(', '),
            'guests_afloat' => (string) $this->guestsAfloat,
            'start_time' => $this->startTime,
            'hours_afloat' => Plural::with((int) $this->hoursAfloat, 'час', 'часа', 'часов'),
            'media' => $this->media,
            'needs_venue' => $this->needsVenue ? 'yes' : 'no',
            'venue' => (string) ($quote['venue']['title'] ?? $this->venue),
            'catering' => $this->catering,
            'fleet_selected' => $this->selectedFleet()
                ->map(fn (Yacht $yacht): string => $yacht->name.' — '.$this->planner()->money(
                    $this->planner()->yachtCost($yacht, $variant['date'], (int) $this->hoursAfloat),
                ))
                ->implode('; '),
            // Ноль в итоге значит «тарифов нет», а не «бесплатно»: менеджер
            // должен увидеть в заявке ровно ту формулировку, что и заказчик.
            'estimate' => match (true) {
                $quote === null => '',
                $quote['total'] <= 0 => 'по запросу',
                $quote['has_unpriced'] => 'от '.$this->planner()->money($quote['total']).' + позиции по запросу',
                default => 'от '.$this->planner()->money($quote['total']),
            },
            'estimate_details' => collect($quote['items'] ?? [])
                ->map(fn (array $item): string => $item['title'].': '.$this->planner()->money($item['amount']))
                ->implode('; '),
        ];
    }

    // ──────────────────────────────────────────────
    // Служебное
    // ──────────────────────────────────────────────

    /**
     * Варианты select-поля заявки — источник и для формы, и для валидации.
     *
     * @return array<string, string>
     */
    public function options(string $field): array
    {
        return ServiceType::Event->payloadFields()[$field]['options'] ?? [];
    }

    /** @return list<string> */
    private function optionKeys(string $field): array
    {
        return array_keys($this->options($field));
    }

    public function render()
    {
        return view('livewire.event-constructor');
    }
}
