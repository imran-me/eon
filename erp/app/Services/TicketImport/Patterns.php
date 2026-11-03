<?php

namespace App\Services\TicketImport;

/**
 * What every ticket has in the same shape, whatever its layout (owner,
 * 2026-10-11: "be more logical"):
 *
 *  - the PNR — six letters/digits, printed by a booking label and/or
 *    mixing letters and digits, usually more than once;
 *  - airport codes — "DAC", "DXB" — from a library of every IATA airport
 *    with scheduled flights (data/airports.php, OurAirports). The codes on
 *    the page, in reading order, are paired up into flights; a pair that
 *    only summarises others (ECN–DAC over ECN–IST + IST–DAC) is dropped.
 */
final class Patterns
{
    private static ?array $lib = null;

    /** Capitals that are airport codes but far more often mean something else on a ticket. */
    private const NOT_AIRPORT = [
        'THE', 'AND', 'FOR', 'ADT', 'CHD', 'INF', 'PNR', 'MRS', 'USD', 'BDT', 'AED', 'SAR', 'QAR', 'INR', 'EUR', 'GBP',
        'MYR', 'VAT', 'TAX', 'ETA', 'ETD', 'STD', 'STA', 'AIR', 'NEW', 'OUT', 'DUE', 'BAG', 'PCS', 'KGS', 'ALL', 'ANY',
        'ARE', 'NOT', 'YOU', 'HAS', 'WAS', 'ONE', 'TWO', 'AGE', 'DOB', 'SEX', 'FEE', 'NET', 'PAY', 'VIA', 'REF', 'DEP',
        'ARR', 'FLT', 'SEQ', 'ROW', 'BUS', 'CAR', 'OFF', 'YES', 'DAY', 'PER', 'MAX', 'MIN', 'APP', 'WEB', 'COM', 'NON',
        'END', 'OPN', 'REQ', 'OUR', 'ITS', 'CAN', 'MAY', 'BOX', 'TEL', 'FAX', 'URL', 'NID', 'TKT', 'EMD', 'MCO', 'OSI',
        'SSR', 'CTC', 'SVC', 'LTD', 'PVT', 'INC', 'GMT', 'UTC', 'HRS', 'SAT', 'SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI',
        'JAN', 'FEB', 'MAR', 'APR', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC', 'ECO', 'ADD', 'GST', 'AIT', 'BIN',
        'MST', 'MDA', 'NIL', 'OKA', 'PAX', 'SIN', 'TOP', 'USA', 'UAE', 'KSA', 'IAT', 'BSP', 'GDS', 'API', 'PDF', 'QTY',
        'AGT', 'CPN', 'FOP', 'TOT', 'YQ', 'BHD', 'KWD', 'OMR', 'SGD', 'THB', 'CNY', 'JPY', 'PKR', 'NPR', 'LKR', 'MVR', 'ASF', 'ADF', 'UDF', 'PSF', 'CGT', 'ZYQ',
    ];

    /** Six-letter words that sit where a PNR could. */
    private const NOT_PNR = [
        'ISSUED', 'ADULTS', 'ARRIVE', 'DEPART', 'STATUS', 'NUMBER', 'TICKET', 'AIRWAY', 'FLIGHT', 'AMOUNT', 'REMARK',
        'RETURN', 'BOOKED', 'CHANGE', 'REFUND', 'TRAVEL', 'OFFICE', 'AGENCY', 'SAMPLE', 'ONWARD', 'ECONOMY', 'PERSON',
        'CHILDS', 'INFANT', 'CHARGE', 'SECTOR', 'PLEASE', 'BEFORE', 'WITHIN', 'PRIMARY', 'GENDER', 'MOBILE', 'NOTICE',
        'ARRIVAL', 'DETAIL', 'POLICY', 'CHECKS', 'COUNTER', 'MEMBER', 'ACCESS', 'CUSTOM', 'RECORD', 'CREDIT', 'ISSUER',
        'AGENTS', 'DOMEST', 'SINGLE', 'DOUBLE', 'SEATED', 'GROUND', 'SPECIAL', 'TRANSIT', 'LAYOVER', 'CABINS', 'BRANCH',
        'LIMITED', 'MANUAL', 'ONLINE', 'SELECT', 'PAYING', 'FAMILY', 'CHOICE', 'GLOBAL', 'TRAVELS', 'NORMAL', 'STOPS',
    ];

