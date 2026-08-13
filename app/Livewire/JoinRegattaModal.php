<?php

namespace App\Livewire;

use App\Actions\Auth\SendEmailVerificationLinkAction;
use App\Actions\Payment\StartOnlinePaymentAction;
use App\Actions\Regatta\SubmitRegattaEntryAction;
use App\Enums\CreationSource;
use App\Enums\PaymentStatus;
use App\Enums\SportCategory;
use App\Enums\TeamMemberRole;
use App\Filament\User\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Mail\RegattaEntrySubmitted;
use App\Mail\SendLoginCredentials;
use App\Mail\SendRegattaEntryPassword;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\RegattaEntryCrew;
use App\Models\Scopes\OwnedScope;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Yacht;
use App\Services\Payments\PaymentManager;
use App\Services\SettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class JoinRegattaModal extends Component
{
    use WithFileUploads;

    /** Максимальное количество добавляемых участников на заявку (без капитана) */
    public const int MAX_ADDED_MEMBERS = 9;

    public ?string $regattaId = null;

    public ?string $yachtId = null;

    public bool $isOpen = false;

    public bool $submitted = false;

    public bool $leftCrew = false;

    /** @var array<string, TemporaryUploadedFile[]> */
    public array $documentFiles = [];

    /** Отметка участника об оплате сбора (если регата требует сбор) */
    public bool $feePaid = false;

    /** ID только что поданной заявки — для кнопки «Оплатить онлайн» на экране успеха */
    public ?string $submittedEntryId = null;

    /** Письмо для подтверждения e-mail отправлено повторно (экран успеха) */
    public bool $verificationEmailSent = false;

    /**
     * Экипаж готов взять людей со стороны (клубные регаты).
     * При включении на странице заявок появляется кнопка «Хочу в этот экипаж».
     */
    public bool $openForJoin = false;

    /** Условия, на которых экипаж берёт людей */
    public string $joinConditions = '';

    /** Почта, на которую уходят отклики желающих */
    public string $joinContactEmail = '';

    /** Специальный пароль заявки — для редактирования на странице регаты без входа */
    public string $entryPassword = '';

    /** Подтверждение пароля заявки */
    public string $entryPasswordConfirmation = '';

    /**
     * Свободные яхты для текущей регаты (для единого списка выбора/создания).
     *
     * @var array<int, array{id: string, name: string, vfps: ?string}>
     */
    public array $freeYachts = [];

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

    /** Дата рождения нового капитана */
    public string $guestBirthDate = '';

    /** Спортивный разряд нового капитана */
    public string $guestSportCategory = '';

    /** Способ указания капитана гостем: 'select' (существующий) | 'create' (новый) */
    public string $captainMode = 'select';

    /** ID выбранного существующего пользователя-капитана (режим 'select') */
    public ?string $captainUserId = null;

    /** Отображаемое имя выбранного капитана (для чипа) */
    public ?string $captainName = null;

    /** Поисковый запрос для выбора капитана */
    public string $captainSearchQuery = '';

    /** Результаты поиска пользователей для капитана */
    public array $captainSearchResults = [];

    /** Способ указания команды: 'select' (существующая) | 'create' (новая) */
    public string $teamMode = 'select';

    /** ID выбранной существующей команды (режим 'select') */
    public ?string $teamId = null;

    /** Отображаемое название выбранной команды (для чипа) */
    public ?string $teamSelectedName = null;

    /** Название создаваемой команды */
    public string $teamName = '';

    /** Поисковый запрос для выбора команды */
    public string $teamSearchQuery = '';

    /** Результаты поиска команд */
    public array $teamSearchResults = [];

    /** Способ указания яхты гостем: 'select' (свободная) | 'create' (новая) */
    public string $yachtMode = 'select';

    /** Поля новой яхты гостя */
    public string $newYachtName = '';

    public string $newYachtVfps = '';

    /**
     * Слоты экипажа — ровно MAX_ADDED_MEMBERS штук, показываются сразу все.
     * Каждый слот — независимое поле «выбрать существующего или добавить нового».
     *
     * mode: 'empty' (поиск) | 'filled' (выбран/добавлен) | 'new' (ввод нового).
     * registered: null (пусто) | true (существующий user) | false (новый, аккаунт
     * создаётся при подаче заявки).
     *
     * @var array<int, array{
     *     ref: string, mode: string, registered: ?bool, user_id: ?string,
     *     name: string, email: string, birth_date: ?string, sport_category: ?string,
     *     role: string, query: string, results: array<int, array<string, mixed>>,
     *     newName: string, newBirthDate: string, newSportCategory: string
     * }>
     */
    public array $guestMembers = [];

    #[On('open-join-regatta-modal')]
    public function openModal(?string $regattaId = null): void
    {
        $this->regattaId = $regattaId;
        $this->yachtId = null;
        $this->documentFiles = [];
        $this->submitted = false;
        $this->submittedEntryId = null;
        $this->verificationEmailSent = false;
        $this->leftCrew = false;
        $this->isOpen = true;
        $this->feePaid = false;
        $this->entryPassword = '';
        $this->entryPasswordConfirmation = '';
        $this->guestRegistered = false;
        $this->guestName = '';
        $this->guestEmail = '';
        $this->guestPhone = '';
        $this->guestBirthDate = '';
        $this->guestSportCategory = '';
        // Капитан по умолчанию — текущий пользователь (для авторизованного), иначе пусто
        $authUser = Auth::user();
        $this->captainMode = 'select';
        $this->captainUserId = $authUser instanceof User ? (string) $authUser->id : null;
        $this->captainName = $authUser instanceof User ? $authUser->name : null;
        $this->captainSearchQuery = '';
        $this->captainSearchResults = [];
        $this->teamId = null;
        $this->teamMode = 'select';
        $this->teamSelectedName = null;
        $this->teamName = '';
        $this->teamSearchQuery = '';
        $this->loadTeams();
        $this->yachtMode = 'select';
        $this->newYachtName = '';
        $this->newYachtVfps = '';
        $this->initMemberSlots();
        $this->loadFreeYachts();
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->reset([
            'regattaId', 'yachtId', 'documentFiles', 'submitted', 'submittedEntryId',
            'verificationEmailSent', 'leftCrew',
            'feePaid', 'entryPassword', 'entryPasswordConfirmation', 'freeYachts',
            'openForJoin', 'joinConditions', 'joinContactEmail',
            'guestRegistered', 'guestName', 'guestEmail', 'guestPhone', 'guestBirthDate', 'guestSportCategory',
            'captainMode', 'captainUserId', 'captainName', 'captainSearchQuery', 'captainSearchResults',
            'teamMode', 'teamId', 'teamSelectedName', 'teamName', 'teamSearchQuery', 'teamSearchResults',
            'yachtMode', 'newYachtName', 'newYachtVfps', 'guestMembers',
        ]);
    }

    /**
     * Регаты, доступные для выбора в заявке — предстоящие, открытые к регистрации.
     *
     * @return Collection<int, Regatta>
     */
    #[Computed]
    public function availableRegattas(): Collection
    {
        $regattas = Regatta::closest()->get();

        // Регата, переданная при открытии модалки (по кнопке), может быть вне
        // списка предстоящих (например, уже идёт) — добавляем её, чтобы сработала
        // автоматическая подстановка в селекте.
        if ($this->regattaId && ! $regattas->contains('id', $this->regattaId)) {
            $current = Regatta::find($this->regattaId);
            if ($current) {
                $regattas = $regattas->prepend($current);
            }
        }

        return $regattas;
    }

    /** При смене регаты сбрасываем зависящие от неё поля (свободные яхты, документы) */
    public function updatedRegattaId(): void
    {
        $this->yachtId = null;
        $this->yachtMode = 'select';
        $this->newYachtName = '';
        $this->newYachtVfps = '';
        $this->documentFiles = [];
        $this->feePaid = false;
        unset($this->selectedRegatta);
        $this->loadFreeYachts();
        $this->resetErrorBag(['yachtId', 'newYachtName', 'regattaId']);
    }

    /** Выбранная регата — для показа информации о сборах в форме. */
    #[Computed]
    public function selectedRegatta(): ?Regatta
    {
        return $this->regattaId ? Regatta::find($this->regattaId) : null;
    }

    /** Загрузить свободные яхты для текущей регаты в плоский список для единого селекта. */
    private function loadFreeYachts(): void
    {
        unset($this->allFreeYachts);

        $this->freeYachts = $this->allFreeYachts
            ->map(fn (Yacht $y): array => [
                'id' => (string) $y->id,
                'name' => $y->name,
                'vfps' => $y->vfps_number,
                'taken' => (bool) $y->getAttribute('is_taken'),
            ])
            ->values()
            ->all();
    }

    /** Выбрать свободную яхту из единого списка. */
    public function selectYacht(string $yachtId): void
    {
        // Яхта уже заявлена в эту регату — выбрать её нельзя.
        if (in_array($yachtId, $this->takenYachtIds(), true)) {
            return;
        }

        $this->yachtMode = 'select';
        $this->yachtId = $yachtId;
        $this->newYachtName = '';
        $this->newYachtVfps = '';
        $this->resetErrorBag(['yachtId', 'newYachtName']);
    }

    /** Начать добавление своей яхты (название берётся из поискового запроса). */
    public function startNewYacht(string $name = ''): void
    {
        $this->yachtMode = 'create';
        $this->yachtId = null;
        $this->newYachtName = trim($name);
        $this->newYachtVfps = '';
        $this->resetErrorBag(['yachtId', 'newYachtName']);
    }

    /** Существует ли яхта с таким названием (без учёта регистра, любой владелец). */
    private function yachtNameExists(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        return Yacht::withoutGlobalScope(OwnedScope::class)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
    }

    /** Существует ли яхта с таким номером паруса (любой владелец). */
    private function yachtVfpsExists(string $vfps): bool
    {
        $vfps = trim($vfps);
        if ($vfps === '') {
            return false;
        }

        return Yacht::withoutGlobalScope(OwnedScope::class)
            ->where('vfps_number', $vfps)
            ->exists();
    }

    /** Сбросить выбор/создание яхты — вернуться к поиску по списку. */
    public function clearYacht(): void
    {
        $this->yachtMode = 'select';
        $this->yachtId = null;
        $this->newYachtName = '';
        $this->newYachtVfps = '';
        $this->resetErrorBag(['yachtId', 'newYachtName']);
    }

    /**
     * Поиск команд по названию (любая команда, не зависит от капитана).
     */
    public function updatedTeamSearchQuery(): void
    {
        $query = trim($this->teamSearchQuery);

        if ($query === '') {
            $this->loadTeams();

            return;
        }

        $this->teamSearchResults = Team::where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name'])
            ->toArray();
    }

    /** Загрузить начальный список команд для показа сразу по клику (как у яхт). */
    private function loadTeams(): void
    {
        $this->teamSearchResults = Team::orderBy('name')
            ->limit(10)
            ->get(['id', 'name'])
            ->toArray();
    }

    /** Выбрать существующую команду. */
    public function selectTeam(string $teamId): void
    {
        $team = Team::find($teamId);
        if (! $team) {
            return;
        }

        $this->teamMode = 'select';
        $this->teamId = (string) $team->id;
        $this->teamSelectedName = $team->name;
        $this->teamName = '';
        $this->teamSearchQuery = '';
        $this->teamSearchResults = [];
        $this->resetErrorBag(['teamId', 'teamName']);
    }

    /** Начать создание новой команды (название берётся из поискового запроса). */
    public function startNewTeam(string $name = ''): void
    {
        $this->teamMode = 'create';
        $this->teamId = null;
        $this->teamSelectedName = null;
        $this->teamName = trim($name);
        $this->teamSearchQuery = '';
        $this->teamSearchResults = [];
        $this->resetErrorBag(['teamId', 'teamName']);
    }

    /**
     * Зафиксировать поле команды при потере фокуса:
     * точное совпадение по названию — выбрать существующую, иначе — создать новую.
     */
    public function commitTeam(?string $value = null): void
    {
        // Поле уже зафиксировано (выбрана или создаётся команда) — например, blur
        // прилетел после удаления поля поиска из DOM при выборе варианта. Пропускаем.
        if ($this->teamMode !== 'select' || $this->teamId !== null) {
            return;
        }

        $query = trim((string) ($value ?? $this->teamSearchQuery));
        if ($query === '') {
            return;
        }

        $this->teamSearchQuery = $query;

        $team = Team::whereRaw('LOWER(name) = ?', [mb_strtolower($query)])->first(['id']);

        if ($team) {
            $this->selectTeam((string) $team->id);
        } else {
            $this->startNewTeam($query);
        }
    }

    /** Сбросить выбор/создание команды — вернуться к поиску. */
    public function clearTeam(): void
    {
        $this->teamMode = 'select';
        $this->teamId = null;
        $this->teamSelectedName = null;
        $this->teamName = '';
        $this->teamSearchQuery = '';
        $this->loadTeams();
        $this->resetErrorBag(['teamId', 'teamName']);
    }

    /**
     * Поиск пользователей для выбора капитана (только гость).
     * Исключает уже добавленных в экипаж участников.
     */
    public function updatedCaptainSearchQuery(): void
    {
        $query = trim($this->captainSearchQuery);

        if ($query === '') {
            $this->captainSearchResults = [];

            return;
        }

        $excludeIds = array_values(array_filter(array_column($this->guestMembers, 'user_id')));

        $this->captainSearchResults = User::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%");
        })
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->limit(10)
            ->get(['id', 'name', 'email', 'birth_date'])
            ->toArray();
    }

    /** Выбрать существующего пользователя капитаном. */
    public function selectCaptain(string $userId): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $this->captainMode = 'select';
        $this->captainUserId = (string) $user->id;
        $this->captainName = $user->name;
        $this->captainSearchQuery = '';
        $this->captainSearchResults = [];
        $this->guestName = '';
        $this->guestEmail = '';
        $this->guestPhone = '';
        $this->guestBirthDate = '';
        $this->guestSportCategory = '';
        $this->resetErrorBag(['captainUserId', 'guestName', 'guestEmail', 'guestPhone', 'guestBirthDate', 'guestSportCategory']);
    }

    /** Начать создание нового пользователя-капитана (ФИО берётся из запроса). */
    public function startNewCaptain(string $name = ''): void
    {
        $this->captainMode = 'create';
        $this->captainUserId = null;
        $this->captainName = null;
        $this->captainSearchQuery = '';
        $this->captainSearchResults = [];
        $this->guestName = trim($name);
        $this->resetErrorBag(['captainUserId']);
    }

    /**
     * Зафиксировать поле капитана при потере фокуса:
     * точное совпадение по имени/email — выбрать пользователя, иначе — создать нового.
     */
    public function commitCaptain(?string $value = null): void
    {
        // Поле уже зафиксировано (выбран или создаётся капитан) — пропускаем blur,
        // прилетевший после удаления поля поиска из DOM при выборе варианта.
        if ($this->captainMode !== 'select' || $this->captainUserId !== null) {
            return;
        }

        $query = trim((string) ($value ?? $this->captainSearchQuery));
        if ($query === '') {
            return;
        }

        $this->captainSearchQuery = $query;

        $excludeIds = array_values(array_filter(array_column($this->guestMembers, 'user_id')));

        $user = User::where(function ($q) use ($query) {
            $q->whereRaw('LOWER(name) = ?', [mb_strtolower($query)])
                ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($query)]);
        })
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->first(['id']);

        if ($user) {
            $this->selectCaptain((string) $user->id);
        } else {
            $this->startNewCaptain($query);
        }
    }

    /** Сбросить выбор/создание капитана — вернуться к поиску. */
    public function clearCaptain(): void
    {
        $this->captainMode = 'select';
        $this->captainUserId = null;
        $this->captainName = null;
        $this->captainSearchQuery = '';
        $this->captainSearchResults = [];
        $this->guestName = '';
        $this->guestEmail = '';
        $this->guestPhone = '';
        $this->guestBirthDate = '';
        $this->guestSportCategory = '';
        $this->resetErrorBag(['captainUserId', 'guestName', 'guestEmail', 'guestPhone', 'guestBirthDate', 'guestSportCategory']);
    }

    /** Пустой слот экипажа. */
    private function emptySlot(): array
    {
        return [
            'ref' => (string) Str::uuid(),
            'mode' => 'empty',
            'registered' => null,
            'user_id' => null,
            'name' => '',
            'email' => '',
            'birth_date' => null,
            'sport_category' => null,
            'role' => 'main',
            'query' => '',
            'results' => [],
            'newName' => '',
            'newBirthDate' => '',
            'newSportCategory' => '',
        ];
    }

    /** Создать ровно MAX_ADDED_MEMBERS пустых слотов экипажа. */
    private function initMemberSlots(): void
    {
        $this->guestMembers = [];
        for ($i = 0; $i < self::MAX_ADDED_MEMBERS; $i++) {
            $this->guestMembers[] = $this->emptySlot();
        }
    }

    /**
     * ID пользователей, уже выбранных в других слотах (для исключения из поиска),
     * плюс выбранный капитан.
     *
     * @return array<int, string>
     */
    private function excludedMemberUserIds(?int $exceptIndex = null): array
    {
        $ids = [];
        foreach ($this->guestMembers as $idx => $slot) {
            if ($exceptIndex !== null && $idx === $exceptIndex) {
                continue;
            }
            if (! empty($slot['user_id'])) {
                $ids[] = (string) $slot['user_id'];
            }
        }

        if ($this->captainMode === 'select' && $this->captainUserId) {
            $ids[] = (string) $this->captainUserId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Реакция на изменение полей слотов: поиск пользователей при вводе в поле слота.
     */
    public function updatedGuestMembers(mixed $value, ?string $key): void
    {
        if ($key === null || ! str_ends_with($key, '.query')) {
            return;
        }

        $index = (int) explode('.', $key)[0];
        $this->searchSlot($index);
    }

    /** Поиск пользователей для конкретного слота экипажа. */
    private function searchSlot(int $i): void
    {
        if (! isset($this->guestMembers[$i])) {
            return;
        }

        $query = trim($this->guestMembers[$i]['query'] ?? '');

        if ($query === '') {
            $this->guestMembers[$i]['results'] = [];

            return;
        }

        $excludeIds = $this->excludedMemberUserIds($i);

        $this->guestMembers[$i]['results'] = User::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%");
        })
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->limit(10)
            ->get(['id', 'name', 'email', 'birth_date'])
            ->toArray();
    }

    /** Выбрать существующего пользователя в слот экипажа. */
    public function selectSlotUser(int $i, string $userId): void
    {
        if (! isset($this->guestMembers[$i])) {
            return;
        }

        // Уже выбран в другом слоте или это капитан — игнорируем.
        if (in_array($userId, $this->excludedMemberUserIds($i), true)) {
            $this->guestMembers[$i]['query'] = '';
            $this->guestMembers[$i]['results'] = [];

            return;
        }

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $ref = $this->guestMembers[$i]['ref'];
        $this->guestMembers[$i] = array_merge($this->emptySlot(), [
            'ref' => $ref,
            'mode' => 'filled',
            'registered' => true,
            'user_id' => (string) $user->id,
            'name' => $user->name,
            'email' => (string) $user->email,
            'role' => 'main',
        ]);
    }

    /** Начать ввод нового (незарегистрированного) участника в слот. */
    public function startSlotNew(int $i): void
    {
        if (! isset($this->guestMembers[$i])) {
            return;
        }

        $name = trim($this->guestMembers[$i]['query'] ?? '');
        $this->guestMembers[$i]['mode'] = 'new';
        $this->guestMembers[$i]['newName'] = $name;
        $this->guestMembers[$i]['newBirthDate'] = '';
        $this->guestMembers[$i]['newSportCategory'] = '';
        $this->guestMembers[$i]['query'] = '';
        $this->guestMembers[$i]['results'] = [];
    }

    /**
     * Зафиксировать слот экипажа при потере фокуса:
     * точное совпадение по имени/email — выбрать пользователя, иначе — создать нового.
     */
    public function commitSlot(int $i, ?string $value = null): void
    {
        if (! isset($this->guestMembers[$i])) {
            return;
        }

        // Слот уже зафиксирован (выбран/создаётся участник) — пропускаем blur,
        // прилетевший после удаления поля поиска из DOM при выборе варианта.
        if (($this->guestMembers[$i]['mode'] ?? 'empty') !== 'empty') {
            return;
        }

        $query = trim((string) ($value ?? $this->guestMembers[$i]['query'] ?? ''));
        if ($query === '') {
            return;
        }

        $this->guestMembers[$i]['query'] = $query;

        $excludeIds = $this->excludedMemberUserIds($i);

        $user = User::where(function ($q) use ($query) {
            $q->whereRaw('LOWER(name) = ?', [mb_strtolower($query)])
                ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($query)]);
        })
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->first(['id']);

        if ($user) {
            $this->selectSlotUser($i, (string) $user->id);
        } else {
            $this->startSlotNew($i);
        }
    }

    /** Подтвердить нового незарегистрированного участника в слоте. */
    public function addSlotNew(int $i): void
    {
        if (! isset($this->guestMembers[$i])) {
            return;
        }

        $rules = [
            "guestMembers.$i.newName" => ['required', 'string', 'max:255', $this->fullNameRule()],
            "guestMembers.$i.newBirthDate" => ['required', 'date', 'before:today'],
        ];

        if (($this->guestMembers[$i]['newSportCategory'] ?? '') !== '') {
            $rules["guestMembers.$i.newSportCategory"] = [Rule::enum(SportCategory::class)];
        }

        $this->validate($rules, [], [
            "guestMembers.$i.newName" => 'ФИО',
            "guestMembers.$i.newBirthDate" => 'дата рождения',
            "guestMembers.$i.newSportCategory" => 'разряд',
        ]);

        $name = trim($this->guestMembers[$i]['newName']);
        $birthDate = $this->guestMembers[$i]['newBirthDate'];

        // Уникальность по сочетанию ФИО + дата рождения (как в User::saving)
        $exists = User::where('name', $name)
            ->whereDate('birth_date', $birthDate)
            ->exists();

        if ($exists) {
            $this->addError("guestMembers.$i.newName", 'Пользователь с таким ФИО и датой рождения уже зарегистрирован');

            return;
        }

        $ref = $this->guestMembers[$i]['ref'];
        $this->guestMembers[$i] = array_merge($this->emptySlot(), [
            'ref' => $ref,
            'mode' => 'filled',
            'registered' => false,
            'name' => $name,
            'birth_date' => $birthDate,
            'sport_category' => $this->guestMembers[$i]['newSportCategory'] ?: null,
            'role' => 'main',
        ]);
    }

    /** Очистить слот — вернуть его в режим поиска. */
    public function clearSlot(int $i): void
    {
        if (! isset($this->guestMembers[$i])) {
            return;
        }

        $ref = $this->guestMembers[$i]['ref'];
        $this->guestMembers[$i] = $this->emptySlot();
        $this->guestMembers[$i]['ref'] = $ref;
        $this->resetErrorBag(["guestMembers.$i.newName", "guestMembers.$i.newBirthDate", "guestMembers.$i.newSportCategory"]);
    }

    /**
     * Правило валидации: ФИО должно содержать отчество.
     * name имеет вид «Фамилия Имя Отчество» — минимум три слова.
     */
    private function fullNameRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $parts = preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);

            if (count($parts) < 3) {
                $fail('Укажите ФИО полностью, включая отчество (Фамилия Имя Отчество).');
            }
        };
    }

    /** Сгенерировать уникальный «технический» email для незарегистрированного участника */
    private function generateUniqueEmail(): string
    {
        do {
            $email = 'noemail_'.Str::lower(Str::random(24)).'@noemail.local';
        } while (User::where('email', $email)->exists());

        return $email;
    }

    /** Сгенерировать уникальный «технический» телефон для незарегистрированного участника */
    private function generateUniquePhone(): string
    {
        do {
            $phone = '+7'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        } while (User::where('phone', $phone)->exists());

        return $phone;
    }

    /**
     * Подача заявки: создаёт команду и яхту, подаёт заявку.
     * Для гостя дополнительно регистрирует пользователя, входит под ним
     * и отправляет учётные данные на email.
     */
    public function submitGuest(SubmitRegattaEntryAction $action): void
    {
        $actor = Auth::user() instanceof User ? Auth::user() : null;

        // Команда: выбор существующей или создание новой — независимо от капитана
        $selectsTeam = $this->teamMode === 'select';

        // Капитан: выбор существующего пользователя или создание нового — для всех
        $selectsCaptain = $this->captainMode === 'select';

        $rules = [
            'regattaId' => ['required', 'string', 'exists:regattas,id'],
            'entryPassword' => ['required', 'string', 'min:4', 'max:255'],
            'entryPasswordConfirmation' => ['required', 'string', 'same:entryPassword'],
            'joinConditions' => ['nullable', 'string', 'max:2000'],
        ];

        // Почта для откликов обязательна: без неё экипаж не узнает о желающих.
        if ($this->openForJoin) {
            $rules['joinContactEmail'] = ['required', 'email', 'max:255'];
        }

        if ($selectsTeam) {
            $rules['teamId'] = ['required', 'string', 'uuid', 'exists:teams,id'];
        } else {
            $rules['teamName'] = ['required', 'string', 'max:255'];
        }

        if ($selectsCaptain) {
            $rules['captainUserId'] = ['required', 'string', 'exists:users,id'];
        } else {
            $rules['guestName'] = ['required', 'string', 'max:255', $this->fullNameRule()];
            $rules['guestEmail'] = ['required', 'email', 'unique:users,email'];
            $rules['guestPhone'] = ['required', 'unique:users,phone'];
            $rules['guestBirthDate'] = ['required', 'date', 'before:today'];

            if ($this->guestSportCategory !== '') {
                $rules['guestSportCategory'] = [Rule::enum(SportCategory::class)];
            }
        }

        if ($this->yachtMode === 'create') {
            $rules['newYachtName'] = ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->yachtNameExists((string) $value)) {
                    $fail('Яхта с таким названием уже существует — выберите её из списка.');
                }
            }];
            $rules['newYachtVfps'] = ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->yachtVfpsExists((string) $value)) {
                    $fail('Яхта с таким номером паруса уже существует — выберите её из списка.');
                }
            }];
        } else {
            $rules['yachtId'] = ['required', 'string', 'uuid'];
        }

        // Документы не блокируют подачу: заявку можно подать без файлов.
        // Недостающие обязательные документы помечаются на заявке (documents_complete).
        foreach ($this->requiredDocuments() as $doc) {
            $key = 'documentFiles.'.$doc['doc_type'];
            $rules[$key] = ['nullable', 'array'];
            $rules[$key.'.*'] = ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:20480'];
        }

        $this->validate($rules, [
            'guestEmail.unique' => 'Пользователь с таким email уже зарегистрирован',
            'guestPhone.unique' => 'Пользователь с таким телефоном уже зарегистрирован',
            'entryPasswordConfirmation.same' => 'Пароли не совпадают',
        ], [
            'regattaId' => 'регата',
            'entryPassword' => 'пароль заявки',
            'entryPasswordConfirmation' => 'подтверждение пароля',
            'guestName' => 'ФИО',
            'guestEmail' => 'email',
            'guestPhone' => 'телефон',
            'guestBirthDate' => 'дата рождения',
            'guestSportCategory' => 'разряд',
            'teamId' => 'команда',
            'teamName' => 'название команды',
            'yachtId' => 'яхта',
            'newYachtName' => 'название яхты',
            'newYachtVfps' => 'номер паруса',
            'captainUserId' => 'капитан',
        ]);

        $regatta = Regatta::findOrFail($this->regattaId);

        // Заявки закрыты для завершённых, отменённых и перенесённых регат.
        if (! $regatta->isOpenForRegistration()) {
            $this->addError('regattaId', 'Регистрация на эту регату закрыта.');

            return;
        }

        // Команда выбрана из списка — в экипаже (рулевой или участники) должен быть
        // хотя бы один постоянный участник этой команды. Новые (незарегистрированные)
        // участники постоянными быть не могут — учитываем только существующих.
        if ($selectsTeam && $this->teamId) {
            $crewUserIds = [];

            if ($selectsCaptain && $this->captainUserId) {
                $crewUserIds[] = (string) $this->captainUserId;
            }

            foreach ($this->guestMembers as $m) {
                if (($m['registered'] ?? null) === true && ! empty($m['user_id'])) {
                    $crewUserIds[] = (string) $m['user_id'];
                }
            }

            $hasPermanentMember = $crewUserIds !== [] && TeamMember::query()
                ->where('team_id', $this->teamId)
                ->where('is_permanent', true)
                ->whereIn('user_id', $crewUserIds)
                ->exists();

            if (! $hasPermanentMember) {
                $this->addError('teamId', 'В экипаже должен быть хотя бы один постоянный участник выбранной команды.');

                return;
            }
        }

        // $password = Str::password(12);
        $password = 'Carter30pro';

        try {
            [$entry, $captain] = DB::transaction(function () use ($action, $regatta, $password, $actor, $selectsTeam, $selectsCaptain) {
                // 1. Капитан: существующий пользователь или новый
                //    (пароль захешируется кастом 'hashed').
                if ($selectsCaptain) {
                    $captain = User::findOrFail($this->captainUserId);
                } else {
                    $captain = User::create([
                        'name' => $this->guestName,
                        'email' => $this->guestEmail,
                        'phone' => $this->guestPhone,
                        'birth_date' => $this->guestBirthDate,
                        'sport_category' => $this->guestSportCategory ?: null,
                        'password' => $password,
                        'creation_source' => CreationSource::QuickRequest,
                    ]);
                }

                // Владелец/податель заявки: авторизованный — он сам; гость — капитан
                // (единственная доступная личность в гостевом потоке).
                $submitter = $actor ?? $captain;

                // 2. Команда: существующая или новая — независимо от капитана.
                //    Роль «капитан» назначается только в экипаже (см. ниже), организатора
                //    существующей команды не трогаем.
                if ($selectsTeam) {
                    $team = Team::findOrFail($this->teamId);

                    // Право на подачу заявки даёт действующий организатор/админ команды
                    $submitActor = $team->teamMembers()
                        ->whereIn('role', [TeamMemberRole::Organizer->value, TeamMemberRole::TeamAdmin->value])
                        ->where('status', 'active')
                        ->with('user')
                        ->first()?->user
                        ?? $team->organizer;

                    if (! $submitActor instanceof User) {
                        throw ValidationException::withMessages([
                            'teamId' => 'У выбранной команды нет организатора, подача невозможна.',
                        ]);
                    }
                } else {
                    $team = Team::create([
                        'name' => $this->teamName,
                        'organizer_id' => $submitter->id,
                        'approval_status' => 'approved',
                    ]);

                    TeamMember::firstOrCreate(
                        ['team_id' => $team->id, 'user_id' => $submitter->id],
                        [
                            'role' => TeamMemberRole::Organizer->value,
                            'status' => 'active',
                            'joined_at' => now(),
                        ],
                    );

                    $submitActor = $submitter;
                }

                // 3. Капитан — активный участник команды (без повышения роли).
                //    Если уже состоит — роль не меняем; роль «капитан» — только в экипаже.
                $captainMember = TeamMember::firstOrCreate(
                    ['team_id' => $team->id, 'user_id' => $captain->id],
                    [
                        'role' => TeamMemberRole::Member->value,
                        'status' => 'active',
                        'joined_at' => now(),
                    ],
                );
                if ($captainMember->status !== 'active') {
                    $captainMember->update(['status' => 'active']);
                }

                // 4. Экипаж: капитан — captain, добавленные участники — со своими ролями
                $crew = [(string) $captainMember->id => 'captain'];

                foreach ($this->guestMembers as $m) {
                    // Пустой слот — пропускаем.
                    if (($m['registered'] ?? null) === null) {
                        continue;
                    }

                    $isUnregistered = ! ($m['registered'] ?? false);

                    // Незарегистрированный участник: создаём аккаунт со случайным email/телефоном
                    if ($isUnregistered) {
                        $memberUser = User::create([
                            'name' => $m['name'],
                            'birth_date' => $m['birth_date'],
                            'sport_category' => $m['sport_category'],
                            'email' => $this->generateUniqueEmail(),
                            'phone' => $this->generateUniquePhone(),
                            'password' => 'Carter30pro',
                            'creation_source' => CreationSource::QuickRequest,
                        ]);
                        $memberUserId = (string) $memberUser->id;
                    } else {
                        $memberUserId = (string) $m['user_id'];
                    }

                    // Капитан уже в экипаже как captain — не дублируем
                    if ($memberUserId === (string) $captain->id) {
                        continue;
                    }

                    // Участник может уже состоять в выбранной команде — не дублируем
                    $member = TeamMember::firstOrCreate(
                        [
                            'team_id' => $team->id,
                            'user_id' => $memberUserId,
                        ],
                        [
                            'role' => TeamMemberRole::Member->value,
                            'status' => 'active',
                            'is_permanent' => $isUnregistered,
                            'joined_at' => now(),
                        ],
                    );
                    $crew[(string) $member->id] = in_array($m['role'], ['main', 'reserve'], true) ? $m['role'] : 'main';
                }

                // 5. Яхта: новая или выбранная свободная
                if ($this->yachtMode === 'create') {
                    $yacht = Yacht::create([
                        'name' => $this->newYachtName,
                        'vfps_number' => $this->newYachtVfps ?: null,
                        'user_id' => $submitter->id,
                        'approval_status' => 'approved',
                    ]);
                } else {
                    $yacht = Yacht::withoutGlobalScope(OwnedScope::class)->findOrFail($this->yachtId);
                }

                // 6. Подаём заявку от имени организатора команды (проверка прав на подачу)
                $entry = $action->handle(
                    $regatta,
                    $team,
                    $yacht,
                    $submitActor,
                    $crew,
                    $this->entryPassword,
                    $this->feePaid,
                    [
                        'open_for_join' => $this->openForJoin,
                        'join_conditions' => $this->joinConditions ?: null,
                        'join_contact_email' => $this->joinContactEmail ?: null,
                    ],
                );

                return [$entry, $captain];
            });
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                if ($field === 'teamId' && ! $selectsTeam) {
                    $field = 'teamName';
                } elseif ($field === 'yachtId' && $this->yachtMode === 'create') {
                    $field = 'newYachtName';
                } elseif ($field === 'name' && ! $selectsCaptain) {
                    // Дубль ФИО+дата рождения при создании нового капитана (User::saving)
                    $field = 'guestName';
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

        // Сохраняем загруженные документы и попутно отмечаем нехватку обязательных.
        $missingRequired = false;
        foreach ($this->requiredDocuments() as $doc) {
            $files = $this->documentFiles[$doc['doc_type']] ?? [];

            if (! is_array($files)) {
                $files = [$files];
            }

            $files = array_filter($files);

            if ($files === [] && ($doc['is_required'] ?? false)) {
                $missingRequired = true;
            }

            foreach ($files as $file) {
                $path = $file->store('documents', 'public');
                $entry->documents()->create([
                    'doc_type' => $doc['doc_type'],
                    'title' => $doc['title'],
                    'url' => $path,
                ]);
            }
        }

        if ($missingRequired) {
            $entry->update(['documents_complete' => false]);
        }

        // Уведомляем администраторов о новой заявке на регату.
        $adminEmails = app(SettingsService::class)->adminNotificationEmails();

        if ($adminEmails !== []) {
            try {
                Mail::to($adminEmails)->send(new RegattaEntrySubmitted($entry));
            } catch (\Exception $e) {
                report($e);
            }
        }

        // Отправляем пароль заявки на почту капитану (кроме «технических» адресов).
        if ($captain->email && ! str_ends_with($captain->email, '@noemail.local')) {
            try {
                Mail::to($captain->email)->send(new SendRegattaEntryPassword($captain, $regatta, $this->entryPassword));
            } catch (\Exception $e) {
                report($e);
            }
        }

        // Гость, создавший нового капитана: входим под ним и отправляем учётные данные.
        // Если капитаном выбран существующий пользователь или заявку подаёт
        // авторизованный — вход и письмо не нужны.
        if (! $actor && ! $selectsCaptain) {
            Auth::login($captain, remember: true);
            session()->regenerate();

            try {
                Mail::to($captain->email)->send(new SendLoginCredentials($captain, $captain->email, $password));
            } catch (\Exception $e) {
                report($e);
            }

            // Письмо для подтверждения e-mail — без него недоступна онлайн-оплата взноса.
            try {
                app(SendEmailVerificationLinkAction::class)->handle($captain, throttle: false);
            } catch (\Exception $e) {
                report($e);
            }

            $this->guestRegistered = true;
        }

        $this->dispatch('regatta-entry-submitted', entryId: $entry->id);
        $this->submittedEntryId = $entry->id;
        $this->submitted = true;
    }

    // ──────────────────────────────────────────────
    // Онлайн-оплата стартового взноса
    // ──────────────────────────────────────────────

    /** Есть ли по поданной заявке неоплаченный взнос с доступной онлайн-оплатой. */
    private function feeIsPayable(): bool
    {
        return $this->submittedEntryId !== null
            && (bool) $this->selectedRegatta?->entry_fee_required
            && ! $this->feePaid
            && app(PaymentManager::class)->isEnabled();
    }

    /** Доступна ли онлайн-оплата взноса для только что поданной заявки. */
    #[Computed]
    public function canPayOnline(): bool
    {
        return $this->feeIsPayable()
            && (bool) Auth::user()?->hasVerifiedEmail();
    }

    /** Нужно ли перед оплатой подтвердить e-mail (вместо кнопки оплаты). */
    #[Computed]
    public function needsEmailVerification(): bool
    {
        $user = Auth::user();

        return $this->feeIsPayable()
            && $user instanceof User
            && ! $user->hasVerifiedEmail()
            && ! $user->hasTechnicalEmail();
    }

    /** Повторная отправка письма для подтверждения e-mail. */
    public function resendVerificationEmail(): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            $this->addError('general', 'Войдите в личный кабинет, чтобы подтвердить e-mail.');

            return;
        }

        $this->verificationEmailSent = false;

        try {
            app(SendEmailVerificationLinkAction::class)->handle($user);
        } catch (ValidationException $e) {
            $this->addError('general', collect($e->errors())->flatten()->first());

            return;
        }

        $this->verificationEmailSent = true;
    }

    /** Редирект на страницу оплаты стартового взноса поданной заявки. */
    public function payOnline(): void
    {
        $entry = $this->submittedEntryId
            ? RegattaEntry::find($this->submittedEntryId)
            : null;

        if ($entry === null) {
            $this->addError('general', 'Заявка не найдена. Оплатить взнос можно из личного кабинета.');

            return;
        }

        $registry = $entry->paymentRegistries()
            ->where('status', '!=', PaymentStatus::Paid->value)
            ->latest()
            ->first();

        if ($registry === null) {
            $this->addError('general', 'Платёж не найден. Оплатить взнос можно из личного кабинета.');

            return;
        }

        $actor = Auth::user();

        if (! $actor instanceof User) {
            $this->addError('general', 'Для онлайн-оплаты войдите в личный кабинет.');

            return;
        }

        try {
            $transaction = app(StartOnlinePaymentAction::class)
                ->handle($registry, $actor);
        } catch (ValidationException $e) {
            $this->addError('general', collect($e->errors())->flatten()->first());

            return;
        }

        $this->redirect($transaction->confirmation_url);
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
        $takenIds = $this->takenYachtIds();

        return Yacht::query()
            ->withoutGlobalScope(OwnedScope::class)
            ->orderBy('name')
            ->get()
            ->map(function (Yacht $y) use ($takenIds): Yacht {
                $y->setAttribute('is_taken', in_array((string) $y->id, $takenIds, true));

                return $y;
            })
            // Свободные яхты — в начале списка, занятые — в конце.
            ->sortBy('is_taken')
            ->values();
    }

    /**
     * ID яхт, уже заявленных в текущую регату.
     *
     * @return list<string>
     */
    private function takenYachtIds(): array
    {
        if (! $this->regattaId) {
            return [];
        }

        return RegattaEntry::query()
            ->where('regatta_id', $this->regattaId)
            ->whereNotNull('yacht_id')
            ->pluck('yacht_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
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

        $crewEntry = $this->userCrewEntry;
        if ($crewEntry) {
            return $crewEntry->role === 'captain' ? 'in-crew-captain' : 'in-crew';
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
