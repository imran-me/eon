<?php

namespace Modules\EON\Intelligence\Support;

use Carbon\CarbonImmutable;

/**
 * Windows of time, defined in one place so every number on an EON page is
 * measured over the same calendar.
 *
 * THE THING THIS CLASS EXISTS TO GET RIGHT: today is almost never the last day
 * of the month, so "this month against last month" compares a part-month with a
 * whole one and reports a collapse that is really just the calendar. Every
 * comparison EON prints uses monthToDate() against previousMonthToDate() — the
 * same number of days, ending on the same day of the month — and the pages say
 * "through day N" so nobody has to take that on trust. The full prior month is
 * still available for the trend chart, where it belongs.
 */
final class Period
{
    /**
     * The last $count calendar months ending with the one $anchor falls in,
     * oldest first, keyed 'YYYY-MM'.
     *
     * @return array<string, array{key:string,label:string,short:string,start:CarbonImmutable,end:CarbonImmutable,partial:bool}>
     */
    public static function months(int $count = 12, ?CarbonImmutable $anchor = null): array
    {
        $anchor = $anchor ?: CarbonImmutable::now();
        $out    = [];

        for ($i = $count - 1; $i >= 0; $i--) {
            $start = $anchor->startOfMonth()->subMonths($i);
            $end   = $start->endOfMonth();

            $out[$start->format('Y-m')] = [
                'key'     => $start->format('Y-m'),
                'label'   => $start->format('F Y'),
                'short'   => $start->format('M y'),
                'start'   => $start,
                'end'     => $end,
                'partial' => $start->isSameMonth($anchor),
            ];
        }

        return $out;
    }

    /** First of this month → now. The window every "current" figure uses. */
    public static function monthToDate(?CarbonImmutable $anchor = null): array
    {
        $anchor = $anchor ?: CarbonImmutable::now();

        return [
            'key'   => $anchor->format('Y-m'),
            'label' => $anchor->format('F Y'),
            'start' => $anchor->startOfMonth(),
            'end'   => $anchor,
            'days'  => $anchor->day,
            'days_in_month' => $anchor->daysInMonth,
        ];
    }

    /**
     * The same slice of the previous month: day 1 to the same day number, or to
     * the last day when the previous month is shorter (31 March compares
     * against all of February, which is the only honest reading available).
     */
    public static function previousMonthToDate(?CarbonImmutable $anchor = null): array
    {
        $anchor = $anchor ?: CarbonImmutable::now();
        $start  = $anchor->startOfMonth()->subMonth();
        $day    = min($anchor->day, $start->daysInMonth);

        return [
            'key'   => $start->format('Y-m'),
            'label' => $start->format('F Y'),
            'start' => $start,
            'end'   => $start->setDay($day)->setTime(
                $anchor->hour,
                $anchor->minute,
                $anchor->second
            ),
            'days'  => $day,
            'days_in_month' => $start->daysInMonth,
        ];
    }

    /** The last $count whole weeks, oldest first, keyed by the Monday. */
    public static function weeks(int $count = 12, ?CarbonImmutable $anchor = null): array
    {
        $anchor = $anchor ?: CarbonImmutable::now();
        $out    = [];

        for ($i = $count - 1; $i >= 0; $i--) {
            $start = $anchor->startOfWeek()->subWeeks($i);

            $out[$start->format('Y-m-d')] = [
                'key'   => $start->format('Y-m-d'),
                'label' => $start->format('j M'),
                'start' => $start,
                'end'   => $start->endOfWeek(),
            ];
        }

        return $out;
    }

    /** 'March 2026' from '2026-03'; the raw key back if it is not one. */
    public static function label(?string $key): ?string
    {
        if (!$key || !preg_match('/^\d{4}-\d{2}$/', $key)) {
            return $key;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $key . '-01')->format('F Y');
    }

    /** 'Mar 26' from '2026-03' — the axis form. */
    public static function shortLabel(?string $key): ?string
    {
        if (!$key || !preg_match('/^\d{4}-\d{2}$/', $key)) {
            return $key;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $key . '-01')->format('M y');
    }
}
