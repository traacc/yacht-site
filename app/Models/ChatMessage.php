<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageAuthorRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Сообщение в диалоге.
 *
 * author_role фиксируется в момент отправки и не пересчитывается по текущей
 * системной роли автора: оператор, ставший обычным пользователем, не должен
 * задним числом «переписать» историю обращения.
 *
 * ★ Удалять сообщения и диалоги только через Eloquent: FK `chat_messages` →
 * `conversations` каскадит на уровне БД, а каскад не дёргает observer медиатеки,
 * и файлы вложений осиротеют (та же грабля, что с race_results — см. AGENTS.md).
 */
class ChatMessage extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia;

    /** Вложения сообщения. */
    public const ATTACHMENTS = 'attachments';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'author_role',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'author_role' => MessageAuthorRole::class,
        ];
    }

    /**
     * Вложения лежат на приватном диске: в переписке с поддержкой встречаются
     * документы, которые нельзя отдавать по прямой ссылке без проверки прав.
     * Отдаёт их маршрут `chat.attachment` (@see \App\Services\Chat\ChatAttachments).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENTS)
            ->useDisk('local');
    }

    /**
     * Только превью для ленты. WebP/AVIF (RegistersResponsiveFormats) здесь не
     * нужны: приватная отдача потребовала бы маршрута под каждую конверсию, а
     * клиентские фото и так пережаты при загрузке.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('preview')
            ->fit(Fit::Contain, 1200, 1200)
            ->format('jpg')
            ->quality(80)
            ->queued();
    }

    public function hasAttachments(): bool
    {
        return $this->getMedia(self::ATTACHMENTS)->isNotEmpty();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Подпись автора в ленте: у поддержки — обезличенная. */
    public function authorName(): string
    {
        return match ($this->author_role) {
            MessageAuthorRole::Support => 'Поддержка',
            MessageAuthorRole::System => 'Система',
            MessageAuthorRole::Client => $this->author?->name ?? 'Пользователь',
        };
    }
}
