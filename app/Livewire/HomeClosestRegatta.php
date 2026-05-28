<?php

namespace App\Livewire;

use App\Models\Regatta;
use App\Services\WeatherService;
use Livewire\Component;

class HomeClosestRegatta extends Component
{
    public function render(WeatherService $weather): \Illuminate\View\View
    {
        $regatta = Regatta::closestUpcoming();

        $currentWeather = $regatta?->coordinates
            ? $weather->getWeather(
                lat: (float) $regatta->coordinates[0],
                lon: (float) $regatta->coordinates[1],
            )
            : null;
            
        $temp = $currentWeather['current']['temperature_2m'] ?? '—';

        return view('livewire.home-closest-regatta', [
            'regatta'        => $regatta,
            'currentWeather' => $temp . '  ℃',
        ]);
    }
}
