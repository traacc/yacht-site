<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\ServiceOptionProvider;
use App\Contracts\ServiceSubject;
use App\Models\ForeignRegatta;
use App\Models\GiftCertificate;
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
 * YachtRental — единственный подраздел без общей формы: у аренды яхт свой,
 * ранее сделанный поток YachtRentalRequest, завязанный на календарь занятости.
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
            self::Training => 'Обучение',
            self::Tour => 'Яхтенные путешествия и походы',
            self::ForeignRegatta => 'Регаты за рубежом',
            self::GiftCertificate => 'Подарочные сертификаты',
        };
    }

    /** Текст карточки на хабе «Услуги». */
    public function shortDescription(): string
    {
        return match ($this) {
            self::YachtRental => 'Аренда яхты на день или на несколько дней: поиск по датам, цены и бронирование онлайн.',
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
     * У аренды яхт своя витрина бронирования с поиском по датам; каталог
     * /yachts остаётся реестром флота ассоциации.
     */
    public function routeName(): ?string
    {
        return match ($this) {
            self::YachtRental => 'services.yacht-rental',
            self::FleetRental => 'services.fleet-rental',
            self::Event => 'services.events',
            self::Training => 'services.training',
            self::Tour => 'services.tours',
            self::ForeignRegatta => 'services.foreign-regattas',
            self::GiftCertificate => 'services.gift-certificates',
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
            self::FleetRental, self::Event, self::Training,
            self::Tour, self::ForeignRegatta, self::GiftCertificate => true,
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
            self::ForeignRegatta => ForeignRegatta::class,
            self::GiftCertificate => GiftCertificate::class,
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

    /**
     * По ТЗ форма обучения обязательно собирает ФИО, телефон и email.
     *
     * Сертификат тоже: электронный бланк отправляют на почту, без неё заказ
     * нечем исполнить.
     */
    public function requiresEmail(): bool
    {
        return $this === self::Training || $this === self::GiftCertificate;
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
     * Варианты части полей зависят от объекта заявки: у зарубежной регаты это
     * предлагаемые ею варианты участия и её свободные яхты. Такие поля
     * объявляются с пустым `options`, а заполняет их сам объект
     * (@see \App\Contracts\ServiceOptionProvider).
     *
     * `visible_when` — поле показывается и требуется, только когда другое поле
     * приняло указанное значение (яхту выбирают лишь при участии «яхта целиком»).
     *
     * @param  ServiceSubject|null  $subject  объект заявки, если она подаётся на него
     * @return array<string, array{
     *     label: string,
     *     type: 'text'|'textarea'|'select'|'checkbox',
     *     options?: array<string, string>,
     *     required?: bool,
     *     placeholder?: string,
     *     visible_when?: array{0: string, 1: string},
     * }>
     */
    public function payloadFields(?ServiceSubject $subject = null): array
    {
        $fields = $this->declaredPayloadFields();

        if ($subject instanceof ServiceOptionProvider) {
            foreach ($subject->serviceOptions() as $key => $options) {
                if (array_key_exists($key, $fields)) {
                    $fields[$key]['options'] = $options;
                }
            }
        }

        return $fields;
    }

    /**
     * Поля, которые реально показываются в форме, — они же и валидируются.
     *
     * От payloadFields() отличается тем, что выбрасывает select без вариантов:
     * на общей форме подраздела объекта нет и списка яхт взяться неоткуда, а у
     * конкретной регаты весь чартер мог оказаться занят. В payloadFields такое
     * поле остаётся — иначе уже поданная заявка потеряла бы подпись значения.
     *
     * @return array<string, array<string, mixed>>
     */
    public function formFields(?ServiceSubject $subject = null): array
    {
        return array_filter(
            $this->payloadFields($subject),
            fn (array $field): bool => $field['type'] !== 'select' || ($field['options'] ?? []) !== [],
        );
    }

    /**
     * Поля подраздела до подстановки вариантов объекта.
     *
     * @return array<string, array<string, mixed>>
     */
    private function declaredPayloadFields(): array
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

            self::ForeignRegatta => [
                // Варианты обоих полей приходят от самой регаты: она объявляет,
                // что предлагает, и какие из её яхт ещё свободны.
                'participation' => [
                    'label' => 'Вариант участия',
                    'type' => 'select',
                    'required' => true,
                    'options' => ParticipationOption::options(),
                ],
                'charter_yacht' => [
                    'label' => 'Яхта',
                    'type' => 'select',
                    'options' => [],
                    'visible_when' => ['participation', ParticipationOption::Yacht->value],
                ],
            ],

            self::GiftCertificate => [
                // Варианты номинала приходят от самого сертификата: у него свои
                // границы и шаг. У сертификата с фиксированной ценой вариантов
                // нет — и поле само пропадает из формы (@see formFields()).
                'nominal' => [
                    'label' => 'Номинал сертификата',
                    'type' => 'select',
                    'required' => true,
                    'options' => [],
                ],
                'delivery' => [
                    'label' => 'Как передать сертификат',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        'email' => 'Электронный — PDF на почту',
                        'printed' => 'Печатный бланк — забрать в офисе',
                    ],
                ],
                'recipient_name' => [
                    'label' => 'Имя получателя',
                    'type' => 'text',
                    'placeholder' => 'Кому вручается, необязательно',
                ],
                'greeting' => [
                    'label' => 'Пожелание на сертификате',
                    'type' => 'textarea',
                    'placeholder' => 'Необязательно',
                ],
            ],

            // У аренды яхт свой поток заявок — общей формы у неё нет.
            self::YachtRental => [],
        };
    }

    /**
     * Правила валидации ключей payload.* — выводятся из payloadFields().
     *
     * @param  ServiceSubject|null  $subject  объект заявки: от него зависят варианты части полей
     * @return array<string, list<string>>
     */
    public function payloadRules(?ServiceSubject $subject = null): array
    {
        $rules = [];

        foreach ($this->formFields($subject) as $key => $field) {
            // Поле, зависящее от другого, обязательно только вместе с ним:
            // яхту спрашиваем лишь при участии «яхта целиком».
            $required = match (true) {
                isset($field['visible_when']) => 'required_if:payload.'.$field['visible_when'][0].','.$field['visible_when'][1],
                ($field['required'] ?? false) => 'required',
                default => 'nullable',
            };

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
