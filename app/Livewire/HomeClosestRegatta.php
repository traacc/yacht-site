<?php

namespace App\Livewire;

use App\Models\Regatta;
use App\Services\WeatherService;
use App\Services\YandexMapService;
use Livewire\Component;

use DateTime;

use Illuminate\Support\Facades\Log;

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

        $temp = '—';
        if ($currentWeather && isset($currentWeather['hourly'])) {
            $hourly = array_combine(
                $currentWeather['hourly']['time'],
                $currentWeather['hourly']['temperature_2m']
            );

            $date = $regatta?->date_start;
            $datetime = (new DateTime($date))
                ->setTime(12, 0)
                ->format('Y-m-d\TH:i');

            $temp = $hourly[$datetime] ?? '—';
        }
        $mapUrl = $regatta?->coordinates
            ? $map->makeUrl(
                lat: (float) $regatta->coordinates[0],
                lon: (float) $regatta->coordinates[1],
            )
            : null;

        $hasDocuments = $regatta?->documents()
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->exists() ?? false;

        return view('livewire.home-closest-regatta', [
            'regatta'        => $regatta,
            'currentWeather' => $temp . '  ℃',
            'mapUrl'         => $mapUrl,
            'hasDocuments'   => $hasDocuments,
        ]);
    }
}
