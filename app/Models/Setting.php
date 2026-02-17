<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
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
}
