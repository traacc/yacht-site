<?php

namespace App\Livewire;

use App\Actions\Regatta\SubmitRegattaEntryAction;
use App\Enums\TeamMemberRole;
use App\Filament\User\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Models\Regatta;
use App\Models\RegattaEntryCrew;
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

    public bool $leftCrew = false;

    /** @var array<string, \Livewire\TemporaryUploadedFile[]> */
    public array $documentFiles = [];

    /**
     * Экипаж: team_member_id => роль ('main'|'reserve'|'captain').
     *
     * @var array<string, string>
     */
    public array $crew = [];

    /** Поисковый запрос для добавления участников не из команды */
    public string $searchQuery = '';

    /** Результаты поиска пользователей, не состоящих в выбранной команде */
    public array $searchResults = [];

    /** ID только что добавленных team_member (для мгновенного отображения в crew) */
    public array $newMemberIds = [];

    #[On('open-join-regatta-modal')]
    public function openModal(string $regattaId): void
    {
        $this->regattaId = $regattaId;
        $this->teamId = null;
        $this->yachtId = null;
        $this->documentFiles = [];
        $this->crew = [];
        $this->submitted = false;
        $this->leftCrew = false;
        $this->isOpen = true;
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->newMemberIds = [];
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->reset(['regattaId', 'teamId', 'yachtId', 'documentFiles', 'crew', 'submitted', 'leftCrew', 'searchQuery', 'searchResults', 'newMemberIds']);
    }

    public function updatedTeamId(): void
    {
        $this->crew = [];
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->newMemberIds = [];
    }

    /**
     * При изменении роли участника: если выбрали «Капитан»,
     * сбрасываем предыдущего капитана.
     */
    public function updatedCrew(string $value, string $memberId): void
    {
        if ($value !== 'captain') {
            return;
        }

        foreach ($this->crew as $id => $role) {
            if ($id !== $memberId && $role === 'captain') {
                $this->crew[$id] = 'main';
            }
        }
    }

    /**
     * Поиск пользователей по имени/фамилии/email,
     * исключая уже состоящих в выбранной команде.
     */
    public function updatedSearchQuery(): void
    {
        $query = trim($this->searchQuery);

        if ($query === '' || ! $this->teamId) {
            $this->searchResults = [];

            return;
        }

        // ID пользователей, уже состоящих в команде
        $existingUserIds = TeamMember::where('team_id', $this->teamId)
            ->pluck('user_id')
            ->toArray();

        $this->searchResults = User::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->when($existingUserIds !== [], fn ($q) => $q->whereNotIn('id', $existingUserIds))
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Добавить пользователя в команду и сразу в экипаж.
     */
    public function addExternalMember(string $userId): void
    {
        if (! $this->teamId) {
            return;
        }

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        // Проверяем, не состоит ли уже в команде
        $exists = TeamMember::where('team_id', $this->teamId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            $this->searchQuery = '';
            $this->searchResults = [];

            return;
        }

        $teamMember = TeamMember::create([
            'team_id'   => $this->teamId,
            'user_id'   => $userId,
            'role'      => TeamMemberRole::Member->value,
            'status'    => 'active',
            'joined_at' => now(),
        ]);

        // Добавляем в экипаж (по умолчанию «Основной»)
        $this->crew[(string) $teamMember->id] = 'main';
        $this->newMemberIds[] = (string) $teamMember->id;

        // Сбрасываем поиск
        $this->searchQuery = '';
        $this->searchResults = [];
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

        // Только один капитан в экипаже
        $captainCount = count(array_filter($this->crew, fn ($role) => $role === 'captain'));
        if ($captainCount > 1) {
            $this->addError('crew', 'Можно выбрать только одного капитана.');

            return;
        }

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
     * Активные участники выбранной команды + только что добавленные.
     *
     * @return Collection<int, TeamMember>
     */
    public function teamMembers(): Collection
    {
        if (! $this->teamId) {
            return collect();
        }

        $members = TeamMember::where('team_id', $this->teamId)
            ->where('status', 'active')
            ->with(['user', 'team'])
            ->orderBy('joined_at', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (TeamMember $member): TeamMember {
                $member->is_captain = $member->team->organizer_id === $member->user_id;
                return $member;
            });

        // Подгружаем только что добавленных участников, если они ещё не в коллекции
        if ($this->newMemberIds !== []) {
            $existingIds = $members->pluck('id')->map(fn ($id) => (string) $id)->toArray();
            $missingIds = array_diff($this->newMemberIds, $existingIds);

            if ($missingIds !== []) {
                $newMembers = TeamMember::whereIn('id', $missingIds)
                    ->with(['user', 'team'])
                    ->get()
                    ->map(function (TeamMember $member): TeamMember {
                        $member->is_captain = $member->team->organizer_id === $member->user_id;
                        return $member;
                    });

                $members = $members->merge($newMembers);
            }
        }

        return $members->values();
    }

    #[Computed]
    public function userCrewEntry(): ?RegattaEntryCrew
    {
        if (! $this->regattaId || ! Auth::check()) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return RegattaEntryCrew::whereHas('teamMember', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('regattaEntry', fn ($q) => $q->where('regatta_id', $this->regattaId)
                ->whereNotIn('status', ['withdrawn', 'rejected']))
            ->first();
    }

    #[Computed]
    public function state(): string
    {
        if (! Auth::check()) {
            return 'guest';
        }

        /** @var User $user */
        $user = Auth::user();

        $crewEntry = $this->userCrewEntry;
        if ($crewEntry) {
            return $crewEntry->role === 'captain' ? 'in-crew-captain' : 'in-crew';
        }

        $hasOrganizerTeam = $user->teamMemberships()
            ->whereIn('role', ['organizer', 'team_admin'])
            ->where('status', 'active')
            ->exists();

        if (! $hasOrganizerTeam) {
            return 'no-team';
        }

        return 'form';
    }

    public function leaveCrew(): void
    {
        $crewEntry = $this->userCrewEntry;
        if (! $crewEntry || $crewEntry->role === 'captain') {
            return;
        }

        $crewEntry->delete();
        $this->dispatch('regatta-crew-left');
        $this->leftCrew = true;
    }

    public function render()
    {
        return view('livewire.join-regatta-modal');
    }
}
