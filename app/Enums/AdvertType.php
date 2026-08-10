<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Доска объявлений.
 *
 * Барахолка и «Продать яхту» — подразделы раздела «Carter 30» (ТЗ 3-го этапа,
 * п. 5), четыре биржи — подразделы «Соревнований» (п. 8). Модель, премодерация,
 * фото, контакты и переписка с автором у всех досок общие: новая доска — это
 * кейс здесь плюс набор методов-переключателей ниже, которые решают, какие поля
 * показывает форма в ЛК и какие фильтры рендерит витрина.
 */
enum AdvertType: string
{
    case Marketplace = 'marketplace';
    case YachtSale = 'yacht_sale';
    case Skippers = 'skippers';
    case Sails = 'sails';
    case Crews = 'crews';
    case CompetitionYachts = 'competition_yachts';

    public function label(): string
    {
        return match ($this) {
            self::Marketplace => 'Барахолка',
            self::YachtSale => 'Продать яхту',
            self::Skippers => 'Биржа шкиперов и матросов',
            self::Sails => 'Биржа парусов',
            self::Crews => 'Экипажи для соревнований',
            self::CompetitionYachts => 'Яхты для соревнований',
        };
    }

    /** Заголовок витрины. */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Marketplace => 'Барахолка',
            self::YachtSale => 'Яхты на продажу',
            self::Skippers => 'Биржа шкиперов и матросов',
            self::Sails => 'Биржа парусов',
            self::Crews => 'Экипажи для соревнований',
            self::CompetitionYachts => 'Яхты для соревнований',
        };
    }

    /** Имя роута витрины; страница объявления — то же имя с суффиксом `-item`. */
    public function routeName(): string
    {
        return match ($this) {
            self::Marketplace => 'carter30.marketplace',
            self::YachtSale => 'carter30.yacht-sale',
            self::Skippers => 'competitions.skippers',
            self::Sails => 'competitions.sails',
            self::Crews => 'competitions.crews',
            self::CompetitionYachts => 'competitions.yachts',
        };
    }

    public function itemRouteName(): string
    {
        return $this->routeName().'-item';
    }

    /**
     * Сегмент URL внутри `/competitions`.
     *
     * У досок Carter 30 путь и имя роута расходятся
     * («/carter30/yachts-for-sale» ↔ «carter30.yacht-sale»), поэтому они
     * регистрируются явно и сюда не попадают.
     */
    public function boardPath(): ?string
    {
        return match ($this) {
            self::Skippers => 'skippers',
            self::Sails => 'sails',
            self::Crews => 'crews',
            self::CompetitionYachts => 'yachts',
            default => null,
        };
    }

    // ──────────────────────────────────────────────
    // Состав формы и фильтров
    // ──────────────────────────────────────────────

    /**
     * Максимум фотографий в объявлении.
     *
     * У парусов лимит зависит от вида: предложение — 10 фото, запрос — 5 (ТЗ п. 8.2).
     */
    public function maxPhotos(?AdvertKind $kind = null): int
    {
        return match ($this) {
            self::Skippers => 5,
            self::Sails => $kind === AdvertKind::Request ? 5 : 10,
            default => 10,
        };
    }

    /**
     * Дуальность «предложение / запрос».
     *
     * Пустой список означает одностороннюю доску: экипажи всегда ищут лодку,
     * владельцы яхт — всегда экипаж, колонка `kind` у них пустая.
     *
     * @return list<AdvertKind>
     */
    public function kinds(): array
    {
        return match ($this) {
            self::Skippers, self::Sails => [AdvertKind::Offer, AdvertKind::Request],
            default => [],
        };
    }

    /** Подпись вида под конкретную доску: «Продам или сдам» ≠ «Предлагаю услуги». */
    public function kindLabel(AdvertKind $kind): string
    {
        return match ($this) {
            self::Skippers => $kind === AdvertKind::Offer ? 'Предлагаю услуги' : 'Хочу в экипаж',
            self::Sails => $kind === AdvertKind::Offer ? 'Продам или сдам' : 'Ищу парус',
            default => $kind->label(),
        };
    }

    /** @return array<string, string> value => label вида для Select и фильтров доски. */
    public function kindOptions(): array
    {
        return collect($this->kinds())
            ->mapWithKeys(fn (AdvertKind $kind): array => [$kind->value => $this->kindLabel($kind)])
            ->all();
    }

    /**
     * Нужен ли справочник категорий: «продажа чего угодно» без него не ищется.
     *
     * У парусов справочник — это тип паруса (грот, стаксель, спинакер…), без
     * него доска не фильтруется; строки заводит data-миграция.
     */
    public function usesCategories(): bool
    {
        return $this === self::Marketplace || $this === self::Sails;
    }

    /** Привязывается ли объявление к своей зарегистрированной яхте (обязательно). */
    public function usesYacht(): bool
    {
        return $this === self::YachtSale || $this === self::CompetitionYachts;
    }

    /**
     * «На какую яхту» — необязательная ссылка с тройственной семантикой:
     * яхта из реестра, свободный текст (`yacht_name`) или ничего.
     */
    public function usesYachtReference(): bool
    {
        return $this === self::Skippers || $this === self::Crews;
    }

    /** Позиция в экипаже: рулевой / матрос / любая. */
    public function usesPosition(): bool
    {
        return $this === self::Skippers;
    }

    /** Спортивный разряд (@see SportCategory). */
    public function usesSportCategory(): bool
    {
        return $this === self::Skippers;
    }

    /**
     * Единицы цены на выбор; пустой список — цена просто в рублях.
     *
     * @return list<AdvertPriceUnit>
     */
    public function priceUnits(): array
    {
        return match ($this) {
            self::Skippers => [AdvertPriceUnit::PerHour, AdvertPriceUnit::PerDay],
            self::Sails => [AdvertPriceUnit::Total, AdvertPriceUnit::PerDay],
            default => [],
        };
    }

    /** Залог — только там, где вещь сдают в аренду. */
    public function usesDeposit(): bool
    {
        return $this === self::Sails;
    }

    /** Даты «Когда»: на какой период человек свободен или ищет. */
    public function usesDates(): bool
    {
        return $this === self::Skippers || $this === self::Crews;
    }

    /** Выбор нескольких регат. */
    public function usesRegattas(): bool
    {
        return $this === self::CompetitionYachts;
    }

    /** Подпись второго текстового поля; null — поля нет. */
    public function detailsLabel(): ?string
    {
        return match ($this) {
            self::Crews => 'Описание запроса: какую лодку ищем',
            default => null,
        };
    }

    /** Подпись основного описания. */
    public function descriptionLabel(): string
    {
        return match ($this) {
            self::Crews => 'Описание экипажа',
            default => 'Описание',
        };
    }

    // ──────────────────────────────────────────────
    // Оформление витрины
    // ──────────────────────────────────────────────

    public function metaDescription(): string
    {
        return match ($this) {
            self::Marketplace => 'Объявления о продаже яхтенного оборудования, парусов, такелажа и снаряжения от участников Ассоциации Carter 30',
            self::YachtSale => 'Продажа яхт класса Carter 30: объявления от владельцев с фотографиями и контактами',
            self::Skippers => 'Биржа шкиперов и матросов: предложения услуг рулевых и матросов и запросы на поиск членов экипажа',
            self::Sails => 'Биржа парусов: продажа и аренда гротов, стакселей, спинакеров и другого парусного вооружения',
            self::Crews => 'Экипажи для соревнований: команды без яхты ищут лодку для участия в регатах',
            self::CompetitionYachts => 'Яхты для соревнований: владельцы яхт ищут экипаж для участия в регатах',
        };
    }

    public function heroDescription(): string
    {
        return match ($this) {
            self::Marketplace => 'Оборудование, паруса, такелаж и всё, что нужно на воде — от участников Ассоциации.',
            self::YachtSale => 'Яхты класса Carter 30, выставленные на продажу владельцами.',
            self::Skippers => 'Рулевые и матросы предлагают услуги, а капитаны ищут людей в экипаж.',
            self::Sails => 'Продажа и аренда парусов: гроты, стаксели, спинакеры и штормовое вооружение.',
            self::Crews => 'Готовые экипажи без своей лодки ищут яхту для участия в соревнованиях.',
            self::CompetitionYachts => 'Владельцы яхт набирают экипаж на регаты сезона.',
        };
    }

    public function heroImage(): string
    {
        return match ($this) {
            self::Marketplace, self::YachtSale => 'images/bg/regulations.webp',
            self::Skippers, self::Crews => 'images/bg/teams.webp',
            self::Sails => 'images/bg/competitions.webp',
            self::CompetitionYachts => 'images/bg/yachts.webp',
        };
    }

    /** Подпись кнопки связи с автором. */
    public function contactButtonLabel(): string
    {
        return match ($this) {
            self::Marketplace, self::YachtSale => 'Написать автору',
            default => 'Отправить запрос',
        };
    }

    /** Подпись кнопки подачи объявления на витрине. */
    public function submitButtonLabel(): string
    {
        return match ($this) {
            self::Marketplace, self::YachtSale => 'Разместить объявление',
            default => 'Подать объявление',
        };
    }

    // ──────────────────────────────────────────────
    // Наборы
    // ──────────────────────────────────────────────

    /**
     * Биржи раздела «Соревнования» в порядке ТЗ п. 8.1 — для роутов, меню и sitemap.
     *
     * @return list<self>
     */
    public static function competitionBoards(): array
    {
        return [self::Skippers, self::Sails, self::Crews, self::CompetitionYachts];
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
