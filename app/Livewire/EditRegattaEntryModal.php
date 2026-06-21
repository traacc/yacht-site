<?php

namespace App\Livewire;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Enums\CreationSource;
use App\Enums\SportCategory;
use App\Enums\TeamMemberRole;
use App\Filament\User\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Models\RegattaEntry;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Редактирование заявки на регату на публичной странице по спец-паролю,
 * без входа в аккаунт. Доступно только для заявок с заданным entry_password.
 *
 * Редактируемые поля: экипаж (состав и роли), яхта, документы.
 */
class EditRegattaEntryModal extends Component
{
    use WithFileUploads;

    /** Максимальное количество участников экипажа */
    public const int MAX_MEMBERS = 10;

    public bool $isOpen = false;

    public ?string $entryId = null;

    /** Пройдена ли проверка пароля */
    public bool $authenticated = false;

    /** Введённый пароль */
    public string $password = '';

    /** Признак успешного сохранения */
    public bool $saved = false;

    // ──────────────────────────────────────────────
    // Яхта
    // ──────────────────────────────────────────────

    public ?string $yachtId = null;

    public string $yachtMode = 'select'; // select | create

    public string $newYachtName = '';

    public string $newYachtVfps = '';

    /** @var array<int, array{id: string, name: string, vfps: ?string}> */
    public array $freeYachts = [];

    // ──────────────────────────────────────────────
    // Экипаж (transient-список)
    // ──────────────────────────────────────────────

    /**
     * @var array<int, array{ref: string, team_member_id: ?string, user_id: ?string, registered: bool, name: string, birth_date: ?string, sport_category: ?string, role: string}>
     */
    public array $crew = [];

    /** Поиск пользователей для добавления в экипаж */
    public string $searchQuery = '';

    public array $searchResults = [];

    /** Незарегистрированный участник */
    public string $newMemberName = '';

    public string $newMemberBirthDate = '';

    public string $newMemberSportCategory = '';

    // ──────────────────────────────────────────────
    // Документы
    // ──────────────────────────────────────────────

    /** @var array<string, \Livewire\TemporaryUploadedFile[]> новые загрузки по doc_type */
    public array $documentFiles = [];

    /** @var array<string, array<int, array{url: string, name: string}>> существующие файлы по doc_type */
    public array $existingDocuments = [];

    /** @var array<int, string> url существующих файлов, помеченных к удалению */
    public array $removedDocuments = [];

    #[On('open-edit-regatta-entry')]
    public function openModal(string $entryId): void
    {
        $this->resetState();
        $this->entryId = $entryId;
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetState();
    }

    private function resetState(): void
    {
        $this->reset([
            'entryId', 'authenticated', 'password', 'saved',
            'yachtId', 'yachtMode', 'newYachtName', 'newYachtVfps', 'freeYachts',
            'crew', 'searchQuery', 'searchResults',
            'newMemberName', 'newMemberBirthDate', 'newMemberSportCategory',
            'documentFiles', 'existingDocuments', 'removedDocuments',
        ]);
        $this->resetErrorBag();
    }

    #[Computed]
    public function entry(): ?RegattaEntry
    {
        if (! $this->entryId) {
            return null;
        }

        return RegattaEntry::with(['regatta', 'team', 'yacht', 'crew.teamMember.user'])
            ->find($this->entryId);
    }

    // ──────────────────────────────────────────────
    // Шаг 1: проверка пароля
    // ──────────────────────────────────────────────

    public function authenticate(): void
    {
        $this->validate(
            ['password' => ['required', 'string']],
            [],
            ['password' => 'пароль'],
        );

        $entry = $this->entry;

        if (! $entry || ! $entry->hasEntryPassword()) {
            $this->addError('password', 'Редактирование по паролю недоступно для этой заявки.');

            return;
        }

        if (! $entry->checkEntryPassword($this->password)) {
            $this->addError('password', 'Неверный пароль.');

            return;
        }

        $this->authenticated = true;
        $this->password = '';
        $this->loadEntryData($entry);
    }

