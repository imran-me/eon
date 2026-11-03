<?php

namespace Modules\EON\Intelligence\Support;

/**
 * The arithmetic EON is built on. Pure functions, no state, no database — so
 * every number the engine prints can be reproduced by hand from the same input.
 *
 * Everything here is defensive about small samples, because that is the normal
 * case for a person who joined last month: three data points must produce an
 * honest "not enough to say", never a confident slope.
 */
final class Stats
{
    public static function mean(array $values): float
    {
        $values = self::numbers($values);

        return $values ? array_sum($values) / count($values) : 0.0;
    }

    public static function median(array $values): float
    {
        $values = self::numbers($values);

        if (!$values) {
            return 0.0;
        }

        sort($values);
        $n   = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /** Population standard deviation — the whole history is the population. */
    public static function stdDev(array $values): float
    {
        $values = self::numbers($values);
        $n      = count($values);

        if ($n < 2) {
            return 0.0;
        }

        $mean = self::mean($values);
        $sum  = 0.0;

        foreach ($values as $v) {
            $sum += ($v - $mean) ** 2;
        }

        return sqrt($sum / $n);
    }

    /**
     * Coefficient of variation — spread as a share of the average, so a person
     * doing 4±2 a month and one doing 40±20 read as equally erratic.
     */
    public static function coefficientOfVariation(array $values): ?float
    {
        $mean = self::mean($values);

        if ($mean <= 0.0) {
            return null;
        }

        return self::stdDev($values) / $mean;
    }

    /**
     * Least-squares slope over evenly spaced points — units per step.
     * Null under three points: two points always make a perfect line and would
     * otherwise be reported as a trend.
     */
    public static function slope(array $values): ?float
    {
        $values = array_values(self::numbers($values));
        $n      = count($values);

        if ($n < 3) {
            return null;
        }

        $meanX = ($n - 1) / 2;
        $meanY = self::mean($values);
        $num   = 0.0;
        $den   = 0.0;

        foreach ($values as $i => $y) {
            $num += ($i - $meanX) * ($y - $meanY);
            $den += ($i - $meanX) ** 2;
        }

        return $den == 0.0 ? null : $num / $den;
    }

    /**
     * The slope again, but as a percentage of the average level — "falling 12%
     * a month" travels between people in a way "falling 3.4 a month" does not.
     */
    public static function trendPercent(array $values): ?float
    {
        $slope = self::slope($values);
        $mean  = self::mean($values);

        if ($slope === null || $mean <= 0.0) {
            return null;
        }

        return ($slope / $mean) * 100;
    }

    /** Percentage change from $from to $to, null when there is no base. */
    public static function change(?float $from, ?float $to): ?float
    {
        if ($from === null || $to === null || $from == 0.0) {
            return null;
        }

        return (($to - $from) / abs($from)) * 100;
    }

    public static function clamp(float $value, float $min = 0.0, float $max = 100.0): float
    {
        return max($min, min($max, $value));
    }

    /**
     * Score a ratio against a target on a 0-100 scale, where hitting the target
     * is 100 and hitting nothing is 0. Beating the target is capped rather than
     * rewarded without limit: closing 300% of a normal month is a signal to
     * read, not a licence to bank points against a bad one.
     */
    public static function scoreRatio(?float $actual, ?float $target, float $ceiling = 1.25): float
    {
        if ($actual === null || $target === null || $target <= 0.0) {
            return 50.0;
        }

        return self::clamp(min($actual / $target, $ceiling) / $ceiling * 100);
    }

    /**
     * Score where LOW is good — average days late, cancellations, and so on.
     * $good scores 100, $bad scores 0, in between is linear.
     */
    public static function scoreInverse(?float $value, float $good, float $bad): float
    {
        if ($value === null) {
            return 50.0;
        }

        if ($bad <= $good) {
            return 50.0;
        }

        return self::clamp((1 - (($value - $good) / ($bad - $good))) * 100);
    }

    /** Where $value sits inside $values, 0-100. Used for "vs the company". */
    public static function percentileOf(float $value, array $values): ?float
    {
        $values = self::numbers($values);

        if (count($values) < 2) {
            return null;
        }

        $below = 0;

        foreach ($values as $v) {
            if ($v < $value) {
                $below++;
            }
        }

        return ($below / count($values)) * 100;
    }

    /**
     * Peak-to-trough analysis of a series — the drawdown language the owner
     * asked for, borrowed from finance and honest here for the same reason it
     * is honest there: it measures the fall from a level the person has
     * actually reached, not from a target somebody invented.
     *
     * Returns the peak and when it happened, the current level, the fall from
     * peak in percent, and the deepest fall recorded anywhere in the window.
     */
    public static function drawdown(array $series): array
    {
        $labels = array_keys($series);
        $values = array_values(self::numbers($series));

        $out = [
            'peak'              => null,
            'peak_label'        => null,
            'peak_index'        => null,
            'current'           => null,
            'current_label'     => null,
            'from_peak_pct'     => null,
            'max_pct'           => null,
            'max_label'         => null,
            'months_since_peak' => null,
            'recovering'        => false,
        ];

        if (!$values) {
            return $out;
        }

        $runningPeak = null;
        $maxPct      = null;
        $maxLabel    = null;

        foreach ($values as $i => $v) {
            if ($runningPeak === null || $v > $runningPeak) {
                $runningPeak = $v;
            }

            if ($runningPeak > 0) {
                $fall = (($runningPeak - $v) / $runningPeak) * 100;

                if ($maxPct === null || $fall > $maxPct) {
                    $maxPct   = $fall;
                    $maxLabel = $labels[$i] ?? null;
                }
            }
        }

        $peak      = max($values);
        $peakIndex = (int) array_search($peak, $values, true);
        $lastIndex = count($values) - 1;
        $current   = $values[$lastIndex];

        $out['peak']              = (float) $peak;
        $out['peak_label']        = $labels[$peakIndex] ?? null;
        $out['peak_index']        = $peakIndex;
        $out['current']           = (float) $current;
        $out['current_label']     = $labels[$lastIndex] ?? null;
        $out['from_peak_pct']     = $peak > 0 ? (($peak - $current) / $peak) * 100 : null;
        $out['max_pct']           = $maxPct;
        $out['max_label']         = $maxLabel;
        $out['months_since_peak'] = $lastIndex - $peakIndex;

        // Rising off the bottom: the last point beats the one before it, and the
        // fall is no longer the deepest it has been.
        if (count($values) >= 2) {
            $out['recovering'] = $values[$lastIndex] > $values[$lastIndex - 1]
                && $out['from_peak_pct'] !== null
                && $maxPct !== null
                && $out['from_peak_pct'] < $maxPct;
        }

        return $out;
    }

    /** Numeric, finite values only — nulls and NaNs never reach the maths. */
    private static function numbers(array $values): array
    {
        $out = [];

        foreach ($values as $k => $v) {
            if (is_numeric($v) && is_finite((float) $v)) {
                $out[$k] = (float) $v;
            }
        }

        return $out;
    }
}
