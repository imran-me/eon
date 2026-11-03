<?php

namespace App\Services\TicketImport;

use App\Services\TicketImport\Readers\GenericReader;
use App\Services\TicketImport\Readers\Reader;
use Illuminate\Support\Facades\DB;

/**
 * Reads a ticket of ANY layout by what its values look like, not by where a
 * layout puts them (owner, 2026-10-11: "identify PNR, passenger name,
 * airports, routes … from any kind of PDF, no matter the layout").
 *
 * Every air ticket prints the same kinds of value, whatever the design:
 *
 *  - typed tokens  — every flight number ("QR 639", "TK0977"), date ("08 Oct 26",
 *                   "12 September 2026", "SUNDAY 11 OCT"), time ("14:55"),
 *                   13-digit ticket number, passport number, and person
 *                   ("KHATUN/MST RIPA MS", "Mr. Riad MIA") on the page, with
 *                   the line it sits on;
 *  - flight legs   — the airport pairs Patterns finds; for a pair printed more
 *                   than once, the sighting with the most flight details
 *                   around it;
 *  - assembly      — each flight number / date / time belongs to the leg it is
 *                   printed nearest to; when the page names exactly one flight
 *                   number per leg they go in order instead (a header "FZ 524/FZ 781"
 *                   sits nearer the first leg than the second). A leg's times
 *                   are departure then arrival, put the right way round if the
 *                   page prints arrival first; a date without a year takes the
 *                   ticket's year and rolls into the next one after December.
 *
 * Reads only, writes nothing, never throws to its caller (Lessons wraps it).
 */
final class Detector extends Reader
{
    /** A token this far (in lines) from every leg belongs to none of them. */
    private const REACH = 8;

    private const MONTH = '(JAN(?:UARY)?|FEB(?:RUARY)?|MAR(?:CH)?|APR(?:IL)?|MAY|JUNE?|JULY?|AUG(?:UST)?|SEP(?:T(?:EMBER)?)?|OCT(?:OBER)?|NOV(?:EMBER)?|DEC(?:EMBER)?)(?![A-Z])';

    /** A date on a line saying this is not the flight's date. */
    private const NOT_TRAVEL_DATE = '/BIRTH|\bDOB\b|ISSUE|BOOK(?:ED|ING)|CREATE|PURGE|EXPIR|PAYMENT|VALID|PRINT|GENERATED|\bIATA\b|DEADLINE|TIME ?LIMIT|PAID|INVOICE/';

    /** A time on a line saying this is not the flight's time. */
    private const NOT_FLIGHT_TIME = '/CHECK.?IN|CLOSES?|BOOK(?:ED|ING)|PRINT|GENERATED|ISSUED|REPORT|DEADLINE|TIME ?LIMIT|BAG ?DROP|GATE CLOSE|DURATION|LAYOVER|TRAVEL TIME|CONNECTION/';

    /** Words that never make up a passenger's name. */
    public const NOT_NAME = '/\b(TRAVELS?|TOURS?|CONSULT\w*|AGENC\w*|LTD|LIMITED|COMPANY|AIRWAYS|AIRLINES?|AIRPORT|INTERNATIONAL|INTL|TERMINAL|FEE|FEES|CHARGES?|TAX|TAXES|VAT|SERVICES?|INFORMATION|GENERAL|SECURITY|TOTAL|FARE|BAGGAGE|ECONOMY|BUSINESS|CONFIRMED|DETAILS|RECEIPT|ITINERARY|BOOKING|RESERVATION|TICKET|FLIGHT|DEPARTURE|ARRIVAL|PASSENGERS?|ADULT|CHILD|INFANT|NUMBER|DATE|CONTACT|EMAIL|PHONE|MOBILE|ADDRESS|OFFICE|BRANCH|PAYMENT|AMOUNT|ISSUED|STATUS|CLASS|SEAT|CABIN|MEAL|KIOSK|BARCODE|PLEASE|THANK|DEAR|NOTE|TERMS|CONDITIONS|POLICY|INSURANCE|HOTEL|VISA|CARGO|PREPARED|SUMMARY|TRANSIT|LAYOVER|DUBAI|DHAKA|DOHA|ISTANBUL)\b/';

