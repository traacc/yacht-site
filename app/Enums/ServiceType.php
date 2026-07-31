<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\ServiceSubject;
use App\Models\Tour;
use App\Support\Plural;
use Illuminate\Database\Eloquent\Model;

/**
 * Подраздел раздела «Услуги» (ТЗ 3-го этапа, п. 7).
 *
 * Кейс несёт полный конфиг подраздела: где его страница, принимает ли он форму
 * заявки, какие поля у этой формы и под каким префиксом лежат настройки
 * лендинга. Так же, как AdvertType описывает доски объявлений — одна модель
 * ServiceRequest на все подразделы, разница только в типе.
 *
 * ForeignRegatta и GiftCertificate заведены заранее: страниц и форм у них пока
 * нет (isPublished/acceptsRequests → false), но модель, админка и письма их уже
 * поддержат.
 */
enum ServiceType: string
{
    case YachtRental = 'yacht_rental';
    case FleetRental = 'fleet_rental';
    case Event = 'event';
    case Training = 'training';
    case Tour = 'tour';
    case ForeignRegatta = 'foreign_regatta';
    case GiftCertificate = 'gift_certificate';

    public function label(): string
    {
        return match ($this) {
            self::YachtRental => 'Аренда яхт',
            self::FleetRental => 'Аренда флота',
            self::Event => 'Проведение мероприятий',
            self::Training => 'Обучение судовождению',
            self::Tour => 'Яхтенные путешествия и походы',
            self::ForeignRegatta => 'Регаты за рубежом',
            self::GiftCertificate => 'Подарочные сертификаты',
        };
    }

    /** Текст карточки на хабе «Услуги». */
    public function shortDescription(): string
    {
        return match ($this) {
            self::YachtRental => 'Аренда яхты на день или на несколько дней: календарь занятости, цены и запрос владельцу.',
            self::FleetRental => 'Подбор нескольких яхт на нужный диапазон дат — для корпоративной регаты, тренировки или съёмок.',
            self::Event => 'Организация мероприятия на воде: флот, площадки и программа под ваш формат.',
            self::Training => 'Обучение судовождению с нуля и подготовка к экзаменам на права IYT и ГИМС.',
            self::Tour => 'Многодневные походы и путешествия под парусом по маршрутам ассоциации.',
            self::ForeignRegatta => 'Участие в зарубежных регатах: заявка, аренда яхты и сопровождение.',
            self::GiftCertificate => 'Подарочный сертификат на выход в море, обучение или аренду яхты.',
        };
    }

    /** Heroicon для карточки услуги и колокольчика в админке. */
    public function icon(): string
    {
        return match ($this) {
            self::YachtRental, self::FleetRental => 'heroicon-o-lifebuoy',
            self::Event => 'heroicon-o-sparkles',
            self::Training => 'heroicon-o-academic-cap',
            self::Tour => 'heroicon-o-map',
            self::ForeignRegatta => 'heroicon-o-globe-alt',
            self::GiftCertificate => 'heroicon-o-gift',
        };
    }

    /**
     * Имя маршрута публичной страницы; null — подраздел ещё не реализован.
     *
     * У аренды яхт своя, ранее сделанная страница — каталог /yachts с
     * календарём занятости; в разделе «Услуги» на неё ведёт ссылка.
     */
    public function routeName(): ?string
    {
        return match ($this) {
            self::YachtRental => 'yachts',
            self::FleetRental => 'services.fleet-rental',
            self::Event => 'services.events',
            self::Training => 'services.training',
            self::Tour => 'services.tours',
            self::ForeignRegatta, self::GiftCertificate => null,
        };
    }

    public function url(): ?string
    {
        $route = $this->routeName();

        return $route === null ? null : route($route);
    }

    /** Есть ли у подраздела публичная страница (карточка на хабе кликабельна). */
    public function isPublished(): bool
    {
        return $this->routeName() !== null;
    }

    /**
     * Принимает ли подраздел общую форму ServiceRequest.
     *
     * Аренда яхт исключена: у неё собственный поток YachtRentalRequest,
     * завязанный на календарь занятости конкретной яхты.
     */
    public function acceptsRequests(): bool
    {
        return match ($this) {
            self::FleetRental, self::Event, self::Training, self::Tour => true,
            default => false,
        };
    }

    /**
     * Модель, к которой можно привязать заявку подраздела.
     *
     * Класс берётся отсюда, а не из запроса: morph-класс, пришедший из формы,
     * позволил бы привязать заявку к произвольной модели. С сайта приходит
     * только id — @see \App\Services\ServiceSubjectResolver.
     *
     * @return class-string<Model&ServiceSubject>|null
     */
    public function subjectModel(): ?string
    {
        return match ($this) {
            self::Tour => Tour::class,
            // Зарубежные регаты и сертификаты — следующая итерация.
            default => null,
        };
    }

    /** Спрашиваем ли в форме диапазон дат. */
    public function usesDateRange(): bool
    {
        return match ($this) {
            self::FleetRental, self::Event, self::Tour, self::ForeignRegatta => true,
            default => false,
        };
    }

    /** Обязателен ли диапазон дат: без него подбор флота бессмыслен. */
    public function requiresDateRange(): bool
    {
        return $this === self::FleetRental;
    }

    public function usesQuantity(): bool
    {
        return $this !== self::ForeignRegatta;
    }

    public function quantityLabel(): string
    {
        return match ($this) {
            self::FleetRental => 'Количество яхт',
            self::Event => 'Количество гостей',
            self::GiftCertificate => 'Количество сертификатов',
            default => 'Количество человек',
        };
    }

