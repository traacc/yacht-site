<?php

namespace App\Providers;

use App\Providers\Faker\YachtNameProvider;
use App\Providers\Faker\RegattaTeamProvider;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Support\ServiceProvider;

class FakerServiceProvider extends ServiceProvider
{
    /**
     * Регистрируем кастомный Faker-провайдер для яхт.
     *
     * После регистрации в config/app.php → 'providers' становятся
     * доступны методы:
     *
     *   $this->faker->yachtName()
     *   $this->faker->classicYachtName()
     *   $this->faker->poeticYachtName()
     *   $this->faker->celestialYachtName()
     *   $this->faker->windYachtName()
     */
    public function register(): void
    {
        $this->app->extend(Generator::class, function (Generator $faker) {
            $faker->addProvider(new YachtNameProvider($faker));
            $faker->addProvider(new RegattaTeamProvider($faker));
            return $faker;
        });
    }
}