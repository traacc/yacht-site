<?php

namespace App\Providers\Faker;

use Faker\Provider\Base;

/**
 * Faker-провайдер для генерации названий яхт.
 *
 * Использование:
 *   $faker->yachtName()              // случайное название любым методом
 *   $faker->classicYachtName()       // классическое мифологическое/морское
 *   $faker->poeticYachtName()        // поэтическое (прилагательное + существительное)
 *   $faker->celestialYachtName()     // небесное/астрономическое
 *   $faker->windYachtName()          // ветровое/стихийное
 */
class YachtNameProvider extends Base
{
    // ─── Классические мифологические / морские имена ───────────────────────

    protected static array $classicNames = [
        'Aphrodite', 'Poseidon', 'Triton', 'Calypso', 'Nereid',
        'Ariadne', 'Cassiopeia', 'Andromeda', 'Persephone', 'Thetis',
        'Atalanta', 'Circe', 'Penelope', 'Selene', 'Artemis',
        'Naiad', 'Galatea', 'Amphitrite', 'Leucothea', 'Daphne',
        'Alcyone', 'Medusa', 'Phaedra', 'Iphigenia', 'Cressida',
        'Lysander', 'Leander', 'Orion', 'Perseus', 'Odysseus',
        'Endymion', 'Proteus', 'Hyperion', 'Helios', 'Zephyr',
        'Peleus', 'Acheron', 'Aeolus', 'Boreas', 'Notus',
    ];

    // ─── Прилагательные для поэтических названий ───────────────────────────

    protected static array $poeticAdjectives = [
        'Golden', 'Silver', 'Crimson', 'Azure', 'Midnight',
        'Ivory', 'Scarlet', 'Sapphire', 'Amber', 'Emerald',
        'Velvet', 'Phantom', 'Mystic', 'Wild', 'Free',
        'Silent', 'Swift', 'Bold', 'Fearless', 'Distant',
        'Endless', 'Eternal', 'Restless', 'Wandering', 'Daring',
        'Radiant', 'Shining', 'Glowing', 'Shimmering', 'Gleaming',
        'Lost', 'Forgotten', 'Hidden', 'Secret', 'Ancient',
        'Mighty', 'Noble', 'Proud', 'Brave', 'True',
    ];

    // ─── Существительные для поэтических названий ──────────────────────────

    protected static array $poeticNouns = [
        'Horizon', 'Mist', 'Dawn', 'Dusk', 'Tide',
        'Wave', 'Crest', 'Shore', 'Depths', 'Passage',
        'Journey', 'Voyage', 'Odyssey', 'Quest', 'Dream',
        'Vision', 'Spirit', 'Soul', 'Heart', 'Star',
        'Moon', 'Sun', 'Sky', 'Wind', 'Storm',
        'Gale', 'Breeze', 'Current', 'Flow', 'Drift',
        'Wing', 'Arrow', 'Compass', 'Anchor', 'Helm',
        'Siren', 'Echo', 'Shadow', 'Whisper', 'Legend',
    ];

    // ─── Небесные / астрономические названия ───────────────────────────────

    protected static array $celestialNames = [
        'Polaris', 'Vega', 'Sirius', 'Rigel', 'Altair',
        'Arcturus', 'Capella', 'Deneb', 'Fomalhaut', 'Procyon',
        'Aldebaran', 'Antares', 'Spica', 'Regulus', 'Canopus',
        'Achernar', 'Hadar', 'Acrux', 'Mimosa', 'Gacrux',
        'Lyra', 'Cygnus', 'Aquila', 'Piscis', 'Corvus',
        'Lupus', 'Ara', 'Corona', 'Crater', 'Columba',
        'Nebula', 'Pulsar', 'Quasar', 'Solstice', 'Equinox',
        'Zenith', 'Nadir', 'Apogee', 'Perigee', 'Azimuth',
    ];

    // ─── Ветровые / стихийные названия ─────────────────────────────────────

    protected static array $windNames = [
        'Mistral', 'Sirocco', 'Tramontane', 'Levante', 'Ponente',
        'Gregale', 'Ostro', 'Libeccio', 'Vendaval', 'Solano',
        'Etesian', 'Bora', 'Meltemi', 'Khamsin', 'Harmattan',
        'Chinook', 'Foehn', 'Scirocco', 'Tramontana', 'Maestrale',
        'Typhoon', 'Cyclone', 'Squall', 'Gust', 'Zephyr',
        'Trades', 'Roaring', 'Howling', 'Tempest', 'Maelstrom',
    ];

    // ─── Числовые суффиксы (опционально добавляются к названию) ────────────

    protected static array $romanNumerals = [
        'II', 'III', 'IV', 'V', 'VI', 'VII',
    ];

    // ───────────────────────────────────────────────────────────────────────

    /**
     * Случайное название яхты (любым из методов).
     */
    public function yachtName(): string
    {
        $method = static::randomElement([
            'classicYachtName',
            'classicYachtName',       // выше вес — чаще встречается
            'poeticYachtName',
            'poeticYachtName',
            'celestialYachtName',
            'windYachtName',
        ]);

        return $this->$method();
    }

    /**
     * Классическое мифологическое / морское название.
     * Иногда добавляет римский номер (Lady Aphrodite III).
     *
     * Примеры: "Calypso", "Lady Nereid", "Aphrodite III"
     */
    public function classicYachtName(): string
    {
        $name = static::randomElement(static::$classicNames);

        return match (static::numberBetween(1, 4)) {
            1       => 'Lady ' . $name,
            2       => $name . ' ' . static::randomElement(static::$romanNumerals),
            default => $name,
        };
    }

    /**
     * Поэтическое название (прилагательное + существительное).
     *
     * Примеры: "Golden Horizon", "Midnight Wave", "The Restless Current"
     */
    public function poeticYachtName(): string
    {
        $adj  = static::randomElement(static::$poeticAdjectives);
        $noun = static::randomElement(static::$poeticNouns);

        $name = "{$adj} {$noun}";

        // Иногда добавляем артикль "The"
        if ($this->generator->boolean(25)) {
            $name = 'The ' . $name;
        }

        return $name;
    }

    /**
     * Небесное / астрономическое название.
     *
     * Примеры: "Polaris", "Lyra Star", "Spirit of Vega"
     */
    public function celestialYachtName(): string
    {
        $name = static::randomElement(static::$celestialNames);

        return match (static::numberBetween(1, 3)) {
            1       => 'Spirit of ' . $name,
            2       => $name . ' Star',
            default => $name,
        };
    }

    /**
     * Ветровое / стихийное название.
     *
     * Примеры: "Mistral", "Wild Tramontane", "Sirocco Wind"
     */
    public function windYachtName(): string
    {
        $name = static::randomElement(static::$windNames);

        return match (static::numberBetween(1, 3)) {
            1       => static::randomElement(static::$poeticAdjectives) . ' ' . $name,
            2       => $name . ' Wind',
            default => $name,
        };
    }
}