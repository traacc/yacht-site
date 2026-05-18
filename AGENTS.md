# AGENTS.md — Инструкции для ИИ-агентов

Этот файл описывает правила и соглашения, которым **обязан** следовать любой ИИ-агент при работе с кодовой базой проекта **«Yacht Association»**. Перед выполнением любой задачи прочитай этот документ целиком.

---

## 0. Главные правила

1. **Не изобретай архитектуру** — она описана в [`DESIGN.md`](DESIGN.md). Следуй ей.
2. **Не устанавливай пакеты**, не перечисленные в разделе 16 DESIGN.md, без явного указания.
3. **Не меняй схему БД** без создания новой миграции.
4. **Не пиши бизнес-логику в контроллерах или Livewire-компонентах** — для этого существуют Action-классы и Service-классы.
5. **Не пиши inline SQL** — используй Eloquent и Query Builder.
6. **Форматируй код через Laravel Pint** (`./vendor/bin/pint`) перед коммитом.
7. **Покрывай новый функционал тестами** (Pest) согласно разделу «Тестирование».

---

## 1. Технологический стек

| Слой | Технология | Версия |
|------|-----------|--------|
| Backend | Laravel | 12.x |
| Auth | Laravel Jetstream (Livewire-стек) | 5.x |
| Admin | Filament PHP | 4.x |
| Динамические компоненты | Livewire | 3.x |
| Интерактивность (клиент) | Alpine.js | 3.x |
| Стили | Tailwind CSS | 4.x |
| ORM | Eloquent | — |
| БД | PostgreSQL | 16.x |
| Кэш / Очереди | Redis | 7.x |
| Медиа | Spatie Media Library | 11.x |
| Поиск | Laravel Scout + Meilisearch | — |
| Тесты | PestPHP | 2.x |
| Линтер PHP | Laravel Pint | 1.x |
| Статический анализ | Larastan | 2.x |

> **PHP версия**: `^8.2`. Используй union types, readonly properties, enums, match expressions — все современные возможности PHP 8.2.

---

## 2. Структура директорий

```
app/
├── Actions/          ← Бизнес-логика (Single Action Classes)
├── Enums/            ← PHP-перечисления
├── Filament/         ← Ресурсы и страницы Filament
│   ├── Resources/
│   ├── Pages/
│   └── Widgets/
├── Http/
│   ├── Controllers/  ← Только роутинг и отдача View, без бизнес-логики
│   └── Middleware/
├── Jobs/             ← Фоновые задачи (очередь)
├── Livewire/         ← Livewire-компоненты
├── Models/           ← Eloquent-модели
├── Notifications/    ← Laravel Notifications
├── Policies/         ← Gate Policies
└── Services/         ← Сервисы для внешних интеграций

database/
├── migrations/
├── seeders/
└── factories/

resources/views/
├── layouts/
├── pages/
├── profile/
├── livewire/         ← Blade-шаблоны Livewire-компонентов
└── components/       ← Анонимные Blade-компоненты

routes/
├── web.php
├── auth.php
└── api.php
```

---

## 3. Соглашения по именованию

### Файлы и классы

| Что | Пример |
|-----|--------|
| Модель | `app/Models/Regatta.php` → `class Regatta extends Model` |
| Миграция | `database/migrations/0007_create_regattas_table.php` |
| Action | `app/Actions/Regatta/CreateRegattaAction.php` |
| Livewire | `app/Livewire/Regatta/ApplicationForm.php` |
| Filament Resource | `app/Filament/Resources/RegattaResource.php` |
| Job | `app/Jobs/SendToTelegram.php` |
| Notification | `app/Notifications/ApplicationSubmitted.php` |
| Policy | `app/Policies/ApplicationPolicy.php` |
| Enum | `app/Enums/ApplicationStatus.php` |
| Service | `app/Services/TelegramService.php` |
| Тест Feature | `tests/Feature/Application/SubmitApplicationTest.php` |
| Тест Unit | `tests/Unit/RatingCalculatorTest.php` |

### Маршруты

- Публичные — только в `routes/web.php`
- Защищённые — группа `auth` в `routes/web.php`
- API (webhook Telegram) — `routes/api.php`
- Именование: `route('competitions.show', $regatta)`, `route('profile.teams')`

