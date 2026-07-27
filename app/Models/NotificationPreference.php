<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Настройка доставки уведомлений: одна строка на пару «категория + канал».
 *
 * Отсутствие строки означает «включено» — так новые и ранее зарегистрированные
 * пользователи по умолчанию получают всё, без миграции данных (требование ТЗ).
 *
 * @see NotificationPreferences
 */
class NotificationPreference extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'category',
        'channel',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'channel' => NotificationChannel::class,
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
