<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YandexGeocoderService
{
    /**
     * Геокодировать адрес (населённый пункт) в координаты [lat, lng].
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address): ?array
    {
        $apiKey = config('services.yandex_map.api_key');

        if (blank($apiKey) || blank($address)) {
            return null;
        }

        $response = Http::timeout(10)
            ->get('https://geocode-maps.yandex.ru/1.x/', [
                'apikey'  => $apiKey,
                'geocode' => $address,
                'format'  => 'json',
                'results' => 1,
                'lang'    => 'ru_RU',
            ]);

        if (! $response->successful()) {
            Log::warning('YandexGeocoder: HTTP error', [
                'status'  => $response->status(),
                'address' => $address,
            ]);

            return null;
        }

        $data = $response->json();

        try {
            $pos = $data['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['Point']['pos'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('YandexGeocoder: unable to parse response', [
                'address' => $address,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }

        if ($pos === null) {
            return null;
        }

        // Yandex возвращает "долгота широта"
        [$lng, $lat] = explode(' ', $pos, 2);

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
        ];
    }
}
