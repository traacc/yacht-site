<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SportCategory;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\Yacht;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Формирует судейский файл .rgd зачётной группы «КАРТЕР 30» с данными только об
 * участниках (колонки результатов пустые). Используется CLI-командой
 * export:carter30-participants и кнопкой экспорта в админ-ресурсе регаты.
 *
 * Файл валиден для судейской программы: секции [RegattaParams] (шаблон
 * resources/rgd/params.stub с подстановкой имени/акватории/дат), [Classes],
 * секция класса [КАРТЕР 30] с 37-колоночными строками участников и секции
 * [Race_N] с датами гонок и коэффициентом регаты (level_coefficient).
 * Кодировка Windows-1251, разделитель полей ¶ (U+00B6), переводы строк CRLF.
 */
class RgdParticipantsExporter
{
    public const CLASS_NAME = 'КАРТЕР 30';

    private const DELIM = "\u{00B6}";

    /** Разделитель значений внутри поля (члены экипажа): DC3 (0x13), как в исходном .rgd. */
    private const CREW_DELIM = "\x13";

    /** Число полей в строке участника (как в исходном .rgd). */
    private const COLS = 37;

    /**
     * Заявки регаты для экспорта. Яхта под OwnedScope (user_id IS NULL скрыт),
     * поэтому грузим её без глобальных scope вручную.
     *
     * @return Collection<int, RegattaEntry>
     */
    public function loadParticipants(Regatta $regatta): Collection
    {
        return $regatta->entries()
            ->with(['team', 'crew.teamMember.user'])
            ->get()
            ->each(fn (RegattaEntry $e) => $e->setRelation(
                'yacht',
                Yacht::withoutGlobalScopes()->find($e->yacht_id),
            ))
            ->sortBy(fn (RegattaEntry $e) => $e->yacht?->name)
            ->values();
    }

    /** Имя файла для скачивания. */
    public function filename(Regatta $regatta): string
    {
        $slug = Str::slug($regatta->name) ?: 'regatta';

        return "uchastniki-{$slug}.rgd";
    }

    /** Готовые байты файла в кодировке Windows-1251. */
    public function toBytes(string $content): string
    {
        return mb_convert_encoding($content, 'Windows-1251', 'UTF-8');
    }