    private function loadEntryData(RegattaEntry $entry): void
    {
        // Яхта
        $this->yachtMode = 'select';
        $this->yachtId = $entry->yacht_id ? (string) $entry->yacht_id : null;
        $this->loadFreeYachts();

        // Экипаж
        $this->crew = $entry->crew
            ->map(fn ($c): array => [
                'ref'            => (string) Str::uuid(),
                'team_member_id' => (string) $c->team_member_id,
                'user_id'        => $c->teamMember?->user_id ? (string) $c->teamMember->user_id : null,
                'registered'     => true,
                'name'           => $c->teamMember?->user?->name ?? 'Неизвестный',
                'birth_date'     => null,
                'sport_category' => null,
                'role'           => $c->role,
            ])
            ->values()
            ->all();

        // Документы
        $required = $this->requiredDocuments();
        $loaded = app(SyncDocumentFilesAction::class)->load($entry, $required);

        $this->existingDocuments = [];
        foreach ($loaded as $doc) {
            $this->existingDocuments[$doc['doc_type']] = array_map(
                fn (string $url): array => ['url' => $url, 'name' => basename($url)],
                $doc['files'],
            );
        }
    }

    // ──────────────────────────────────────────────
    // Яхта
    // ──────────────────────────────────────────────

    private function loadFreeYachts(): void
    {
        $entry = $this->entry;
        if (! $entry) {
            return;
        }

        $this->freeYachts = Yacht::query()
            ->where(function ($q) use ($entry) {
                $q->whereDoesntHave('regattaEntries', fn ($q) => $q->where('regatta_id', $entry->regatta_id))
                  ->orWhere('id', $entry->yacht_id);
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Yacht $y): array => [
                'id'   => (string) $y->id,
                'name' => $y->name,
                'vfps' => $y->vfps_number,
            ])
            ->values()
            ->all();
    }

    public function startNewYacht(string $name = ''): void
    {
        $this->yachtMode = 'create';
        $this->yachtId = null;
        $this->newYachtName = trim($name);
        $this->newYachtVfps = '';
        $this->resetErrorBag(['yachtId', 'newYachtName']);
    }

    public function clearNewYacht(): void
    {
        $this->yachtMode = 'select';
        $this->newYachtName = '';
        $this->newYachtVfps = '';
        $this->resetErrorBag(['yachtId', 'newYachtName']);
    }

    // ──────────────────────────────────────────────
    // Экипаж
    // ──────────────────────────────────────────────

    public function updatedSearchQuery(): void
    {
        $query = trim($this->searchQuery);

        if ($query === '') {
            $this->searchResults = [];

            return;
        }

        $excludeIds = array_values(array_filter(array_column($this->crew, 'user_id')));

        $this->searchResults = User::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->limit(10)
            ->get(['id', 'name', 'email'])
            ->toArray();
    }

    public function addMember(string $userId): void
    {
        if (count($this->crew) >= self::MAX_MEMBERS) {
            $this->searchQuery = '';
            $this->searchResults = [];
            $this->addError('crew', 'Можно добавить не более ' . self::MAX_MEMBERS . ' участников.');

            return;
        }

        if (in_array($userId, array_column($this->crew, 'user_id'), true)) {
            $this->searchQuery = '';
            $this->searchResults = [];

            return;
        }

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $this->crew[] = [
            'ref'            => (string) Str::uuid(),
            'team_member_id' => null,
            'user_id'        => (string) $user->id,
            'registered'     => true,
            'name'           => $user->name,
            'birth_date'     => null,
            'sport_category' => null,
            'role'           => 'main',
        ];

        $this->searchQuery = '';
        $this->searchResults = [];
    }

