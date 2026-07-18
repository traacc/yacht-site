# DESIGN.md — Архитектура проекта «Yacht Association»

_Документ отражает фактическую архитектуру на 2026-07-18 (изначальная версия была проектом «до реализации» и сильно устарела). Актуальную структуру каталогов см. в [`PROJECT_STRUCTURE.md`](PROJECT_STRUCTURE.md), термины предметной области — в [`doc.md`](doc.md), правила для ИИ-агентов — в [`AGENTS.md`](AGENTS.md)._

## 1. Технологический стек (TALL)

| Компонент | Технология | Версия |
|-----------|-----------|--------|
| **T** — Стили | Tailwind CSS (+ Vite 8) | 4.x |
| **A** — Интерактивность | Alpine.js (+ flatpickr) | 3.x |
| **L** — Бэкенд | Laravel (PHP ^8.3) | 13.x |
| **L** — Динамические компоненты | Livewire + Volt | 3.x / 1.x |
| Админ-панель и ЛК | Filament PHP (две панели) | 4.x |
| Аутентификация | Laravel Breeze (Livewire-стек) | 2.x |
| Медиа-файлы | Spatie Media Library | 11.x |
| База данных | MySQL | 8.4 |
| Кэш / Очереди | Redis | — |
| PDF | barryvdh/laravel-dompdf | 3.x |
| Excel | phpoffice/phpspreadsheet | 5.x |
| UI-киты | TallStackUI, filament-map-picker, filament-yandex-map | — |
| Тесты | PHPUnit | 12.x |
| Окружение | Laravel Sail (Docker: app, worker, mysql, redis, meilisearch, mailpit, selenium) | — |

## 2. Архитектура высокого уровня

```mermaid
graph TB
    Browser[Браузер] --> Public[Публичный сайт: роуты-замыкания + Blade + Livewire/Volt]
    Browser --> Admin["/admin — Filament (админ)"]
    Browser --> Cabinet["/user — Filament (личный кабинет)"]

    Public --> Actions[Actions / Services]
    Admin --> Actions
    Cabinet --> Actions

    Actions --> DB[(MySQL 8.4)]
    Actions --> Media[Spatie Media Library / storage public]
    Actions --> Queue[Redis Queue → queue:work в контейнере worker]

    Queue --> TG[Telegram Bot API]
    Queue --> VK[VK API]
    Actions --> Yandex[Яндекс: Карты / Геокодер / SmartCaptcha]
    Actions --> Weather[Погодный API]
    Actions --> Mail[SMTP / Mailpit в dev]

    Judge[Судейская программа КАРТЕР 30] --> Api["/api — REST, Bearer-токен"]
    Api --> Actions
```

Контроллеров практически нет: публичные страницы — замыкания в `routes/web.php`, отдающие Blade-шаблоны; интерактив — Livewire-компоненты; мутации — Action-классы; интеграции и расчёты — Service-классы.

## 3. Схема базы данных

### Домены и таблицы (ключевые поля)

**Пользователи и команды**
- `users` — external_id, ФИО (first/last/patronymic), birth_date, sport_category, about, email/phone (+verified_at), photo_url, `system_role`, creation_source, is_banned; soft deletes.
- `teams` — external_id, name, organizer_id→users, default_yacht_id→yachts, approval_status, is_archived; soft deletes.
- `team_members` — team_id, user_id, `role` (organizer/team_admin/member), status (в т.ч. left), is_permanent, joined_at.
- `team_member_invitations` — приглашение участника (в т.ч. из другой команды): team_id, user_id, from_team_id, requested_by, status, rejection_reason.

**Яхты**
- `yachts` — name, `vfps_number` (уник.), user_id→users (владелец, **nullable — см. OwnedScope**), gims_number, class/project/year, home_region, mooring_place, for_rent, suitable_for, approval_status, owner_* (контакты), past_regattas; soft deletes.
- `yacht_document_types` — типы документов яхты/пользователя (`owner`), настраиваются в админке.
- `yacht_ownership_transfers` — заявки на смену владельца: yacht_id, requester_id, previous_owner_id, status, reviewed_by.
- `yacht_rentals` — занятые диапазоны дат аренды: yacht_id, date_start/date_end, price_event/price_pro.
- `yacht_rental_requests` — публичные заявки на аренду: yacht_id, name, phone, desired_date(_end), status (pending/approved/rejected), user_id.
- `yacht_options` / `yacht_option_values` / `yacht_option_selections` — справочник опций аренды и выбор значений для яхты.

