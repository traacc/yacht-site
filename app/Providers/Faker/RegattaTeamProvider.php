<?php

namespace App\Providers\Faker;

use Faker\Provider\Base;

/**
 * Faker-провайдер для генерации названий команд регаты.
 *
 * Регистрация в AppServiceProvider:
 *
 *   $this->app->extend(\Faker\Generator::class, function ($faker) {
 *       $faker->addProvider(new \App\Faker\RegattaTeamProvider($faker));
 *       return $faker;
 *   });
 *
 * Использование:
 *   fake()->regattaTeamName();              // «Северный Ветер»
 *   fake()->regattaTeamName('en');          // «Northern Wind»
 *   fake()->regattaTeamName('ru', 'noun');  // «Альбатрос»
 */
class RegattaTeamProvider extends Base
{
    // -------------------------------------------------------------------------
    // Русские словари
    // -------------------------------------------------------------------------

    /** Прилагательные (согласуются с мужским существительным) */
    private static array $adjectives_ru = [
        'Северный', 'Южный', 'Восточный', 'Западный',
        'Морской',  'Штормовой', 'Солёный',  'Бурный',
        'Свободный', 'Дерзкий', 'Стремительный', 'Бесстрашный',
        'Синий',    'Лазурный', 'Вечный',   'Дальний',
        'Белый',    'Серебряный', 'Золотой', 'Тёмный',
        'Полярный', 'Тропический', 'Атлантический', 'Тихоокеанский',
    ];

    /** Прилагательные (согласуются с женским существительным) */
    private static array $adjectives_ru_f = [
        'Северная', 'Южная', 'Восточная', 'Западная',
        'Морская',  'Штормовая', 'Солёная',  'Бурная',
        'Свободная', 'Дерзкая', 'Стремительная', 'Бесстрашная',
        'Синяя',    'Лазурная', 'Вечная',   'Дальняя',
        'Белая',    'Серебряная', 'Золотая', 'Тёмная',
        'Полярная', 'Тропическая', 'Атлантическая', 'Тихоокеанская',
    ];

    /** Существительные мужского рода */
    private static array $nouns_ru_m = [
        'Ветер',   'Альбатрос', 'Горизонт', 'Прибой',
        'Бриз',    'Шторм',     'Форштевень', 'Галс',
        'Бакштаг', 'Гарпун',    'Компас',   'Маяк',
        'Кракен',  'Дельфин',   'Кит',      'Нептун',
        'Корвет',  'Фрегат',    'Бриган',   'Флибустьер',
        'Прибой',  'Буревестник',
    ];

    /** Существительные женского рода */
    private static array $nouns_ru_f = [
        'Волна',   'Звезда',   'Комета',   'Акула',
        'Рифа',    'Удача',    'Свобода',  'Победа',
        'Гроза',   'Сирена',   'Нереида',  'Афродита',
        'Каравелла', 'Галера', 'Шхуна',   'Бригантина',
        'Вахта',   'Рында',    'Корма',    'Гавань',
        'Навигация', 'Регата',
    ];

    /** Одиночные существительные-названия (без прилагательного) */
    private static array $nouns_ru_single = [
        'Альбатрос', 'Буревестник', 'Кракен',   'Посейдон',
        'Нептун',    'Горизонт',    'Бриз',     'Шторм',
        'Прибой',    'Компас',      'Маяк',     'Тритон',
        'Дельфин',   'Кит',         'Корвет',   'Фрегат',
        'Галеон',    'Каравелла',   'Бригантина', 'Шхуна',
        'Фортуна',   'Аврора',      'Виктория', 'Эскадра',
        'Навигатор', 'Рулевой',     'Боцман',   'Адмирал',
        'Гарпун',    'Якорь',
    ];

    // -------------------------------------------------------------------------
    // Английские словари
    // -------------------------------------------------------------------------

    private static array $adjectives_en = [
        'Northern', 'Southern', 'Eastern', 'Western',
        'Sea',      'Stormy',   'Salty',   'Wild',
        'Free',     'Bold',     'Swift',   'Fearless',
        'Blue',     'Azure',    'Eternal', 'Distant',
        'White',    'Silver',   'Golden',  'Dark',
        'Polar',    'Tropical', 'Atlantic', 'Pacific',
        'Iron',     'Crimson',  'Midnight', 'Ancient',
    ];

