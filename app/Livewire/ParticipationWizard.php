<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Regatta\SubmitSeatEntryAction;
use App\Actions\YachtRental\SubmitYachtRentalRequestAction;
use App\Enums\ParticipationKind;
use App\Enums\RegattaType;
use App\Models\Regatta;
use App\Models\Yacht;
use App\Services\ParticipationOptions;
use App\Services\SeasonCalendar;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Мастер «Хочу участвовать» с главной страницы.
 *
 * Пять шагов: вариант участия → тип регаты → регата → лодка или число мест →
 * заявка. Сам мастер ничего не изобретает: он подбирает варианты
 * (@see ParticipationOptions) и на последнем шаге отдаёт работу тем же
 * действиям и формам, что и остальной сайт —
 *   - регулярная регата: SubmitSeatEntryAction (места и лодки ассоциации);
 *   - клубная со своей лодкой: обычная форма заявки JoinRegattaModal;
 *   - клубная с арендой: SubmitYachtRentalRequestAction на даты регаты;
 *   - клубная индивидуально: отклик в открытый экипаж (CrewJoinModal).
 */
class ParticipationWizard extends Component
{
    /** Своя лодка вместо аренды — значение шага выбора лодки. */
    public const OWN_YACHT = 'own';

    public bool $isOpen = false;

    /** Текущий шаг: kind → type → regatta → boat → form → done. */
    public string $step = 'kind';

    public ?string $kind = null;

    public ?string $type = null;

    public ?string $regattaId = null;

    /** Выбранная лодка: id яхты, self::OWN_YACHT или null. */
    public ?string $yachtId = null;

    /** Выбранный экипаж для добора (клубная + индивидуально). */
    public ?string $entryId = null;

    /** Фильтр списка по типу регаты; null — показывать все. */
    public ?string $typeFilter = null;

    public int $seats = 1;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $comment = '';

    /** Согласие с условиями аренды — обязательно для арендной ветки. */
    public bool $agreement = false;

    #[On('open-participation-wizard')]
    public function open(): void
    {
        $this->reset(['kind', 'type', 'regattaId', 'yachtId', 'entryId', 'typeFilter', 'comment', 'agreement']);
        $this->resetValidation();
        $this->seats = 1;
        $this->step = 'kind';

        $user = auth()->user();
        $this->name = $user?->name ?? '';
        $this->email = $user?->email ?? '';
        $this->phone = $user?->phone ?? '';

        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->reset(['kind', 'type', 'regattaId', 'yachtId', 'entryId', 'typeFilter', 'comment', 'agreement']);
        $this->resetValidation();
        $this->step = 'kind';
    }

    // ──────────────────────────────────────────────
    // Шаги
    // ──────────────────────────────────────────────

    public function chooseKind(string $kind): void
    {
        $this->kind = ParticipationKind::from($kind)->value;
        // Набор доступных типов зависит от способа участия — фильтр сбрасываем.
        $this->typeFilter = null;
        $this->step = 'regatta';
    }

    /** Повторный клик по активному фильтру снимает его. */
    public function filterByType(?string $type): void
    {
        $this->typeFilter = $this->typeFilter === $type ? null : $type;
    }

    /**
     * Тип регаты человек не выбирает — список общий, а тип берётся из самой
     * регаты: от него зависят следующий шаг и способ подачи заявки.
     */
    public function chooseRegatta(string $regattaId): void
    {
        $regatta = Regatta::find($regattaId);

        if ($regatta === null) {
            return;
        }

        $this->regattaId = $regatta->id;
        $this->type = $regatta->type->value;
        $this->yachtId = null;
        $this->entryId = null;
        $this->step = 'boat';
    }

    /**
     * Своя лодка — это обычная клубная заявка: закрываем мастер и открываем
     * привычную форму, как с карточки регаты.
     */
    public function chooseYacht(string $yachtId): void
    {
        $this->yachtId = $yachtId;

        if ($yachtId === self::OWN_YACHT) {
            $regattaId = $this->regattaId;
            $this->closeModal();
            $this->dispatch('open-join-regatta-modal', regattaId: $regattaId);

            return;
        }

        $this->step = 'form';
    }

