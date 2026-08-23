<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Yacht;
use App\Support\Plural;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * «Конструктор мероприятия» (ТЗ 3-го этапа, п. 7): подбор яхт и расчёт
 * минимальной стоимости по параметрам будущего ивента.
 *
 * Заказчик перечисляет возможные даты — на каждую из них считается свой
 * вариант: сколько яхт свободно и во что обойдётся программа. Свободной
 * считается та же яхта, что и в «Аренде флота» (@see FleetAvailability):
 * занятость берётся из одного скоупа, иначе страницы разойдутся в ответе
 * «свободна / занята».
 *
 * Тарифы алгоритма заказчиком не согласованы, поэтому в коде нет ни одной
 * зашитой суммы: все коэффициенты — настройки группы `services`, правятся на
 * вкладке «Мероприятия» и по умолчанию нулевые. Ноль означает «позиция не
 * тарифицирована» — она не молча пропадает из сметы, а показывается строкой
 * «по запросу», чтобы расчёт не выглядел полным, когда он неполон.
 *
 * Формула стоимости яхты выбрана самой осторожной из возможных: час стоит
 * долю от суточной ставки владельца, но за день нельзя заплатить больше самой
 * суточной ставки. Так расчёт никогда не окажется выше реальной аренды.
 */
final class EventPlanner
{
    /** Ключи настроек конструктора в группе `services`. */
    private const PREFIX = 'services.event.constructor.';

    /** Сколько дат разрешено перечислить: подбор считается по каждой. */
    public const MAX_DATES = 5;

    /** Гостей на одну яхту, когда в настройках не задано другое. */
    public const DEFAULT_YACHT_CAPACITY = 8;

    /** Минимальная оплачиваемая продолжительность водной части, часов. */
    public const DEFAULT_MIN_HOURS = 2;