    /**
     * Собирает содержимое .rgd (UTF-8) для регаты и её заявок.
     *
     * @param  Collection<int, RegattaEntry>  $entries
     */
    public function build(Regatta $regatta, Collection $entries): string
    {
        $lines = [];

        // [RegattaParams] — из шаблона с подстановкой динамических значений.
        $params = file_get_contents(resource_path('rgd/params.stub'));
        $params = strtr($params, [
            '{{REG_NAME}}' => $regatta->name,
            '{{REG_PLACE}}' => (string) $regatta->water_area,
            '{{REG_BEG}}' => $regatta->date_start?->format('d.m.Y') ?? '',
            '{{REG_END}}' => $regatta->date_end?->format('d.m.Y') ?? '',
            '{{REG_UMPAIR}}' => '',
            '{{REG_SECRETAR}}' => '',
            '{{REG_MEASURER}}' => '',
        ]);
        $lines[] = rtrim($params, "\r\n");
        $lines[] = '';

        // [Classes] — одна зачётная группа.
        $lines[] = '[Classes]';
        $lines[] = 'Row_1='.implode(self::DELIM, [self::CLASS_NAME, 'IOR-TOTD', 'Самостоятельная', '2', '1', '']);
        $lines[] = '';

        // [КАРТЕР 30] — участники.
        $lines[] = '['.self::CLASS_NAME.']';
        $lines[] = 'RowCount='.($entries->count() + 2);   // как в исходнике: N данных + 2
        $lines[] = 'ColCount='.(self::COLS - 1);
        $lines[] = 'Rowh_headers='.rtrim(file_get_contents(resource_path('rgd/carter30_headers.stub')), "\r\n");

        foreach ($entries as $i => $entry) {
            $lines[] = 'Row_'.($i + 1).'='.$this->participantRow($entry);
        }

        $lines[] = 'SortSettingsColumn=-1';
        $lines[] = 'FleetTable=';
        $lines[] = '';

        // [Race_N] — гонки регаты: судейская программа читает коэффициент
        // регаты из RaceCol_3 (поле 7), поэтому без этих секций коэффициент
        // в файл не попадает.
        foreach ($this->raceSections($regatta, $entries->count()) as $line) {
            $lines[] = $line;
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Секции [Race_N] по образцу судейского файла (см. import_data/test.rgd):
     * имя, дата и RaceCol_3 с допущенными и коэффициентом регаты (формат — см.
     * комментарий у $raceCol3 ниже). Гонки берутся из расписания регаты; если
     * их ещё нет — генерируются «Гонка N» по races_count с датой старта регаты.
     *
     * @return array<int, string>
     */
    private function raceSections(Regatta $regatta, int $admitted): array
    {
        $races = $regatta->races
            ->map(fn ($race) => [
                'name' => $race->name,
                'date' => $race->event_datetime?->format('d.m.Y') ?? $regatta->date_start?->format('d.m.Y') ?? '',
            ])
            ->values()
            ->all();

        if ($races === []) {
            for ($i = 1; $i <= (int) $regatta->races_count; $i++) {
                $races[] = [
                    'name' => "Гонка {$i}",
                    'date' => $regatta->date_start?->format('d.m.Y') ?? '',
                ];
            }
        }

        // Коэффициент без хвостовых нулей, разделитель — точка (как в образце: 1.4444).
        $coefficient = (string) (float) ($regatta->level_coefficient ?? 1);

        // RaceCol_3 двухуровневый: DC3 (0x13) разделяет блоки (заголовок, список
        // классов, данные класса), ¶ — поля внутри блока данных. Байтово по
        // образцу: «(Все)␓¶¶КЛАСС;␓␓¶¶допущено¶¶¶коэффициент¶×9␓».
        $raceCol3 = '(Все)'.self::CREW_DELIM
            .self::DELIM.self::DELIM
            .self::CLASS_NAME.';'.self::CREW_DELIM.self::CREW_DELIM
            .self::DELIM.self::DELIM
            .$admitted.str_repeat(self::DELIM, 3).$coefficient.str_repeat(self::DELIM, 9)
            .self::CREW_DELIM;

        // «(Нет)» с разделителями DC3 — байтово как в образце судейской программы.
        $none = '(Нет) '.self::CREW_DELIM.' '.self::CREW_DELIM;

        $lines = [];
        foreach ($races as $i => $race) {
            $lines[] = '[Race_'.($i + 1).']';
            $lines[] = 'RaceCol_0=0';
            $lines[] = 'RaceCol_1='.$race['name'];
            $lines[] = 'RaceCol_2='.$race['date'];
            $lines[] = 'RaceCol_3='.$raceCol3;
            $lines[] = 'RaceCol_4='.$none;
            $lines[] = 'RaceCol_5='.$none;
            $lines[] = 'RaceCol_6='.$none;
            $lines[] = 'RaceCol_7='.$none;
            $lines[] = 'RaceCol_8=';
            $lines[] = 'RaceCol_9=';
            $lines[] = 'RaceCol_10=';
            $lines[] = 'RaceCol_11=';
            $lines[] = '';
        }

        return $lines;
    }

    /** Собирает 37-колоночную строку участника; заполнены только поля об участнике. */
    private function participantRow(RegattaEntry $entry): string
    {
        $cols = array_fill(0, self::COLS, '');

        $yacht = $entry->yacht;
        $class = $yacht?->class ?: 'CARTER 30';

        // Один проход по экипажу — колонки состава (ФИО, дата рожд., разряд, роль)
        // выровнены по одному порядку и одинаковому числу значений (разделитель 0x13).
        $names = $births = $ranks = $roles = [];
        foreach ($entry->crew as $c) {
            $user = $c->teamMember?->user;
            $name = trim((string) $user?->name);
            if ($name === '') {
                continue;   // без имени пропускаем целиком, чтобы не сбить выравнивание колонок
            }
            $names[] = $name;
            $births[] = $user?->birth_date?->format('d.m.Y') ?? '';
            $ranks[] = $this->rankLabel($user?->sport_category);
            $roles[] = $c->role === 'captain' ? 'капитан' : '';
        }

        $cols[1] = 'RUS';                                             // страна (в БД не хранится)
        $cols[2] = (string) ($yacht?->vfps_number ?? '');            // парус №
        $cols[3] = sprintf('%s(%s,,,)', $yacht?->name ?? '', $class); // яхта(тип)
        $cols[4] = implode(self::CREW_DELIM, $names);                 // экипаж (ФИО)
        $cols[27] = implode(self::CREW_DELIM, $births);                // даты рождения
        $cols[29] = implode(self::CREW_DELIM, $ranks);                 // спортивные разряды
        $cols[30] = implode(self::CREW_DELIM, $roles);                 // роли (капитан)
        $cols[33] = (string) ($yacht?->reg_place ?? '');              // город
        $cols[34] = (string) ($entry->team?->name ?? '');             // команда

        return implode(self::DELIM, $cols);
    }

    /** Разряд в формате .rgd: числовые — «N р», категории — аббревиатурой, б/р — пусто. */
    private function rankLabel(mixed $category): string
    {
        $value = $category instanceof SportCategory
            ? $category->value
            : (string) $category;

        return match ($value) {
            '3' => '3 р',
            '2' => '2 р',
            '1' => '1 р',
            'kms' => 'КМС',
            'ms' => 'МС',
            'msmk' => 'МСМК',
            'zms' => 'ЗМС',
            default => 'б/р',   // 'no' (б/р) или неизвестно — пусто
        };
    }
}