    public function addUnregisteredMember(): void
    {
        if (count($this->crew) >= self::MAX_MEMBERS) {
            $this->addError('crew', 'Можно добавить не более ' . self::MAX_MEMBERS . ' участников.');

            return;
        }

        $rules = [
            'newMemberName'      => ['required', 'string', 'max:255'],
            'newMemberBirthDate' => ['required', 'date', 'before:today'],
        ];

        if ($this->newMemberSportCategory !== '') {
            $rules['newMemberSportCategory'] = [Rule::enum(SportCategory::class)];
        }

        $this->validate($rules, [], [
            'newMemberName'          => 'ФИО',
            'newMemberBirthDate'     => 'дата рождения',
            'newMemberSportCategory' => 'разряд',
        ]);

        $name = trim($this->newMemberName);

        $exists = User::where('name', $name)
            ->whereDate('birth_date', $this->newMemberBirthDate)
            ->exists();

        if ($exists) {
            $this->addError('newMemberName', 'Пользователь с таким ФИО и датой рождения уже зарегистрирован');

            return;
        }

        $this->crew[] = [
            'ref'            => (string) Str::uuid(),
            'team_member_id' => null,
            'user_id'        => null,
            'registered'     => false,
            'name'           => $name,
            'birth_date'     => $this->newMemberBirthDate,
            'sport_category' => $this->newMemberSportCategory ?: null,
            'role'           => 'main',
        ];

        $this->reset(['newMemberName', 'newMemberBirthDate', 'newMemberSportCategory']);
    }

    public function removeMember(string $ref): void
    {
        $this->crew = array_values(array_filter(
            $this->crew,
            fn (array $m): bool => $m['ref'] !== $ref,
        ));
    }

    public function setCaptain(string $ref): void
    {
        foreach ($this->crew as $i => $m) {
            $this->crew[$i]['role'] = $m['ref'] === $ref ? 'captain' : ($m['role'] === 'captain' ? 'main' : $m['role']);
        }
    }

    // ──────────────────────────────────────────────
    // Документы
    // ──────────────────────────────────────────────

    public function removeExistingDocument(string $docType, string $url): void
    {
        if (! in_array($url, $this->removedDocuments, true)) {
            $this->removedDocuments[] = $url;
        }
    }

    public function restoreExistingDocument(string $url): void
    {
        $this->removedDocuments = array_values(array_filter(
            $this->removedDocuments,
            fn (string $u): bool => $u !== $url,
        ));
    }

    #[Computed]
    public function requiredDocuments(): array
    {
        return ManageRegattaEntries::getRequiredDocuments($this->entry?->regatta_id);
    }

    // ──────────────────────────────────────────────
    // Сохранение
    // ──────────────────────────────────────────────

