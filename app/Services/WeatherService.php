<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const TIMEOUT_SECONDS = 2;

    public function getWeather(float $lat, float $lon): array
    {
        return Cache::remember("weather:{$lat}:{$lon}", now()->addMinutes(240), function () use ($lat, $lon) {
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->retry(2, 1000)
                    ->get('https://api.open-meteo.com/v1/forecast', [
                        'latitude'       => $lat,
                        'longitude'      => $lon,
                        'hourly'         => 'temperature_2m',
                        'forecast_days'  => '14',
                    ]);

                return $response->json();
            } catch (ConnectionException $e) {
                Log::warning('Weather API request failed', [
                    'lat'    => $lat,
                    'lon'    => $lon,
                    'error'  => $e->getMessage(),
                ]);

                return ['error' => 'Сервис погоды временно недоступен. Попробуйте позже.'];
            }
        });
    }
}