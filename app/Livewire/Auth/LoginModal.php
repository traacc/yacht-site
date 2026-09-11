<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\SendEmailVerificationLinkAction;
use App\Actions\Auth\SubmitPasswordResetRequestAction;
use App\Actions\Notifications\ApplyRegistrationPreferencesAction;
use App\Enums\CreationSource;
use App\Enums\NotificationCategory;
use App\Enums\SportCategory;
use App\Mail\UserRegistered;
use App\Models\User;
use App\Rules\YandexCaptcha;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class LoginModal extends Component
{
    // Поля формы
    public string $name = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $patronymic = '';

    public string $birth_day = '';

    public string $birth_month = '';

    public string $birth_year = '';

    public string $sports_category = '';

    public string $phone = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $loginCaptchaToken = '';

    public string $registerCaptchaToken = '';

    // Данные для отправки credentials
    public string $selectedUserId = '';

    public string $senderPassword = '';

    public string $sendStatus = '';

    // Восстановление пароля
    public bool $resetLinkSent = false;

    /** Строка поиска себя среди участников на вкладке восстановления пароля. */
    public string $resetUserSearch = '';

    /** @var list<array{id: string, name: string}> */
    public array $resetUserResults = [];

    /** Пользователь, которого заявитель выбрал в списке (необязательно). */
    public string $resetUserId = '';

    /** @var list<string> Категории уведомлений, отмеченные при регистрации. */
    public array $notification_categories = [];

    public function mount(): void
    {
        // По умолчанию отмечены все категории — требование ТЗ.
        $this->notification_categories = array_column(NotificationCategory::cases(), 'value');
    }

    public function login()
    {
        $this->validateWithCaptcha('loginCaptchaToken', 'login', [
            'email' => ['required', 'email'],
            'password' => ['required'],
            'loginCaptchaToken' => YandexCaptcha::rules(),
        ], attributes: [
            'email' => 'email',
            'password' => 'пароль',
        ], messages: [
            'loginCaptchaToken.required' => 'Вам необходимо пройти проверку на бота',
        ]);

        // Попытка авторизации. Всегда выдаём remember-куку: сессия живёт
        // ограниченное время, и без неё пользователей выбивает после
        // периода неактивности.
        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], remember: true)) {
            session()->regenerate();

            // Полная перезагрузка страницы (без wire:navigate): после
            // session()->regenerate() меняется CSRF-токен, а navigate обновляет
            // только <body> и оставляет старый токен из @livewireScripts в <head>,
            // из-за чего все последующие Livewire-запросы падают с 419.
            return $this->redirect(route('home'));
        }

        // Если пароль не подошел, возвращаем ошибку в форму
        $this->resetCaptcha('loginCaptchaToken', 'login');
        $this->addError('email', 'Неверный email или пароль.');
    }

    /**
     * Валидация формы с капчей: при любой ошибке виджет сбрасывается, потому
     * что токен SmartCaptcha одноразовый и повторная отправка с тем же
     * токеном заведомо не пройдёт.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $attributes
     * @param  array<string, string>  $messages
     * @return array<string, mixed>
     */
    private function validateWithCaptcha(string $property, string $widget, array $rules, array $attributes = [], array $messages = []): array
    {
        try {
            return $this->validate($rules, messages: $messages, attributes: $attributes);
        } catch (ValidationException $e) {
            $this->resetCaptcha($property, $widget);

            throw $e;
        }
    }

    /**
     * Сбрасывает виджет капчи на фронте и «сгоревший» токен в состоянии.
     */
    private function resetCaptcha(string $property, string $widget): void
    {
        $this->{$property} = '';
        $this->dispatch('yandex-captcha-reset', name: $widget);
    }

    public function sendResetLink()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'phone' => ['required', 'string'],
        ], attributes: [
            'email' => 'email',
            'phone' => 'телефон',
        ]);

        // Выбор в списке необязателен, но присланный id управляем клиентом —
        // перепроверяем его тем же запросом, по которому ищем.
        $user = null;

        if ($this->resetUserId !== '') {
            $user = User::query()->withoutAdministrators()->find($this->resetUserId);

            if ($user === null) {
                $this->addError('resetUserId', 'Выберите пользователя из списка.');

                return;
            }
        }

        // Заявка фиксируется в админ-панели (раздел «Обращения») и дублируется
        // письмом администраторам — они свяжутся с пользователем по контактам
        // из формы или отправят ссылку на смену пароля прямо из панели.
        app(SubmitPasswordResetRequestAction::class)->handle($this->email, $this->phone, $user);

        $this->resetLinkSent = true;
    }

    /**
     * Поиск себя в списке участников для заявки на восстановление пароля.
     *
     * Ищем на сервере, а не встраиваем список в страницу: модалка есть на каждой
     * публичной странице, и перечень участников получал бы любой гость. Поэтому
     * же отдаём только ФИО, не раньше трёх символов и не больше десяти строк.
     */
    public function updatedResetUserSearch(): void
    {
        // Правка текста после выбора сбрасывает выбор: иначе в заявку ушёл бы
        // пользователь, которого в поле уже не видно.
        $this->resetUserId = '';

        $query = trim($this->resetUserSearch);

        if (mb_strlen($query) < 3) {
            $this->resetUserResults = [];

            return;
        }

        $this->resetUserResults = User::query()
            ->withoutAdministrators()
            ->where('name', 'like', '%'.addcslashes($query, '%_\\').'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name'])
            ->map(static fn (User $user): array => ['id' => (string) $user->id, 'name' => trim((string) $user->name)])
            ->all();
    }

    public function selectResetUser(string $userId): void
    {
        $user = User::query()->withoutAdministrators()->find($userId);

        if ($user === null) {
            return;
        }

        // Присваивание на сервере не вызывает updatedResetUserSearch(),
        // поэтому выбор не сбрасывается.
        $this->resetUserId = (string) $user->id;
        $this->resetUserSearch = trim((string) $user->name);
        $this->resetUserResults = [];
        $this->resetErrorBag('resetUserId');
    }

    public function clearResetUser(): void
    {
        $this->reset('resetUserId', 'resetUserSearch', 'resetUserResults');
    }

    public function register()
    {
        $this->validateWithCaptcha('registerCaptchaToken', 'register', [
            'name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                // ФИО должно содержать отчество: «Фамилия Имя Отчество» — минимум три слова.
                if (count(preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY)) < 3) {
                    $fail('Укажите ФИО полностью, включая отчество (Фамилия Имя Отчество).');
                }
            }],
            /*
            'first_name'            => ['required', 'string', 'max:255'],
            'last_name'             => ['required', 'string', 'max:255'],
            'patronymic'            => ['required', 'string', 'max:255'],
            */
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::defaults(), 'same:password_confirmation'],
            'password_confirmation' => ['required'],
            'phone' => ['required', 'unique:users,phone'],
            'birth_day' => ['required', 'integer', 'min:1', 'max:31'],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
            'birth_year' => ['required', 'integer', 'min:1926', 'max:2016'],
            'sports_category' => ['nullable', Rule::enum(SportCategory::class)],
            'notification_categories' => ['array'],
            'notification_categories.*' => [Rule::enum(NotificationCategory::class)],
            'registerCaptchaToken' => YandexCaptcha::rules(),
        ], attributes: [
            'name' => 'ФИО',
            /*
            'first_name'            => 'имя',
            'last_name'             => 'фамилия',
            'patronymic'            => 'отчество',
            */
            'email' => 'email',
            'phone' => 'телефон',
            'birth_day' => 'день рождения',
            'birth_month' => 'месяц рождения',
            'birth_year' => 'год рождения',
            'password' => 'пароль',
            'password_confirmation' => 'подтверждение пароля',
            'sports_category' => 'спортивный разряд',
        ], messages: [
            'email.unique' => 'Пользователь с таким email уже зарегистрирован',
            'phone.unique' => 'Пользователь с таким телефоном уже зарегистрирован',
            'registerCaptchaToken.required' => 'Вам необходимо пройти проверку на бота',
        ]);

        // Проверка корректности даты (30 февраля и т.п.)
        if (! checkdate((int) $this->birth_month, (int) $this->birth_day, (int) $this->birth_year)) {
            $this->resetCaptcha('registerCaptchaToken', 'register');
            $this->addError('birth_day', 'Некорректная дата рождения.');

            return;
        }

        // 1. Создаем пользователя
        $birthDate = sprintf('%04d-%02d-%02d', $this->birth_year, $this->birth_month, $this->birth_day);

        $user = User::create([
            'name' => $this->name,

            'first_name' => '',
            'last_name' => '',
            /*
            'patronymic'     => $this->patronymic,
            */
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'birth_date' => $birthDate,
            'sport_category' => $this->sports_category ?: null,
            // Пароль передаём без хеширования — каст 'hashed' в модели сделает это автоматически
            'password' => $this->password,
            'creation_source' => CreationSource::Registration,
        ]);

        // Настройки уведомлений по галочкам с формы регистрации.
        app(ApplyRegistrationPreferencesAction::class)->handle($user, $this->notification_categories);

        // Уведомляем администраторов о регистрации нового пользователя.
        $adminEmails = app(SettingsService::class)->adminNotificationEmails();
        if ($adminEmails !== []) {
            try {
                Mail::to($adminEmails)->send(new UserRegistered($user));
            } catch (\Exception $e) {
                report($e);
            }
        }

        // Письмо для подтверждения e-mail (без него недоступна онлайн-оплата).
        // Сбой почты не должен ломать регистрацию — письмо можно запросить повторно.
        try {
            app(SendEmailVerificationLinkAction::class)->handle($user, throttle: false);
        } catch (\Exception $e) {
            report($e);
        }

        // 2. Автоматически входим в систему (с remember-кукой, см. login())
        Auth::login($user, remember: true);

        session()->regenerate();

        session()->flash('registered', true);

        // 3. Редирект на главную (полная перезагрузка, не wire:navigate —
        // см. комментарий в login() про CSRF-токен после regenerate)
        return $this->redirect(route('home'));
    }

    public function sendLoginCredentials()
    {
        $this->validate([
            'selectedUserId' => ['required', 'exists:users,id'],
            'senderPassword' => ['required'],
        ], attributes: [
            'selectedUserId' => 'пользователь',
            'senderPassword' => 'пароль',
        ]);

        $user = User::findOrFail($this->selectedUserId);

        // Проверяем пароль через getAuthPassword() — он возвращает сырое значение из БД
        if (! Hash::check($this->senderPassword, $user->getAuthPassword())) {
            $this->addError('senderPassword', 'Неверный пароль пользователя.');

            return;
        }

        // Входим под выбранным пользователем (с remember-кукой, см. login())
        Auth::login($user, remember: true);
        session()->regenerate();

        $this->selectedUserId = '';
        $this->senderPassword = '';

        // Полная перезагрузка, не wire:navigate (см. комментарий в login())
        return $this->redirect(route('home'));
    }

    public function getUsersProperty()
    {
        return User::query()
            ->whereNotNull('email')
            /*
            ->orderBy('last_name')
            ->orderBy('first_name')
            */
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function render()
    {
        return view('livewire.auth.login-modal');
    }
}
