# API для внешней (судейской) программы

JSON-API для двустороннего обмена данными регаты с внешней программой:

- **экспорт участников** регаты из заявок (GET);
- **импорт результатов** регаты (итоги + пооночные) (POST).

Внешняя программа сама обращается к API по HTTPS с Bearer-токеном. Формат обмена —
`application/json`, кодировка UTF-8. Обрабатывается зачётная группа **«КАРТЕР 30»**.

---

## Базовый адрес

```
https://<host>/api
```

Все ответы — JSON. Клиенту рекомендуется слать заголовок `Accept: application/json`.

---

## Аутентификация

Каждый запрос должен содержать заголовок:

```
Authorization: Bearer <API_TOKEN>
```

Токен выдаёт администратор. Он хранится на сервере только в виде sha256-хеша,
поэтому plaintext-значение показывается **один раз** при выпуске — его нужно сохранить
на стороне внешней программы.

Выпуск токена (на сервере):

```bash
docker exec yacht-site-laravel.worker-1 php artisan tinker --execute='
  [$c, $token] = App\Models\ApiClient::issue("Судейская программа");
  echo $token.PHP_EOL;
'
```

Отзыв — установкой `revoked_at` у записи `api_clients`.

При отсутствующем/неверном/отозванном токене — `401`:

```json
{ "message": "Неверный или отсутствующий API-токен." }
```

---

## Идентификатор регаты

В путях используется **`external_id`** регаты — стабильный целочисленный номер
(не UUID). Например, регата с `external_id = 42`:

```
/api/regattas/42/participants
```

Получить список регат и найти нужный `external_id` — метод ниже.

---

## 1. Список регат

Возвращает регаты (свежие первыми) — для поиска `external_id` нужной регаты.

```
GET /api/regattas
```

### Параметры запроса (query)

| Параметр | Тип | Описание |
|---|---|---|
| `status` | string | Необязательный фильтр по статусу (см. значения ниже). Неизвестное значение игнорируется |

### Ответ `200 OK`

```json
{
  "data": [
    {
      "external_id": 42,
      "name": "Кубок памяти В.Я. Потапова",
      "water_area": "Пирогово",
      "location": null,
      "date_start": "2026-06-12",
      "date_end": "2026-06-12",
      "status": "finished",
      "entries_count": 21
    }
  ]
}
```

| Поле | Тип | Описание |
|---|---|---|
| `external_id` | int | Идентификатор регаты для путей API |
| `name` | string | Название |
| `water_area` | string \| null | Акватория |
| `location` | string \| null | Место проведения |
| `date_start` / `date_end` | string \| null | Даты, `YYYY-MM-DD` |
| `status` | string | Статус (см. ниже) |
| `entries_count` | int | Число заявок |

**Значения `status`:** `upcoming` (планируемая), `closest` (ближайшая),
`active` (идёт), `finished` (завершена), `cancelled` (отменена),
`postponed` (перенесена).

---

## 2. Экспорт участников

Возвращает участников регаты (заявки) зачётной группы «КАРТЕР 30».

```
GET /api/regattas/{external_id}/participants
```

### Ответ `200 OK`

```json
{
  "regatta": {
    "external_id": 42,
    "name": "Кубок памяти В.Я. Потапова",
    "water_area": "Пирогово",
    "date_start": "2026-06-12",
    "date_end": "2026-06-12"
  },
  "class": "КАРТЕР 30",
  "participants": [
    {
      "entry_id": "019ebb56-033e-7390-8f2b-3b957c657e17",
      "country": "RUS",
      "sail_number": "691",
      "yacht": {
        "name": "Energie",
        "class": "CARTER 30",
        "type": "CARTER 30",
        "city": "Москва"
      },
      "team": "Energie",
      "crew": [
        {
          "name": "Харитонов Денис Владимирович",
          "birth_date": "1965-12-13",
          "sport_category": "kms",
          "role": "captain"
        },
        {
          "name": "Пошиваник Александр Дмитриевич",
          "birth_date": "1968-04-29",
          "sport_category": "kms",
          "role": "main"
        }
      ]
    }
  ]
}
```

### Поля участника

