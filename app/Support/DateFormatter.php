<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class DateFormatter
{
    public static function date(mixed $value, ?string $fallback = null): ?string
    {
        $date = self::parse($value);

        return $date ? $date->format('d M Y') : $fallback;
    }

    public static function dateTime(mixed $value, ?string $fallback = null): ?string
    {
        $date = self::parse($value);

        return $date ? $date->format('d M Y g:i A') : $fallback;
    }

    public static function time(mixed $value, ?string $fallback = null): ?string
    {
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return self::parse($value)?->format('g:i A') ?? $fallback;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return $fallback;
        }

        return rescue(
            fn (): string => Carbon::createFromFormat(
                str_contains($normalized, ':') && substr_count($normalized, ':') === 1 ? 'H:i' : 'H:i:s',
                $normalized,
                config('app.timezone', 'UTC'),
            )->format('g:i A'),
            report: false,
        ) ?? $fallback;
    }

    public static function inputDate(mixed $value): ?string
    {
        $date = self::parse($value);

        return $date?->format('Y-m-d');
    }

    public static function iso(mixed $value): ?string
    {
        $date = self::parse($value);

        return $date?->toIso8601String();
    }

    public static function monthYear(mixed $value, ?string $fallback = null): ?string
    {
        $date = self::parse($value);

        return $date ? $date->format('F Y') : $fallback;
    }

    private static function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->copy()->timezone(config('app.timezone', 'UTC'));
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->timezone(config('app.timezone', 'UTC'));
        }

        return rescue(
            fn (): Carbon => Carbon::parse($value, config('app.timezone', 'UTC'))->timezone(config('app.timezone', 'UTC')),
            report: false,
        );
    }
}