    public function save(SyncDocumentFilesAction $syncDocuments): void
    {
        $entry = $this->entry;

        if (! $entry || ! $this->authenticated) {
            $this->addError('general', 'Сессия редактирования недействительна. Откройте форму заново.');

            return;
        }

        $rules = [
            'crew'        => ['array', 'min:1', 'max:' . self::MAX_MEMBERS],
            'crew.*.role' => ['required', Rule::in(['main', 'reserve', 'captain'])],
        ];

        if ($this->yachtMode === 'create') {
            $rules['newYachtName'] = ['required', 'string', 'max:255'];
            $rules['newYachtVfps'] = ['required', 'string', 'max:255'];
        } else {
            $rules['yachtId'] = ['required', 'string', 'uuid'];
        }

        foreach ($this->requiredDocuments() as $doc) {
            $key = 'documentFiles.' . $doc['doc_type'];
            $rules[$key] = ['nullable', 'array'];
            $rules[$key . '.*'] = ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:20480'];
        }

        $this->validate($rules, [
            'crew.min'  => 'В экипаже должен быть хотя бы один участник.',
            'crew.max'  => 'Можно добавить не более ' . self::MAX_MEMBERS . ' участников.',
        ], [
            'yachtId'      => 'яхта',
            'newYachtName' => 'название яхты',
            'newYachtVfps' => 'номер паруса',
        ]);

        // Ровно один капитан
        $captainCount = count(array_filter($this->crew, fn (array $m): bool => $m['role'] === 'captain'));
        if ($captainCount !== 1) {
            $this->addError('crew', 'В экипаже должен быть ровно один рулевой.');

            return;
        }

        // Обязательные документы: должен остаться хотя бы один файл (существующий не удалён или новый)
        foreach ($this->requiredDocuments() as $doc) {
            if (! ($doc['is_required'] ?? false)) {
                continue;
            }

            $existing = array_filter(
                $this->existingDocuments[$doc['doc_type']] ?? [],
                fn (array $f): bool => ! in_array($f['url'], $this->removedDocuments, true),
            );
            $uploaded = array_filter((array) ($this->documentFiles[$doc['doc_type']] ?? []));

            if ($existing === [] && $uploaded === []) {
                $this->addError('documentFiles.' . $doc['doc_type'], 'Загрузите обязательный документ «' . $doc['title'] . '».');

                return;
            }
        }

        try {
            DB::transaction(function () use ($entry, $syncDocuments) {
                $team = $entry->team;

                // 1. Яхта
                if ($this->yachtMode === 'create') {
                    $yacht = Yacht::create([
                        'name'            => $this->newYachtName,
                        'vfps_number'     => $this->newYachtVfps ?: null,
                        'user_id'         => $team->organizer_id,
                        'approval_status' => 'pending',
                    ]);
                    $entry->update(['yacht_id' => $yacht->id]);
                } elseif ($this->yachtId && (string) $entry->yacht_id !== $this->yachtId) {
                    $entry->update(['yacht_id' => $this->yachtId]);
                }

                // 2. Экипаж: материализуем TeamMember и собираем карту [team_member_id => role]
                $crewMap = [];

                foreach ($this->crew as $m) {
                    $teamMemberId = $m['team_member_id'];

                    if (! $teamMemberId) {
                        // Незарегистрированный — создаём пользователя
                        if (! ($m['registered'] ?? false) && ! $m['user_id']) {
                            $user = User::create([
                                'name'            => $m['name'],
                                'birth_date'      => $m['birth_date'],
                                'sport_category'  => $m['sport_category'],
                                'email'           => $this->generateUniqueEmail(),
                                'phone'           => $this->generateUniquePhone(),
                                'password'        => Str::password(16),
                                'creation_source' => CreationSource::QuickRequest,
                            ]);
                            $userId = (string) $user->id;
                            $isPermanent = true;
                        } else {
                            $userId = (string) $m['user_id'];
                            $isPermanent = false;
                        }

                        $member = TeamMember::firstOrCreate(
                            ['team_id' => $team->id, 'user_id' => $userId],
                            [
                                'role'         => TeamMemberRole::Member->value,
                                'status'       => 'active',
                                'is_permanent' => $isPermanent,
                                'joined_at'    => now(),
                            ],
                        );
                        $teamMemberId = (string) $member->id;
                    }

                    $crewMap[$teamMemberId] = $m['role'];
                }

                // Удаляем выбывших, обновляем/создаём остальных
                $entry->crew()->whereNotIn('team_member_id', array_keys($crewMap))->delete();
                foreach ($crewMap as $memberId => $role) {
                    $entry->crew()->updateOrCreate(
                        ['team_member_id' => $memberId],
                        ['role' => $role],
                    );
                }

                // 3. Документы
                $documentsData = [];
                foreach ($this->requiredDocuments() as $doc) {
                    $docType = $doc['doc_type'];

                    // Существующие, не помеченные к удалению
                    $files = array_values(array_filter(
                        array_map(fn (array $f): string => $f['url'], $this->existingDocuments[$docType] ?? []),
                        fn (string $url): bool => ! in_array($url, $this->removedDocuments, true),
                    ));

                    // Новые загрузки
                    $uploads = (array) ($this->documentFiles[$docType] ?? []);
                    foreach ($uploads as $file) {
                        if (! $file) {
                            continue;
                        }
                        $files[] = $file->store('documents', 'public');
                    }

                    $documentsData[] = [
                        'doc_type' => $docType,
                        'title'    => $doc['title'],
                        'files'    => $files,
                    ];
                }

                $syncDocuments->execute($entry, $documentsData);
            });
        } catch (\Exception $e) {
            report($e);
            $this->addError('general', 'Не удалось сохранить изменения. Попробуйте позже.');

            return;
        }

        $this->saved = true;
        $this->dispatch('regatta-entry-updated', entryId: $entry->id);
    }

    private function generateUniqueEmail(): string
    {
        do {
            $email = 'noemail_' . Str::lower(Str::random(24)) . '@noemail.local';
        } while (User::where('email', $email)->exists());

        return $email;
    }

    private function generateUniquePhone(): string
    {
        do {
            $phone = '+7' . str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        } while (User::where('phone', $phone)->exists());

        return $phone;
    }

    public function render()
    {
        return view('livewire.edit-regatta-entry-modal');
    }
}
