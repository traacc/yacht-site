<?php

namespace App\Services;

class YandexMapService
{
    /**
     * Сгенерировать ссылку на Яндекс Карты с меткой по координатам.
     *
     * @param  float  $lat  Широта
     * @param  float  $lon  Долгота
     * @param  int    $zoom  Уровень приближения (0–23, по умолчанию 15)
     * @param  string $mapType  Тип карты: 'map' (схема), 'sat' (спутник), 'skl' (гибрид)
     * @return string
     */
    public function makeUrl(
        float  $lat,
        float  $lon,
        int    $zoom = 15,
        string $mapType = 'map',
    ): string {
        return vsprintf('https://yandex.ru/maps/?ll=%F,%F&pt=%F,%F&z=%d&l=%s', [
            $lon, $lat, // ll — центр карты
            $lon, $lat, // pt — координаты метки
            $zoom,
            $mapType,
        ]);
    }

}