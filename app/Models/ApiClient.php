<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Клиент внешнего API. Аутентификация — Bearer-токен, хранится хешем (sha256).
 * Проверяет токен middleware App\Http\Middleware\VerifyApiToken.
 */
class ApiClient extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'token_hash',
        'last_used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** Хеш токена для хранения/поиска (в БД plaintext не попадает). */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Выпускает нового клиента и возвращает [ApiClient, plaintext-токен].
     * Plaintext показывается один раз — сохраните его на стороне внешней программы.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(string $name): array
    {
        $plain = Str::random(64);

        $client = static::create([
            'name' => $name,
            'token_hash' => static::hashToken($plain),
        ]);

        return [$client, $plain];
    }

    /** Действующий (не отозванный) клиент по plaintext-токену, либо null. */
    public static function findByToken(string $plain): ?self
    {
        return static::query()
            ->where('token_hash', static::hashToken($plain))
            ->whereNull('revoked_at')
            ->first();
    }
}
