<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan aplikasi (tabel settings) dengan cache.
 * Nilai bawaan ada di config('banksoal.settings_defaults').
 */
final class Settings
{
    private const CACHE_KEY = 'banksoal.settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return config("banksoal.settings_defaults.{$key}", $default);
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        /** @var array<string, mixed> $stored */
        $stored = Cache::rememberForever(
            self::CACHE_KEY,
            static fn (): array => Setting::query()->get()->pluck('value', 'key')->all(),
        );

        return $stored;
    }
}
