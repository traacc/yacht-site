<?php

namespace App\Livewire;

use App\Models\Regatta;
use App\Services\WeatherService;
use App\Services\YandexMapService;
use Livewire\Component;

class HomeClosestRegatta extends Component
{
    public function render(WeatherService $weather, YandexMapService $map): \Illuminate\View\View
    {
        $regatta = Regatta::closestUpcomingAndActive();

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

        return view('livewire.home-closest-regatta', [
            'regatta'        => $regatta,
            'currentWeather' => $temp . '  ℃',
            'mapUrl'         => $mapUrl,
        ]);
    }
}
