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
        $this->reset(['kind', 'type', 'regattaId', 'yachtId', 'entryId', 'comment', 'agreement']);
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
        $this->reset(['kind', 'type', 'regattaId', 'yachtId', 'entryId', 'comment', 'agreement']);
        $this->resetValidation();
        $this->step = 'kind';
    }

    // ──────────────────────────────────────────────
    // Шаги
    // ──────────────────────────────────────────────

    public function chooseKind(string $kind): void
    {
        $this->kind = ParticipationKind::from($kind)->value;
        $this->step = 'type';
    }

    public function chooseType(string $type): void
    {
        $this->type = RegattaType::from($type)->value;
        $this->step = 'regatta';
    }

    public function chooseRegatta(string $regattaId): void
    {
        $this->regattaId = $regattaId;
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

    /** Регулярная регата: лодку можно не выбирать — её назначит ассоциация. */
    public function skipYacht(): void
    {
        $this->yachtId = null;
        $this->step = 'form';
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
            'type' => 'kind',
            'regatta' => 'type',
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

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function regattas(): array
    {
        if ($this->kind === null || $this->type === null) {
            return [];
        }

        return app(ParticipationOptions::class)
            ->regattas(ParticipationKind::from($this->kind), RegattaType::from($this->type))
            ->all();
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
