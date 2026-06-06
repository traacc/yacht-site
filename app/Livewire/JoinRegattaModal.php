<?php

namespace App\Livewire;

use App\Actions\Regatta\SubmitRegattaEntryAction;
use App\Filament\User\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Models\Regatta;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class JoinRegattaModal extends Component
{
    use WithFileUploads;

    public ?string $regattaId = null;

    public ?string $teamId = null;

    public ?string $yachtId = null;

    public bool $isOpen = false;

    public bool $submitted = false;

    /** @var array<string, \Livewire\TemporaryUploadedFile[]> */
    public array $documentFiles = [];

    /**
     * Экипаж: team_member_id => роль ('main'|'reserve'|'captain').
     *
     * @var array<string, string>
     */
    public array $crew = [];

    #[On('open-join-regatta-modal')]
    public function openModal(string $regattaId): void
    {
        $this->regattaId = $regattaId;
        $this->teamId = null;
        $this->yachtId = null;
        $this->documentFiles = [];
        $this->crew = [];
        $this->submitted = false;
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->reset(['regattaId', 'teamId', 'yachtId', 'documentFiles', 'crew', 'submitted']);
    }

    public function updatedTeamId(): void
    {
        $this->crew = [];
    }

    public function submit(SubmitRegattaEntryAction $action): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            $this->addError('general', 'Требуется авторизация.');

            return;
        }

        $rules = [
            'teamId'  => ['required', 'string', 'uuid'],
            'yachtId' => ['required', 'string', 'uuid'],
        ];

        // Добавляем правила валидации для документов (массив файлов)
        foreach ($this->requiredDocuments() as $doc) {
            $key = 'documentFiles.' . $doc['doc_type'];
            $isRequired = $doc['is_required'] ?? false;
            $rules[$key] = [$isRequired ? 'required' : 'nullable', 'array'];
            $rules[$key . '.*'] = ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:20480'];
        }

        $this->validate($rules, [], [
            'teamId'  => 'команда',
            'yachtId' => 'яхта',
        ]);

        $regatta = Regatta::findOrFail($this->regattaId);
        $team = Team::findOrFail($this->teamId);
        $yacht = Yacht::findOrFail($this->yachtId);

        try {
            $entry = $action->handle($regatta, $team, $yacht, $user, array_filter($this->crew, fn ($role) => $role !== '' && $role !== null));
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

        // Сохраняем загруженные документы (поддержка нескольких файлов)
        foreach ($this->requiredDocuments() as $doc) {
            $files = $this->documentFiles[$doc['doc_type']] ?? [];

            if (! is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                if (! $file) {
                    continue;
                }
                $path = $file->store('documents', 'public');
                $entry->documents()->create([
                    'doc_type' => $doc['doc_type'],
                    'title'    => $doc['title'],
                    'url'      => $path,
                ]);
            }
        }

        $this->dispatch('regatta-entry-submitted', entryId: $entry->id);
        $this->submitted = true;
    }

    #[Computed]
    public function organizerTeams(): Collection
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return collect();
        }

        return $user->teamMemberships()
            ->whereIn('role', ['organizer', 'team_admin'])
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
    public function allFreeYachts(): Collection
    {
        return Yacht::whereDoesntHave('regattaEntries', function ($q) {
            $q->where('regatta_id', $this->regattaId);
        })->get();
    }

    /**
     * Возвращает список обязательных документов для текущей регаты.
     * Если у регаты настроены собственные документы — применяются они,
     * иначе — глобальные настройки.
     *
     * @return array<int, array{doc_type: string, title: string}>
     */
    #[Computed]
    public function requiredDocuments(): array
    {
        return ManageRegattaEntries::getRequiredDocuments($this->regattaId);
    }

    /**
     * Активные участники выбранной команды.
     *
     * @return Collection<int, TeamMember>
     */
    public function teamMembers(): Collection
    {
        if (! $this->teamId) {
            return collect();
        }

        return TeamMember::where('team_id', $this->teamId)
            ->where('status', 'active')
            ->with(['user', 'team'])
            ->get()
            ->map(function (TeamMember $member): TeamMember {
                $member->is_captain = $member->team->organizer_id === $member->user_id;
                return $member;
            });
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
            ->whereIn('role', ['organizer', 'team_admin'])
            ->where('status', 'active')
            ->exists();

        if (! $hasOrganizerTeam) {
            return $hasOrganizerTeam;
        }

        return $hasOrganizerTeam;
    }

    public function render()
    {
        return view('livewire.join-regatta-modal');
    }
}