**Соревнования**
- `seasons` — год, даты; `series` — группа регат внутри сезона.
- `regattas` — season_id, series_id, name, `level_coefficient`, date/time start/end, location + coordinates, water_area, описания, regulations, races_count, entry_fee_required/amount, `entry_required_documents` (json), `regatta_status` (upcoming/closest/active/finished/cancelled/postponed), postponed_to_date/note/regatta_id; soft deletes.
- `regatta_events` — **гонки** регаты (event_datetime); `regatta_schedule_events` — программа/расписание регаты.
- `regatta_entries` — заявки: regatta_id, team_id, yacht_id, `status` (pending/approved/rejected/withdrawn), source, documents_complete, fee_paid, entry_password.
- `regatta_entry_crew` — экипаж заявки: regatta_entry_id, team_member_id, role (в т.ч. captain).
- `race_results` — результат гонки: event_id→regatta_events, regatta_entry_id, position/points (string — допускают DNF/DNS и пр.), penalty_code.
- `regatta_results` — итоговый протокол регаты (result_type, source, is_published, pdf_path) и `regatta_result_items` — строки протокола со **снапшотами** (team_name, yacht_name, sail_number, captain_name, crew_snapshot, race_breakdown) + override-флаги для ручной правки total_points/final_position, not_participate.
- `team_ratings` / `personal_ratings` — рейтинг сезона по командам/участникам (total_points, rank_position).
- `sequences` — счётчики (генерация номеров).

**Финансы**
- `payment_registries` — платежи с morph-привязкой `payable` (например, к заявке); name, amount, status, document.
- `financial_reports`, `expenses` — публикуемые документы отчётности.

**Контент и обратная связь**
- `news` — author_id, type, title, content, external_url, cover_image_url (+object_position), published_to_tg/vk, published_at; soft deletes.
- `gallery` (+ `video_links`) — альбомы (season_id, regatta_id, images json, cover) и видео-ссылки.
- `help` / `help_category` — раздел «Помощь» (специалисты, контакты); soft deletes.
- `faqs`, `user_questions` — FAQ и вопросы пользователей (ответ можно импортировать в FAQ).
- `documents` — morph `documentable`: файлы, привязанные к любым сущностям.
- `votings` / `voting_options` / `votes` — голосования (анонимность, множественный выбор, период).
- `feedback_requests` — обращения с формы обратной связи.
- `settings` — key/value (+group) настройки страниц сайта, редактируются в админке через `SettingsService`.
- `media` — таблица Spatie Media Library (morph, кастомная модель `App\Models\Media`).
- `api_clients` — Bearer-токены (хэши) внешнего API судейской программы; выпуск/отзыв — в админке (`SiteSettings`).

### Связи (компактно)

```mermaid
erDiagram
    users ||--o{ team_members : ""
    teams ||--o{ team_members : ""
    teams }o--|| users : "organizer"
    users |o--o{ yachts : "владелец (nullable)"

    seasons ||--o{ series : ""
    seasons ||--o{ regattas : ""
    series |o--o{ regattas : ""

    regattas ||--o{ regatta_entries : ""
    teams ||--o{ regatta_entries : ""
    yachts |o--o{ regatta_entries : ""
    regatta_entries ||--o{ regatta_entry_crew : ""
    team_members ||--o{ regatta_entry_crew : ""

    regattas ||--o{ regatta_events : "гонки"
    regatta_events ||--o{ race_results : ""
    regatta_entries ||--o{ race_results : "без FK-каскада!"

    regattas ||--o{ regatta_results : ""
    regatta_results ||--o{ regatta_result_items : "снапшоты"

    seasons ||--o{ team_ratings : ""
    seasons ||--o{ personal_ratings : ""

    yachts ||--o{ yacht_rentals : ""
    yachts ||--o{ yacht_rental_requests : ""
    yachts ||--o{ yacht_ownership_transfers : ""
    yacht_options ||--o{ yacht_option_values : ""
    yachts }o--o{ yacht_options : "selections"

    votings ||--o{ voting_options : ""
    voting_options ||--o{ votes : ""
    gallery ||--o{ video_links : ""
```

