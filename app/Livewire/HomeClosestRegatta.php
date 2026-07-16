<?php

namespace App\Livewire;

use App\Models\Regatta;
use App\Services\SettingsService;
use App\Services\WeatherService;
use App\Services\YandexMapService;
use Livewire\Component;

class HomeClosestRegatta extends Component
{
    public function render(WeatherService $weather, YandexMapService $map, SettingsService $settings): \Illuminate\View\View
    {
        $regatta = Regatta::closestUpcomingAndActive();

        $currentWeather = $regatta?->coordinates
            ? $weather->getWeather(
                lat: (float) $regatta->coordinates[0],
                lon: (float) $regatta->coordinates[1],
            )
            : null;

        $temp = null;
        $windSpeed = null;
        $windDirection = null;
        if ($currentWeather && isset($currentWeather['current'])) {
            $temp = $currentWeather['current']['temperature_2m'] ?? null;
            $windSpeed = $currentWeather['current']['wind_speed_10m'] ?? null;
            $windDirection = $currentWeather['current']['wind_direction_10m'] ?? null;
        }
        $mapUrl = $regatta?->coordinates
            ? $map->makeUrl(
                lat: (float) $regatta->coordinates[0],
                lon: (float) $regatta->coordinates[1],
            )
            : null;

        $startDateTime = $regatta?->startDateTime()?->format('Y-m-d\TH:i:s');

        $hasDocuments = $regatta?->documents()
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->exists() ?? false;

        $wind = $windSpeed !== null
            ? round($windSpeed) . ' м/с'
                . ($windDirection !== null ? ', ' . WeatherService::windDirectionLabel($windDirection) : '')
            : null;

        return view('livewire.home-closest-regatta', [
            'regatta'        => $regatta,
            'hasWeather'     => $temp !== null,
            'currentWeather' => $temp . '  ℃',
            'wind'           => $wind,
            'mapUrl'         => $mapUrl,
            'hasDocuments'   => $hasDocuments,
            'startDateTime'  => $startDateTime,
            'lat'            => $regatta?->coordinates ? (float) $regatta->coordinates[0] : null,
            'lon'            => $regatta?->coordinates ? (float) $regatta->coordinates[1] : null,
            'heroMedia'      => $settings->getHeroMedia(),
            'heroViewport'   => $settings->getHeroViewport(),
        ]);
    }
}