    /**
     * Лодку выбирать не из чего: на регулярной её назначит ассоциация,
     * на выездной флот даёт принимающая сторона.
     */
    public function skipYacht(): void
    {
        $this->yachtId = null;
        $this->step = 'form';
    }

    /** Нужен ли на шаге лодки счётчик мест (индивидуальное участие за деньги). */
    public function needsSeatPicker(): bool
    {
        return $this->kind === ParticipationKind::Individual->value
            && in_array($this->type, [RegattaType::Regular->value, RegattaType::Travel->value], true);
    }

    /** Отклик в открытый экипаж уходит в свою форму — там условия экипажа. */
    public function chooseCrew(string $entryId): void
    {
        $this->closeModal();
        $this->dispatch('open-crew-join', entryId: $entryId);
    }

    public function setSeats(int $seats): void
    {
        $limit = $this->regatta()?->maxCrewSize() ?? 6;
        $this->seats = max(1, min($seats, $limit));
    }

    public function confirmSeats(): void
    {
        $this->step = 'form';
    }

    public function back(): void
    {
        $this->step = match ($this->step) {
            'regatta' => 'kind',
            'boat' => 'regatta',
            'form' => 'boat',
            default => 'kind',
        };
    }

    // ──────────────────────────────────────────────
    // Данные шагов
    // ──────────────────────────────────────────────

    #[Computed]
    public function regatta(): ?Regatta
    {
        return $this->regattaId ? Regatta::find($this->regattaId) : null;
    }

    /**
     * Все регаты, куда можно заявиться выбранным способом, — любого типа
     * и вместе с зарубежными. Тип виден меткой в строке списка.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function allRegattas(): array
    {
        if ($this->kind === null) {
            return [];
        }

        return app(ParticipationOptions::class)
            ->regattas(ParticipationKind::from($this->kind))
            ->all();
    }

    /**
     * Список с учётом выбранного фильтра по типу.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function regattas(): array
    {
        if ($this->typeFilter === null) {
            return $this->allRegattas();
        }

        return array_values(array_filter(
            $this->allRegattas(),
            fn (array $item): bool => $item['type'] === $this->typeFilter,
        ));
    }

    /**
     * Кнопки фильтра — всегда весь набор типов, включая зарубежные.
     *
     * Типы, которых сейчас нет, остаются в ряду с нулём: постоянный состав
     * фильтров показывает, какие соревнования вообще бывают, и ряд не скачет
     * при переключении способа участия.
     *
     * @return array<int, array{value: string, label: string, class: string, count: int}>
     */
    #[Computed]
    public function typeFilters(): array
    {
        $counts = array_count_values(array_column($this->allRegattas(), 'type'));

        $filters = [];

        // Порядок фильтров повторяет порядок типов, а не порядок регат в списке.
        foreach (RegattaType::cases() as $type) {
            $filters[] = [
                'value' => $type->value,
                'label' => $type->pluralLabel(),
                'class' => $type->backgroundClass(),
                'count' => $counts[$type->value] ?? 0,
            ];
        }

        $filters[] = [
            'value' => SeasonCalendar::FOREIGN_TYPE,
            'label' => 'За рубежом',
            'class' => 'bg-[#7B5FC4]',
            'count' => $counts[SeasonCalendar::FOREIGN_TYPE] ?? 0,
        ];

        return $filters;
    }