    private static function lib(): array
    {
        return self::$lib ??= require __DIR__.'/data/airports.php';
    }

    public static function isAirport(string $code): bool
    {
        return isset(self::lib()['codes'][strtoupper($code)]);
    }

    /** Does this text name a city / airport ("ISTANBUL", "Dhaka - Hazrat")? Such text is a value, never a label. */
    public static function isPlace(string $text): bool
    {
        $u = strtoupper(trim($text));
        if ($u === '') {
            return false;
        }
        $cities = self::lib()['cities'] + Cities::MAP;
        if (isset($cities[$u]) || Cities::inOrder($u)) {
            return true;
        }
        foreach (preg_split('/[^A-Z]+/', $u, -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (strlen($w) >= 4 && isset($cities[$w])) {
                return true;
            }
        }

        return false;
    }

    public static function airportName(string $code): ?string
    {
        return self::lib()['codes'][strtoupper($code)][0] ?? null;
    }

    /**
     * Airport codes on one line, in reading order. A code that is also a
     * common word counts only in "(XXX)" or in a pair "XXX-YYY" / "XXX → YYY".
     */
    public static function codesOn(string $line): array
    {
        $codes = [];
        if (! preg_match_all('/(?<![A-Za-z0-9])([A-Z]{3})(?![A-Za-z0-9])/', $line, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        foreach ($m[1] as [$c, $at]) {
            if (! self::isAirport($c)) {
                continue;
            }
            if (in_array($c, self::NOT_AIRPORT, true)) {
                $before = substr($line, max(0, $at - 3), 3);
                $after = substr($line, $at + 3, 4);
                $paired = preg_match('/(?:\(|[-–—→>]\s*)$/u', $before) || preg_match('/^\s*(?:\)|[-–—→]\s*[A-Z]{3})/u', $after);
                if (! $paired) {
                    continue;
                }
            }
            if (end($codes) !== $c) {
                $codes[] = $c;
            }
        }

        return $codes;
    }

    /**
     * Every flight the page shows, as [from, to] pairs in order. A line with
     * two or more codes is a pair (its first and last); single codes on
     * nearby lines pair with each other (layouts that stack From over To).
     */
    public static function airportPairs(array $lines): array
    {
        $lines = array_values(array_map(fn ($l) => str_replace('<<PAGE>>', '', (string) $l), $lines));
        // airport codes first; city names only on a page that prints no codes
        // (Galileo) — otherwise an office list "Dhaka · Sylhet · Saidpur" becomes flights
        $pairs = self::sightings($lines);

        // same pair twice (header + row, passenger + airline copy) = once
        $uniq = [];
        foreach ($pairs as $p) {
            $p = [$p[0], $p[1]];
            if ($p[0] !== $p[1] && ! in_array($p, $uniq, true)) {
                $uniq[] = $p;
            }
        }
        // drop a pair that only sums up others — a heading before its legs
        // ("Departure | ECN - DAC" over ECN–IST, IST–DAC) or a total after them.
        // The legs it sums must all sit on ONE side of it, so the legs of a
        // round trip never cancel each other out.
        $keep = [];
        foreach ($uniq as $k => $p) {
            if (! self::sums($uniq, $k)) {
                $keep[] = $p;
            }
        }

        return self::inTravelOrder($keep);
    }

    /**
     * Every place on the page a flight is printed, as [from, to, line] —
     * the same pair can be printed several times (a summary table, each
     * passenger's copy); Detector picks the sighting with its details around it.
     */
    public static function sightings(array $lines): array
    {
        $lines = array_values(array_map(fn ($l) => str_replace('<<PAGE>>', '', (string) $l), $lines));

        return self::collect($lines, false) ?: self::collect($lines, true);
    }

    /** Legs that join up (A–B, B–C) in the order they are flown, whatever order the page prints them. */
    private static function inTravelOrder(array $pairs): array
    {
        if (count($pairs) < 2) {
            return $pairs;
        }
        $tos = array_column($pairs, 1);
        $start = null;
        foreach ($pairs as $k => $p) {
            if (! in_array($p[0], $tos, true)) {
                $start = $k;
                break;
            }
        }
        if ($start === null) {
            return $pairs;          // a round trip: the page order is already the travel order
        }
        $out = [$pairs[$start]];
        $left = $pairs;
        unset($left[$start]);
        while ($left) {
            $at = end($out)[1];
            $k = null;
            foreach ($left as $j => $p) {
                if ($p[0] === $at) {
                    $k = $j;
                    break;
                }
            }
            if ($k === null) {
                return $pairs;      // they do not join up — keep the page order
            }
            $out[] = $left[$k];
            unset($left[$k]);
        }

        return $out;
    }

    /**
     * One reading pass. A line with two or more places is a pair (first and
     * last); single places on nearby lines pair with each other (From over To).
     * A pair counts only when a time, date or flight number is printed within
     * two lines of it — a real flight always has one; the fine print
     * ("flights between DEL-DXB …") does not.
     */
    private static function collect(array $lines, bool $cities): array
    {
        $pairs = [];
        $pending = null;
        $evidence = function (int $a, int $b) use ($lines): bool {
            $win = implode(' ', array_slice($lines, max(0, $a - 2), $b - $a + 5));

            return (bool) preg_match('/\b\d{1,2}[:.]\d{2}\b|\b\d{1,2}[\s\/\-]?(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)|\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b|\b[A-Z][A-Z0-9]\s?-?\s?\d{2,4}\b/i', $win);
        };
        foreach ($lines as $i => $line) {
            if ($cities) {
                // a lone city counts only when the line says it is an airport — not an address
                $found = Cities::inOrder(strtoupper($line));
                // … or a flight row: a flight number and a date on the same line ("DHAKA HAZRAT SHAHJALAL | SV 805 | V | 25OCT")
                $codes = count($found) >= 2 || preg_match('/\b(AIRPORT|ARPT|INTL|INTERNATIONAL)\b|[A-Za-z]\s+-\s+[A-Z][a-z]/', $line)
                    || preg_match('/\b[A-Z][A-Z0-9]\s?\d{2,4}\b.*\b\d{1,2}\s?(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)/', $line) ? $found : [];
            } else {
                $codes = self::codesOn($line);
            }
            if (count($codes) >= 2) {
                if ($evidence($i, $i)) {
                    $pairs[] = [$codes[0], end($codes), $i];
                }
                $pending = null;
            } elseif (count($codes) === 1) {
                if ($pending && $pending[0] !== $codes[0] && $i - $pending[1] <= 3) {
                    if ($evidence($pending[1], $i)) {
                        $pairs[] = [$pending[0], $codes[0], $pending[1]];
                    }
                    $pending = null;
                } else {
                    $pending = [$codes[0], $i];
                }
            }
        }

        return $pairs;
    }

    /**
     * Is pair $k a heading / total of the legs printed right next to it?
     * Walk the pairs just after it (or just before it) while each starts where
     * the last ended; if that walk goes from its origin to its destination in
     * two or more legs, it only sums them up.
     */
    private static function sums(array $pairs, int $k): bool
    {
        [$from, $to] = $pairs[$k];
        $at = $from;
        for ($j = $k + 1, $n = 0; $j < count($pairs) && $pairs[$j][0] === $at; $j++) {
            $at = $pairs[$j][1];
            if (++$n >= 2 && $at === $to) {
                return true;
            }
        }
        $at = $to;
        for ($j = $k - 1, $n = 0; $j >= 0 && $pairs[$j][1] === $at; $j--) {
            $at = $pairs[$j][0];
            if (++$n >= 2 && $at === $from) {
                return true;
            }
        }

        return false;
    }

    /**
     * The PNR: of all six-character codes on the page, the one with the most
     * signs of being it — beside a booking label (strongest), mixing letters
     * and digits, printed more than once. Ordinary words, city names, flight
     * numbers ("TK0975") and aircraft ("A330NB") are not candidates.
     */
    public static function pnr(array $lines): ?string
    {
        $label = '/\b(PNR|BOOKING|RESERVATION|LOCATOR|CONFIRMATION|REFERENCE|ORDER\s*ID)\b/i';
        $airline = '/\bAIRLINE\s*(PNR|REF\w*|RESERVATION|BOOKING|CODE)/i';
        $cities = self::lib()['cities'] + Cities::MAP;
        $score = [];
        $labelled = [];
        $lines = array_values($lines);
        foreach ($lines as $i => $line) {
            $line = (string) $line;
            // "TK/WJNEI7" — a carrier prefix before the slash is allowed
            if (! preg_match_all('/(?<![A-Za-z0-9\-#])(?<![A-Za-z]{3}\/)([A-Z0-9]{6})(?![A-Za-z0-9\-\/])/', $line, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[1] as [$t, $at]) {
                if (ctype_digit($t) || in_array($t, self::NOT_PNR, true) || isset($cities[$t])) {
                    continue;
                }
                $letters = ctype_alpha($t);
                // an English word ("MEDIUM", "REMOTE") is a word, not a booking code —
                // unless the line says so right before it ("PNR: BANANA")
                if ($letters && self::isWord($t) && ! preg_match('/\b(PNR|LOCATOR|REF(ERENCE)?|CODE)\s*[:#]?\s*$/i', substr($line, 0, $at))) {
                    continue;
                }
                // a quantity with its unit: "750KGS", "28BAGS", "12PCS"
                if (preg_match('/^\d+(KGS?|MTS?|LBS?|PCS?|BAGS?|KMS?|HRS?|MINS?|PAX|NOS?|SETS?|BOX)$/', $t)) {
                    continue;
                }
                // the label is before it on its line, or heads its column on the line above
                $above = $i > 0 ? self::headerOver((string) $lines[$i - 1], $line, $at) : '';
                $byLabel = preg_match($label, substr($line, 0, $at)) || preg_match($label, $above);
                // two airport codes glued together ("DACDXB") or a flight / aircraft / duration
                if ($letters && self::isAirport(substr($t, 0, 3)) && self::isAirport(substr($t, 3)) && ! $byLabel) {
                    continue;
                }
                if (! $byLabel && (preg_match('/^[A-Z0-9]{2}\d{3,4}$/', $t) || preg_match('/^[AB]\d{3}/', $t) || preg_match('/^\d+[HM]\d*[HM]?$/', $t))) {
                    continue;
                }
                // the TICKET's own PNR (a booking / GDS label) beats the airline's (owner, 2026-10-17:
                // "Galileo PNR (Ticket PNR)", not the airline PNR). The airline's — by an airline label, or
                // written the airline way "QSDGRW (GF - Gulf Air)" — still counts as labelled, so a
                // ticket that prints only that one still gives it.
                $byAirline = preg_match($airline, substr($line, 0, $at)) || preg_match($airline, $above)
                    || preg_match('/^\s*\(\s*[A-Z0-9]{2}\s*-/', substr($line, $at + 6, 12));
                $byLabel = $byLabel || $byAirline;
                $s = ($byLabel ? 5 : 0) + ($byLabel && ! $byAirline ? 3 : 0);
                $s += $letters ? 0 : 2;
                // its best sighting, plus one for every time it is printed again
                $score[$t] = isset($score[$t]) ? max($score[$t], $s) + 1 : $s;
                $labelled[$t] = ($labelled[$t] ?? false) || $byLabel;
            }
        }
        // letters only needs a label pointing at it — being printed often is not enough
        // ("MEDIUM" in every row of a task report); a mix of letters and digits stands alone
        $score = array_filter($score, fn ($s, $t) => $s >= 2 && (! ctype_alpha((string) $t) || ! empty($labelled[$t])),
            ARRAY_FILTER_USE_BOTH);
        if (! $score) {
            return null;
        }
        arsort($score);

        return (string) array_key_first($score);
    }

    private static ?string $words = null;

    /** A six-letter English word (data/words6.php). */
    public static function isWord(string $t): bool
    {
        self::$words ??= ' '.(require __DIR__.'/data/words6.php').' ';

        return strlen($t) === 6 && str_contains(self::$words, ' '.strtoupper($t).' ');
    }

    /**
     * The header over the cell at $at: the same column of a " | " table, or
     * the whole line above when it is not a table.
     */
    private static function headerOver(string $above, string $line, int $at): string
    {
        $head = explode(' | ', $above);
        if (count($head) < 2) {
            return $above;
        }
        $col = substr_count(substr($line, 0, $at), ' | ');

        return $head[$col] ?? '';
    }
}
