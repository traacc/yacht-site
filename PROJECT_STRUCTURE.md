# Структура проекта yacht-site

_Актуально на 2026-07-18._

Сайт ассоциации парусного спорта: регаты, команды, яхты, рейтинги, аренда яхт, новости, голосования. Laravel 13 (PHP 8.3) + Filament 4 (две админ-панели) + Livewire 3 / Volt + Tailwind CSS 4 (Vite 8, Alpine.js). Медиа — spatie/laravel-medialibrary; PDF — dompdf; Excel — phpspreadsheet. Запуск через Laravel Sail (Docker, MySQL).

## Корень

| Путь | Назначение |
|---|---|
| `app/` | Весь код приложения (подробно ниже) |
| `routes/` | `web.php` (~34 публичных маршрута, в основном замыкания), `auth.php` (Breeze), `api.php` (API судейской программы), `console.php` |
| `resources/views/` | Blade-шаблоны публичного сайта |
| `resources/css`, `resources/js` | Исходники фронтенда (Tailwind 4, Alpine, flatpickr) |
| `database/migrations/` | ~90 миграций (внимание: одна MySQL-only ENUM-миграция ломает SQLite-тесты) |
| `database/factories/`, `database/seeders/` | Фабрики и сидеры |
| `tests/` | PHPUnit (на SQLite не работают — см. AGENTS.md/память) |
| `config/`, `bootstrap/`, `public/`, `storage/`, `lang/` | Стандартные каталоги Laravel |
| `docker/`, `compose.yaml` | Окружение Sail (контейнер `yacht-site-laravel.worker-1`) |
| `import_data/` | Данные для импорта (исторические результаты и т.п.) |
| `doc.md` | Предметная область: термины (регата, гонка, сезон, серия) и описание разделов сайта |
| `DESIGN.md` | Архитектура: стек, схема БД, роли, бизнес-процессы, интеграции |
| `AGENTS.md` | Инструкции для AI-агентов |

## app/ — основной код

### Модели (`app/Models`)
Домены:
- **Регаты**: `Regatta`, `RegattaEntry` (заявка), `RegattaEntryCrew` (экипаж заявки), `RegattaEvents`, `RegattaScheduleEvent`, `RegattaResult`, `RegattaResultItem`, `RaceResult` (результат гонки), `Season`, `Series`
- **Команды**: `Team`, `TeamMember`, `TeamMemberInvitation`, `TeamRating`, `PersonalRating`
- **Яхты**: `Yacht` (глобальный скоуп `Scopes/OwnedScope` — скрывает яхты без владельца!), `YachtDocumentType`, `YachtOwnershipTransfer`, `YachtOption` / `YachtOptionValue` / `YachtOptionSelection` (опции аренды), `YachtRental`, `YachtRentalRequest`
- **Финансы**: `PaymentRegistry`, `FinancialReport`, `Expense`
- **Контент**: `News`, `Gallery`, `Album`, `VideoLink`, `Faq`, `Help` / `HelpCategory`, `Document`, `Setting`
- **Пользователи и обратная связь**: `User`, `UserQuestion`, `FeedbackRequests`, `Voting` / `VotingOption` / `Vote`
- Служебные: `Media` (кастомная модель medialibrary), `ApiClient` (Bearer-токены внешнего API), `Concerns/HasUuid`

### Filament — две панели (`app/Providers/Filament`)
- **Админ-панель** (`AdminPanelProvider`, `app/Filament/`): ~25 ресурсов (Regattas, RegattaEntries + Pending/Archived, RegattaResults, RaceResults, Teams, TeamRatings, PersonalRatings, Yachts, YachtOptions, YachtDocumentTypes, YachtOwnershipTransfers, RentalRequests, Users, News, Galleries, Helps, Faqs, Votings, PaymentRegistries, FinancialReports, Expenses, Series, UserQuestions, DocumentTypes); страницы настроек разделов сайта (`Pages/*PageSettings`, `SiteSettings`, `AccessControlSettings`, `TestEmailSender`); виджеты (`StatsOverview`, `UpcomingRegattas`, `UpcomingBirthdaysWidget`); кастомный компонент формы `Forms/Components/RentalCalendar`; ограничение доступа — `Concerns/RestrictsAccessByRole`; пересчёт рейтингов из ресурсов рейтингов — `Concerns/RecalculatesRatings`; управление API-токенами судейской программы — на странице `SiteSettings`.
- **Личный кабинет** (`UserPanelProvider`, `app/Filament/User/`): ресурсы Teams, Yachts, RegattaEntries, RegattaResults, RentalRequests, OwnershipTransfers, Questions + `EditProfile`.

