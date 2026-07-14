<?php

declare(strict_types=1);

namespace App\Actions\Regatta;

use App\Models\Document;
use App\Models\Regatta;
use App\Models\RegattaScheduleEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Создаёт копию регаты вместе с расписанием и документами.
 *
 * Файлы документов физически копируются в новые пути, чтобы копия
 * не делила файлы с оригиналом (иначе удаление файла в одной регате
 * затронуло бы другую).
 */
final class ReplicateRegattaAction
{
    /**
     * Атрибуты, которые не переносятся в копию.
     *
     * @var array<int, string>
     */
    private const EXCLUDED_ATTRIBUTES = [
        'external_id',
        'postponed_to_date',
        'postponed_note',
        'postponed_to_regatta_id',
    ];

    /**
     * @param  array<string, mixed>  $overrides  Атрибуты, перезаписывающие значения копии
     *                                            (например, название и даты этапа серии).
     */
    public function execute(Regatta $source, array $overrides = []): Regatta
    {
        return DB::transaction(function () use ($source, $overrides): Regatta {
            /** @var Regatta $replica */
            $replica = $source->replicate(self::EXCLUDED_ATTRIBUTES);

            $replica->name           = $this->buildCopyName($source->name);
            $replica->regatta_status = \App\Enums\RegattaStatus::Upcoming->value;

            foreach ($overrides as $attribute => $value) {
                $replica->{$attribute} = $value;
            }

            $replica->save();

            $this->copyScheduleEvents($source, $replica);
            $this->copyDocuments($source, $replica);

            return $replica;
        });
    }

    /**
     * Добавляет пометку «(копия)» к названию.
     */
    private function buildCopyName(?string $name): string
    {
        $name = trim((string) $name);

        return $name === '' ? 'Копия регаты' : "{$name} (копия)";
    }

    private function copyScheduleEvents(Regatta $source, Regatta $replica): void
    {
        foreach ($source->scheduleEvents()->get() as $event) {
            /** @var RegattaScheduleEvent $copy */
            $copy = $event->replicate(['regatta_id']);
            $copy->regatta_id = $replica->getKey();
            $copy->save();
        }
    }

    private function copyDocuments(Regatta $source, Regatta $replica): void
    {
        $disk = Storage::disk('public');

        foreach ($source->documents()->get() as $document) {
            /** @var Document $copy */
            $copy = $document->replicate(['documentable_id']);
            $copy->documentable_id = $replica->getKey();

            $sourcePath = (string) $document->url;
            if ($sourcePath !== '' && $disk->exists($sourcePath)) {
                $newPath = $this->buildCopyPath($sourcePath);
                $disk->copy($sourcePath, $newPath);
                $copy->url = $newPath;
            }

            $copy->save();
        }
    }

    /**
     * Формирует новый путь в той же папке с уникальным именем файла.
     */
    private function buildCopyPath(string $path): string
    {
        $directory = trim(Str::beforeLast($path, '/'), '/');
        $filename  = Str::afterLast($path, '/');
        $unique    = Str::random(8) . '-' . $filename;

        return ($directory === '' || $directory === $path)
            ? $unique
            : "{$directory}/{$unique}";
    }
}
