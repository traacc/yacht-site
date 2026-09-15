<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * ФИО вида «Фамилия Имя [Отчество]»: минимум два слова, отчество необязательно.
 */
class FullName implements ValidationRule
{
    public const MIN_WORDS = 2;

    public const MESSAGE = 'Укажите как минимум фамилию и имя (Фамилия Имя Отчество)';

    public static function passes(?string $value): bool
    {
        return count(preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY)) >= self::MIN_WORDS;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes(is_string($value) ? $value : null)) {
            $fail(self::MESSAGE);
        }
    }
}
