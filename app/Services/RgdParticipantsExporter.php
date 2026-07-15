<?php

declare(strict_types=1);

namespace App\Services;

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
 * resources/rgd/params.stub с подстановкой имени/акватории/дат), [Classes] и
 * секция класса [КАРТЕР 30] с 37-колоночными строками участников. Кодировка
 * Windows-1251, разделитель полей ¶ (U+00B6), переводы строк CRLF.
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
            '{{REG_NAME}}'     => $regatta->name,
            '{{REG_PLACE}}'    => (string) $regatta->water_area,
            '{{REG_BEG}}'      => $regatta->date_start?->format('d.m.Y') ?? '',
            '{{REG_END}}'      => $regatta->date_end?->format('d.m.Y') ?? '',
            '{{REG_UMPAIR}}'   => '',
            '{{REG_SECRETAR}}' => '',
            '{{REG_MEASURER}}' => '',
        ]);
        $lines[] = rtrim($params, "\r\n");
        $lines[] = '';

        // [Classes] — одна зачётная группа.
        $lines[] = '[Classes]';
        $lines[] = 'Row_1=' . implode(self::DELIM, [self::CLASS_NAME, 'IOR-TOTD', 'Самостоятельная', '2', '1', '']);
        $lines[] = '';

        // [КАРТЕР 30] — участники.
        $lines[] = '[' . self::CLASS_NAME . ']';
        $lines[] = 'RowCount=' . ($entries->count() + 2);   // как в исходнике: N данных + 2
        $lines[] = 'ColCount=' . (self::COLS - 1);
        $lines[] = 'Rowh_headers=' . rtrim(file_get_contents(resource_path('rgd/carter30_headers.stub')), "\r\n");

        foreach ($entries as $i => $entry) {
            $lines[] = 'Row_' . ($i + 1) . '=' . $this->participantRow($entry);
        }

        $lines[] = 'SortSettingsColumn=-1';
        $lines[] = 'FleetTable=';
        $lines[] = '';

        return implode("\r\n", $lines) . "\r\n";
    }

    /** Собирает 37-колоночную строку участника; заполнены только поля об участнике. */
    private function participantRow(RegattaEntry $entry): string
    {
        $cols = array_fill(0, self::COLS, '');

        $yacht     = $entry->yacht;
        $class     = $yacht?->class ?: 'CARTER 30';
        $crewNames = $entry->crew
            ->map(fn ($c) => trim((string) $c->teamMember?->user?->name))
            ->filter()
            ->implode(self::CREW_DELIM);

        $cols[1]  = 'RUS';                                             // страна (в БД не хранится)
        $cols[2]  = (string) ($yacht?->vfps_number ?? '');            // парус №
        $cols[3]  = sprintf('%s(%s,,,)', $yacht?->name ?? '', $class); // яхта(тип)
        $cols[4]  = $crewNames;                                        // экипаж (ФИО)
        $cols[33] = (string) ($yacht?->reg_place ?? '');              // город
        $cols[34] = (string) ($entry->team?->name ?? '');             // команда

        return implode(self::DELIM, $cols);
    }
}
