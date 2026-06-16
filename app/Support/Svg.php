<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class Svg
{
    /** Память в рамках одного запроса (одна и та же иконка читается несколько раз на странице). */
    private static array $memo = [];

    /**
     * Возвращает содержимое SVG-файла из public/ для инлайна.
     *
     * Кэшируется в Redis по mtime файла — повторных чтений с диска на каждый
     * запрос нет, но при изменении файла кэш инвалидируется автоматически.
     */
    public static function inline(string $relativePath): string
    {
        if (isset(self::$memo[$relativePath])) {
            return self::$memo[$relativePath];
        }

        $path = public_path($relativePath);
        $mtime = @filemtime($path);

        if ($mtime === false) {
            return self::$memo[$relativePath] = '';
        }

        return self::$memo[$relativePath] = Cache::rememberForever(
            "svg:{$relativePath}:{$mtime}",
            fn (): string => (string) @file_get_contents($path),
        );
    }
}
