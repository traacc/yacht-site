<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RegattaResult\ImportRgdResultItemsAction;
use App\Models\Regatta;
use App\Models\RegattaResult;
use App\Services\Rgd\RgdParser;
use Illuminate\Console\Command;

/**
 * Импорт результатов зачётного класса из судейского файла .rgd в выбранную регату:
 * итоговая таблица + пооночные результаты. По умолчанию — сухой прогон (план);
 * запись только с --commit. Без --class выводит список классов файла.
 */
class ImportRgdResults extends Command
{
    protected $signature = 'import:rgd
                            {file : Путь к .rgd-файлу}
                            {--regatta= : Регата (id или часть имени) — обязательно для --commit}
                            {--class= : Зачётный класс (без него — список классов файла)}
                            {--type=preliminary : Тип результата: preliminary|final}
                            {--create-missing : Создавать недостающие яхты/команды}
                            {--replace : Заменить существующие строки итогов}
                            {--commit : Записать в БД (иначе сухой прогон)}';

    protected $description = 'Импорт результатов из .rgd (итоги + пооночные) в выбранную регату.';

    public function handle(RgdParser $parser, ImportRgdResultItemsAction $action): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $content = file_get_contents($path);
        $classes = $parser->classes($content);

        $class = $this->option('class');

        if (blank($class)) {
            $this->info('Зачётные классы в файле:');
            foreach ($classes as $name) {
                $this->line("  • {$name}");
            }
            $this->comment('Укажите --class="…" для импорта.');

            return self::SUCCESS;
        }

        $data = $parser->parse($content, $class);

        $this->info("Класс «{$class}»: гонок " . count($data['races']) . ', экипажей ' . count($data['crews']));
        $this->table(
            ['Место', 'Парус', 'Яхта', 'Команда', 'Очки', 'Гонки (место)'],
            array_map(fn (array $c) => [
                $c['final_position'],
                $c['sail'],
                $c['yacht'],
                $c['team'] !== '' ? $c['team'] : "Экипаж {$c['yacht']}",
                $c['total_points'],
                implode(' ', array_map(fn ($r) => $r['position'], $c['races'])),
            ], $data['crews']),
        );

        if (! $this->option('commit')) {
            $this->warn('Сухой прогон. Запись НЕ выполнена. Добавьте --regatta и --commit для импорта.');

            return self::SUCCESS;
        }

        $regatta = $this->resolveRegatta((string) $this->option('regatta'));

        if ($regatta === null) {
            return self::FAILURE;
        }

        // Результат регаты создаём/находим по (регата, тип, imported) и передаём в Action.
        $result = RegattaResult::firstOrCreate(
            ['regatta_id' => $regatta->id, 'result_type' => (string) $this->option('type'), 'source' => 'imported'],
            ['is_published' => false],
        );

        $summary = $action->execute(
            $result,
            $content,
            $class,
            (bool) $this->option('create-missing'),
            (bool) $this->option('replace'),
        );

        $this->info("Импортировано в «{$regatta->name}»:");
        $this->line("  строк итогов: {$summary['imported']}, пропущено: {$summary['skipped']}");
        $this->line("  создано яхт: {$summary['created_yachts']}, команд: {$summary['created_teams']}");

        foreach ($summary['errors'] as $error) {
            $this->warn("  ! {$error}");
        }

        return self::SUCCESS;
    }

    private function resolveRegatta(string $needle): ?Regatta
    {
        if (blank($needle)) {
            $this->error('Для --commit укажите --regatta (id или часть имени).');

            return null;
        }

        // Сначала точное совпадение по id или имени — чтобы не залить данные не в ту
        // регату при неоднозначном поиске.
        $regatta = Regatta::whereKey($needle)->first()
            ?? Regatta::where('name', $needle)->first();

        if ($regatta !== null) {
            return $regatta;
        }

        $matches = Regatta::where('name', 'like', "%{$needle}%")->get();

        if ($matches->count() > 1) {
            $this->error("По «{$needle}» найдено несколько регат — уточните id или точное имя:");
            foreach ($matches as $m) {
                $this->line("  {$m->id}  {$m->name}");
            }

            return null;
        }

        if ($matches->isEmpty()) {
            $this->error("Регата не найдена: «{$needle}».");

            return null;
        }

        return $matches->first();
    }
}
