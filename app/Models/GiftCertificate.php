<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ServiceOptionProvider;
use App\Contracts\ServiceSubject;
use App\Enums\CertificatePriceType;
use App\Models\Concerns\RegistersResponsiveFormats;
use App\Support\Plural;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Позиция каталога подарочных сертификатов (раздел «Услуги», ТЗ 3-го этапа, п. 7).
 *
 * Это предложение, а не выданный бланк: заказ — обычный ServiceRequest типа
 * GiftCertificate, связанный с сертификатом через morph-поле `subject`, и
 * доводится менеджером до статуса «Услуга оказана».
 *
 * Своей страницы у сертификата нет — каталог целиком помещается на витрине,
 * поэтому subjectUrl() ведёт на якорь карточки.
 */
class GiftCertificate extends Model implements HasMedia, ServiceOptionProvider, ServiceSubject
{
    use HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    /**
     * Потолок списка номиналов.
     *
     * Шаг задаёт админ, и «от 10 000 до 100 000 шагом 100» превратилось бы в
     * select на 900 пунктов. Упираемся в потолок — шаг пересчитывается.
     */
    private const MAX_NOMINALS = 50;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'content',
        'terms',
        'price_type',
        'price',
        'price_min',
        'price_max',
        'price_step',
        'price_note',
        'validity_months',
        'is_published',
        'sort_order',
    ];

    protected $attributes = [
        'price_type' => CertificatePriceType::Fixed->value,
    ];

    protected function casts(): array
    {
        return [
            'price_type' => CertificatePriceType::class,
            'price' => 'integer',
            'price_min' => 'integer',
            'price_max' => 'integer',
            'price_step' => 'integer',
            'validity_months' => 'integer',
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
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addResponsiveFormatConversions();
    }

    // ──────────────────────────────────────────────
    // Связи
    // ──────────────────────────────────────────────

    /** Заказы сертификата — для счётчика в админке. */
    public function serviceRequests(): MorphMany
    {
        return $this->morphMany(ServiceRequest::class, 'subject');
    }

    // ──────────────────────────────────────────────
    // Скоупы
    // ──────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    // ──────────────────────────────────────────────
    // Контракт ServiceSubject
    // ──────────────────────────────────────────────

    /** Сроков у сертификата нет — заказать можно, пока он опубликован. */
    public function acceptsServiceRequests(): bool
    {
        return $this->is_published;
    }

    public function subjectLabel(): string
    {
        return 'Сертификат «'.$this->title.'», '.$this->priceLabel();
    }

    public function subjectUrl(): ?string
    {
        return $this->publicUrl();
    }

    // ──────────────────────────────────────────────
    // Контракт ServiceOptionProvider
    // ──────────────────────────────────────────────

    /**
     * Номиналы диапазонного сертификата.
     *
     * У сертификата с фиксированной ценой вариантов нет — и поле номинала само
     * пропадает из формы: ServiceType::formFields() выбрасывает select без
     * вариантов. Сумма в таком заказе известна из самого сертификата.
     *
     * @return array<string, array<string, string>>
     */
    public function serviceOptions(): array
    {
        return ['nominal' => $this->nominalOptions()];
    }

    /**
     * Подпись сохранённого номинала.
     *
     * Нужна отдельно от serviceOptions(): границы и шаг могли поменяться уже
     * после заказа, а показать сумму заказа всё равно надо.
     */
    public function serviceOptionLabel(string $field, string $value): ?string
    {
        if ($field !== 'nominal' || ! is_numeric($value)) {
            return null;
        }

        return $this->formatPrice((int) $value);
    }

    // ──────────────────────────────────────────────
    // Вывод на сайте
    // ──────────────────────────────────────────────

    /** Отдельной страницы нет: каталог целиком на витрине, ссылка — якорь карточки. */
    public function publicUrl(): string
    {
        return route('services.gift-certificates').'#'.$this->anchor();
    }

    public function anchor(): string
    {
        return 'certificate-'.$this->slug;
    }

    public function hasRangePrice(): bool
    {
        return $this->price_type === CertificatePriceType::Range;
    }

    /** «15 000 ₽» либо «от 15 000 до 40 000 ₽». */
    public function priceLabel(): string
    {
        if (! $this->hasRangePrice()) {
            return $this->price === null ? 'Цена по запросу' : $this->formatPrice($this->price);
        }

        if ($this->price_min === null || $this->price_max === null) {
            return 'Цена по запросу';
        }

        // Рубль ставим один раз, в конце: «от 15 000 до 40 000 ₽».
        return 'от '.number_format((float) $this->price_min, 0, ',', ' ')
            .' до '.$this->formatPrice($this->price_max);
    }

    public function validityLabel(): ?string
    {
        return $this->validity_months === null
            ? null
            : 'Действует '.Plural::with($this->validity_months, 'месяц', 'месяца', 'месяцев');
    }

    /**
     * Номиналы для формы заказа: от price_min до price_max включительно.
     *
     * Максимум добавляется всегда, даже если не попадает на сетку шага, — иначе
     * объявленная на витрине верхняя граница оказалась бы недоступна в заказе.
     *
     * @return array<string, string> сумма => подпись
     */
    public function nominalOptions(): array
    {
        if (! $this->hasRangePrice() || $this->price_min === null || $this->price_max === null) {
            return [];
        }

        $min = $this->price_min;
        $max = $this->price_max;

        if ($max <= $min) {
            return [(string) $min => $this->formatPrice($min)];
        }

        $step = max(1, (int) ($this->price_step ?: $max - $min));
        // Слишком мелкий шаг растянул бы select на сотни пунктов — укрупняем.
        // Делим на MAX_NOMINALS - 1: максимум добавляется отдельным пунктом.
        $step = max($step, (int) ceil(($max - $min) / (self::MAX_NOMINALS - 1)));

        $options = [];

        for ($value = $min; $value < $max; $value += $step) {
            $options[(string) $value] = $this->formatPrice($value);
        }

        $options[(string) $max] = $this->formatPrice($max);

        return $options;
    }

    private function formatPrice(int $value): string
    {
        return number_format((float) $value, 0, ',', ' ').' ₽';
    }
}
