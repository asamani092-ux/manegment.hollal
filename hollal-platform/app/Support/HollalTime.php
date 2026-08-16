<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Platform-wide 12-hour clock display (Arabic ص/م).
 * Form inputs (datetime-local) stay 24h — browsers require that.
 * Time: O(1) | Space: O(1)
 */
final class HollalTime
{
    public static function datetime(?CarbonInterface $dt, bool $withSeconds = false): string
    {
        if ($dt === null) {
            return '—';
        }

        $ampm = ((int) $dt->format('G')) < 12 ? 'ص' : 'م';
        $pattern = $withSeconds ? 'Y-m-d g:i:s' : 'Y-m-d g:i';

        return $dt->format($pattern).' '.$ampm;
    }

    public static function time(?CarbonInterface $dt): string
    {
        if ($dt === null) {
            return '—';
        }

        $ampm = ((int) $dt->format('G')) < 12 ? 'ص' : 'م';

        return $dt->format('g:i').' '.$ampm;
    }

    public static function date(?CarbonInterface $dt): string
    {
        return $dt?->format('Y-m-d') ?? '—';
    }
}
