<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Regatta;
use App\Services\RgdParticipantsExporter;
use Illuminate\Console\Command;

/**
 * Экспорт участников зачётной группы «КАРТЕР 30» в судейский формат .rgd
 * (обратная операция к import:carter30-results, но только данные об участниках —
 * колонки результатов остаются пустыми). Построение .rgd — в RgdParticipantsExporter,
 * тем же сервисом пользуется кнопка экспорта в админ-ресурсе регаты.
 */
class ExportCarter30Participants extends Command
{
    protected $signature = 'export:carter30-participants
                            {--regatta= : ID или часть названия регаты (по умолчанию Кубок Федерации)}
                            {--out= : Куда записать .rgd (по умолчанию import_data/…participants.rgd)}
                            {--stdout : Вывести в консоль вместо файла (в UTF-8, для просмотра)}';

    protected $description = 'Экспорт участников зачётной группы «КАРТЕР 30» в .rgd (только данные об участниках).';

    private const DEFAULT_REGATTA = 'Кубок Федерации';

    public function handle(RgdParticipantsExporter $exporter): int
    {
        $regatta = $this->resolveRegatta();

        if (! $regatta) {
            $this->error('Регата не найдена.');

            return self::FAILURE;
        }

        $entries = $exporter->loadParticipants($regatta);

        if ($entries->isEmpty()) {
            $this->error('У регаты нет заявок для экспорта.');

            return self::FAILURE;
        }

        $content = $exporter->build($regatta, $entries);

        if ($this->option('stdout')) {
            $this->line($content);

            return self::SUCCESS;
        }

        $out   = $this->option('out') ?: base_path('import_data/export_carter30_participants.rgd');
        $bytes = $exporter->toBytes($content);

        file_put_contents($out, $bytes);

        $this->info("Экспортировано участников: {$entries->count()}");
        $this->info("Файл: {$out} (" . strlen($bytes) . ' байт, Windows-1251)');

        return self::SUCCESS;
    }

    private function resolveRegatta(): ?Regatta
    {
        $key = $this->option('regatta') ?: self::DEFAULT_REGATTA;

        // Точное совпадение по id или названию — берём сразу.
        $exact = Regatta::query()
            ->where('id', $key)
            ->orWhere('name', $key)
            ->first();

        if ($exact) {
            return $exact;
        }

        // Иначе подстрока: при неоднозначности показываем кандидатов и не гадаем.
        $matches = Regatta::query()->where('name', 'like', "%{$key}%")->orderBy('date_start')->get();

        if ($matches->count() > 1) {
            $this->error("Найдено несколько регат по «{$key}» — уточните --regatta=<id|точное имя>:");
            $this->table(
                ['ID', 'Дата', 'Название', 'Заявок'],
                $matches->map(fn (Regatta $r) => [
                    $r->id, $r->date_start?->format('d.m.Y'), $r->name, $r->entries()->count(),
                ])->all(),
            );

            return null;
        }

        return $matches->first();
    }
}
