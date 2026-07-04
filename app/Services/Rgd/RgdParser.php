<?php

declare(strict_types=1);

namespace App\Services\Rgd;

use RuntimeException;

/**
 * Разбор судейского файла .rgd (экспорт парусной программы).
 *
 * Формат: INI-подобный, кодировка Windows-1251, поля строки участника разделены
 * символом ¶ (U+00B6). Секция зачётного класса (напр. [ORC], [КАРТЕР 30]) содержит:
 *  - Rowh_racename — склеенные метаданные гонок (имя, старт, КВ, допущено);
 *  - Row_N — строку участника, 37 колонок:
 *      0=итог.место 1=страна 2=парус 3=яхта(тип) 4=экипаж 5=итог.очки
 *      8..=6×[место, очки, ET/CT] 33=город 34=команда/спонсор.
 *  - Блок гонки — 3 колонки [место, очки, ET/CT], число гонок берётся из Rowh_racename.
 *
 * Класс сервиса — чистый (без БД): нормализует ячейки в формат приложения (скобки
 * сброшенной гонки сохраняются, «НФ» → DNF, запятая → точка); числовые очки из места
 * выводит уже потребитель через RegattaResultResource::deriveRacePoints.
 */
class RgdParser
{
    private const DELIMITER = "\u{00B6}";

    /**
     * Список зачётных классов из секции [Classes] (первое ¶-поле каждой Row_N).
     *
     * @return array<int, string>
     */
    public function classes(string $content): array
    {
        $sections = $this->sections($content);
        $classes  = [];

        foreach ($sections['Classes'] ?? [] as $line) {
            if (! str_starts_with($line, 'Row_')) {
                continue;
            }

            $value = substr($line, strpos($line, '=') + 1);
            $name  = trim(explode(self::DELIMITER, $value)[0]);

            if ($name !== '') {
                $classes[] = $name;
            }
        }

        return $classes;
    }

    /**
     * Разбирает результаты одного зачётного класса.
     *
     * @return array{
     *     races: array<int, array{name: string, at: string}>,
     *     crews: array<int, array{
     *         final_position: string, sail: string, country: string, yacht: string,
     *         type: string, total_points: string, city: string, team: string,
     *         races: array<int, array{position: string, points: string}>
     *     }>
     * }
     */
    public function parse(string $content, string $className): array
    {
        $sections = $this->sections($content);
        $lines    = $this->classSection($sections, $className);

        if ($lines === null) {
            throw new RuntimeException("В файле нет секции зачётного класса «{$className}».");
        }

        $races = $this->parseRaces($lines);
        $crews = [];

        foreach ($lines as $line) {
            if (! str_starts_with($line, 'Row_')) {
                continue;
            }

            $crew = $this->parseCrew($line, count($races));

            if ($crew !== null) {
                $crews[] = $crew;
            }
        }

        return ['races' => $races, 'crews' => $crews];
    }

    /**
     * Секции файла: заголовок [Name] → массив последующих строк (до следующей секции).
     *
     * @return array<string, array<int, string>>
     */
    private function sections(string $content): array
    {
        $text     = mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
        $sections = [];
        $current  = null;

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (preg_match('/^\[(.+)\]$/u', $line, $m)) {
                $current            = $m[1];
                $sections[$current] = [];
                continue;
            }

            if ($current !== null) {
                $sections[$current][] = $line;
            }
        }

