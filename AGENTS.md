# AGENTS.md — Инструкции для ИИ-агентов

Этот файл описывает правила и соглашения, которым **обязан** следовать любой ИИ-агент при работе с кодовой базой проекта **«Yacht Association»**. Перед выполнением любой задачи прочитай этот документ целиком.

---

## 0. Главные правила

1. **Не меняй схему БД** без создания новой миграции.
2. **Не пиши бизнес-логику в контроллерах или Livewire-компонентах** — для этого существуют Action-классы и Service-классы.
3. **Не пиши inline SQL** — используй Eloquent и Query Builder.
4. **Пиши кратко, без лишних объяснений и вежливости.** Выводи только код или конкретные ответы на вопросы.
5. **В данном окружении проект работает на Laravel Sail** Используй vendor/bin/sail для взаимодействия с консолью
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
| Кэш / Очереди | Redis | 7.x |

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