### Бизнес-логика
- **Actions** (`app/Actions`) — по доменам: Regatta (подача заявки, дублирование регаты — `ReplicateRegattaAction`, PDF команд/документов), RegattaResult (импорт результатов, включая RGD-формат, применение импортированных результатов — `ApplyRegattaResultsAction`, генерация PDF), Team (создание, приглашения, история в PDF), Yacht/YachtRental (опции, заявка на аренду), Voting, Feedback, Document, генерация PDF-календаря.
- **Services** (`app/Services`): `RaceScorer` и `RatingCalculator` (подсчёт очков и рейтингов, dense ranking по `rank_position`), `RegattaService`, `SettingsService` (настройки страниц из админки), `Rgd/RgdParser` и `RgdParticipantsExporter` (обмен с судейской программой), интеграции — `TelegramService`, `VkService`, `WeatherService`, `YandexGeocoderService`, `YandexMapService`, а также `ImageConverter`, `SitemapGenerator`, `TeamRoleGuard`.
- **Observers** (`app/Observers`): регистрируются в `AppServiceProvider`; ключевой — `RegattaEntryResultObserver` (чистит осиротевшие race_results и пересчитывает результаты, т.к. FK-каскад в БД не работает); также News (автопубликация в соцсети), PaymentRegistry, RegattaEntryFee, RegattaResultItem, TeamMember.
- **Enums** (`app/Enums`): статусы и роли — `RegattaStatus`, `EntryStatus`, `RentalRequestStatus`, `PaymentStatus`, `VotingStatus`, `SystemRole`, `TeamMemberRole`, `PenaltyCode`, `SportCategory` и др.
- **Jobs**: `PublishNewsToTelegram`, `PublishNewsToVk`.
- **Console/Commands**: публикация отложенных новостей в TG/VK, `RecalculateRatings`, `UpdateRegattaStatuses`, `ImportRgdResults`, `ExportCarter30Participants`, `ApiCheck` (проверка внешнего API), `PruneOrphanYachts`, `ListUsersWithoutPatronymic`, `ListMultiTeamUsers` и др.
- **Mail** (`app/Mail`): ~10 писем (регистрация, заявки на регату, аренда, сброс пароля...).
- **Прочее**: `Exports/` (Excel: результаты, участники регаты, команды, пользователи, яхты), `Imports/YachtImport`, `Policies/TeamPolicy`, `Rules/YandexCaptcha`, `Support/` (`AccessControl`, `SafeDelete`, `Svg`), `Http/Middleware` (`MaintenanceMode`, `FilamentAuthenticate`, `VerifyApiToken`).

### Внешний API (`routes/api.php` + `app/Http/Controllers/Api`)
REST API для судейской программы («КАРТЕР 30»). Аутентификация — Bearer-токен (`api.token` → `VerifyApiToken`; токены — модель `ApiClient`, выпускаются в админке на странице `SiteSettings`, партиал `filament/pages/partials/api-clients`). Регата резолвится по `external_id`:
- `GET /api/regattas` — список регат (`RegattaListController`);
- `GET /api/regattas/{regatta}/participants` — экспорт участников (`RegattaParticipantsController`, формат КАРТЕР 30);
- `POST /api/regattas/{regatta}/results` — импорт результатов (`RegattaResultsController` + `ImportResultsRequest`).
DTO — `Http/Resources/{RegattaResource, ParticipantResource}`.

### Livewire (`app/Livewire`)
Публичные интерактивные компоненты: `RegattasCalendar`, `RegattasList`, `RegattaResults`, `TeamsList`, `TeamCardModal`, `UserCardModal`, `JoinRegattaModal`, `EditRegattaEntryModal`, `EntryCrewModal`, `HomeClosestRegatta`, `HomeRegattaTimer`, `Auth/LoginModal`, `CookieConsent`. Часть страниц — Volt (`app/Providers/VoltServiceProvider`).

## Публичный сайт (routes/web.php + resources/views)

Маршруты определены замыканиями прямо в `web.php` (контроллеров почти нет — только auth). Страницы (`resources/views/pages/`): главная (`home`), новости, регаты (список/детали/заявки/результаты), серии, команды, яхты, рейтинги, галерея, помощь, `association-info/` (страницы «Ассоциация»: руководство, попечители, документы, финансы, голосования и т.п.), `maintenance`. Также `resources/views/livewire/`, `mail/`, `pdf/` (шаблоны PDF), `filament/` (переопределения), `layouts/`, `components/`.

## Важные особенности (грабли)

- CSS собирается заранее: новые Tailwind-классы не применятся без `npm run build`.
- Artisan запускать в контейнере: `docker exec yacht-site-laravel.worker-1 php artisan …` (на хосте нет pdo_mysql).
- Тесты на in-memory SQLite падают из-за MySQL-only ENUM-миграции — проверять логику через tinker на MySQL.
- `Yacht` имеет глобальный `OwnedScope` (`user_id IS NOT NULL`) — для работы со «стабами» яхт использовать `withoutGlobalScopes()`.
- Удаление `RegattaEntry` не каскадит `race_results` на уровне БД — за очистку отвечает `RegattaEntryResultObserver`.
