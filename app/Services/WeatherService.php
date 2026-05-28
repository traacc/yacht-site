<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherService
{
    public function getWeather(float $lat, float $lon): array
    {
        return Cache::remember("weather:{$lat}:{$lon}", now()->addMinutes(60), function () use ($lat, $lon) {
            $response = Http::get('https://api.open-meteo.com/v1/forecast', [
                'latitude'       => $lat,
                'longitude'      => $lon,
                'current'        => 'temperature_2m',
            ]);

            return $response->json();
        });
    }
}