<?php

namespace App\Livewire;

use App\Actions\Regatta\SubmitRegattaEntryAction;
use App\Models\Regatta;
use App\Models\Team;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class JoinRegattaModal extends Component
{
    public ?string $regattaId = null;

    public ?string $teamId = null;

    public ?string $yachtId = null;

    public bool $isOpen = false;

    #[On('open-join-regatta-modal')]
    public function openModal(string $regattaId): void
    {
        $this->regattaId = $regattaId;
        $this->teamId = null;
        $this->yachtId = null;
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->reset(['regattaId', 'teamId', 'yachtId']);
    }

    public function submit(SubmitRegattaEntryAction $action): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            $this->addError('general', 'Требуется авторизация.');

            return;
        }

        $this->validate([
            'teamId' => ['required', 'string', 'uuid'],
            'yachtId' => ['required', 'string', 'uuid'],
        ]);

        $regatta = Regatta::findOrFail($this->regattaId);
        $team = Team::findOrFail($this->teamId);
        $yacht = Yacht::findOrFail($this->yachtId);

        try {
            $entry = $action->handle($regatta, $team, $yacht, $user);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        } catch (\DomainException $e) {
            $this->addError('yachtId', $e->getMessage());

            return;
        } catch (\Exception $e) {
            $this->addError('general', 'Произошла ошибка при подаче заявки. Попробуйте позже.');
            report($e);

            return;
        }

        $this->dispatch('regatta-entry-submitted', entryId: $entry->id);
        $this->closeModal();
        $this->dispatch('notify',
            type: 'success',
            message: 'Заявка на регату успешно подана!'
        );
    }

    #[Computed]
    public function organizerTeams(): Collection
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return collect();
        }

        return $user->teamMemberships()
            ->where('role', 'organizer')
            ->where('status', 'active')
            ->with('team')
            ->get()
            ->pluck('team')
            ->filter();
    }

    #[Computed]
    public function userYachts(): Collection
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return collect();
        }

        return $user->yachts()->get();
    }

    #[Computed]
    public function state(): string
    {
        if (! Auth::check()) {
            return 'guest';
        }

        /** @var User $user */
        $user = Auth::user();

        $hasOrganizerTeam = $user->teamMemberships()
            ->where('role', 'organizer')
            ->where('status', 'active')
            ->exists();

        if (! $hasOrganizerTeam) {
            return 'no-team';
        }

        return 'can-apply';
    }

    public function render()
    {
        return view('livewire.join-regatta-modal');
    }
}
