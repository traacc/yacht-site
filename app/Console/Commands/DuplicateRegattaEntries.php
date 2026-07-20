<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RegattaEntry\DuplicateRegattaEntriesAction;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class DuplicateRegattaEntries extends Command
{
    protected $signature = 'regatta:duplicate-entries
                            {source? : ID или часть названия регаты-источника}
                            {target? : ID или часть названия регаты-приёмника}
                            {--status=* : Статусы копируемых заявок (по умолчанию pending, approved; "all" — все)}
                            {--entry=* : Копировать только заявки с указанными ID}
                            {--keep-status : Сохранить статусы заявок (по умолчанию все копии — pending)}
                            {--without-yachts : Не переносить яхты (снимает конфликт занятости яхты)}
                            {--without-crew : Не переносить экипаж}
                            {--with-documents : Перенести документы заявок (файлы копируются на диске)}
                            {--dry-run : Показать, что будет скопировано, ничего не записывая}
                            {--force : Не спрашивать подтверждение}';

    protected $description = 'Дублирует заявки из одной регаты в другую (команда, яхта, экипаж, опционально документы).';

    public function handle(DuplicateRegattaEntriesAction $action): int
    {
        $source = $this->resolveRegatta($this->argument('source'), 'Регата-источник');
        $target = $this->resolveRegatta($this->argument('target'), 'Регата-приёмник');

        if (! $source || ! $target) {
            return self::FAILURE;
        }

        if ($source->is($target)) {
            $this->error('Источник и приёмник — одна и та же регата.');

            return self::FAILURE;
        }

        $statuses = $this->statuses();
        $entryIds = array_values(array_filter((array) $this->option('entry')));
        $entries = $action->sourceEntries($source, $statuses, $entryIds);

        if ($entries->isEmpty()) {
            $this->info('Подходящих заявок в регате-источнике не найдено.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->line("Источник: <info>{$source->name}</info> ({$source->date_start?->format('d.m.Y')})");
        $this->line("Приёмник: <info>{$target->name}</info> ({$target->date_start?->format('d.m.Y')})");
        $this->table(
            ['Команда', 'Яхта', 'Статус', 'Экипаж', 'Док.'],
            $entries->map(fn (RegattaEntry $entry) => [
                $entry->team?->name ?? '—',
                $entry->yacht?->name ?? '—',
                $entry->status,
                $entry->crew->count(),
                $entry->documents->count(),
            ])->all()
        );

        $count = $entries->count();

        if (! $dryRun && ! $this->option('force')
            && ! $this->confirm("Скопировать {$count} заявок в «{$target->name}»?", false)) {
            $this->info('Отменено, ничего не скопировано.');

            return self::SUCCESS;
        }

        $results = $action->execute(
            source: $source,
            target: $target,
            statuses: $statuses,
            entryIds: $entryIds,
            withYacht: ! $this->option('without-yachts'),
            withCrew: ! $this->option('without-crew'),
            withDocuments: (bool) $this->option('with-documents'),
            keepStatus: (bool) $this->option('keep-status'),
            dryRun: $dryRun,
        );

        $this->report($results, $dryRun);

        return $results->contains(fn (array $row) => $row['result'] === 'failed')
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @param  Collection<int, array{entry: RegattaEntry, result: string, message: string|null}>  $results
     */
    private function report(Collection $results, bool $dryRun): void
    {
        foreach ($results as $row) {
            if ($row['result'] === 'created') {
                continue;
            }

            $team = $row['entry']->team?->name ?? $row['entry']->id;
            $label = $row['result'] === 'skipped' ? 'пропущена' : 'ошибка';
            $style = $row['result'] === 'skipped' ? 'comment' : 'error';

            $this->line("<{$style}>{$team}: {$label} — {$row['message']}</{$style}>");
        }

        $created = $results->where('result', 'created')->count();
        $skipped = $results->where('result', 'skipped')->count();
        $failed = $results->where('result', 'failed')->count();

        $verb = $dryRun ? 'Будет скопировано' : 'Скопировано';
        $this->info("{$verb}: {$created}; пропущено: {$skipped}; с ошибкой: {$failed}.");

        if ($dryRun) {
            $this->comment('Режим --dry-run: изменения не сохранены.');
        }
    }

    /**
     * @return string[]
     */
    private function statuses(): array
    {
        $statuses = array_values(array_filter((array) $this->option('status')));

        if ($statuses === []) {
            return DuplicateRegattaEntriesAction::DEFAULT_STATUSES;
        }

        return in_array('all', $statuses, true) ? [] : $statuses;
    }

    /**
     * Найти регату по ID или части названия; при неоднозначности — спросить.
     */
    private function resolveRegatta(?string $needle, string $label): ?Regatta
    {
        $needle ??= $this->ask("{$label} (ID или часть названия)");

        if (blank($needle)) {
            $this->error("{$label} не указана.");

            return null;
        }

        $matches = Regatta::query()
            ->where('id', $needle)
            ->orWhere('name', 'like', "%{$needle}%")
            ->orderByDesc('date_start')
            ->limit(25)
            ->get();

        if ($matches->isEmpty()) {
            $this->error("{$label}: ничего не найдено по «{$needle}».");

            return null;
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        $options = $matches
            ->mapWithKeys(fn (Regatta $regatta) => [
                $regatta->id => "{$regatta->name} ({$regatta->date_start?->format('d.m.Y')})",
            ])
            ->all();

        $choice = $this->choice("{$label}: уточните выбор", $options);

        return $matches->firstWhere(fn (Regatta $regatta) => $options[$regatta->id] === $choice);
    }
}
