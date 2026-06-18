<?php

namespace App\Livewire;

use App\Models\Regatta;
use App\Services\WeatherService;
use App\Services\YandexMapService;
use Livewire\Component;

use DateTime;

class HomeRegattaTimer extends Component
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

        $temp = '—';
        $windSpeed = null;
        $windDirection = null;
        if ($currentWeather && isset($currentWeather['hourly'])) {
            $times = $currentWeather['hourly']['time'];
            $hourly = array_combine($times, $currentWeather['hourly']['temperature_2m']);
            $windSpeeds = isset($currentWeather['hourly']['wind_speed_10m'])
                ? array_combine($times, $currentWeather['hourly']['wind_speed_10m'])
                : [];
            $windDirections = isset($currentWeather['hourly']['wind_direction_10m'])
                ? array_combine($times, $currentWeather['hourly']['wind_direction_10m'])
                : [];

            $date = $regatta?->date_start;
            $datetime = (new DateTime($date))
                ->setTime(12, 0)
                ->format('Y-m-d\TH:i');
            $temp = $hourly[$datetime] ?? '—';
            $windSpeed = $windSpeeds[$datetime] ?? null;
            $windDirection = $windDirections[$datetime] ?? null;
        }
        $mapUrl = $regatta?->coordinates
            ? $map->makeUrl(
                lat: (float) $regatta->coordinates[0],
                lon: (float) $regatta->coordinates[1],
            )
            : null;

        // Дататаргет для таймера: date_start + time_start (по умолчанию 12:00)
        $startDateTime = $regatta?->startDateTime()?->format('Y-m-d\TH:i:s');

        $hasDocuments = $regatta?->documents()
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->exists() ?? false;

        $wind = $windSpeed !== null
            ? round($windSpeed) . ' м/с'
                . ($windDirection !== null ? ', ' . WeatherService::windDirectionLabel($windDirection) : '')
            : null;

        return view('livewire.home-regatta-timer', [
            'regatta'         => $regatta,
            'currentWeather'  => $temp . ' ℃',
            'wind'            => $wind,
            'mapUrl'          => $mapUrl,
            'startDateTime'   => $startDateTime,
            'hasDocuments'    => $hasDocuments,
        ]);
    }
}