    /** Количество словами: «4 яхты», «2 человека» — для темы письма и колокольчика. */
    public function quantityWithUnit(int $quantity): string
    {
        return match ($this) {
            self::FleetRental => Plural::with($quantity, 'яхта', 'яхты', 'яхт'),
            self::Event => Plural::with($quantity, 'гость', 'гостя', 'гостей'),
            self::GiftCertificate => Plural::with($quantity, 'сертификат', 'сертификата', 'сертификатов'),
            default => Plural::with($quantity, 'человек', 'человека', 'человек'),
        };
    }

    /** По ТЗ форма обучения обязательно собирает ФИО, телефон и email. */
    public function requiresEmail(): bool
    {
        return $this === self::Training;
    }

    /** Префикс ключей SettingsService для лендинга подраздела. */
    public function settingsPrefix(): string
    {
        return 'services.'.$this->value;
    }

    /**
     * Специфичные поля формы — уходят в колонку payload.
     *
     * Порядок ключей задаёт порядок полей в модалке, в письме и в админке.
     *
     * @return array<string, array{
     *     label: string,
     *     type: 'text'|'textarea'|'select'|'checkbox',
     *     options?: array<string, string>,
     *     required?: bool,
     *     placeholder?: string,
     * }>
     */
    public function payloadFields(): array
    {
        return match ($this) {
            self::FleetRental => [
                'region' => [
                    'label' => 'Регион или акватория',
                    'type' => 'text',
                    'placeholder' => 'Например: Пироговское водохранилище',
                ],
                'event_purpose' => [
                    'label' => 'Цель аренды',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        'corporate' => 'Корпоратив',
                        'corporate_regatta' => 'Корпоративная регата',
                        'training' => 'Тренировка',
                        'filming' => 'Съёмки',
                        'other' => 'Другое',
                    ],
                ],
                'crew_needed' => [
                    'label' => 'Экипаж',
                    'type' => 'select',
                    'options' => [
                        'with_skipper' => 'Со шкипером',
                        'bareboat' => 'Без шкипера',
                        'full_crew' => 'Нужен полный экипаж',
                    ],
                ],
                'budget' => [
                    'label' => 'Ориентировочный бюджет',
                    'type' => 'text',
                    'placeholder' => 'Необязательно',
                ],
            ],

            self::Event => [
                'event_format' => [
                    'label' => 'Формат мероприятия',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        'corporate' => 'Корпоратив',
                        'corporate_regatta' => 'Корпоративная регата',
                        'teambuilding' => 'Тимбилдинг',
                        'birthday' => 'День рождения',
                        'wedding' => 'Свадьба',
                        'other' => 'Другое',
                    ],
                ],
                'venue' => [
                    'label' => 'Предпочтительная площадка',
                    'type' => 'text',
                    'placeholder' => 'Необязательно',
                ],
                'needs_fleet' => [
                    'label' => 'Нужен флот (выход в море для гостей)',
                    'type' => 'checkbox',
                ],
                'budget' => [
                    'label' => 'Ориентировочный бюджет',
                    'type' => 'text',
                    'placeholder' => 'Необязательно',
                ],
            ],

            self::Training => [
                'study_goal' => [
                    'label' => 'Чему хотите научиться',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        'from_scratch' => 'С нуля',
                        'advanced' => 'Повышение квалификации',
                        'exam' => 'Подготовка к экзамену',
                        'practice' => 'Шкиперская практика',
                    ],
                ],
                'license' => [
                    'label' => 'Права',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        'iyt' => 'IYT',
                        'gims' => 'ГИМС',
                        'none' => 'Прав нет',
                        'undecided' => 'Ещё не определился',
                    ],
                ],
            ],

            self::Tour => [
                // Прямо соответствует ценам похода: за место и за каюту.
                'accommodation' => [
                    'label' => 'Размещение',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        'seat' => 'Место в каюте',
                        'cabin' => 'Каюта целиком',
                        'whole_yacht' => 'Яхта целиком',
                    ],
                ],
                'experience' => [
                    'label' => 'Опыт хождения под парусом',
                    'type' => 'select',
                    'options' => [
                        'none' => 'Нет опыта',
                        'beginner' => 'Начинающий',
                        'crew' => 'Уверенный матрос',
                        'skipper' => 'Есть права шкипера',
                    ],
                ],
                'needs_equipment' => [
                    'label' => 'Нужна аренда снаряжения (спальник, шторм-костюм)',
                    'type' => 'checkbox',
                ],
                'departure_city' => [
                    'label' => 'Город выезда',
                    'type' => 'text',
                    'placeholder' => 'Необязательно',
                ],
            ],

            // Подразделы следующей итерации: поля появятся вместе со страницами.
            self::YachtRental, self::ForeignRegatta, self::GiftCertificate => [],
        };
    }

    /**
     * Правила валидации ключей payload.* — выводятся из payloadFields().
     *
     * @return array<string, list<string>>
     */
    public function payloadRules(): array
    {
        $rules = [];

        foreach ($this->payloadFields() as $key => $field) {
            $required = ($field['required'] ?? false) ? 'required' : 'nullable';

            $rules['payload.'.$key] = match ($field['type']) {
                'select' => [$required, 'string', 'in:'.implode(',', array_keys($field['options'] ?? []))],
                'checkbox' => ['nullable', 'boolean'],
                'textarea' => [$required, 'string', 'max:2000'],
                default => [$required, 'string', 'max:255'],
            };
        }

        return $rules;
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Подразделы с готовой страницей — для хаба, меню и sitemap.
     *
     * @return list<self>
     */
    public static function published(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => $case->isPublished(),
        ));
    }
}