    public function key(): string { return 'detector'; }

    public function label(): string { return 'Read by value types'; }

    public function matches(string $text): bool { return true; }

    /** What a reader returns, from types alone. */
    public function read(array $lines): array
    {
        $d = $this->detect($lines);
        $out = TicketPdfImporter::blank();
        foreach ($d['people'] as $p) {
            $out['passengers'][] = $this->passenger($p['raw'], array_filter([
                'eticket' => $p['eticket'], 'passport_no' => $p['passport'],
            ]));
        }
        $out['segments'] = array_map(fn ($l) => $this->segment($l), $d['legs']);
        $out['carrier'] = $d['legs'][0]['carrier'] ?? null;
        return $out;
    }

    /**
     * Everything the page holds, typed.
     *
     * @return array{people: array, legs: array, etickets: array, passports: array}
     */
    public function detect(array $lines): array
    {
        $lines = array_values(array_map(fn ($l) => str_replace('<<PAGE>>', '', (string) $l), $lines));
        $up = array_map('strtoupper', $lines);
        $etickets = $this->etickets($up);
        $passports = $this->passports($up);
        return [
            'people'    => $this->people($lines, $etickets, $passports),
            'legs'      => $this->legs($lines, $up),
            'etickets'  => array_column($etickets, 'value'),
            'passports' => array_column($passports, 'value'),
        ];
    }

    // ── people ──────────────────────────────────────────────────────────

    /**
     * Persons printed the way tickets print them — "SURNAME/GIVEN TITLE" or
     * "Mr. Given Surname" — once each, in order, each with the ticket number
     * and passport printed on the same line (else, when there are as many of
     * those as persons, in order).
     */
    private function people(array $lines, array $etickets, array $passports): array
    {
        $titles = 'MR|MRS|MS|MISS|MSTR|MASTER|DR';
        $found = [];
        foreach ($lines as $i => $line) {
            foreach (explode(' | ', $line) as $cell) {
                $cell = trim(preg_replace('/^[»•\-\*\d.\s]+/u', '', $cell));
                $cell = trim(preg_replace('/\s*[(,]\s*(ADULT|CHILD|INFANT|ADT|CHD|INF)\)?\s*$/i', '', $cell));   // "… (Adult)"
                $raw = null;
                // "KHATUN/MST RIPA MS" — capitals as printed, a title optional
                if (preg_match('/^([A-Z][A-Z\'\-]+(?: [A-Z][A-Z\'\-]+)*)\s*\/\s*([A-Z][A-Z\'\-]+(?: [A-Z][A-Z\'\-]+)*?)(?:\s*\.?\s*(' . $titles . '))?$/', $cell, $m)
                    && strlen($m[1] . $m[2]) >= 5 && (isset($m[3]) || strlen($m[1]) >= 3 && strlen($m[2]) >= 3)) {
                    $raw = $m[1] . '/' . $m[2] . (isset($m[3]) ? ' ' . $m[3] : '');
                } elseif (preg_match('/^(' . $titles . ')\.?\s+([A-Za-z][A-Za-z\'\-]+(?: [A-Za-z][A-Za-z\'\-]+){0,5})$/i', $cell, $m)
                    && ctype_upper(substr($m[2], 0, 1))) {
                    $raw = strtoupper($m[1]) . ' ' . $m[2];
                }
                if ($raw === null || preg_match(self::NOT_NAME, strtoupper($raw))) {
                    continue;
                }
                [$name] = $this->person($raw);
                $key = $this->nameKey($name);
                if ($key === '' || count(explode(' ', $key)) > 7) {
                    continue;
                }
                $found[$key] ??= ['raw' => $raw, 'name' => $name, 'line' => $i, 'lines' => []];
                $found[$key]['lines'][] = $i;
            }
        }
        $people = array_values($found);
        foreach (['eticket' => $etickets, 'passport' => $passports] as $field => $values) {
            $byLine = [];
            foreach ($values as $v) {
                $byLine[$v['line']] ??= $v['value'];
            }
            $used = [];
            foreach ($people as $k => $p) {
                $people[$k][$field] = null;
                foreach ($p['lines'] as $ln) {
                    if (isset($byLine[$ln]) && ! isset($used[$byLine[$ln]])) {
                        $people[$k][$field] = $used[$byLine[$ln]] = $byLine[$ln];
                        break;
                    }
                }
            }
            $left = array_values(array_diff(array_unique(array_column($values, 'value')), array_keys($used)));
            $without = array_keys(array_filter($people, fn ($p) => $p[$field] === null));
            if ($left && count($left) === count($without)) {
                foreach ($without as $n => $k) {
                    $people[$k][$field] = $left[$n];
                }
            }
        }
        return $people;
    }

