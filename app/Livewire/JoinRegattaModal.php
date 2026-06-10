<?php

namespace App\Livewire;

use App\Actions\Regatta\SubmitRegattaEntryAction;
use App\Enums\TeamMemberRole;
use App\Filament\User\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Mail\SendLoginCredentials;
use App\Models\Regatta;
use App\Models\RegattaEntryCrew;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

    // ──────────────────────────────────────────────
    // Гостевой поток: регистрация + создание команды/яхты при подаче заявки
    // ──────────────────────────────────────────────

    /** Признак того, что заявку оформил гость (для текста на экране успеха) */
    public bool $guestRegistered = false;

    /** ФИО гостя */
    public string $guestName = '';

    /** Email гостя */
    public string $guestEmail = '';

    /** Телефон гостя */
    public string $guestPhone = '';

    /** Название создаваемой командой гостя */
    public string $teamName = '';

    /** Способ указания яхты гостем: 'select' (свободная) | 'create' (новая) */
    public string $yachtMode = 'select';

    /** Поля новой яхты гостя */
    public string $newYachtName = '';

    public string $newYachtVfps = '';

    /**
     * Добавленные гостем участники экипажа (создаются при подаче заявки).
     *
     * @var array<int, array{user_id: string, name: string, email: string, role: string}>
     */
    public array $guestMembers = [];

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
        $this->guestRegistered = false;
        $this->guestName = '';
        $this->guestEmail = '';
        $this->guestPhone = '';
        $this->teamName = '';
        $this->yachtMode = 'select';
        $this->newYachtName = '';
        $this->newYachtVfps = '';
        $this->guestMembers = [];
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->reset([
            'regattaId', 'teamId', 'yachtId', 'documentFiles', 'crew', 'submitted', 'leftCrew',
            'searchQuery', 'searchResults', 'newMemberIds',
            'guestRegistered', 'guestName', 'guestEmail', 'guestPhone', 'teamName',
            'yachtMode', 'newYachtName', 'newYachtVfps', 'guestMembers',
        ]);
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

        // Гость: команды ещё нет, исключаем уже добавленных в экипаж пользователей
        if (! Auth::check()) {
            if ($query === '') {
                $this->searchResults = [];

                return;
            }

            $excludeIds = array_column($this->guestMembers, 'user_id');

            $this->searchResults = User::where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%");
                })
                ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
                ->limit(10)
                ->get()
                ->toArray();

            return;
        }

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
     * Гость: добавить найденного пользователя в экипаж (в transient-список).
     * Реальные TeamMember создаются при подаче заявки.
     */
    public function addGuestMember(string $userId): void
    {
        if (in_array($userId, array_column($this->guestMembers, 'user_id'), true)) {
            $this->searchQuery = '';
            $this->searchResults = [];

            return;
        }

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $this->guestMembers[] = [
            'user_id' => (string) $user->id,
            'name'    => $user->name,
            'email'   => (string) $user->email,
            'role'    => 'main',
        ];

        $this->searchQuery = '';
        $this->searchResults = [];
    }

    public function removeGuestMember(string $userId): void
    {
        $this->guestMembers = array_values(array_filter(
            $this->guestMembers,
            fn (array $m): bool => $m['user_id'] !== $userId,
        ));
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

    /**
     * Подача заявки гостем: регистрирует пользователя, создаёт команду и яхту,
     * подаёт заявку и отправляет учётные данные на email.
     */
    public function submitGuest(SubmitRegattaEntryAction $action): void
    {
        $rules = [
            'guestName'  => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (User::whereRaw('LOWER(name) = LOWER(?)', [$value])->exists()) {
                    $fail('Пользователь с таким ФИО уже зарегистрирован');
                }
            }],
            'guestEmail' => ['required', 'email', 'unique:users,email'],
            'guestPhone' => ['required', 'unique:users,phone'],
            'teamName'   => ['required', 'string', 'max:255'],
        ];

        if ($this->yachtMode === 'create') {
            $rules['newYachtName'] = ['required', 'string', 'max:255'];
            $rules['newYachtVfps'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['yachtId'] = ['required', 'string', 'uuid'];
        }

        foreach ($this->requiredDocuments() as $doc) {
            $key = 'documentFiles.' . $doc['doc_type'];
            $isRequired = $doc['is_required'] ?? false;
            $rules[$key] = [$isRequired ? 'required' : 'nullable', 'array'];
            $rules[$key . '.*'] = ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:20480'];
        }

        $this->validate($rules, [
            'guestEmail.unique' => 'Пользователь с таким email уже зарегистрирован',
            'guestPhone.unique' => 'Пользователь с таким телефоном уже зарегистрирован',
        ], [
            'guestName'    => 'ФИО',
            'guestEmail'   => 'email',
            'guestPhone'   => 'телефон',
            'teamName'     => 'название команды',
            'yachtId'      => 'яхта',
            'newYachtName' => 'название яхты',
        ]);

        $regatta = Regatta::findOrFail($this->regattaId);
        $password = Str::password(12);

        try {
            [$entry, $user] = DB::transaction(function () use ($action, $regatta, $password) {
                // 1. Регистрируем пользователя (пароль захешируется кастом 'hashed')
                $user = User::create([
                    'name'     => $this->guestName,
                    'email'    => $this->guestEmail,
                    'phone'    => $this->guestPhone,
                    'password' => $password,
                ]);

                // 2. Создаём команду, пользователь — организатор
                $team = Team::create([
                    'name'            => $this->teamName,
                    'organizer_id'    => $user->id,
                    'approval_status' => 'pending',
                ]);

                $organizerMember = TeamMember::create([
                    'team_id'   => $team->id,
                    'user_id'   => $user->id,
                    'role'      => TeamMemberRole::Organizer->value,
                    'status'    => 'active',
                    'joined_at' => now(),
                ]);

                // 3. Экипаж: организатор — капитан, добавленные участники — со своими ролями
                $crew = [(string) $organizerMember->id => 'captain'];

                foreach ($this->guestMembers as $m) {
                    $member = TeamMember::create([
                        'team_id'   => $team->id,
                        'user_id'   => $m['user_id'],
                        'role'      => TeamMemberRole::Member->value,
                        'status'    => 'active',
                        'joined_at' => now(),
                    ]);
                    $crew[(string) $member->id] = in_array($m['role'], ['main', 'reserve'], true) ? $m['role'] : 'main';
                }

                // 4. Яхта: новая или выбранная свободная
                if ($this->yachtMode === 'create') {
                    $yacht = Yacht::create([
                        'name'            => $this->newYachtName,
                        'vfps_number'     => $this->newYachtVfps ?: null,
                        'user_id'         => $user->id,
                        'approval_status' => 'pending',
                    ]);
                } else {
                    $yacht = Yacht::findOrFail($this->yachtId);
                }

                // 5. Подаём заявку
                $entry = $action->handle($regatta, $team, $yacht, $user, $crew);

                return [$entry, $user];
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                if ($field === 'teamId') {
                    $field = 'teamName';
                } elseif ($field === 'yachtId' && $this->yachtMode === 'create') {
                    $field = 'newYachtName';
                }
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        } catch (\DomainException $e) {
            $this->addError($this->yachtMode === 'create' ? 'newYachtName' : 'yachtId', $e->getMessage());

            return;
        } catch (\Exception $e) {
            $this->addError('general', 'Произошла ошибка при подаче заявки. Попробуйте позже.');
            report($e);

            return;
        }

        // Сохраняем загруженные документы
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

        // Входим под новым пользователем и отправляем учётные данные
        Auth::login($user);
        session()->regenerate();

        try {
            Mail::to($user->email)->send(new SendLoginCredentials($user, $user->email, $password));
        } catch (\Exception $e) {
            report($e);
        }

        $this->dispatch('regatta-entry-submitted', entryId: $entry->id);
        $this->guestRegistered = true;
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