    private static array $nouns_en = [
        'Wind',      'Albatross', 'Horizon', 'Surge',
        'Breeze',    'Storm',     'Compass', 'Lighthouse',
        'Kraken',    'Dolphin',   'Whale',   'Neptune',
        'Corvette',  'Frigate',   'Corsair', 'Buccaneer',
        'Wave',      'Star',      'Comet',   'Shark',
        'Reef',      'Fortune',   'Freedom', 'Victory',
        'Thunder',   'Siren',     'Nereid',  'Triton',
        'Galleon',   'Caravel',   'Brigantine', 'Schooner',
        'Anchor',    'Harpoon',   'Admiral', 'Navigator',
    ];

    private static array $nouns_en_single = [
        'Albatross', 'Petrel',    'Kraken',   'Poseidon',
        'Neptune',   'Horizon',   'Breeze',   'Tempest',
        'Compass',   'Lighthouse', 'Triton',  'Dolphin',
        'Whale',     'Corvette',  'Frigate',  'Galleon',
        'Caravel',   'Brigantine', 'Schooner', 'Fortune',
        'Aurora',    'Victoria',  'Vendetta', 'Vanguard',
        'Navigator', 'Helmsman',  'Boatswain', 'Admiral',
        'Harpoon',   'Anchor',
    ];

    // -------------------------------------------------------------------------
    // Шаблоны
    // -------------------------------------------------------------------------

    /** Функции-генераторы по типу шаблона (RU) */
    private array $patterns_ru;

    /** Функции-генераторы по типу шаблона (EN) */
    private array $patterns_en;

    public function __construct(\Faker\Generator $generator)
    {
        parent::__construct($generator);

        $this->patterns_ru = [
            // «Северный Ветер» — прил. + сущ. м.р.
            'adjNounM' => function (): string {
                $adj  = static::randomElement(static::$adjectives_ru);
                $noun = static::randomElement(static::$nouns_ru_m);
                return "$adj $noun";
            },
            // «Синяя Волна» — прил. + сущ. ж.р.
            'adjNounF' => function (): string {
                $adj  = static::randomElement(static::$adjectives_ru_f);
                $noun = static::randomElement(static::$nouns_ru_f);
                return "$adj $noun";
            },
            // «Альбатрос» — одно существительное
            'noun' => function (): string {
                return static::randomElement(static::$nouns_ru_single);
            },
            // «Команда Нептун»
            'team' => function (): string {
                $noun = static::randomElement(static::$nouns_ru_single);
                return "Команда {$noun}";
            },
        ];

        $this->patterns_en = [
            'adjNoun' => function (): string {
                $adj  = static::randomElement(static::$adjectives_en);
                $noun = static::randomElement(static::$nouns_en);
                return "$adj $noun";
            },
            'noun' => function (): string {
                return static::randomElement(static::$nouns_en_single);
            },
            'team' => function (): string {
                $noun = static::randomElement(static::$nouns_en_single);
                return "Team $noun";
            },
            'theNoun' => function (): string {
                $noun = static::randomElement(static::$nouns_en_single);
                return "The $noun";
            },
        ];
    }

    // -------------------------------------------------------------------------
    // Публичный метод — точка входа
    // -------------------------------------------------------------------------

    /**
     * Генерирует название команды регаты.
     *
     * @param string      $locale  'ru' или 'en'
     * @param string|null $pattern Конкретный шаблон (null = случайный):
     *                             RU: 'adjNounM', 'adjNounF', 'noun', 'team'
     *                             EN: 'adjNoun', 'noun', 'team', 'theNoun'
     */
    public function regattaTeamName(string $locale = 'ru', ?string $pattern = null): string
    {
        $patterns = match ($locale) {
            'en'    => $this->patterns_en,
            default => $this->patterns_ru,
        };

        if ($pattern !== null && isset($patterns[$pattern])) {
            return ($patterns[$pattern])();
        }

        $key = static::randomElement(array_keys($patterns));

        return ($patterns[$key])();
    }
}