# CLAUDE.md

Сайт ассоциации парусного спорта «Yacht Association»: регаты, заявки, результаты и рейтинги, команды, яхты и их аренда, новости, голосования. Laravel 13 (PHP 8.3) + Filament 4 + Livewire 3/Volt + Tailwind 4, MySQL, Sail.

Обязательно к прочтению перед задачей:
- [AGENTS.md](AGENTS.md) — правила работы с кодовой базой (главный документ, следуй ему).
- [PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md) — структура каталогов и домены.
- [DESIGN.md](DESIGN.md) — архитектура: схема БД, роли, бизнес-процессы, интеграции, внешний API.
- [doc.md](doc.md) — термины предметной области (регата, гонка, сезон, серия).

## Команды

```bash
# Artisan — ТОЛЬКО внутри контейнера (на хосте нет pdo_mysql)
docker exec yacht-site-laravel.worker-1 php artisan <команда>

# После добавления новых Tailwind-классов (CSS собран заранее, иначе классы молча не применятся)
npm run build

# Форматирование перед коммитом
./vendor/bin/pint --dirty
```

## Критические особенности

- **Тесты (PHPUnit) не работают на SQLite** — MySQL-only ENUM-миграции. БД-логику проверяй через `php artisan tinker` в контейнере.
- **`Yacht` имеет глобальный скоуп `OwnedScope`** (`user_id IS NOT NULL`) — яхты без владельца ищи через `withoutGlobalScopes()`.
- **FK-каскад `race_results` → `regatta_entries` не работает в БД** — заявки удаляй только через Eloquent, чтобы сработал `RegattaEntryResultObserver`.
- Бизнес-логика — в `app/Actions` и `app/Services`, не в роутах/контроллерах/Livewire. Схема БД меняется только миграциями. Новые пакеты — только по явному указанию.