### Blade-представления

- Публичные страницы: `resources/views/pages/{section}/{view}.blade.php`
- Livewire-шаблоны: `resources/views/livewire/{section}/{component}.blade.php`
- Blade-компоненты: `resources/views/components/{name}.blade.php`

---

## 4. Модели Eloquent

### Правила написания модели

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    // 1. Всегда указывай $fillable (не используй $guarded = [])
    protected $fillable = [
        'regatta_id', 'team_id', 'yacht_id', 'status', 'created_by',
    ];

    // 2. Всегда используй casts для Enum, даты, булевых значений
    protected $casts = [
        'status' => ApplicationStatus::class,
    ];

    // 3. Отношения — отдельные методы с возвращаемым типом
    public function regatta(): BelongsTo
    {
        return $this->belongsTo(Regatta::class);
    }

    public function crew(): HasMany
    {
        return $this->hasMany(ApplicationCrew::class);
    }

    // 4. Local Scopes — для часто используемых фильтров
    public function scopePending($query)
    {
        return $query->where('status', ApplicationStatus::Pending);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', ApplicationStatus::Approved);
    }
}
```

### Где определены все модели

Полный список моделей и их связи — в разделах **3** (ER-диаграмма) и **8** (детализация связей) файла [`DESIGN.md`](DESIGN.md).

---

## 5. Enums

Используй `enum` (PHP 8.1+) с типом `string` для всех статусных полей:

```php
<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'На рассмотрении',
            self::Approved  => 'Одобрена',
            self::Rejected  => 'Отклонена',
            self::Cancelled => 'Отменена',
        };
    }
}
```
---

## 6. Action-классы (бизнес-логика)

**Вся бизнес-логика** помещается в Action-классы — тонкие, тестируемые классы с единственным публичным методом `handle()` или `execute()`.

```php
<?php

namespace App\Actions\Application;

use App\Models\Application;
use App\Models\User;
use App\Notifications\ApplicationSubmitted;

class SubmitApplicationAction
{
    public function handle(array $data, User $submittedBy): Application
    {
        // Только бизнес-логика, никакого HTTP-кода
        $application = Application::create([
            'regatta_id' => $data['regatta_id'],
            'team_id'    => $data['team_id'],
            'yacht_id'   => $data['yacht_id'],
            'created_by' => $submittedBy->id,
        ]);

        foreach ($data['crew'] as $crewMember) {
            $application->crew()->create($crewMember);
        }

        $submittedBy->notify(new ApplicationSubmitted($application));

        return $application;
    }
}
```

Вызывай Action из Livewire-компонента или Filament-ресурса через `app(SubmitApplicationAction::class)->handle(...)`.

---

## 7. Service-классы (внешние интеграции)

Service-классы — только для работы с внешними API и сложными алгоритмами.

| Service | Назначение |
|---------|-----------|
| [`TelegramService`](app/Services/TelegramService.php) | Отправка сообщений через Telegram Bot API |
| [`RatingCalculatorService`](app/Services/RatingCalculatorService.php) | Расчёт рейтингов по результатам гонок |
| [`ResultsImportService`](app/Services/ResultsImportService.php) | Парсинг и валидация CSV/Excel результатов |
| [`VfpsLookupService`](app/Services/VfpsLookupService.php) | Поиск яхт по номеру ВФПС |

---

## 8. Livewire-компоненты

Правила для Livewire-компонентов:

1. **Не содержат бизнес-логики** — делегируют в Action-классы
2. **Используют wire:model** для двустороннего связывания форм
3. **Валидация** — через `#[Validate]` атрибуты или метод `rules()`
4. **Авторизацию** проверяй в методе `mount()` через `$this->authorize()`
5. **Кэш не инвалидируй напрямую** — это делают Model Observers

