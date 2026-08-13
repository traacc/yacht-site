<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Regatta\SubmitSeatEntryAction;
use App\Enums\ParticipationKind;
use App\Models\Regatta;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Заявка на регулярную или выездную регату: экипажем или индивидуально.
 *
 * Открывается событием 'open-seat-entry' с id регаты:
 *   Livewire.dispatch('open-seat-entry', { regattaId: '...' })
 *
 * Здесь нет ни выбора команды, ни выбора яхты (в отличие от клубной
 * {@see JoinRegattaModal}): лодку выставляет ассоциация, экипаж бывает сборным.
 * Заявка уходит на модерацию администратору — см. SubmitSeatEntryAction.
 */
class SeatEntryModal extends Component
{
    public bool $isOpen = false;

    public ?string $regattaId = null;

    /** Шапка окна: название регаты, цены, длительность и лимит экипажа. */
    public ?array $regattaInfo = null;

    public string $kind = ParticipationKind::Individual->value;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    /** Остальные участники экипажа: [['name' => ..., 'email' => ..., 'phone' => ...]] */
    public array $crew = [];

    public bool $submitted = false;

    protected function rules(): array
    {
        return [
            'kind' => ['required', 'string', 'in:crew,individual'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'crew' => ['array', 'max:20'],
            'crew.*.name' => ['nullable', 'string', 'max:255'],
            'crew.*.email' => ['nullable', 'email', 'max:255'],
            'crew.*.phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Укажите имя.',
            'email.required' => 'Укажите e-mail — на него придёт подтверждение.',
            'email.email' => 'Проверьте адрес e-mail.',
        ];
    }

    #[On('open-seat-entry')]
    public function open(string $regattaId): void
    {
        $regatta = Regatta::find($regattaId);

        if (! $regatta instanceof Regatta || ! $regatta->isOpenForRegistration()) {
            return;
        }

        $this->resetValidation();
        $this->submitted = false;
        $this->regattaId = $regatta->id;
        $this->regattaInfo = [
            'name' => $regatta->name,
            'dates' => $regatta->dateRange(),
            'type_label' => $regatta->type->getLabel(),
            'seat_price' => $regatta->seat_price,
            'boat_price' => $regatta->boat_price,
            'race_days' => $regatta->race_days_count,
            'race_hours_per_day' => $regatta->race_hours_per_day,
            'crew_limit' => $regatta->maxCrewSize(),
            'allows_individual' => $regatta->allowsIndividualEntry(),
        ];

        $this->kind = $regatta->allowsIndividualEntry()
            ? ParticipationKind::Individual->value
            : ParticipationKind::Crew->value;

        $user = auth()->user();
        $this->name = $user?->name ?? '';
        $this->email = $user?->email ?? '';
        $this->phone = $user?->phone ?? '';
        $this->crew = [];

        $this->isOpen = true;
    }

    /** Заявитель — рулевой, поэтому в список добавляются только остальные места. */
    public function addCrewMember(): void
    {
        $limit = $this->regattaInfo['crew_limit'] ?? null;

        if ($limit !== null && count($this->crew) + 1 >= $limit) {
            return;
        }

        $this->crew[] = ['name' => '', 'email' => '', 'phone' => ''];
    }

    public function removeCrewMember(int $index): void
    {
        unset($this->crew[$index]);
        $this->crew = array_values($this->crew);
    }

    public function submit(SubmitSeatEntryAction $action): void
    {
        $data = $this->validate();

        $regatta = Regatta::find($this->regattaId);

        if (! $regatta instanceof Regatta) {
            return;
        }

        $action->handle(
            regatta: $regatta,
            kind: ParticipationKind::from($data['kind']),
            applicant: [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ],
            crew: $data['crew'] ?? [],
            actor: auth()->user(),
        );

        $this->submitted = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->regattaId = null;
        $this->regattaInfo = null;
        $this->submitted = false;
        $this->reset(['name', 'email', 'phone', 'crew']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.seat-entry-modal');
    }
}
