<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const IP_WHITELIST_CACHE_KEY = 'ip_whitelist';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * @param  array<int, string>  $default
     * @return array<int, string>
     */
    public static function getJsonArray(string $key, array $default = []): array
    {
        $value = static::get($key);
        if ($value === null || $value === '') {
            return $default;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values($decoded) : $default;
    }

    public static function putJsonArray(string $key, array $value): void
    {
        static::put($key, json_encode(array_values($value)));
    }

    /**
     * @return array<int, string>
     */
    public static function getIpWhitelistCached(): array
    {
        $whitelist = Cache::get(self::IP_WHITELIST_CACHE_KEY);

        if ($whitelist === null) {
            $whitelist = static::getJsonArray('ip_whitelist', []);
            Cache::forever(self::IP_WHITELIST_CACHE_KEY, $whitelist);
        }

        if ($whitelist === [] && static::get('ip_whitelist') !== null && static::get('ip_whitelist') !== '') {
            $whitelist = static::getJsonArray('ip_whitelist', []);
            static::clearIpWhitelistCache();
            if ($whitelist !== []) {
                Cache::forever(self::IP_WHITELIST_CACHE_KEY, $whitelist);
            }
        }

        return is_array($whitelist) ? $whitelist : [];
    }

    public static function clearIpWhitelistCache(): void
    {
        Cache::forget(self::IP_WHITELIST_CACHE_KEY);
    }
}