```php
<?php

namespace App\Livewire\Regatta;

use App\Actions\Application\SubmitApplicationAction;
use Livewire\Component;

class ApplicationForm extends Component
{
    public int $regattaId;
    public ?int $teamId = null;
    public ?int $yachtId = null;
    public array $crew = [];

    public function mount(int $regattaId): void
    {
        $this->authorize('apply', Regatta::findOrFail($regattaId));
        $this->regattaId = $regattaId;
    }

    public function submit(SubmitApplicationAction $action): void
    {
        $this->validate();
        $action->handle($this->only(['regattaId', 'teamId', 'yachtId', 'crew']), auth()->user());
        $this->dispatch('application-submitted');
    }

    public function render()
    {
        return view('livewire.regatta.application-form');
    }
}
```

---

## 9. Filament Resources

Структура Filament-ресурса:

- `form(Form $form)` — поля для создания/редактирования
- `table(Table $table)` — столбцы, фильтры, actions
- `getRelationManagers()` — для связанных сущностей (например, гонки у регаты)
- Кастомные `Action`-кнопки (Approve, Reject, SendToTelegram) реализуются через `Tables\Actions\Action`

```php
// Пример кастомного action в Filament
Tables\Actions\Action::make('approve')
    ->label('Одобрить')
    ->icon('heroicon-o-check')
    ->color('success')
    ->visible(fn (Application $record) => $record->status === ApplicationStatus::Pending)
    ->action(fn (Application $record) => app(ApproveApplicationAction::class)->handle($record));
```

Все ресурсы — в [`app/Filament/Resources/`](app/Filament/Resources/). Полный список в разделе **10** [`DESIGN.md`](DESIGN.md).

---

## 10. Миграции

Правила создания миграций:

1. Нумерация: `0001_`, `0002_`, ... — строго последовательно
2. Все внешние ключи с `constrained()->cascadeOnDelete()` или `nullOnDelete()` в зависимости от семантики
3. Всегда добавляй `$table->timestamps()`
4. Все `string` поля с ограничением длины, например `$table->string('name', 255)`
5. Индексы: добавляй `->unique()` для полей `email`, `vfps_number`, `slug`

```php
// Пример: всегда использовать blueprints так:
Schema::create('applications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('regatta_id')->constrained()->cascadeOnDelete();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->foreignId('yacht_id')->constrained()->restrictOnDelete();
    $table->string('status', 20)->default('pending');
    $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
    $table->timestamps();
});
```

---

## 12. Кэширование

Кэш-ключи строго соответствуют соглашению (см. раздел 13 DESIGN.md):

```php
// Чтение из кэша:
$upcomingRegatta = Cache::remember('home.upcoming_regatta', now()->addHour(), fn () =>
    Regatta::upcoming()->first()
);

// Инвалидация — ТОЛЬКО через Model Observers (не в контроллерах):
// app/Observers/RegattaObserver.php
public function saved(Regatta $regatta): void
{
    Cache::forget('home.upcoming_regatta');
    Cache::forget("home.calendar.{$regatta->season_id}");
}
```

| Ключ | TTL |
|------|-----|
| `home.upcoming_regatta` | 1 час |
| `home.calendar.{season_id}` | 1 час |
| `ratings.team.{season_id}` | 6 часов |
| `ratings.personal.{season_id}` | 6 часов |
| `pages.{slug}` | 24 часа |

---

## 13. Очереди (Jobs)

При создании новой Job-задачи:

```php
<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendToTelegram implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly int $newsId) {}

    public function handle(TelegramService $telegram): void
    {
        // логика отправки
    }
}
```

Привязка Job к очередям:

| Job | Очередь |
|-----|---------|
| `SendToTelegram` | `notifications` |
| `ProcessResultsImport` | `imports` |
| `RecalculateRatings` | `default` |
| `ProcessMediaConversions` | `media` |

---

## 19. Запрещённые паттерны

| ❌ Запрещено | ✅ Правильно |
|------------|------------|
| Бизнес-логика в Controller | Вынести в Action-класс |
| Бизнес-логика в Livewire | Вынести в Action-класс |
| `$guarded = []` | Явный `$fillable = [...]` |
| `env()` вне `config/` файлов | `config('key.value')` |
| Inline SQL (`DB::select('SELECT...')`) | Eloquent / Query Builder |
| Инвалидация кэша в Controller | Инвалидация в Model Observer |
| Хардкод строк ролей (`'admin'`) | Enum `UserRole::Admin` |
| Логика авторизации в View | Gate, Policy или `@can` |

---