> ⚠️ Каскад `race_results` → `regatta_entries` **не обеспечивается БД**: очистку осиротевших результатов и пересчёт выполняет `RegattaEntryResultObserver`. Удалять заявки только через Eloquent.

## 4. Роли и права доступа

Системные роли (`App\Enums\SystemRole`, поле `users.system_role`): `user`, `admin`, `judge`, `secretary`, `accountant`. Доступ к разделам админки ограничивается через `RestrictsAccessByRole` + страница `AccessControlSettings`; хелперы — `App\Support\AccessControl`.

Роли в команде (`App\Enums\TeamMemberRole`, поле `team_members.role`): `organizer`, `team_admin`, `member`. Проверки — `TeamRoleGuard`, `TeamPolicy` (подача заявок на регату, управление составом).

## 5. Структура директорий

Актуальная структура и разбор по доменам — в [`PROJECT_STRUCTURE.md`](PROJECT_STRUCTURE.md). Ключевое:

- `app/Actions/{Regatta,RegattaEntry,RegattaResult,Team,Yacht,YachtRental,Voting,Feedback,Document}/` — бизнес-логика (Single Action Classes);
- `app/Services/` — расчёты (`RaceScorer`, `RatingCalculator`), `SettingsService`, `Rgd/RgdParser`, интеграции (Telegram, VK, Яндекс, погода);
- `app/Filament/` — админ-панель; `app/Filament/User/` — личный кабинет;
- `app/Livewire/` — публичные компоненты; `app/Observers/` — регистрируются в `AppServiceProvider`;
- `routes/web.php` (замыкания), `routes/auth.php` (Breeze), `routes/api.php` (внешний API судейской программы), `routes/console.php` (планировщик).

## 6. Маршрутизация (фактическая)

### Публичные маршруты (`routes/web.php`)

| Метод | URI | Описание |
|-------|-----|----------|
| GET | `/` | Главная: новости, галерея, FAQ, партнёры, таймер и календарь регат |
| GET | `/association/{charter,management,trustees,regulations,decisions,votings}` | Раздел «Ассоциация» (контент из `settings` + документы) |
| POST | `/association/votings/{voting}/vote` | Голосование (`CastVoteAction`) |
| GET | `/competitions` | Список регат |
| GET | `/regattas/{regatta}` | Карточка регаты |
| GET | `/regattas/entries` | Заявки на регаты |
| GET | `/regattas/calendar/pdf` | PDF-календарь сезона |
| GET | `/regatta/{regatta}/download-{documents,teams,teams-pdf,results-pdf}` | Выгрузки по регате (архивы, PDF) |
| GET | `/series/results`, `/series/{series}` | Результаты серий |
| GET | `/teams` (+ `/team/{team}/download-history`) | Команды, PDF-история команды |
| GET | `/yachts` | Каталог яхт (вкл. аренду) |
| POST | `/yachts/{yacht}/rental-request` | Заявка на аренду (`SubmitYachtRentalRequestAction`) |
| GET | `/ratings` | Рейтинги (командный/личный) |
| GET | `/news`, `/news/{news}` | Новости |
| GET | `/gallery` (+ `/gallery/{gallery}/download`) | Галерея, скачивание альбома |
| GET | `/help` | Помощь |
| POST | `/feedback`, `/questions` | Обратная связь, вопрос (Yandex SmartCaptcha) |

### Панели Filament

