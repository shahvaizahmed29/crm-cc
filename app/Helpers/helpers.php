<?php

declare(strict_types=1);

use App\Models\Setting;
use Carbon\Carbon;

if (! function_exists('app_timezone')) {
    /**
     * Return the display timezone from admin settings (used for all UI dates and date filters).
     */
    function app_timezone(): string
    {
        $tz = Setting::get('app_timezone', config('app.timezone'));

        return $tz !== null && $tz !== '' ? $tz : config('app.timezone');
    }
}

if (! function_exists('format_in_app_tz')) {
    /**
     * Format a datetime in the app timezone for display.
     *
     * @param  \Carbon\Carbon|\DateTimeInterface|string|null  $datetime
     */
    function format_in_app_tz($datetime, string $format = 'Y-m-d H:i'): string
    {
        if ($datetime === null) {
            return '—';
        }

        $carbon = $datetime instanceof Carbon
            ? $datetime->copy()
            : Carbon::parse($datetime);

        return $carbon->setTimezone(app_timezone())->format($format);
    }
}

if (! function_exists('date_filter_utc_range')) {
    /**
     * Convert optional date_from / date_to (in app timezone) to UTC Carbon bounds for DB queries.
     * DB stores UTC; filters are interpreted in app timezone.
     *
     * @return array{start: \Carbon\Carbon|null, end: \Carbon\Carbon|null}
     */
    function date_filter_utc_range(?string $dateFrom, ?string $dateTo): array
    {
        $tz = app_timezone();
        $start = null;
        $end = null;

        if ($dateFrom !== null && $dateFrom !== '') {
            $start = Carbon::parse($dateFrom, $tz)->startOfDay()->utc();
        }
        if ($dateTo !== null && $dateTo !== '') {
            $end = Carbon::parse($dateTo, $tz)->endOfDay()->utc();
        }

        return ['start' => $start, 'end' => $end];
    }
}