| Поле | Тип | Описание |
|---|---|---|
| `entry_id` | string (uuid) | ID заявки |
| `country` | string | Всегда `"RUS"` (в БД не хранится) |
| `sail_number` | string \| null | Парусный номер яхты (`vfps_number`) — **ключ привязки** результатов |
| `yacht.name` | string \| null | Название яхты |
| `yacht.class` | string \| null | Класс яхты |
| `yacht.type` | string \| null | Тип/проект |
| `yacht.city` | string \| null | Город приписки |
| `team` | string \| null | Название команды |
| `crew[]` | array | Экипаж (только члены с заданным ФИО) |

### Поля члена экипажа

| Поле | Тип | Описание |
|---|---|---|
| `name` | string | ФИО |
| `birth_date` | string \| null | Дата рождения, `YYYY-MM-DD` |
| `sport_category` | string \| null | Спортивный разряд (см. ниже) |
| `role` | string | `captain` \| `main` \| `reserve` |

**Значения `sport_category`:** `no` (б/р), `3`, `2`, `1`, `kms` (КМС), `ms` (МС),
`msmk` (МСМК), `zms` (ЗМС).

---

## 3. Список результатов

Возвращает протоколы результатов регаты (предварительный/итоговый) с итоговыми
таблицами. У регаты может быть несколько протоколов.

```
GET /api/regattas/{external_id}/results
```

### Параметры запроса (query)

| Параметр | Тип | Описание |
|---|---|---|
| `type` | string | Фильтр: `preliminary` или `final` |
| `published` | bool | Фильтр по публикации: `1`/`0` |

### Ответ `200 OK`

```json
{
  "regatta": { "external_id": 42, "name": "Кубок памяти В.Я. Потапова" },
  "results": [
    {
      "result_id": "019f6c4b-d3ff-7272-a90c-a48e2f336837",
      "result_type": "final",
      "source": "manual",
      "is_published": true,
      "pdf_url": "https://<host>/storage/results/....pdf",
      "items": [
        {
          "final_position": "1",
          "total_points": "3.0",
          "not_participate": false,
          "sail_number": "691",
          "yacht_name": "Energie",
          "team_name": "Energie",
          "captain_name": "Харитонов Денис Владимирович",
          "race_breakdown": null
        }
      ]
    }
  ]
}
```

### Поля протокола

| Поле | Тип | Описание |
|---|---|---|
| `result_id` | string (uuid) | ID протокола |
| `result_type` | string | `preliminary` \| `final` |
| `source` | string | Источник: `imported` (из API/файла), `manual` и т.п. |
| `is_published` | bool | Опубликован ли протокол |
| `pdf_url` | string \| null | Ссылка на PDF, если сгенерирован |
| `items[]` | array | Итоговая таблица, отсортирована по месту |

### Поля строки итога (`items[]`)

| Поле | Тип | Описание |
|---|---|---|
| `final_position` | string \| null | Итоговое место |
| `total_points` | string \| null | Итоговые очки |
| `not_participate` | bool | Не участвовал(а) |
| `sail_number` | string \| null | Парусный номер |
| `yacht_name` | string \| null | Яхта |
| `team_name` | string \| null | Команда |
| `captain_name` | string \| null | Капитан |
| `race_breakdown` | array \| null | Пооночная разбивка (снапшот, может отсутствовать) |

Имена яхты/команды/номера берутся из живой связи, а при удалении сущности — из
сохранённого снапшота, поэтому строка результата уцелевает.

---

## 4. Импорт результатов

Записывает результаты зачётной группы «КАРТЕР 30» в регату: итоговую таблицу и
результаты по отдельным гонкам.

```
POST /api/regattas/{external_id}/results
Content-Type: application/json
```

### Тело запроса

```json
{
  "result_type": "preliminary",
  "create_missing": false,
  "replace": false,
  "races": [
    { "name": "Гонка 1", "at": "2026-06-12 12:00:00" },
    { "name": "Гонка 2", "at": "2026-06-12 14:00:00" }
  ],
  "crews": [
    {
      "sail_number": "691",
      "yacht_name": "Energie",
      "type": "CARTER 30",
      "city": "Москва",
      "team": "Energie",
      "final_position": "1",
      "total_points": "3.0",
      "races": [
        { "position": "1", "points": "1.0" },
        { "position": "2", "points": "2.0" }
      ]
    }
  ]
}
```