    /** Доля суточной ставки яхты за один час, %. */
    public const DEFAULT_HOUR_SHARE = 25;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly FleetAvailability $fleet,
    ) {}

    // ──────────────────────────────────────────────
    // Настройки
    // ──────────────────────────────────────────────

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get(self::PREFIX.'enabled', true);
    }

    public function intro(): string
    {
        return trim((string) $this->settings->get(self::PREFIX.'intro', ''));
    }

    /** Примечание под расчётом: оговорка про ориентировочность суммы. */
    public function note(): string
    {
        return trim((string) $this->settings->get(self::PREFIX.'note', ''));
    }

    public function yachtCapacity(): int
    {
        return max(1, (int) $this->settings->get(self::PREFIX.'yacht_capacity', self::DEFAULT_YACHT_CAPACITY));
    }

    public function minHours(): int
    {
        return max(1, (int) $this->settings->get(self::PREFIX.'min_hours', self::DEFAULT_MIN_HOURS));
    }

    /**
     * Варианты активности на воде: заголовок и надбавка за мероприятие.
     *
     * Список ведётся в админке — гонка с судейством и фотосессия стоят
     * по-разному. Пустой список означает «вариантов не завели»: тогда
     * активность заказчик пишет своими словами и надбавки нет.
     *
     * @return list<array{title: string, surcharge: float}>
     */
    public function activities(): array
    {
        return collect((array) $this->settings->get(self::PREFIX.'activities', []))
            ->filter(fn ($item): bool => is_array($item) && trim((string) ($item['title'] ?? '')) !== '')
            ->map(fn (array $item): array => [
                'title' => trim((string) $item['title']),
                'surcharge' => (float) ($item['surcharge'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Площадки на берегу, пригодные для наземной части.
     *
     * Ведутся тем же репитером, что и блок «Площадки» на лендинге: цена и
     * вместимость там необязательны, поэтому площадка без цены остаётся в
     * списке, но в минимальную стоимость попадает строкой «по запросу».
     *
     * @return list<array{title: string, address: string, guests: ?int, price: ?float}>
     */
    public function venues(): array
    {
        return collect((array) $this->settings->get('services.event.venues', []))
            ->filter(fn ($item): bool => is_array($item) && trim((string) ($item['title'] ?? '')) !== '')
            ->map(fn (array $item): array => [
                'title' => trim((string) $item['title']),
                'address' => trim((string) ($item['address'] ?? '')),
                'guests' => ($item['guests'] ?? null) !== null && $item['guests'] !== ''
                    ? (int) $item['guests']
                    : null,
                'price' => ($item['price'] ?? null) !== null && $item['price'] !== ''
                    ? (float) $item['price']
                    : null,
            ])
            ->values()
            ->all();
    }

    // ──────────────────────────────────────────────
    // Подбор яхт
    // ──────────────────────────────────────────────

    /** Сколько яхт нужно, чтобы разместить гостей водной части. */
    public function yachtsNeeded(int $guestsAfloat): int
    {
        return max(1, (int) ceil(max(1, $guestsAfloat) / $this->yachtCapacity()));
    }

    /**
     * Даты из формы: только будущие, без дублей и не больше MAX_DATES.
     *
     * @param  list<string|null>  $dates
     * @return list<CarbonImmutable>
     */
    public function parseDates(array $dates): array
    {
        $today = CarbonImmutable::today();

        return collect($dates)
            ->map(function ($value): ?CarbonImmutable {
                $value = trim((string) $value);

                if ($value === '') {
                    return null;
                }

                try {
                    return CarbonImmutable::parse($value)->startOfDay();
                } catch (\Exception) {
                    return null;
                }
            })
            ->filter(fn (?CarbonImmutable $date): bool => $date !== null && $date->gte($today))
            ->unique(fn (CarbonImmutable $date): string => $date->toDateString())
            ->sort()
            ->take(self::MAX_DATES)
            ->values()
            ->all();
    }

    /**
     * Вариант по каждой дате: что свободно и хватает ли этого.
     *
     * Водная часть укладывается в один день, поэтому диапазон подбора —
     * сама дата. Яхты отсортированы по стоимости: первыми идут те, из
     * которых и собирается минимальная смета.
     *
     * @param  list<CarbonImmutable>  $dates
     * @return list<array{
     *     key: string,
     *     date: CarbonImmutable,
     *     yachts: Collection<int, Yacht>,
     *     available: int,
     *     enough: bool,
     *     suggested: list<string>,
     * }>
     */
    public function availability(array $dates, int $needed): array
    {
        $variants = [];

        foreach ($dates as $date) {
            $yachts = $this->fleet->availableYachts($date, $date)
                ->sortBy(fn (Yacht $yacht): float => $this->dailyPrice($yacht, $date) ?? INF)
                ->values();

            $variants[] = [
                'key' => $date->toDateString(),
                'date' => $date,
                'yachts' => $yachts,
                'available' => $yachts->count(),
                'enough' => $yachts->count() >= $needed,
                'suggested' => $yachts->take($needed)
                    ->map(fn (Yacht $yacht): string => (string) $yacht->getKey())
                    ->all(),
            ];
        }

        return $variants;
    }

    /**
     * Суточная ставка яхты на дату — минимальная из предложений, её покрывающих.
     *
     * Цену проставляют не все владельцы: аренда части флота идёт «по запросу»,
     * такие лодки в расчёт не попадают (@see FleetAvailability).
     */
    public function dailyPrice(Yacht $yacht, CarbonImmutable $date): ?float
    {
        $prices = $yacht->rentals
            ->filter(fn ($rental): bool => $rental->date_start !== null
                && $rental->date_end !== null
                && $rental->date_start->lte($date)
                && $rental->date_end->gte($date)
                && $rental->price_event !== null)
            ->map(fn ($rental): float => (float) $rental->price_event)
            ->filter(fn (float $price): bool => $price > 0);

        return $prices->isEmpty() ? null : (float) $prices->min();
    }

    /** Стоимость одной яхты за водную часть; null — аренда по запросу. */
    public function yachtCost(Yacht $yacht, CarbonImmutable $date, int $hours): ?float
    {
        $daily = $this->dailyPrice($yacht, $date);

        if ($daily === null) {
            return null;
        }

        $share = (float) $this->settings->get(self::PREFIX.'hour_share', self::DEFAULT_HOUR_SHARE) / 100;

        // Час дешевле суток, но за один день не может выйти дороже суточной
        // ставки: длинная программа упирается в обычную аренду на день.
        return min($daily, round($daily * $share * $this->billableHours($hours), 2));
    }

    /** Оплачиваемая продолжительность: не меньше минимальной по настройкам. */
    public function billableHours(int $hours): int
    {
        return max($this->minHours(), max(1, $hours));
    }

    // ──────────────────────────────────────────────
    // Расчёт
    // ──────────────────────────────────────────────

    /**
     * Минимальная стоимость мероприятия по выбранным дате и яхтам.
     *
     * Позиция с `amount === null` — «по запросу»: услуга нужна, но тариф на
     * неё не задан. Такие позиции не попадают в сумму, и расчёт честно
     * помечается неполным (`has_unpriced`).
     *
     * @param  Collection<int, Yacht>  $yachts  выбранные яхты
     * @param  array{
     *     hours: int,
     *     guests_total: int,
     *     guests_afloat: int,
     *     activity?: string|null,
     *     media?: string,
     *     needs_venue?: bool,
     *     venue?: string|null,
     *     catering?: string,
     * }  $params
     * @return array{
     *     items: list<array{title: string, amount: float|null, note: string, partial?: bool}>,
     *     total: float,
     *     hours: int,
     *     has_unpriced: bool,
     *     venue: array{title: string, address: string, guests: ?int, price: ?float}|null,
     * }
     */
    public function quote(CarbonImmutable $date, Collection $yachts, array $params): array
    {
        $hours = $this->billableHours((int) $params['hours']);
        $guests = max(1, (int) $params['guests_total']);
        $items = [];

        // Флот попадает в смету, только когда есть что считать: на дату без
        // свободных лодок строка «0 яхт» лишь мешала бы читать расчёт.
        if ($yachts->isNotEmpty()) {
            $items[] = $this->fleetItem($yachts, $date, $hours);
        }

        if (($skipper = (float) $this->settings->get(self::PREFIX.'skipper_hour', 0)) > 0 && $yachts->isNotEmpty()) {
            $items[] = [
                'title' => 'Шкиперы',
                'amount' => $skipper * $hours * $yachts->count(),
                'note' => $this->money($skipper).'/ч × '.$hours.' ч × '.$yachts->count(),
            ];
        }

        if (($activity = $this->activityItem($params['activity'] ?? null)) !== null) {
            $items[] = $activity;
        }

        if (($media = $this->mediaItem((string) ($params['media'] ?? 'none'))) !== null) {
            $items[] = $media;
        }

        $venue = ($params['needs_venue'] ?? false)
            ? $this->pickVenue($guests, $params['venue'] ?? null)
            : null;

        if ($venue !== null) {
            $items[] = [
                'title' => 'Площадка на берегу: '.$venue['title'],
                'amount' => $venue['price'],
                'note' => $venue['price'] === null ? 'стоимость уточняется' : '',
            ];
        } elseif ($params['needs_venue'] ?? false) {
            $items[] = [
                'title' => 'Площадка на берегу',
                'amount' => null,
                'note' => 'подберём под количество гостей',
            ];
        }

        if (($catering = $this->cateringItem((string) ($params['catering'] ?? 'none'), $guests)) !== null) {
            $items[] = $catering;
        }

        if (($base = (float) $this->settings->get(self::PREFIX.'base_fee', 0)) > 0) {
            $items[] = [
                'title' => 'Организация мероприятия',
                'amount' => $base,
                'note' => '',
            ];
        }

        $items = array_values(array_filter(
            $items,
            fn (array $item): bool => $item['amount'] === null || $item['amount'] > 0,
        ));

        return [
            'items' => $items,
            'total' => (float) collect($items)->sum(fn (array $item): float => (float) ($item['amount'] ?? 0)),
            'hours' => $hours,
            // Неполной смету делает не только позиция без цены целиком, но и
            // флот, где часть лодок сдаётся по запросу.
            'has_unpriced' => collect($items)->contains(
                fn (array $item): bool => $item['amount'] === null || ($item['partial'] ?? false),
            ),
            'venue' => $venue,
        ];
    }

    /**
     * Флот: сумма по выбранным яхтам за водную часть.
     *
     * @param  Collection<int, Yacht>  $yachts
     * @return array{title: string, amount: float|null, note: string, partial: bool}
     */
    private function fleetItem(Collection $yachts, CarbonImmutable $date, int $hours): array
    {
        $costs = $yachts->map(fn (Yacht $yacht): ?float => $this->yachtCost($yacht, $date, $hours));
        $priced = $costs->filter(fn (?float $cost): bool => $cost !== null);
        $onRequest = $costs->count() - $priced->count();

        return [
            'title' => 'Яхты — '.Plural::with($yachts->count(), 'яхта', 'яхты', 'яхт')
                .' × '.$hours.' ч',
            'amount' => $priced->isEmpty() ? null : (float) $priced->sum(),
            'note' => $onRequest > 0
                ? Plural::with($onRequest, 'яхта', 'яхты', 'яхт').' — аренда по запросу'
                : '',
            'partial' => $onRequest > 0,
        ];
    }

    /** @return array{title: string, amount: float|null, note: string}|null */
    private function activityItem(?string $activity): ?array
    {
        $activity = trim((string) $activity);

        if ($activity === '') {
            return null;
        }

        foreach ($this->activities() as $option) {
            if ($option['title'] === $activity && $option['surcharge'] > 0) {
                return [
                    'title' => 'Программа на воде: '.$activity,
                    'amount' => $option['surcharge'],
                    'note' => '',
                ];
            }
        }

        return null;
    }

    /** @return array{title: string, amount: float|null, note: string}|null */
    private function mediaItem(string $media): ?array
    {
        if ($media === '' || $media === 'none') {
            return null;
        }

        $photo = (float) $this->settings->get(self::PREFIX.'photo_price', 0);
        $video = (float) $this->settings->get(self::PREFIX.'video_price', 0);

        [$title, $amount] = match ($media) {
            'photo' => ['Фотосъёмка', $photo],
            'video' => ['Видеосъёмка', $video],
            default => ['Фото- и видеосъёмка', $photo + $video],
        };

        return [
            'title' => $title,
            'amount' => $amount > 0 ? $amount : null,
            'note' => $amount > 0 ? '' : 'стоимость уточняется',
        ];
    }

    /** @return array{title: string, amount: float|null, note: string}|null */
    private function cateringItem(string $catering, int $guests): ?array
    {
        if ($catering === '' || $catering === 'none') {
            return null;
        }

        $perPerson = $catering === 'restaurant'
            ? (float) $this->settings->get(self::PREFIX.'restaurant_person', 0)
            : (float) $this->settings->get(self::PREFIX.'catering_person', 0);

        $title = $catering === 'restaurant' ? 'Ресторан' : 'Кейтеринг';

        return [
            'title' => $title.' на '.Plural::with($guests, 'гостя', 'гостей', 'гостей'),
            'amount' => $perPerson > 0 ? $perPerson * $guests : null,
            'note' => $perPerson > 0 ? $this->money($perPerson).' на гостя' : 'стоимость уточняется',
        ];
    }

    /**
     * Площадка: названная заказчиком либо самая дешёвая из подходящих.
     *
     * Подходящая — та, что вмещает всех гостей; площадка без указанной
     * вместимости считается подходящей, пока не сказано обратное.
     *
     * @return array{title: string, address: string, guests: ?int, price: ?float}|null
     */
    public function pickVenue(int $guests, ?string $title = null): ?array
    {
        $venues = collect($this->venues());

        if ($venues->isEmpty()) {
            return null;
        }

        $title = trim((string) $title);

        if ($title !== '') {
            return $venues->firstWhere('title', $title);
        }

        $suitable = $venues->filter(
            fn (array $venue): bool => $venue['guests'] === null || $venue['guests'] >= $guests,
        );

        if ($suitable->isEmpty()) {
            return null;
        }

        // Минимальная стоимость: площадка без цены не может оказаться дешевле
        // площадки с ценой, иначе смета «схлопнулась» бы в «по запросу».
        return $suitable->sortBy(fn (array $venue): float => $venue['price'] ?? INF)->first();
    }

    /** «12 000 ₽» или «по запросу» — единый формат сумм конструктора. */
    public function money(?float $amount): string
    {
        return $amount === null
            ? 'по запросу'
            : number_format($amount, 0, '.', ' ').' ₽';
    }
}
