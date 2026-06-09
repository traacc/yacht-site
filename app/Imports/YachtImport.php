<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\User;
use App\Models\Yacht;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Импорт яхт из Excel-файла по шаблону public/files/CARTER 30_list.xlsx.
 *
 * Ожидаемые колонки (первая строка — заголовок, пропускается):
 *   A — Тип яхты            → project / class
 *   B — №                   → vfps_number (уникальный, обязательный)
 *   C — Название яхты        → name
 *   D — Г.в.                → year
 *   E — Владелец            → owner_name
 *   F — Место регистрации    → reg_place
 *   G — Дата регистрации     → (нет поля в модели, игнорируется)
 */
class YachtImport
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public int $matchedOwners = 0;

    /** @var array<int, string> */
    public array $errors = [];

    /**
     * Карта нормализованных ФИО → user_id для сопоставления владельцев.
     *
     * @var array<string, string>
     */
    private array $userLookup = [];

    public function import(string $path): self
    {
        $this->userLookup = $this->buildUserLookup();

        $reader = IOFactory::createReader(IOFactory::identify($path));
        $reader->setReadDataOnly(true);

        $sheet = $reader->load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            // Пропускаем строку-заголовок.
            if ($rowNumber === 1) {
                continue;
            }

            $project = trim((string) ($row[0] ?? ''));
            $vfpsNumber = trim((string) ($row[1] ?? ''));
            $name = trim((string) ($row[2] ?? ''));
            $yearRaw = trim((string) ($row[3] ?? ''));
            $ownerName = trim((string) ($row[4] ?? ''));
            $regPlace = trim((string) ($row[5] ?? ''));

            // Полностью пустые строки молча пропускаем.
            if ($project === '' && $vfpsNumber === '' && $name === '') {
                continue;
            }

            if ($vfpsNumber === '') {
                $this->errors[] = "Строка {$rowNumber}: отсутствует номер на парусе (№) — строка пропущена.";
                $this->skipped++;

                continue;
            }

            $userId = $this->matchUser($ownerName);

            if ($userId !== null) {
                $this->matchedOwners++;
            }

            $attributes = [
                'name' => $name !== '' ? mb_convert_case($name, MB_CASE_TITLE, "UTF-8") : "№ {$vfpsNumber}",
                'project' => $project !== '' ? $project : null,
                'class' => $project !== '' ? $project : null,
                'year' => ctype_digit($yearRaw) ? (int) $yearRaw : null,
                'owner_name' => $ownerName !== '' ? $ownerName : null,
                'reg_place' => $regPlace !== '' ? $regPlace : null,
                'approval_status' => 'approved',
            ];

            // user_id присваиваем только при найденном совпадении, чтобы не
            // затирать уже привязанного владельца при повторном импорте.
            if ($userId !== null) {
                $attributes['user_id'] = $userId;
            }

            // Уникальный индекс vfps_number действует и на мягко удалённые записи,
            // а также на яхты без владельца (их скрывает OwnedScope).
            $yacht = Yacht::withoutGlobalScope(\App\Models\Scopes\OwnedScope::class)
                ->withTrashed()
                ->firstWhere('vfps_number', $vfpsNumber);

            if ($yacht !== null) {
                $yacht->fill($attributes)->save();
                $this->updated++;

                continue;
            }

            Yacht::create($attributes + ['vfps_number' => $vfpsNumber]);
            $this->created++;
        }

        return $this;
    }

    /**
     * Находит user_id по ФИО владельца из файла.
     *
     * Сопоставление не зависит от порядка слов («Фамилия Имя Отчество» и
     * «Имя Фамилия» считаются равными) и игнорирует посторонние фрагменты
     * вроде номеров паспортов.
     */
    private function matchUser(string $ownerName): ?string
    {
        $key = $this->normalizeName($ownerName);

        if ($key === '') {
            return null;
        }

        return $this->userLookup[$key] ?? null;
    }

    /**
     * Строит карту нормализованных ФИО → user_id из всех пользователей.
     *
     * Учитываются оба варианта записи имени: поле `name` и связка
     * `last_name first_name patronymic`. Неоднозначные совпадения (один и
     * тот же ключ у разных пользователей) исключаются во избежание ошибочной
     * привязки.
     *
     * @return array<string, string>
     */
    private function buildUserLookup(): array
    {
        $lookup = [];
        $ambiguous = [];

        User::query()
            ->select(['id', 'name', 'first_name', 'last_name', 'patronymic'])
            ->each(function (User $user) use (&$lookup, &$ambiguous): void {
                $keys = [
                    $this->normalizeName((string) $user->name),
                    //$this->normalizeName(trim("{$user->last_name} {$user->first_name} {$user->patronymic}")),
                ];

                foreach (array_unique(array_filter($keys)) as $key) {
                    if (isset($ambiguous[$key])) {
                        continue;
                    }

                    if (isset($lookup[$key]) && $lookup[$key] !== $user->id) {
                        unset($lookup[$key]);
                        $ambiguous[$key] = true;

                        continue;
                    }

                    $lookup[$key] = $user->id;
                }
            });

        return $lookup;
    }

    /**
     * Приводит ФИО к каноничному виду для сравнения: нижний регистр, ё→е,
     * только буквенные слова, отсортированные по алфавиту.
     */
    private function normalizeName(string $value): string
    {
        $value = str_replace(['ё', 'Ё'], ['е', 'Е'], $value);
        $value = (string) Str::of($value)->lower();

        // Оставляем только слова, состоящие из букв (отбрасываем номера
        // паспортов, запятые и прочий мусор).
        preg_match_all('/\p{L}+/u', $value, $matches);

        $words = $matches[0] ?? [];
        sort($words);

        return implode(' ', $words);
    }
}
