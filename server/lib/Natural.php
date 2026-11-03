<?php
/* ============================================================
   EON · Natural — Bangla the way an office in Dhaka speaks it.

   The answers were written in schoolbook Bangla ("তারিখ পেরিয়েছে",
   "পার্টি খাতা", "অঙ্কটা") where people at work say overdue, ledger,
   figure. One list, app/bn-natural.json, shared with the browser
   (app/eon-app.js), rewrites every Bangla answer on its way out —
   longest phrase first, so "পার্টি খাতা" wins over "খাতা".
   ============================================================ */
declare(strict_types=1);

final class Natural
{
    private static ?array $map = null;

    private static function map(): array
    {
        if (self::$map !== null) return self::$map;
        $f = dirname(__DIR__, 2) . '/app/bn-natural.json';
        $j = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        $m = is_array($j['map'] ?? null) ? $j['map'] : [];
        usort($m, fn($a, $b) => mb_strlen($b[0]) <=> mb_strlen($a[0]));
        return self::$map = $m;
    }

    /** rewrite a Bangla answer; anything without Bengali letters passes untouched */
    public static function bn(string $t): string
    {
        if ($t === '' || !preg_match('/\p{Bengali}/u', $t)) return $t;
        foreach (self::map() as [$from, $to]) $t = str_replace($from, $to, $t);
        return $t;
    }
}