    /** Same person whatever the order or case: "Riad MIA" = "MIA/RIAD MR". */
    public function nameKey(string $name): string
    {
        $w = preg_split('/[^A-Z]+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY);
        $w = array_values(array_filter($w, fn ($x) => ! in_array($x, ['MR', 'MRS', 'MS', 'MISS', 'MSTR', 'MASTER', 'DR'], true)));
        sort($w);
        return implode(' ', $w);
    }

    /** 13-digit ticket numbers: a known airline prefix, or "ticket" printed close by. Never a phone number. */
    private function etickets(array $up): array
    {
        $out = [];
        foreach ($up as $i => $line) {
            if (! preg_match_all('/(?<![\dA-Z+=_])(\d{3})[\s\-]?(\d{3}\s?\d{3}\s?\d{3}\s?\d|\d{10})(?![\d])/', $line, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $near = implode(' ', array_slice($up, max(0, $i - 4), 5));
            foreach ($m as $t) {
                $no = $t[1][0] . str_replace(' ', '', $t[2][0]);
                if (str_starts_with($no, '880') || str_starts_with($no, '01') || str_starts_with($no, '00')) {
                    continue;   // a Bangladeshi phone number, not a ticket
                }
                if (isset(GenericReader::PREFIX[$t[1][0]]) || preg_match('/TICKET|TKT|ETKT|RECEIPT/', $near)) {
                    $out[] = ['value' => $no, 'line' => $i];
                }
            }
        }
        return $out;
    }

    /** Passport numbers — only with "passport" printed on the line or in the header above it. */
    private function passports(array $up): array
    {
        $out = [];
        foreach ($up as $i => $line) {
            if (! preg_match_all('/(?<![A-Z0-9])([A-Z]{1,2}\d{7,8})(?![A-Z0-9])/', $line, $m)) {
                continue;
            }
            $near = implode(' ', array_slice($up, max(0, $i - 4), 5));
            if (! preg_match('/PASSPORT|\bPPT\b|\bDOCS?\b|TRAVEL DOCUMENT/', $near)) {
                continue;
            }
            foreach ($m[1] as $v) {
                $out[] = ['value' => $v, 'line' => $i];
            }
        }
        return $out;
    }

    // ── flights ─────────────────────────────────────────────────────────

    /** The flights, in travel order, each with what is printed around it. */
    private function legs(array $lines, array $up): array
    {
        $pairs = Patterns::airportPairs($lines);   // as printed: a code is in capitals, prose is not
        if (! $pairs) {
            return [];
        }
        $flights = $this->tokens($up, 'flight');
        // "28/10/2026" only on a page that writes no date out — elsewhere a numeric
        // date is a print stamp ("9/10/26, 4:01 PM") and could be day- or month-first
        $dates = $this->tokens($up, 'date') ?: $this->tokens($up, 'numdate');
        $times = $this->tokens($up, 'time');

        // the sighting of each pair with the most flight details within 4 lines
        $sightings = Patterns::sightings($lines);
        $legs = [];
        foreach ($pairs as $k => [$from, $to]) {
            $best = null;
            $bestScore = -1;
            foreach ($sightings as [$a, $b, $ln]) {
                if ($a !== $from || $b !== $to) {
                    continue;
                }
                $score = 0;
                foreach ([$flights, $dates, $times] as $set) {
                    foreach ($set as $t) {
                        $score += abs($t['line'] - $ln) <= 4 ? 1 : 0;
                    }
                }
                if ($score > $bestScore) {
                    [$best, $bestScore] = [$ln, $score];
                }
            }
            $legs[$k] = ['from' => $from, 'to' => $to, 'line' => $best ?? 0, 'flights' => [], 'dates' => [], 'times' => []];
        }

        // each token to the leg it is printed nearest (a tie goes to the earlier leg)
        foreach (['flights' => $flights, 'dates' => $dates, 'times' => $times] as $kind => $set) {
            foreach ($set as $t) {
                $at = null;
                $dist = self::REACH + 1;
                foreach ($legs as $k => $leg) {
                    $d = abs($t['line'] - $leg['line']);
                    if ($d < $dist || ($d === $dist && $at !== null && $leg['line'] < $legs[$at]['line'])) {
                        [$at, $dist] = [$k, $d];
                    }
                }
                if ($at !== null) {
                    $legs[$at][$kind][] = $t + ['dist' => $dist];
                }
            }
        }

        // one flight number per leg printed on the page: they go in order
        $distinct = [];
        foreach ($legs as $leg) {
            foreach ($leg['flights'] as $f) {
                $distinct[$f['value']] ??= $f['line'] * 1000 + $f['pos'];
            }
        }
        asort($distinct);
        $inOrder = count($distinct) === count($legs) ? array_keys($distinct) : null;

        $yearHint = $this->yearHint($up);
        $out = [];
        $prevDep = null;
        foreach ($legs as $k => $leg) {
            if ($inOrder) {
                $flight = $inOrder[$k];
            } else {
                usort($leg['flights'], fn ($a, $b) => $a['dist'] <=> $b['dist'] ?: $a['line'] <=> $b['line']);
                $flight = $leg['flights'][0]['value'] ?? null;
            }
            [$dep, $arr] = $this->moments($leg, $yearHint, $prevDep);
            $prevDep = $dep ?? $prevDep;
            $out[] = [
                'carrier' => $flight ? strtok($flight, ' ') : null, 'flight_no' => $flight,
                'from' => $leg['from'], 'to' => $leg['to'],
                'from_name' => Patterns::airportName($leg['from']), 'to_name' => Patterns::airportName($leg['to']),
                'dep' => $dep, 'arr' => $arr,
            ];
        }
        // "sorted": a page that prints its flights out of order still yields them as flown
        if (count($out) > 1 && ! in_array(null, array_column($out, 'dep'), true)) {
            $sorted = $out;
            usort($sorted, fn ($a, $b) => strcmp($a['dep'], $b['dep']));
            $joins = true;
            for ($k = 1; $k < count($sorted); $k++) {
                $joins = $joins && ($sorted[$k]['from'] === $sorted[$k - 1]['to'] || substr($sorted[$k]['dep'], 0, 10) > substr($sorted[$k - 1]['dep'], 0, 10));
            }
            if ($joins) {
                $out = $sorted;
            }
        }
        return $out;
    }

    /** [departure, arrival] of one leg from the dates and times assigned to it. */
    private function moments(array $leg, int $yearHint, ?string $notBefore): array
    {
        $byPos = fn ($a, $b) => $a['line'] <=> $b['line'] ?: $a['pos'] <=> $b['pos'];
        $times = $leg['times'];
        usort($times, $byPos);
        $dates = $leg['dates'];
        usort($dates, $byPos);
        if (! $dates) {
            return [null, null];
        }
        $resolve = fn ($d, $after) => $this->ymd($d['value'], $yearHint, $after);

        // the date nearest the leg, and anything printed beside it (dep | arr)
        $near = $dates;
        usort($near, fn ($a, $b) => $a['dist'] <=> $b['dist'] ?: $byPos($a, $b));
        $row = array_values(array_filter($dates, fn ($d) => $d['line'] === $near[0]['line']));
        $onLine = fn ($t) => array_values(array_filter($dates, fn ($d) => $d['line'] === $t['line']));

        $depT = $times[0] ?? null;
        $arrT = $times[1] ?? null;
        $depD = ($depT && ($x = $onLine($depT))) ? $x[0] : $row[0];
        $arrD = ($arrT && ($x = $onLine($arrT))) ? end($x) : ($row[1] ?? null);

        $depDate = $resolve($depD, $notBefore);
        if (! $depDate) {
            return [null, null];
        }
        $dep = $this->at($depDate, $depT['value'] ?? null);
        if (! $arrT) {
            return [$dep, null];
        }
        $arrDate = $arrD ? ($resolve($arrD, $depDate) ?? $depDate) : $depDate;
        $arr = $this->at($arrDate, $arrT['value']);
        if ($arr < $dep && $arrDate === $depDate) {
            if ($depT && $onLine($depT) && $onLine($arrT)) {
                // each time printed with its own date, arrival first ("06:10, 27 Sep" over "04:05, 27 Sep")
                [$dep, $arr] = [$arr, $dep];
            } elseif ($arrD === null) {
                // no arrival date printed: it lands after midnight
                $arr = $this->at($this->plusDays($depDate, 1), $arrT['value']);
            }
        }
        return [$dep, $arr];
    }

    /** The year the ticket is for: the first date printed with one, else this year. */
    private function yearHint(array $up): int
    {
        foreach ($up as $line) {
            if (preg_match('/\b\d{1,2}[\s\-\/]?' . self::MONTH . '\.?,?[\s\-\/]*(20\d{2})\b/', $line, $m)) {
                return (int) $m[2];
            }
        }
        return (int) date('Y');
    }

    /** "08 Oct 26" / "SUNDAY 11 OCT" / "12 September 2026" → Y-m-d; a yearless date rolls to stay on or after $notBefore. */
    private function ymd(string $s, int $yearHint, ?string $notBefore): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $s : null;
        }
        return $this->date($s, $yearHint, $notBefore ? date('Y-m-d', strtotime($notBefore . ' -1 day')) : null);
    }

    /**
     * Every token of one kind, with its line and position.
     *
     * @return array<int, array{value: string, line: int, pos: int}>
     */
    private function tokens(array $up, string $kind): array
    {
        $out = [];
        foreach ($up as $i => $line) {
            switch ($kind) {
                case 'flight':
                    if (! preg_match_all('/(?<![A-Z0-9\/])([A-Z]{2}|[A-Z]\d|\d[A-Z])(\s?-?\s?)0*(\d{2,4})(?![\d]|-\d|[A-Z]{2})/', $line, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                        break;
                    }
                    foreach ($m as $t) {
                        $code = $t[1][0];
                        $whole = $code . $t[2][0] . $t[3][0];
                        if (! isset($this->carriers()[$code]) || preg_match('/^[AB]\d{3}/', $whole)
                            || (ctype_alpha($code) === false && $t[2][0] === '' && strlen($t[3][0]) === 2)) {
                            continue;
                        }
                        $out[] = ['value' => $code . ' ' . ltrim($t[3][0], '0'), 'line' => $i, 'pos' => $t[0][1]];
                    }
                    break;
                case 'date':
                    if (preg_match(self::NOT_TRAVEL_DATE, $line)) {
                        break;
                    }
                    // a year is 20xx or two digits — "12NOV 0945" is a date and a time, not the year 945
                    if (preg_match_all('/(?<!\d)(\d{1,2})\s*[\-\/ ]?\s*' . self::MONTH . '\.?,?(?:[\s\-\/]*(?:20\d{2}|\d{2})(?![\d:]))?/', $line, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                        foreach ($m as $t) {
                            $out[] = ['value' => $t[0][0], 'line' => $i, 'pos' => $t[0][1]];
                        }
                    } elseif (preg_match_all('/(?<![A-Z])' . self::MONTH . '\.?\s+(\d{1,2}),?\s+(20\d{2})/', $line, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                        foreach ($m as $t) {   // "OCT 11, 2026"
                            $out[] = ['value' => $t[2][0] . ' ' . $t[1][0] . ' ' . $t[3][0], 'line' => $i, 'pos' => $t[0][1]];
                        }
                    } elseif (preg_match_all('/\b(20\d{2})-(\d{2})-(\d{2})\b/', $line, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                        foreach ($m as $t) {
                            $out[] = ['value' => $t[0][0], 'line' => $i, 'pos' => $t[0][1]];
                        }
                    }
                    break;
                case 'numdate':
                    if (preg_match(self::NOT_TRAVEL_DATE, $line)
                        || ! preg_match_all('/(?<![\d\/.])(\d{1,2})[\/.\-](\d{1,2})[\/.\-](20\d{2})(?![\d\/.])/', $line, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                        break;
                    }
                    foreach ($m as $t) {
                        [$a, $b, $y] = [(int) $t[1][0], (int) $t[2][0], (int) $t[3][0]];
                        [$day, $mon] = $b > 12 ? [$b, $a] : [$a, $b];      // day first, as printed here, unless it cannot be
                        if (checkdate($mon, $day, $y)) {
                            $out[] = ['value' => sprintf('%04d-%02d-%02d', $y, $mon, $day), 'line' => $i, 'pos' => $t[0][1]];
                        }
                    }
                    break;
                case 'time':
                    if (preg_match(self::NOT_FLIGHT_TIME, $line)) {
                        break;
                    }
                    if (preg_match_all('/(?<![\d:.\/])([01]?\d|2[0-3]):([0-5]\d)(?![\d:])\s*(AM|PM)?(?!\s*HRS?\b)/', $line, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                        foreach ($m as $t) {
                            $h = (int) $t[1][0];
                            $ap = $t[3][0] ?? '';
                            if ($ap === 'PM' && $h < 12) $h += 12;
                            if ($ap === 'AM' && $h === 12) $h = 0;
                            $out[] = ['value' => sprintf('%02d:%s', $h, $t[2][0]), 'line' => $i, 'pos' => $t[0][1]];
                        }
                    } elseif (preg_match('/\d{1,2}\s?[\-\/]?\s?' . self::MONTH . '|\b(DEP\w*|ARR\w*|TIME)\b/', $line)
                        && preg_match_all('/(?<![\d:.\/\-A-Z])([01]\d|2[0-3])([0-5]\d)(?![\d:.\-\/])/', $line, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                        // GDS rows print "0130" — only on a row that also holds a date or says dep / arr / time,
                        // never a year ("OCT 2026", "OCTOBER 2026"), a flight number ("SV 1023") or an amount ("BDT 1250")
                        foreach ($m as $t) {
                            $before = substr($line, max(0, $t[0][1] - 8), min(8, $t[0][1]));
                            $year = preg_match('/^(19|20)\d\d$/', $t[0][0]) && preg_match('/[A-Z]\.?,?\s*$/', $before);
                            if ($year || preg_match('/(?<![A-Z0-9])[A-Z0-9]{2}\s?-?\s?$|\b(BDT|USD|AED|SAR|QAR|INR|EUR|GBP|MYR|NUC|KWD|OMR|BHD|TK)\s*$|[,.]\s*$/', $before)) {
                                continue;
                            }
                            $out[] = ['value' => $t[1][0] . ':' . $t[2][0], 'line' => $i, 'pos' => $t[0][1]];
                        }
                    }
                    break;
            }
        }
        return $out;
    }

    private static ?array $carriers = null;

    /** Airline codes a flight number can start with: ours, the ones Ticketing knows, and any by ticket prefix. */
    private function carriers(): array
    {
        if (self::$carriers === null) {
            $codes = array_merge(GenericReader::AIRLINES, array_values(Lessons::AIRLINE_NAMES), array_values(GenericReader::PREFIX));
            try {
                foreach (DB::table('airlines')->pluck('name') as $name) {
                    if (preg_match('/\(([A-Z0-9]{2})\)/', (string) $name, $m)) {
                        $codes[] = $m[1];
                    }
                }
            } catch (\Throwable $e) {
                // no table — the built-in list alone
            }
            self::$carriers = array_flip($codes);
        }
        return self::$carriers;
    }
}
