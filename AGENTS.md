# AGENTS.md — Инструкции для ИИ-агентов

Правила и соглашения для любого ИИ-агента, работающего с кодовой базой проекта **«Yacht Association»**. Перед выполнением задачи прочитай этот документ целиком.

Сопутствующие документы:
- [`PROJECT_STRUCTURE.md`](PROJECT_STRUCTURE.md) — актуальная структура проекта и разбор доменов.
- [`doc.md`](doc.md) — предметная область: термины (регата, гонка, сезон, серия) и разделы сайта.
- [`DESIGN.md`](DESIGN.md) — дизайн-система и гайдлайны вёрстки.

---

## 0. Главные правила

1. **Не меняй схему БД** без создания новой миграции.
2. **Не пиши бизнес-логику в контроллерах, роутах и Livewire-компонентах** — для этого существуют Action-классы (`app/Actions`) и Service-классы (`app/Services`).
3. **Не пиши inline SQL** — используй Eloquent и Query Builder.
4. **Не устанавливай новые пакеты** без явного указания.
5. **Пиши кратко, без лишних объяснений и вежливости.** Выводи только код или конкретные ответы на вопросы.
6. **Форматируй код через Laravel Pint** (`./vendor/bin/pint --dirty`) перед коммитом.

---

## 1. Технологический стек

| Слой | Технология | Версия |
|------|-----------|--------|
| Backend | Laravel (PHP ^8.3) | 13.x |
| Auth | Laravel Breeze (Livewire/Volt-стек) | 2.x |
| Admin + личный кабинет | Filament PHP (две панели) | 4.x |
| Динамические компоненты | Livewire + Volt | 3.x / 1.x |
| Интерактивность (клиент) | Alpine.js | 3.x |
| Стили | Tailwind CSS + Vite | 4.x / 8.x |
| БД | MySQL | 8.4 |
| Кэш / Очереди | Redis (queue:work в отдельном контейнере) | alpine |
| Медиа | spatie/laravel-medialibrary | 11.x |
| PDF / Excel | barryvdh/laravel-dompdf, phpoffice/phpspreadsheet | 3.x / 5.x |
| Тесты | PHPUnit (не Pest) | 12.x |

Используй современные возможности PHP 8.3: enums, readonly, match, union types.

---

## 2. Окружение и команды

Проект работает на **Laravel Sail** (Docker). На хосте нет `pdo_mysql` — **все artisan-команды выполняй внутри контейнера**:

```bash
docker exec yacht-site-laravel.worker-1 php artisan <команда>
# или, если доступно:
vendor/bin/sail artisan <команда>
```

Фронтенд: сайт отдаёт **заранее собранный** CSS. Новые (в т.ч. произвольные `[...]`) Tailwind-классы молча не применятся, пока не выполнишь:

```bash
npm run build
```

Почта в dev-окружении уходит в Mailpit, поиск — Meilisearch (контейнеры Sail).

---

## 3. Структура директорий

Подробно — в [`PROJECT_STRUCTURE.md`](PROJECT_STRUCTURE.md). Кратко:

```
app/
├── Actions/          ← Бизнес-логика (Single Action Classes, по доменам)
├── Console/Commands/ ← Artisan-команды (публикация новостей, пересчёт рейтингов…)
├── Enums/            ← Статусы и роли (RegattaStatus, EntryStatus, SystemRole…)
├── Exports/ Imports/ ← Excel-экспорт/импорт (phpspreadsheet)
├── Filament/         ← Админ-панель (Resources/, Pages/, Widgets/)
│   └── User/         ← Личный кабинет пользователя (вторая панель)
├── Http/Middleware/  ← MaintenanceMode, FilamentAuthenticate (контроллеров почти нет)
├── Jobs/             ← Публикация новостей в Telegram/VK
├── Livewire/         ← Публичные интерактивные компоненты
├── Mail/             ← Почтовые уведомления
├── Models/           ← Eloquent-модели (+ Scopes/, Concerns/)
├── Observers/        ← Регистрируются в AppServiceProvider
├── Policies/         ← TeamPolicy
├── Providers/        ← В т.ч. Filament/{Admin,User}PanelProvider
├── Services/         ← Подсчёт очков/рейтингов, настройки, интеграции (TG, VK, Яндекс)
└── Support/          ← AccessControl, SafeDelete, Svg

routes/
├── web.php           ← Публичные маршруты — замыкания (api.php нет)
├── auth.php          ← Breeze
└── console.php

resources/views/
├── pages/            ← Страницы публичного сайта (+ association-info/)
├── livewire/         ← Шаблоны Livewire/Volt
├── layouts/ components/ mail/ pdf/ filament/
```

---

## 4. Известные грабли (обязательно к учёту)

- **Тесты не работают на SQLite**: MySQL-only ENUM-миграция ломает in-memory тестовую БД. Логику с БД проверяй через `php artisan tinker` на MySQL (в контейнере).
- **`Yacht` имеет глобальный скоуп `OwnedScope`** (`user_id IS NOT NULL`) — яхты без владельца невидимы. Для поиска/upsert стаб-яхт по `vfps_number` используй `withoutGlobalScopes()`.
- **FK-каскад `race_results` → `regatta_entries` не работает на уровне БД.** Удаление заявки без очистки осиротит результаты гонок; очистку и пересчёт делает `RegattaEntryResultObserver` — удаляй заявки только через Eloquent (не Query Builder), чтобы обсервер сработал.
- **ENUM-колонки меняются сырыми MySQL-миграциями** — новые миграции такого рода пиши с оглядкой на совместимость (`DB::statement` с `MODIFY COLUMN ... ENUM(...)`).