### Параметры верхнего уровня

| Поле | Тип | По умолч. | Описание |
|---|---|---|---|
| `result_type` | string | `preliminary` | Куда пишем: `preliminary` (предварительный) или `final` (итоговый) протокол |
| `create_missing` | bool | `false` | Создавать отсутствующие в базе яхты (и команду-заглушку). Если `false` — экипаж с неизвестным парусным номером пропускается |
| `replace` | bool | `false` | Перед импортом очистить строки итогов этого протокола |
| `races[]` | array | — | Список гонок по порядку. Гонки создаются/находятся по имени идемпотентно |
| `crews[]` | array | — | Строки результата (минимум 1) |

### Поля гонки (`races[]`)

| Поле | Тип | Описание |
|---|---|---|
| `name` | string | Имя гонки (например `"Гонка 1"`) — ключ идемпотентности |
| `at` | string \| null | Дата/время старта, `YYYY-MM-DD HH:MM:SS` |

### Поля строки результата (`crews[]`)

| Поле | Тип | Описание |
|---|---|---|
| `sail_number` | string | Парусный номер — **ключ привязки** к яхте/заявке (обязательно) |
| `yacht_name` | string \| null | Название яхты (для `create_missing`) |
| `type` | string \| null | Тип/проект (для `create_missing`) |
| `city` | string \| null | Город (для `create_missing`) |
| `team` | string \| null | Команда/клуб (для команды-заглушки) |
| `final_position` | string \| null | Итоговое место |
| `total_points` | string \| null | Итоговые очки |
| `races[]` | array | Результаты по гонкам, по порядку `races` верхнего уровня |
| `races[].position` | string \| null | Место в гонке. Скобки `(3)` — сброшенная гонка; коды `DNF/DNS/DSQ/OCS/RET/BFD/UFD` |
| `races[].points` | string \| null | Очки за гонку. Если пусто — вычисляются из места |

Порядок `crews[].races` соответствует порядку `races` верхнего уровня (i-й элемент —
i-я гонка). Лишние элементы сверх числа гонок игнорируются.

### Логика привязки

- Участник привязывается по **парусному номеру** (`vfps_number`), а не по имени команды.
- Заявка ищется/создаётся по паре (регата, яхта). Итоговые место/очки помечаются
  «переопределёнными» — авторасчёт их не меняет (сохраняются судейские тай-брейки).
- **Идемпотентно:** повторный вызов обновляет строки той же яхты, не создавая дублей.

### Ответ `200 OK`

```json
{
  "result_id": "019f6c4b-d3ff-7272-a90c-a48e2f336837",
  "result_type": "preliminary",
  "summary": {
    "imported": 12,
    "skipped": 1,
    "errors": [
      "Яхта с парусным № NOPE999 («Ghost») не найдена — пропущена"
    ],
    "created_yachts": 0,
    "created_teams": 0
  }
}
```

| Поле | Описание |
|---|---|
| `result_id` | ID протокола результата (`RegattaResult`) |
| `summary.imported` | Сколько строк записано/обновлено |
| `summary.skipped` | Сколько пропущено |
| `summary.errors[]` | Человекочитаемые причины пропусков |
| `summary.created_yachts` | Создано яхт (при `create_missing`) |
| `summary.created_teams` | Создано команд-заглушек |

---

## Коды ответов

| Код | Значение |
|---|---|
| `200` | Успех |
| `401` | Нет/неверный/отозванный токен |
| `404` | Регата с таким `external_id` не найдена |
| `422` | Ошибка валидации тела запроса |

Формат `422`:

```json
{
  "message": "Ошибка валидации запроса.",
  "errors": {
    "crews": ["The crews field is required."]
  }
}
```

---

## Примеры (curl)

Список регат:

```bash
curl -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" \
     "https://<host>/api/regattas?status=active"
```

Экспорт участников:

```bash
curl -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" \
     https://<host>/api/regattas/42/participants
```

Список результатов:

```bash
curl -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" \
     "https://<host>/api/regattas/42/results?type=final"
```

Импорт результатов:

```bash
curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d @results.json \
     https://<host>/api/regattas/42/results
```
