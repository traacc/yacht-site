<?php

namespace App\Livewire;

use App\Models\Regatta;
use App\Services\WeatherService;
use App\Services\YandexMapService;
use Livewire\Component;

class HomeRegattaTimer extends Component
{
    public function render(WeatherService $weather, YandexMapService $map): \Illuminate\View\View
    {
        $regatta = Regatta::closestUpcoming();

        $currentWeather = $regatta?->coordinates
            ? $weather->getWeather(
                lat: (float) $regatta->coordinates[0],
                lon: (float) $regatta->coordinates[1],
            )
            : null;

        $temp = $currentWeather['current']['temperature_2m'] ?? '—';

        $mapUrl = $regatta?->coordinates
            ? $map->makeUrl(
                lat: (float) $regatta->coordinates[0],
                lon: (float) $regatta->coordinates[1],
            )
            : null;

        // Дататаргет для таймера: date_start + time_start (по умолчанию 12:00)
        $startDateTime = $regatta?->startDateTime()?->format('Y-m-d\TH:i:s');

        return view('livewire.home-regatta-timer', [
            'regatta'         => $regatta,
            'currentWeather'  => $temp . ' ℃',
            'mapUrl'          => $mapUrl,
            'startDateTime'   => $startDateTime,
        ]);
    }
}