| URI | Панель | Содержимое |
|-----|--------|-----------|
| `/admin` | `AdminPanelProvider` | ~25 ресурсов (регаты, заявки pending/archived, результаты, команды, рейтинги, яхты + опции/типы документов/передачи владения, аренда, пользователи, новости, галерея, помощь, FAQ, голосования, финансы, серии, вопросы), страницы настроек разделов сайта (`*PageSettings`, `SiteSettings`, `AccessControlSettings`), виджеты |
| `/user` | `UserPanelProvider` | ЛК: мои команды, яхты, заявки на регаты, результаты, аренда, передачи владения, вопросы, профиль |

Аутентификация — Breeze (`routes/auth.php`) + Livewire `Auth/LoginModal`; вход в панели — `FilamentAuthenticate`.

### Внешний API (`routes/api.php`)

REST API для судейской программы «КАРТЕР 30». Middleware `api.token` (`VerifyApiToken`) проверяет Bearer-токен по хэшу в `api_clients` (выпуск токенов — админка, `SiteSettings`). Регата резолвится по `external_id` (`Regatta::getRouteKeyName()`).

| Метод | URI | Назначение |
|-------|-----|-----------|
| GET | `/api/regattas` | Список регат (поиск по external_id) |
| GET | `/api/regattas/{regatta}/participants` | Экспорт участников в формате КАРТЕР 30 (`RgdParticipantsExporter`) |
| POST | `/api/regattas/{regatta}/results` | Импорт результатов регаты (`ImportResultsRequest`, далее — конвейер импорта результатов) |

Проверка доступности — команда `api:check` (`ApiCheck`).

## 7. Ключевые бизнес-процессы

### 7.1. Заявка на регату
`JoinRegattaModal` (Livewire) → `SubmitRegattaEntryAction`: создаёт `RegattaEntry` + `RegattaEntryCrew`, письма (`RegattaEntrySubmitted`, `SendRegattaEntryPassword` — пароль для редактирования заявки без входа). Заявка попадает в Pending-ресурс админки; статусы — `EntryStatus`. Обязательные документы заявки — `UpdateRegattaEntryRequiredDocumentsAction`, оплата — `PaymentRegistry` (morph payable) + `RegattaEntryFeeObserver`.

### 7.2. Результаты и рейтинги
Импорт протокола: `ImportRegattaResultItemsAction` (Excel), `ImportRgdResultItemsAction` + `Services/Rgd/RgdParser` (формат RGD, также команда `regattas:import-rgd`) или по API от судейской программы (`POST /api/regattas/{regatta}/results` + `ApplyRegattaResultsAction`). Очки считает `RaceScorer` (позиции/штрафы — строки: DNF, DNS и т.п.), рейтинги сезона — `RatingCalculator` (+ команда `ratings:recalculate` и действие пересчёта в ресурсах рейтингов админки) с учётом `level_coefficient`; места — dense ranking по `rank_position`. Строки протокола хранят снапшоты состава и разбивку по гонкам; ручные правки — override-флаги. Публикация протокола — `is_published`, PDF — `GenerateRegattaResultPdfAction`. Пересчёты триггерятся обсерверами (`RegattaEntryResultObserver`, `RegattaResultItemObserver`).

### 7.3. Публикация новостей в соцсети
Новость с `published_at` в будущем публикуется отложенно: планировщик ежеминутно запускает `news:publish-to-telegram` / `news:publish-to-vk` → jobs `PublishNewsToTelegram` / `PublishNewsToVk` → `TelegramService` / `VkService` (поддерживают прокси); флаги `published_to_tg/vk`. `NewsObserver` — сопутствующая логика при сохранении.

### 7.4. Аренда яхт
Яхта с `for_rent=true` показывается в каталоге аренды с опциями (`yacht_options`) и занятыми датами (`yacht_rentals`, календарь в админке — `Forms/Components/RentalCalendar`). Публичная заявка → `SubmitYachtRentalRequestAction` → `YachtRentalRequest` (+ письмо `YachtRentalRequested`); одобрение в админке бронирует даты.

### 7.5. Передача владения яхтой
Пользователь в ЛК подаёт `YachtOwnershipTransfer` (яхты без владельца ищутся по `vfps_number` через `withoutGlobalScopes()`); админ одобряет — яхта перепривязывается к новому владельцу.

