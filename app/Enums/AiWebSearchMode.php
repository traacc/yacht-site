<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Способ, которым AI-модель получает доступ к вебу.
 *
 * OpenAI-модели умеют встроенный инструмент `web_search` (Responses API).
 * Роутеры (routerai.ru и другие OpenRouter-совместимые) для остальных моделей
 * этот инструмент молча игнорируют, но предлагают собственный плагин `web`,
 * который работает с любой моделью.
 */
enum AiWebSearchMode: string
{
    case Auto = 'auto';
    case Native = 'native';
    case Plugin = 'plugin';
    case Off = 'off';

    public static function fromConfig(?string $value): self
    {
        return self::tryFrom(trim((string) $value)) ?? self::Auto;
    }

    /**
     * Встроенный web_search есть только у моделей OpenAI: либо прямой вызов
     * API (`gpt-5-mini`), либо тот же вендор через роутер (`openai/gpt-5-mini`).
     */
    public function resolve(string $model): self
    {
        if ($this !== self::Auto) {
            return $this;
        }

        $vendor = str_contains($model, '/') ? Str::before($model, '/') : 'openai';

        return $vendor === 'openai' ? self::Native : self::Plugin;
    }
}
