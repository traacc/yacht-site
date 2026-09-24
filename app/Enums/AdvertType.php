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
            self::Skippers => [AdvertKind::Offer, AdvertKind::Request],
            self::Sails => [AdvertKind::Sale, AdvertKind::Rent, AdvertKind::Request],
            default => [],
        };
    }

    /** Подпись вида под конкретную доску: «Продам» ≠ «Предлагаю услуги». */
    public function kindLabel(AdvertKind $kind): string
    {
        return match (true) {
            $this === self::Skippers && $kind === AdvertKind::Offer => 'Предлагаю услуги',
            $this === self::Skippers && $kind === AdvertKind::Request => 'Ищу в экипаж',
            $this === self::Sails && $kind === AdvertKind::Sale => 'Продам',
            $this === self::Sails && $kind === AdvertKind::Rent => 'Сдам в аренду',
            $this === self::Sails && $kind === AdvertKind::Request => 'Ищу парус',
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
     *
     * На бирже шкиперов лодка есть только у капитана, который ищет людей в
     * экипаж: рулевой или матрос, предлагающий услуги, приходит без неё.
     */
    public function usesYachtReference(?AdvertKind $kind = null): bool
    {
        return match ($this) {
            self::Skippers => $kind === AdvertKind::Request,
            self::Crews => true,
            default => false,
        };
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
     * У парусов единицу задаёт вид: продажа — за всё, аренда — в сутки;
     * выбирать приходится только в запросе, где ищут и купить, и арендовать.
     *
     * @return list<AdvertPriceUnit>
     */
    public function priceUnits(?AdvertKind $kind = null): array
    {
        return match ($this) {
            self::Skippers => [AdvertPriceUnit::PerHour, AdvertPriceUnit::PerDay],
            self::Sails => match ($kind) {
                AdvertKind::Sale => [AdvertPriceUnit::Total],
                AdvertKind::Rent => [AdvertPriceUnit::PerDay],
                default => [AdvertPriceUnit::Total, AdvertPriceUnit::PerDay],
            },
            default => [],
        };
    }

    /** Единица цены, если вид оставляет ровно одну, — тогда её не спрашиваем. */
    public function fixedPriceUnit(?AdvertKind $kind = null): ?AdvertPriceUnit
    {
        $units = $this->priceUnits($kind);

        return count($units) === 1 ? $units[0] : null;
    }

    /** Залог — только там, где вещь сдают в аренду; при продаже паруса его нет. */
    public function usesDeposit(?AdvertKind $kind = null): bool
    {
        return $this === self::Sails && $kind !== AdvertKind::Sale;
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
    // Подписи формы: товар ≠ услуга
    // ──────────────────────────────────────────────

    /** Пример заголовка в форме подачи. */
    public function titlePlaceholder(?AdvertKind $kind = null): string
    {
        return match (true) {
            $this === self::Skippers && $kind === AdvertKind::Request => 'Например: Ищу матроса на летний сезон',
            $this === self::Skippers => 'Например: Рулевой, КМС, 10 лет в гонках',
            $this === self::Crews => 'Например: Экипаж из 6 человек ищет лодку на сезон',
            $this === self::CompetitionYachts => 'Например: Набираю экипаж на Кубок Carter 30',
            $this === self::YachtSale => 'Например: Carter 30, готова к сезону',
            $this === self::Sails && $kind === AdvertKind::Request => 'Например: Ищу грот на Carter 30',
            default => 'Например: Комплект парусов Carter 30',
        };
    }

    /** Подсказка в описании: у услуги нет «состояния и комплектности». */
    public function descriptionPlaceholder(?AdvertKind $kind = null): string
    {
        return match (true) {
            $this === self::Skippers && $kind === AdvertKind::Request => 'Кого ищете, на какие регаты или походы, требования к опыту, условия',
            $this === self::Skippers => 'Опыт, регаты и результаты, квалификация и права, на каких лодках ходили',
            $this === self::Crews => 'Состав экипажа, опыт, результаты на регатах',
            $this === self::CompetitionYachts => 'Какой экипаж нужен, условия участия, требования к опыту',
            $this === self::Sails && $kind === AdvertKind::Request => 'Какой парус ищете: тип, размеры, для какой лодки',
            default => 'Опишите товар: состояние, комплектность, причину продажи',
        };
    }

    /** Заголовок секции с ценой. */
    public function priceSectionLabel(): string
    {
        return match ($this) {
            self::Skippers, self::Crews => 'Стоимость, даты и город',
            default => 'Цена и местонахождение',
        };
    }

    public function priceLabel(?AdvertKind $kind = null): string
    {
        return match (true) {
            $this === self::Skippers && $kind === AdvertKind::Request => 'Оплата, ₽',
            $this === self::Skippers, $this === self::Crews => 'Стоимость услуг, ₽',
            default => 'Цена, ₽',
        };
    }

    /**
     * Подписи дат «Когда».
     *
     * @return array{0: string, 1: string} [с, по]
     */
    public function dateLabels(?AdvertKind $kind = null): array
    {
        return match (true) {
            $this === self::Skippers && $kind === AdvertKind::Request => ['Нужен с', 'Нужен по'],
            $this === self::Crews => ['Свободны с', 'Свободны по'],
            default => ['Свободен с', 'Свободен по'],
        };
    }

    public function positionLabel(?AdvertKind $kind = null): string
    {
        return $this === self::Skippers && $kind === AdvertKind::Request ? 'Кого ищете' : 'Позиция';
    }

    public function sportCategoryLabel(?AdvertKind $kind = null): string
    {
        return $this === self::Skippers && $kind === AdvertKind::Request ? 'Разряд не ниже' : 'Спортивный разряд';
    }

    /** Что снимать; null — подсказки нет. */
    public function photosHint(?AdvertKind $kind = null): ?string
    {
        return match (true) {
            $this === self::Skippers && $kind === AdvertKind::Request => 'Фото яхты и экипажа.',
            $this === self::Skippers => 'Ваше фото, фото с регат.',
            default => null,
        };
    }

    /**
     * Подпись закрытого автором объявления (статус Sold).
     *
     * «Продано» уместно только там, где вещь продают; услуги, запросы и аренда
     * просто становятся неактуальными.
     */
    public function closedLabel(?AdvertKind $kind = null): string
    {
        return match (true) {
            $this === self::Marketplace, $this === self::YachtSale => 'Продано',
            $this === self::Sails && $kind === AdvertKind::Sale => 'Продано',
            default => 'Неактуально',
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