### 7.6. Статусы регат
Команда `regattas:update-statuses` (ежеминутно) переводит регаты по датам: upcoming → closest → active → finished; поддерживаются cancelled/postponed (с переносом на дату или другую регату).

## 8. Интеграции

| Интеграция | Реализация | Конфигурация (env) |
|-----------|------------|--------------------|
| Telegram (новости в канал) | `TelegramService` + job | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `TELEGRAM_PROXY` |
| VK (новости в группу) | `VkService` + job (OAuth-refresh токены) | `VK_CLIENT_ID/SECRET`, `VK_ACCESS_TOKEN`, `VK_REFRESH_TOKEN`, `VK_GROUP_ID`, `VK_DEVICE_ID`, `VK_PROXY` |
| Яндекс.Карты / Геокодер | `YandexMapService`, `YandexGeocoderService`, filament-yandex-map, map-picker | `YANDEX_MAP_API_KEY`, `YANDEX_MAP_SUGGEST_API_KEY` |
| Yandex SmartCaptcha | `Rules/YandexCaptcha` (формы feedback/questions/rental) | `YANDEX_SMARTCAPTCHA_SITE_KEY/SERVER_KEY` |
| Погода | `WeatherService` (кэшируется) | — |
| Почта | Mailable-классы `app/Mail`; в dev — Mailpit | `MAIL_*`, `FEEDBACK_NOTIFICATION_EMAIL` |

## 9. Медиа-файлы (Spatie Media Library)

Кастомная модель `App\Models\Media` (uuid). Коллекции: `Yacht` — `gallery`, `interior_gallery`; `Gallery` — `cover`, `images`, `videos`; `News`, `Team`, `Help` — `gallery`. Часть изображений хранится путями на диске `public` (аватары, обложки новостей, настройки страниц); конвертация — `Services/ImageConverter`.

## 10. Планировщик и очереди

`routes/console.php` (контейнер worker также крутит `queue:work redis`):

| Команда / Job | Периодичность |
|---------------|---------------|
| `regattas:update-statuses` | ежеминутно |
| `news:publish-to-telegram`, `news:publish-to-vk` | ежеминутно, `withoutOverlapping` |
| `model:prune` | ежедневно |
| Jobs: `PublishNewsToTelegram`, `PublishNewsToVk` | очередь Redis |

Разовые команды: `ratings:recalculate`, `regattas:import-rgd`, `yachts:prune-orphans`, `users:update-names`, `users:list-multi-team`.

## 11. Тестирование и известные особенности

- **Тесты (PHPUnit) не работают на in-memory SQLite** — есть MySQL-only ENUM-миграции (`DB::statement ... MODIFY COLUMN ... ENUM`). Проверка БД-логики — `php artisan tinker` на MySQL в контейнере.
- Artisan — только внутри контейнера: `docker exec yacht-site-laravel.worker-1 php artisan …`.
- CSS собран заранее — после новых Tailwind-классов обязателен `npm run build`.
- `Yacht` — глобальный скоуп `OwnedScope` (`user_id IS NOT NULL`); стаб-яхты без владельца видны только через `withoutGlobalScopes()`.
- Позиции/очки в результатах — строки (нужны коды DNF/DNS/OCS и т.п.), см. `PenaltyCode`.
- Мягкое удаление у ключевых сущностей; безопасное удаление — `App\Support\SafeDelete`.

## 12. Зависимости

Источник истины — [`composer.json`](composer.json) / [`package.json`](package.json). Основное: `laravel/framework ^13`, `filament/filament ^4` (+ spatie-media-library-plugin), `livewire/livewire ^3` + `livewire/volt`, `spatie/laravel-medialibrary ^11`, `barryvdh/laravel-dompdf ^3`, `phpoffice/phpspreadsheet ^5`, `tallstackui/tallstackui`, `dotswan/filament-map-picker`, `kpebedko22/filament-yandex-map`; dev: `laravel/breeze`, `laravel/pint`, `laravel/sail`, `phpunit/phpunit ^12`. Фронтенд: `tailwindcss ^4`, `vite ^8`, `alpinejs ^3`, `flatpickr`. **Новые пакеты — только по явному указанию.**
