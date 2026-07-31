<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ServiceSubject;
use App\Models\Concerns\HasCaptionedGallery;
use App\Models\Concerns\RegistersResponsiveFormats;
use App\Models\Scopes\OwnedScope;
use App\Support\Plural;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Яхтенный поход или путешествие (раздел «Услуги», ТЗ 3-го этапа, п. 7).
 *
 * Заявка на участие — обычный ServiceRequest типа Tour, связанный с туром
 * через morph-поле `subject`; контракт ServiceSubject решает, принимает ли
 * поход заявки сейчас.
 */
class Tour extends Model implements HasMedia, ServiceSubject
{
    use HasCaptionedGallery, HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    protected $fillable = [
        'yacht_id',
        'vessel',
        'title',
        'slug',
        'summary',
        'content',
        'region',
        'route_summary',
        'date_start',
        'date_end',
        'price_per_seat',
        'price_per_cabin',
        'org_fee',
        'price_note',
        'seats_total',
        'seats_left',
        'video_links',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
            'price_per_seat' => 'integer',
            'price_per_cabin' => 'integer',
            'org_fee' => 'integer',
            'seats_total' => 'integer',
            'seats_left' => 'integer',
            'video_links' => 'array',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Media
    // ──────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->useDisk('public')
            ->singleFile();

        $this->addMediaCollection('gallery')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addResponsiveFormatConversions();
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * Яхта похода.
     *
     * OwnedScope снимаем: он прячет яхты без владельца (импорт из реестра),
     * а поход должен показывать привязанную яхту в любом случае.
     */
    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class)->withoutGlobalScope(OwnedScope::class);
    }

    /** Заявки на участие — для счётчика в админке. */
    public function serviceRequests(): MorphMany
    {
        return $this->morphMany(ServiceRequest::class, 'subject');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Предстоящие походы.
     *
     * Считаем по дате окончания: иначе идущий сейчас поход уехал бы в архив
     * на второй день. У однодневных выходов date_end пуста — берём date_start.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereRaw(
            'COALESCE(date_end, date_start) >= ?',
            [now()->toDateString()],
        );
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->whereRaw(
            'COALESCE(date_end, date_start) < ?',
            [now()->toDateString()],
        );
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('date_start');
    }

    /** Для архива: сначала самые свежие походы. */
    public function scopeRecentFirst(Builder $query): Builder
    {
        return $query->orderByDesc('date_start');
    }

    // ──────────────────────────────────────────────
    // Контракт ServiceSubject
    // ──────────────────────────────────────────────

    public function acceptsServiceRequests(): bool
    {
        return $this->is_published && ! $this->isPast() && $this->hasSeats();
    }

    public function subjectLabel(): string
    {
        return 'Поход «'.$this->title.'», '.$this->dateRange();
    }

    public function subjectUrl(): ?string
    {
        return $this->publicUrl();
    }

    // ──────────────────────────────────────────────
    // Вывод на сайте
    // ──────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function publicUrl(): string
    {
        return route('services.tour-item', $this);
    }

    public function isPast(): bool
    {
        return ($this->date_end ?? $this->date_start)->endOfDay()->isPast();
    }

    public function dateRange(): string
    {
        if ($this->date_end === null || $this->date_start->isSameDay($this->date_end)) {
            return $this->date_start->format('d.m.Y');
        }

        return $this->date_start->format('d.m.Y').' — '.$this->date_end->format('d.m.Y');
    }

    /** «8 дней» — длительность похода включительно. */
    public function durationLabel(): ?string
    {
        if ($this->date_end === null) {
            return null;
        }

        $days = $this->date_start->diffInDays($this->date_end) + 1;

        return $days > 1 ? Plural::with((int) $days, 'день', 'дня', 'дней') : null;
    }

    /** Есть ли ещё места. Если учёт не ведётся — считаем, что есть. */
    public function hasSeats(): bool
    {
        return $this->seats_left === null || $this->seats_left > 0;
    }

    /**
     * Плашка мест.
     *
     * null, когда учёт не ведётся: иначе у походов без счётчика на сайте
     * появилось бы «Осталось 0 мест».
     */
    public function seatsLabel(): ?string
    {
        if ($this->seats_left === null) {
            return $this->seats_total === null
                ? null
                : 'Всего мест: '.$this->seats_total;
        }

        return $this->seats_left > 0
            ? 'Осталось '.Plural::with($this->seats_left, 'место', 'места', 'мест')
            : 'Мест нет';
    }

    public function seatPriceLabel(): ?string
    {
        return $this->price_per_seat === null
            ? null
            : $this->formatPrice($this->price_per_seat).' за место';
    }

    public function cabinPriceLabel(): ?string
    {
        return $this->price_per_cabin === null
            ? null
            : $this->formatPrice($this->price_per_cabin).' за каюту';
    }

    public function orgFeeLabel(): ?string
    {
        return $this->org_fee === null
            ? null
            : 'Оргсбор '.$this->formatPrice($this->org_fee);
    }

    /** Название судна: яхта из реестра, иначе свободный текст. */
    public function vesselLabel(): ?string
    {
        $name = $this->yacht?->name;

        if ($name !== null && $name !== '') {
            return $name;
        }

        $vessel = trim((string) $this->vessel);

        return $vessel !== '' ? $vessel : null;
    }

    private function formatPrice(int $value): string
    {
        return number_format((float) $value, 0, ',', ' ').' ₽';
    }
}
