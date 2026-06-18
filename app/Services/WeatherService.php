<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const TIMEOUT_SECONDS = 2;

    public function getWeather(float $lat, float $lon): array|null
    {
        return Cache::remember("weather:{$lat}:{$lon}", now()->addMinutes(240), function () use ($lat, $lon) {
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->retry(2, 1000, throw: false)
                    ->get('https://api.open-meteo.com/v1/forecast', [
                        'latitude'       => $lat,
                        'longitude'      => $lon,
                        'hourly'          => 'temperature_2m,wind_speed_10m,wind_direction_10m',
                        'wind_speed_unit' => 'ms',
                        'forecast_days'   => '14',
                    ]);

                if ($response->failed()) {
                    Log::warning('Weather API responded with error', [
                        'lat'    => $lat,
                        'lon'    => $lon,
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);

                    return null;
                }

                return $response->json();
            } catch (ConnectionException | RequestException $e) {
                Log::warning('Weather API connection failed', [
                    'lat'    => $lat,
                    'lon'    => $lon,
                    'error'  => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Преобразует направление ветра в градусах в название румба (С, СВ, В, …).
     */
    public static function windDirectionLabel(float|int $degrees): string
    {
        $points = ['С', 'СВ', 'В', 'ЮВ', 'Ю', 'ЮЗ', 'З', 'СЗ'];

        $index = (int) round(($degrees % 360) / 45) % 8;

        return $points[$index];
    }
}