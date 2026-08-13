<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\RegattaEntry\SubmitCrewJoinRequestAction;
use App\Models\RegattaEntry;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Форма «Хочу в этот экипаж» на клубной регате.
 *
 * Открывается событием 'open-crew-join' с id заявки:
 *   Livewire.dispatch('open-crew-join', { entryId: '...' })
 *
 * Отклик может оставить и незарегистрированный человек — поэтому контакты
 * запрашиваются формой, а не берутся только из аккаунта. Сохранение, проверки
 * и рассылка — в SubmitCrewJoinRequestAction.
 */
class CrewJoinModal extends Component
{
    public bool $isOpen = false;

    public ?string $entryId = null;

    /** Данные для шапки окна: экипаж, регата и объявленные условия. */
    public ?array $entryInfo = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    public bool $submitted = false;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Укажите имя.',
            'email.required' => 'Укажите e-mail — на него экипаж пришлёт ответ.',
            'email.email' => 'Проверьте адрес e-mail.',
        ];
    }

    #[On('open-crew-join')]
    public function open(string $entryId): void
    {
        $entry = RegattaEntry::with(['regatta', 'team'])->find($entryId);

        if (! $entry instanceof RegattaEntry || ! $entry->isOpenForJoin()) {
            return;
        }

        $this->resetValidation();
        $this->submitted = false;
        $this->entryId = $entry->id;
        $this->entryInfo = [
            'regatta' => $entry->regatta?->name ?? '',
            'team' => $entry->team?->name,
            'conditions' => $entry->join_conditions,
        ];

        // Своим подставляем контакты из профиля, чужие вводят вручную.
        $user = auth()->user();
        $this->name = $user?->name ?? '';
        $this->email = $user?->email ?? '';
        $this->phone = $user?->phone ?? '';
        $this->message = '';

        $this->isOpen = true;
    }

    public function submit(SubmitCrewJoinRequestAction $action): void
    {
        $data = $this->validate();

        $entry = RegattaEntry::with(['regatta', 'team'])->find($this->entryId);

        if (! $entry instanceof RegattaEntry) {
            return;
        }

        $action->handle($entry, $data, auth()->user());

        $this->submitted = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->entryId = null;
        $this->entryInfo = null;
        $this->submitted = false;
        $this->reset(['name', 'email', 'phone', 'message']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.crew-join-modal');
    }
}