        return $sections;
    }

    /**
     * Находит строки секции класса. Имя класса в [Classes] и заголовок секции могут
     * различаться регистром («Картер 30» ↔ [КАРТЕР 30]) — сравниваем без учёта регистра.
     *
     * @param  array<string, array<int, string>>  $sections
     * @return array<int, string>|null
     */
    private function classSection(array $sections, string $className): ?array
    {
        $needle = mb_strtoupper(trim($className));

        foreach ($sections as $name => $lines) {
            if (mb_strtoupper(trim($name)) === $needle) {
                return $lines;
            }
        }

        return null;
    }

    /**
     * Гонки класса из Rowh_racename: имя + дата/время старта.
     * Каждый блок гонки: «{имя}D=… Старт dd.mm.yyyy hh:mm КВ …». Захватываем имя-метку
     * и первый следующий «Старт» с датой/временем; число гонок = число стартов.
     *
     * @param  array<int, string>  $lines
     * @return array<int, array{name: string, at: string}>
     */
    private function parseRaces(array $lines): array
    {
        $racename = '';
        foreach ($lines as $line) {
            if (str_starts_with($line, 'Rowh_racename=')) {
                $racename = substr($line, strlen('Rowh_racename='));
                break;
            }
        }

        $races = [];

        // Имя-метка (любой текст до «D=») + первый «Старт dd.mm.yyyy hh:mm» после неё.
        if (preg_match_all(
            '/([^¶]*?)D=\d+.*?Старт\s*(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})/us',
            $racename,
            $matches,
            PREG_SET_ORDER,
        )) {
            foreach ($matches as $i => $m) {
                $name = trim(preg_replace('/\s+/u', ' ', $m[1]));
                $races[] = [
                    'name' => $name !== '' ? $name : 'Гонка ' . ($i + 1),
                    'at'   => sprintf('%s-%s-%s %s:%s:00', $m[4], $m[3], $m[2], $m[5], $m[6]),
                ];
            }
        }

        return $races;
    }

    /**
     * Разбирает строку участника Row_N.
     *
     * @return array<string, mixed>|null  null — если колонок меньше ожидаемого минимума.
     */
    private function parseCrew(string $line, int $raceCount): ?array
    {
        $value = substr($line, strpos($line, '=') + 1);
        $cols  = explode(self::DELIMITER, $value);

        // 8 фиксированных + 3×гонки + хвост данных экипажа (город=33, команда=34).
        if (count($cols) < 8 + $raceCount * 3) {
            return null;
        }

        $yachtRaw = trim($cols[3]);
        preg_match('/^(.*?)\((.*?)[,)]/u', $yachtRaw, $ym);
        $yachtName = trim($ym[1] ?? $yachtRaw);
        $yachtType = trim($ym[2] ?? '');

        $races = [];
        for ($i = 0; $i < $raceCount; $i++) {
            $base    = 8 + $i * 3;
            $races[] = $this->normalizeCell($cols[$base], $cols[$base + 1]);
        }

        $sail = trim($cols[2]);

        return [
            'final_position' => trim($cols[0]),
            'sail'           => $sail,
            'country'        => trim($cols[1]),
            'yacht'          => $yachtName !== '' ? $yachtName : "Яхта {$sail}",
            'type'           => $yachtType,
            'total_points'   => str_replace(',', '.', trim($cols[5])),
            'city'           => $this->firstCity($cols[33] ?? ''),
            'team'           => trim($cols[34] ?? ''),
            'races'          => $races,
        ];
    }

    /**
     * Нормализует ячейку гонки в формат приложения.
     * Скобки (сброшенная гонка) сохраняются, «НФ» → DNF, запятая → точка.
     * Очки возвращаются как есть (запятая → точка, скобки сохранены) — числовые
     * очки из места выводит потребитель через RegattaResultResource::deriveRacePoints.
     *
     * @return array{position: string, points: string}
     */
    private function normalizeCell(string $placeRaw, string $pointsRaw): array
    {
        $place    = trim($placeRaw);
        $discard  = str_starts_with($place, '(') && str_ends_with($place, ')');
        $inner    = $discard ? trim(substr($place, 1, -1)) : $place;
        $inner    = str_replace(['НФ', ','], ['DNF', '.'], $inner);
        $position = $discard ? "({$inner})" : $inner;

        return [
            'position' => $position,
            'points'   => str_replace(',', '.', trim($pointsRaw)),
        ];
    }

    /** Первый непустой топоним из склеенного по числу членов экипажа поля города. */
    private function firstCity(string $raw): string
    {
        foreach (explode(',', $raw) as $part) {
            if (($part = trim($part)) !== '') {
                return $part;
            }
        }

        return '';
    }
}
