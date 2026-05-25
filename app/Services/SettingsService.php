<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_TTL = 3600;

    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting:{$key}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = Setting::find($key);
            return $setting?->value ?? $default;
        });
    }

    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group],
        );

        Cache::forget("setting:{$key}");
    }

    public function setMany(array $data, string $group = 'general'): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value, $group);
        }
    }

    public function getGroup(string $group): array
    {
        return Cache::remember("settings_group:{$group}", self::CACHE_TTL, function () use ($group) {
            return Setting::where('group', $group)
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    public function forgetGroup(string $group): void
    {
        $keys = Setting::where('group', $group)->pluck('key');
        foreach ($keys as $key) {
            Cache::forget("setting:{$key}");
        }
        Cache::forget("settings_group:{$group}");
    }
}