    /** @return array<int, array{id: string, name: string, vfps: ?string}> */
    #[Computed]
    public function yachts(): array
    {
        $regatta = $this->regatta();

        if ($regatta === null) {
            return [];
        }

        return app(ParticipationOptions::class)
            ->availableYachts($regatta)
            ->map(fn (Yacht $yacht): array => [
                'id' => (string) $yacht->id,
                'name' => $yacht->name,
                'vfps' => $yacht->vfps_number,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{id: string, title: string, conditions: ?string, taken: int}> */
    #[Computed]
    public function crews(): array
    {
        $regatta = $this->regatta();

        if ($regatta === null) {
            return [];
        }

        return app(ParticipationOptions::class)
            ->openCrews($regatta)
            ->map(fn ($entry): array => [
                'id' => (string) $entry->id,
                'title' => $entry->participantName().($entry->yacht ? ' · '.$entry->yacht->name : ''),
                'conditions' => $entry->join_conditions,
                'taken' => $entry->crew->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * Что показать на шаге выбора, когда предлагать нечего.
     *
     * «Регат нет» и «в регатах не осталось мест» — разные ситуации: в первой
     * человеку идти некуда, во второй стоит заглянуть позже или выбрать другой
     * тип. Поэтому текст зависит от того, есть ли вообще регаты этого типа.
     *
     * @return array{title: string, hint: string}
     */
    public function emptyState(): array
    {
        // Фильтр спрятал всё, что было: это не «мест нет», а «нет в этом типе».
        if ($this->typeFilter !== null && $this->allRegattas() !== []) {
            return [
                'title' => 'В этом типе регат ничего не нашлось',
                'hint' => 'Снимите фильтр — в других типах регат варианты есть.',
            ];
        }

        if (! app(ParticipationOptions::class)->hasAnyRegattas()) {
            return [
                'title' => 'Регат сейчас нет',
                'hint' => 'Ближайшие соревнования смотрите в календаре регат.',
            ];
        }

        return $this->kind === ParticipationKind::Individual->value
            ? [
                'title' => 'Свободных мест нет',
                'hint' => 'Ни один экипаж сейчас не набирает людей, а места на лодках ассоциации ещё не в продаже. Загляните позже или заявитесь своим экипажем.',
            ]
            : [
                'title' => 'Свободных лодок нет',
                'hint' => 'Лодки во всех ближайших регатах заняты. Загляните позже — или подайте заявку со своей лодкой со страницы регаты.',
            ];
    }

    /** Итоговая сумма выбранного варианта; null — цену назначит администратор. */
    #[Computed]
    public function price(): ?float
    {
        $regatta = $this->regatta();

        if ($regatta === null || $this->kind === null || $this->isRentalBranch()) {
            return null;
        }

        return $regatta->entryPrice(ParticipationKind::from($this->kind), $this->seats);
    }

    /** Клубная регата с арендной лодкой — сначала бронь, потом заявка. */
    public function isRentalBranch(): bool
    {
        return $this->type === RegattaType::Club->value
            && filled($this->yachtId)
            && $this->yachtId !== self::OWN_YACHT;
    }

    // ──────────────────────────────────────────────
    // Отправка
    // ──────────────────────────────────────────────

    public function submit(SubmitSeatEntryAction $entryAction, SubmitYachtRentalRequestAction $rentalAction): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->isRentalBranch()) {
            $rules['agreement'] = ['accepted'];
        }

        $data = $this->validate($rules, [
            'agreement.accepted' => 'Подтвердите согласие с условиями аренды.',
        ]);

        $regatta = $this->regatta();

        if ($regatta === null) {
            return;
        }

        $this->isRentalBranch()
            ? $this->submitRental($regatta, $rentalAction)
            : $this->submitEntry($regatta, $entryAction, $data);

        $this->step = 'done';
    }

    /**
     * Аренда лодки на даты регаты. Заявку на саму регату подают после
     * подтверждения брони — лодка до этого момента не закреплена за экипажем.
     */
    private function submitRental(Regatta $regatta, SubmitYachtRentalRequestAction $action): void
    {
        $yacht = Yacht::find($this->yachtId);

        if ($yacht === null) {
            return;
        }

        $action->handle($yacht, [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'desired_date' => $regatta->date_start?->format('Y-m-d'),
            'desired_date_end' => $regatta->date_end?->format('Y-m-d'),
            'comment' => trim("Аренда на регату «{$regatta->name}». ".$this->comment),
            'agreement' => $this->agreement,
            'source' => 'participation-wizard',
        ], auth()->id());
    }

    /** @param  array<string, mixed>  $data */
    private function submitEntry(Regatta $regatta, SubmitSeatEntryAction $action, array $data): void
    {
        $action->handle(
            regatta: $regatta,
            kind: ParticipationKind::from((string) $this->kind),
            applicant: [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
            ],
            actor: auth()->user(),
            seats: $this->seats,
            yacht: $this->yachtId && $this->yachtId !== self::OWN_YACHT
                ? Yacht::find($this->yachtId)
                : null,
        );
    }

    public function render()
    {
        return view('livewire.participation-wizard');
    }
}
