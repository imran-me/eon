<?php

namespace App\Services\TicketImport;

use App\Services\TicketImport\Readers\Reader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads ANY ticket, and keeps learning from staff (owner, 2026-10-11:
 * "fill what it can, the user fills the rest — and it remembers";
 * "continuously learn after each import … realtime … saves in the db").
 *
 * Three ways of reading a value, in the order they are trusted:
 *
 *  1. a label STAFF TAUGHT — "the value next to / under THIS label is the PNR"
 *     (`ticket_import_lessons`), learned on THIS layout first, then on any;
 *  2. its TYPE — Detector finds every PNR, person, ticket number, passport,
 *     flight number, date and time by what it looks like and builds the
 *     flights from the airport pairs, whatever the layout;
 *  3. a common label (BUILTIN) — "Name:", "PNR:", "Ticket Number | 997…".
 *
 * A dedicated reader (TicketPdfImporter::readers()) beats all three: this only
 * fills what is still empty.
 *
 * Learning, after every Save (learn()):
 *  - what staff typed that the import did not give is found in the PDF and the
 *    label beside / above it remembered; with no label beside it, its place
 *    under the nearest heading ("2 rows under 'Passport', cell 1") is
 *    remembered for this layout;
 *  - what the import got WRONG costs the rule that gave it trust (`misses`);
 *    a rule with more misses than hits stops being used — on this layout only;
 *  - what a taught rule got RIGHT earns it trust (`hits`), so it ranks first.
 *
 * A layout is recognised by its fingerprint — the set of labels it prints
 * (`ticket_import_layouts`) — so a lesson learned on one airline's ticket is
 * applied first to the next ticket of that same design.
 *
 * Only label text is stored — never a name, passport, ticket number or PNR.
 */
class Lessons extends Reader
{
    /** field => labels a ticket prints it under. Longest first. */
    public const BUILTIN = [
        'pnr'      => ['AIRLINE RESERVATION CODE', 'YOUR BOOKING REFERENCE', 'BOOKING REFERENCE (PNR)', 'BOOKING REFERENCE',
                       'CONFIRMATION NUMBER', 'CONFIRMATION CODE', 'RESERVATION CODE', 'RESERVATION PNR', 'RECORD LOCATOR',
                       'BOOKING CODE', 'BOOKING REF', 'AIRLINE PNR', 'PNR NO', 'PNR'],
        'eticket'  => ['E-TICKET NUMBER', 'E-TICKET NO.', 'E-TICKET NO', 'ETICKET NO', 'TICKET NUMBER', 'TICKET NO.', 'TICKET NO',
                       'TICKET #', 'E-TICKET', 'ETKT'],
        'name'     => ['NAME OF PASSENGER', 'NAME OF PASSANGER', 'PASSENGER NAME', 'PAX NAME', 'PREPARED FOR', 'TRAVELLERS',
                       'TRAVELLER', 'TRAVELER', 'PASSENGERS', 'PASSENGER', 'NAME'],
        'passport' => ['PASSPORT NUMBER', 'PASSPORT NO.', 'PASSPORT NO', 'PASSPORT'],
        'flight'   => ['FLIGHT NUMBER', 'FLIGHT NO.', 'FLIGHT NO', 'FLT NO.', 'FLT NO', 'FLIGHT'],
        'from'     => ['DEPARTURE AIRPORT', 'DEPARTURE FROM', 'DEPARTURE', 'DEPARTS', 'ORIGIN', 'DEPART', 'FROM'],
        'to'       => ['ARRIVAL AIRPORT', 'DESTINATION', 'ARRIVAL', 'ARRIVES', 'ARRIVE', 'TO'],
        'date'     => ['DATE OF TRAVEL', 'DEPARTURE DATE', 'TRAVEL DATE', 'FLIGHT DATE', 'DATE'],
        'time'     => ['DEPARTURE TIME', 'DEPARTURE AT', 'DEPARTURE', 'DEPARTS', 'TIME'],
        'arr_time' => ['ARRIVAL TIME', 'ARRIVAL AT', 'ARRIVAL'],
        'total'    => ['GRAND TOTAL', 'TOTAL AMOUNT', 'TOTAL FARE', 'TOTAL'],
    ];

    /** Airline names → code, for a ticket that prints "US-Bangla Airlines | Flight No - 362". */
    public const AIRLINE_NAMES = [
        'BIMAN' => 'BG', 'US-BANGLA' => 'BS', 'US BANGLA' => 'BS', 'NOVOAIR' => 'VQ', 'NOVO AIR' => 'VQ', 'AIR ASTRA' => '2A',
        'EMIRATES' => 'EK', 'QATAR' => 'QR', 'SAUDIA' => 'SV', 'SAUDI ARABIAN' => 'SV', 'ETIHAD' => 'EY', 'GULF AIR' => 'GF',
        'OMAN AIR' => 'WY', 'KUWAIT AIRWAYS' => 'KU', 'TURKISH' => 'TK', 'SINGAPORE AIRLINES' => 'SQ', 'MALAYSIA AIRLINES' => 'MH',
        'MALINDO' => 'OD', 'BATIK' => 'OD', 'THAI AIRWAYS' => 'TG', 'AIR INDIA EXPRESS' => 'IX', 'AIR INDIA' => 'AI', 'INDIGO' => '6E',
        'CATHAY' => 'CX', 'CHINA SOUTHERN' => 'CZ', 'CHINA EASTERN' => 'MU', 'SRILANKAN' => 'UL', 'FLYDUBAI' => 'FZ',
        'AIR ARABIA' => 'G9', 'JAZEERA' => 'J9', 'FLYNAS' => 'XY', 'SALAM AIR' => 'OV', 'SALAMAIR' => 'OV', 'PEGASUS' => 'PC',
        'AIRASIA' => 'AK', 'AIR ASIA' => 'AK', 'MALDIVIAN' => 'Q2', 'PHILIPPINE AIRLINES' => 'PR', 'BRITISH AIRWAYS' => 'BA',
        'AMERICAN AIRLINES' => 'AA', 'LUFTHANSA' => 'LH', 'ETHIOPIAN' => 'ET', 'EGYPTAIR' => 'MS', 'AZERBAIJAN' => 'J2',
        'UZBEKISTAN' => 'HY', 'FLY ADEAL' => 'F3', 'FLYADEAL' => 'F3', 'VISTARA' => 'UK',
    ];

    /** Fields staff can teach (what the Ticket Entry card holds). */
    public const TEACHABLE = ['pnr', 'eticket', 'name', 'passport', 'flight', 'from', 'to', 'date'];

    /** Words that make a "value" really another label. */
    private const LABEL_WORDS = '/\b(NAME|TYPE|DATE|NUMBER|TICKET|BIRTH|PASSENGER|CONTACT|DETAILS|CLASS|SEAT|STATUS|BAGGAGE|FLIGHT|AIRLINE|REFERENCE|BOOKING|ISSUE|FROM|DEPART\w*|ARRIV\w*|SECTOR|DURATION|TERMINAL)\b/';

    /** Words a ticket's labels are made of — what a layout's fingerprint is built from. */
    private const LABEL_VOCAB = '/\b(NAME|DATE|PNR|TICKET|E-?TICKET|FLIGHT|FROM|TO|DEPART\w*|ARRIV\w*|PASSENGERS?|PAX|BOOKING|RESERVATION|REFERENCE|REF|CODE|CLASS|CABIN|STATUS|BAGGAGE|SEAT|TERMINAL|DURATION|AIRLINE|ISSUE[DS]?|ISSUING|NUMBER|NO|FARE|TOTAL|TAX\w*|ITINERARY|RECEIPT|ORDER|PAYMENT|MEALS?|AIRCRAFT|PASSPORT|TYPE|CONTACT|DETAILS|INFORMATION|SECTOR|ROUTE|STOPS?|VIA|TRANSIT|LAYOVER|CHECK.?IN|EQUIPMENT|OPERATED|VALIDITY|ALLOWANCE|BASIS|ENDORSEMENTS?|AGENCY|AGENT|OFFICE|IATA|GDS|LOCATOR|CONFIRMATION|SERVICES?|CHARGES?|SUMMARY|PREPARED|TRAVELL?ERS?|GUESTS?|PERSONS?|SERIAL|COUPONS?|JOURNEY|TRIP|VOUCHER|ONWARD|RETURN|OUTBOUND|INBOUND|DOC\w*|E-?TKT|BOARDING|GATE|SEGMENTS?|LEGS?|CARRIER|OPERATOR|ID)\b/';

    /** A cell that is a person, not a label — never stored. */
    private const PERSONISH = '/[A-Z]\s*\/\s*[A-Z]|^(MR|MRS|MS|MISS|MSTR|MASTER|DR)\.?\s/';

    private static ?array $learned = null;

    private static ?array $layouts = null;

    private static ?bool $smart = null;

    private static ?array $airports = null;

    /** The remembered layout this PDF matched, if any. */
    public ?int $layoutId = null;

    /** field => where its value came from: ['anchor', 'position', 'learned', 'id']. */
    public array $sources = [];

    /** Fields filled by their type alone (Detector) — staff are asked to check them. */
    public array $guessed = [];

    /** Airport codes we know: the city list plus Airport Management. */
    private static function airports(): array
    {
        if (self::$airports === null) {
            self::$airports = array_flip(array_values(Cities::MAP));
            try {
                foreach (DB::table('airports')->whereNotNull('code')->pluck('code') as $c) {
                    self::$airports[strtoupper(trim($c))] = true;
                }
            } catch (\Throwable $e) {
                // no table — the city list alone
            }
        }
        return self::$airports;
    }

    public function key(): string { return 'labels'; }

    public function label(): string { return 'Read by labels'; }

    public function matches(string $text): bool { return true; }

    public function read(array $lines): array
    {
        return $this->fill($lines, TicketPdfImporter::blank());
    }

    public static function available(): bool
    {
        try {
            return Schema::hasTable('ticket_import_lessons');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Layout memory and trust (migration 2026_10_11_000003) — without it, labels are learned as before. */
    public static function smart(): bool
    {
        if (self::$smart === null) {
            try {
                self::$smart = self::available() && Schema::hasTable('ticket_import_layouts')
                    && Schema::hasColumn('ticket_import_lessons', 'misses');
            } catch (\Throwable $e) {
                self::$smart = false;
            }
        }
        return self::$smart;
    }

    /** Every taught rule: [id, layout_id, field, anchor, position, hits, misses]. */
    private static function learnedRows(): array
    {
        if (self::$learned === null) {
            self::$learned = [];
            if (self::available()) {
                try {
                    $cols = self::smart() ? ['id', 'layout_id', 'field', 'anchor', 'position', 'hits', 'misses'] : ['id', 'field', 'anchor', 'position', 'hits'];
                    self::$learned = DB::table('ticket_import_lessons')->orderByDesc('hits')->limit(2000)->get($cols)
                        ->map(fn ($r) => ['id' => (int) $r->id, 'layout' => (int) ($r->layout_id ?? 0), 'field' => $r->field,
                            'anchor' => $r->anchor, 'position' => $r->position, 'hits' => (int) $r->hits, 'misses' => (int) ($r->misses ?? 0)])
                        ->all();
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
        return self::$learned;
    }

    /**
     * [[field, anchor, position, meta]] in the order they are tried:
     * taught on this layout, taught anywhere, then built in. A rule that has
     * been wrong more often than right is left out; so is a built-in label
     * staff have overruled on this layout.
     */
    private function rules(): array
    {
        $rows = self::learnedRows();
        $rules = [];
        $blocked = [];
        foreach (array_unique(array_filter([$this->layoutId, 0], fn ($v) => $v !== null)) as $scope) {
            $here = array_filter($rows, fn ($r) => $r['layout'] === $scope);
            usort($here, fn ($a, $b) => ($b['hits'] - $b['misses']) <=> ($a['hits'] - $a['misses']));
            foreach ($here as $r) {
                if (Patterns::isPlace($r['anchor'])) {
                    continue;   // stored before places were refused as labels — never applied
                }
                if ($r['misses'] > $r['hits']) {
                    $blocked[$r['field'] . '|' . $r['anchor'] . '|' . $r['position']] = true;
                    continue;
                }
                $rules[] = [$r['field'], $r['anchor'], $r['position'], ['learned' => true, 'id' => $r['id'], 'layout' => $scope]];
            }
        }
        foreach (self::BUILTIN as $field => $anchors) {
            foreach ($anchors as $a) {
                foreach (['same', 'below'] as $pos) {
                    if (! isset($blocked[$field . '|' . $a . '|' . $pos])) {
                        $rules[] = [$field, $a, $pos, ['learned' => false, 'id' => null, 'layout' => null]];
                    }
                }
            }
        }
        return $rules;
    }

    // ── layouts ─────────────────────────────────────────────────────────

    /** The labels a PDF prints — its design, without anyone's data. */
    public function fingerprint(array $lines): array
    {
        $set = [];
        foreach ($lines as $line) {
            foreach ($this->cells(str_replace('<<PAGE>>', '', (string) $line)) as $cell) {
                // "Name: Rahim Uddin" — the label only, never what follows it
                $cell = str_contains($cell, ':') ? strstr($cell, ':', true) : $cell;
                $c = trim(preg_replace('/\s+/', ' ', strtoupper($cell)), " \t:#.-|»*");
                if ($c === '' || strlen($c) > 40 || preg_match('/\d/', $c) || preg_match(self::PERSONISH, $c)
                    || count(explode(' ', $c)) > 5 || ! preg_match(self::LABEL_VOCAB, $c)) {
                    continue;
                }
                $set[$c] = true;
            }
        }
        $set = array_keys($set);
        sort($set);
        return array_slice($set, 0, 200);
    }

    /** The remembered layout these lines are (Jaccard ≥ 0.5 on their labels); $remember adds a new one. */
    public function layoutFor(array $lines, bool $remember = false, ?string $name = null): ?int
    {
        if (! self::smart()) {
            return null;
        }
        $mine = $this->fingerprint($lines);
        if (count($mine) < 3) {
            return null;            // too few labels to tell one design from another
        }
        try {
            self::$layouts ??= DB::table('ticket_import_layouts')->orderByDesc('uses')->limit(500)->get(['id', 'labels'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'labels' => json_decode((string) $r->labels, true) ?: []])->all();
            $best = null;
            $bestScore = 0.5;
            $flip = array_flip($mine);
            foreach (self::$layouts as $l) {
                $both = count(array_filter($l['labels'], fn ($x) => isset($flip[$x])));
                $any = count($mine) + count($l['labels']) - $both;
                $score = $any ? $both / $any : 0;
                if ($score >= $bestScore) {
                    [$best, $bestScore] = [$l['id'], $score];
                }
            }
            if ($best !== null) {
                if ($remember) {
                    DB::table('ticket_import_layouts')->where('id', $best)->update(['uses' => DB::raw('uses + 1'), 'last_used_at' => now(), 'updated_at' => now()]);
                }
                return $best;
            }
            if (! $remember) {
                return null;
            }
            $id = (int) DB::table('ticket_import_layouts')->insertGetId([
                'signature' => md5(implode('|', $mine)), 'labels' => json_encode($mine), 'name' => $name ? mb_substr($name, 0, 80) : null,
                'uses' => 1, 'last_used_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            self::$layouts = null;
            return $id;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    // ── reading ─────────────────────────────────────────────────────────

    /**
     * Fill what is still empty in a reader's result. $preferTypes: the reader
     * only guessed too (the general reader) — its flights give way to the ones
     * read by type when those are at least as complete and consistent.
     */
    public function fill(array $lines, array $result, bool $preferTypes = false): array
    {
        $this->layoutId = $this->layoutFor($lines);
        $x = $this->extract($lines);
        $first = fn ($f) => $x[$f][0] ?? null;
        $taught = fn ($f) => ! empty($x[$f]) && ($this->sources[$f]['learned'] ?? false);
        try {
            $d = (new Detector())->detect($lines);
        } catch (\Throwable $e) {
            report($e);
            $d = ['people' => [], 'legs' => [], 'etickets' => [], 'passports' => []];
        }
        $this->guessed = [];

        $result['pnr'] ??= $first('pnr');

        // people: a taught label, else by type, else a common label
        if (! $result['passengers']) {
            if (! $taught('name') && $d['people']) {
                foreach ($d['people'] as $p) {
                    $result['passengers'][] = $this->passenger($p['raw'], ['eticket' => $p['eticket'], 'passport_no' => $p['passport']]);
                }
                $this->guessed[] = 'name';
            } elseif (! empty($x['name'])) {
                $one = count($x['name']) === 1;
                foreach ($x['name'] as $k => $raw) {
                    $result['passengers'][] = $this->passenger($raw, [
                        'eticket'     => $x['eticket'][$k] ?? ($one ? $first('eticket') : null),
                        'passport_no' => $x['passport'][$k] ?? ($one ? $first('passport') : null),
                    ]);
                }
            }
        }
        // ticket number / passport still missing: the one printed with that person, else a lone label
        $det = new Detector();
        $byKey = [];
        foreach ($d['people'] as $p) {
            $byKey[$det->nameKey($p['name'])] = $p;
        }
        foreach ($result['passengers'] as $k => $p) {
            $mine = $byKey[$det->nameKey((string) $p['name'])] ?? (count($d['people']) === 1 && count($result['passengers']) === 1 ? $d['people'][0] : null);
            foreach (['eticket' => 'eticket', 'passport_no' => 'passport'] as $to => $from) {
                if (empty($p[$to]) && $mine && ! empty($mine[$from])) {
                    $result['passengers'][$k][$to] = $mine[$from];
                    $this->guessed[] = $from;
                }
            }
        }
        if (count($result['passengers']) === 1) {
            $result['passengers'][0]['eticket'] ??= $first('eticket');
            $result['passengers'][0]['passport_no'] ??= $first('passport');
        }

        // whatever the layout: the PNR by its pattern
        $result['pnr'] ??= Patterns::pnr($lines);

        // flights: by type (Detector), unless staff taught this layout's route labels
        $taughtRoute = $taught('from') || $taught('to') || $taught('flight') || $taught('date');
        if ($preferTypes && $result['segments'] && $d['legs'] && ! $taughtRoute
            && self::quality($d['legs']) >= self::quality($result['segments'])) {
            $result['segments'] = [];
        }
        if (! $result['segments'] && $d['legs'] && ! $taughtRoute) {
            $result['segments'] = array_map(fn ($l) => $this->segment($l), $d['legs']);
            $this->guessed[] = 'route';
        }
        if (! $result['segments']) {
            $result['segments'] = $this->segmentsByLabel($lines, $x);
        }
        // a flight still missing its number or times takes them from the same leg read by type
        foreach ($result['segments'] as $k => $s) {
            foreach ($d['legs'] as $leg) {
                if ($leg['from'] !== $s['from'] || $leg['to'] !== $s['to']) {
                    continue;
                }
                foreach (['flight_no', 'carrier', 'dep', 'arr'] as $f) {
                    // a departure read without its time ("… 00:00") gives way to one read with it
                    $timeless = $f === 'dep' && ! empty($s['dep']) && str_ends_with($s['dep'], ' 00:00') && ! empty($leg['dep']) && ! str_ends_with($leg['dep'], ' 00:00');
                    if ((empty($s[$f]) || $timeless) && ! empty($leg[$f])) {
                        $result['segments'][$k][$f] = $leg[$f];
                        $this->guessed[] = $f === 'flight_no' || $f === 'carrier' ? 'flight' : 'date';
                    }
                }
                break;
            }
        }
        $result['carrier'] ??= $result['segments'][0]['carrier'] ?? (($f = $first('flight')) && str_contains($f, ' ') ? strtok($f, ' ') : null);

        if (count($result['passengers']) === 1 && empty($result['passengers'][0]['fare']) && $first('total')) {
            $result['passengers'][0]['fare'] = ['base' => null, 'tax' => null, 'total' => $first('total'), 'tax_breakdown' => null];
        }
        $this->guessed = array_values(array_unique($this->guessed));
        return $result;
    }

    /**
     * How believable a list of flights is: a point for each flight number,
     * departure time and arrival read; points off for a journey that does
     * not add up (two legs leaving at the same minute, landing before take-off,
     * a later leg leaving before the one before it).
     */
    private static function quality(array $segs): int
    {
        $q = 0;
        $prev = null;
        $seen = [];
        foreach ($segs as $s) {
            $q += (empty($s['flight_no']) ? 0 : 1) + (empty($s['arr']) ? 0 : 1)
                + (! empty($s['dep']) && ! str_ends_with((string) $s['dep'], ' 00:00') ? 1 : 0);
            if (! empty($s['dep'])) {
                $q -= isset($seen[$s['dep']]) ? 2 : 0;
                $q -= $prev && $s['dep'] < $prev ? 2 : 0;
                $q -= ! empty($s['arr']) && $s['arr'] < $s['dep'] ? 2 : 0;
                $seen[$s['dep']] = true;
                $prev = $s['dep'];
            }
        }
        return $q;
    }

    /** Flights from the From / To / Flight / Date labels (the airport pairs on the page when there are none). */
    private function segmentsByLabel(array $lines, array $x): array
    {
        $first = fn ($f) => $x[$f][0] ?? null;
        $pairs = Patterns::airportPairs($lines);
        if (count($pairs) > max(count($x['from'] ?? []), 0) || empty($x['from']) || empty($x['to'])) {
            $x['from'] = array_column($pairs, 0) ?: ($x['from'] ?? []);
            $x['to'] = array_column($pairs, 1) ?: ($x['to'] ?? []);
        }
        // a bare flight number gets the airline the ticket names
        if (! empty($x['flight']) && ! str_contains($x['flight'][0], ' ')) {
            $u = strtoupper(implode("\n", $lines));
            foreach (self::AIRLINE_NAMES as $name => $code) {
                if (str_contains($u, $name)) {
                    $x['flight'] = array_map(fn ($n) => str_contains($n, ' ') ? $n : $code . ' ' . $n, $x['flight']);
                    break;
                }
            }
        }
        $segs = [];
        foreach ($x['from'] ?? [] as $k => $from) {
            $to = $x['to'][$k] ?? null;
            if (! $to || $to === $from) {
                continue;
            }
            $flight = $x['flight'][$k] ?? null;
            $date = $x['date'][$k] ?? $first('date');
            $segs[] = $this->segment([
                'carrier' => $flight && str_contains($flight, ' ') ? strtok($flight, ' ') : null, 'flight_no' => $flight,
                'from' => $from, 'to' => $to,
                'dep' => $date ? $this->at($date, $x['time'][$k] ?? null) : null,
                'arr' => $date && isset($x['arr_time'][$k]) ? $this->at($date, $x['arr_time'][$k]) : null,
            ]);
        }
        return $segs;
    }

    /** field => [values in print order]; where each came from is left in $this->sources. */
    public function extract(array $lines): array
    {
        $rows = array_map(fn ($l) => $this->cells(str_replace('<<PAGE>>', '', (string) $l)), $lines);
        $out = [];
        $seen = [];
        $this->sources = [];
        $many = fn ($field) => in_array($field, ['name', 'eticket', 'passport'], true);
        foreach ($this->rules() as [$field, $anchor, $pos, $meta]) {
            if (! empty($out[$field])) {
                continue;       // a field fills from its best label only
            }
            $add = function (int $line, ?string $v) use (&$out, &$seen, $field) {
                if ($v !== null && ! isset($seen[$field][$line])) {
                    $out[$field][] = $v;
                    $seen[$field][$line] = true;
                }
            };
            foreach ($rows as $i => $cells) {
                foreach ($cells as $j => $cell) {
                    $u = strtoupper($cell);
                    if (preg_match('/^r(\d+)c(\d+)$/', $pos, $pm)) {
                        // a place: N rows under this heading, cell C
                        if (trim(preg_replace('/\s+/', ' ', $u), " \t:#.-|»*") !== $anchor || ! isset($rows[$i + (int) $pm[1]])) {
                            continue;
                        }
                        $under = $rows[$i + (int) $pm[1]];
                        $cand = $under[(int) $pm[2]] ?? (count($under) === 1 ? $under[0] : null);
                        $add($i + (int) $pm[1], $cand === null ? null : $this->shape($field, $cand));
                    } elseif ($pos === 'same') {
                        if (! preg_match('/^' . preg_quote($anchor, '/') . '(?=$|[\s:#(.\-])/', $u)) {
                            continue;
                        }
                        $rest = ltrim(substr($cell, strlen($anchor)), " \t:#.-");
                        if ($rest === '') {
                            $rest = $cells[$j + 1] ?? '';
                            // the cell beside it is another label ("Airline PNR | Galileo PNR"), not a value
                            if ($this->labelLike($rest) && preg_match('/\b(PNR|CODE|REF\w*|NUMBER|NO|ID|DATE|NAME)\b/i', $rest)) {
                                continue;
                            }
                        }
                        $add($i, $this->shape($field, $rest));
                    } elseif ($pos === 'below' && trim($u, " :#") === $anchor) {
                        // a table header: the values are in the cells under it
                        for ($r = $i + 1, $n = min(count($rows), $i + 10); $r < $n; $r++) {
                            $under = $rows[$r];
                            $cand = $under[$j] ?? (count($under) === 1 ? $under[0] : null);
                            $v = $cand === null ? null : $this->shape($field, $cand);
                            // the right-most header heads the right-most value, even when
                            // the rows have more cells than the header ("… | KWAEEM")
                            if ($v === null && $j === count($cells) - 1 && count($under) > 1) {
                                $v = $this->shape($field, end($under));
                            }
                            if ($v === null) {
                                break;
                            }
                            $add($r, $v);
                            if (! $many($field)) {
                                break;
                            }
                        }
                    }
                }
            }
            if (! empty($out[$field])) {
                $this->sources[$field] = ['anchor' => $anchor, 'position' => $pos] + $meta;
            }
        }
        foreach ($out as $f => $vals) {
            $out[$f] = array_values(array_unique($vals));
        }
        return $out;
    }

    /** The value a field would take from this text, or null when it does not look like one. */
    private function shape(string $field, string $s): ?string
    {
        $s = trim($s);
        $u = strtoupper($s);
        if ($s === '') {
            return null;
        }
        switch ($field) {
            case 'pnr':
                if (preg_match('/^(?:PNR\s*:?\s*)?([A-Z0-9]{5,8})\b/', $u, $m) && preg_match('/[A-Z]/', $m[1])
                    && ! preg_match('/^(ADULT|CHILD|ECONOMY|CONFIRMED|BOOKING|SAMPLE|NUMBER|STATUS|TICKET|ISSUED|PASSENGER|DETAILS|NONE|GALILEO|AMADEUS|SABRE|AIRLINE)$/', $m[1])
                    && ! preg_match('/^[A-Z]+\s+(PNR|CODE|REF\w*|NUMBER|NO\.?)\b/', $u)) {
                    return $m[1];
                }
                return null;
            case 'eticket':
                return preg_match('/\b(\d{3})[\s\-]?(\d{3}\s?\d{3}\s?\d{3}\s?\d|\d{10})\b/', $s, $m) ? $m[1] . str_replace(' ', '', $m[2]) : null;
            case 'passport':
                return preg_match('/^([A-Z]{1,2}\d{6,8})\b/', $u, $m) ? $m[1] : null;
            case 'flight':
                if (preg_match('/^([A-Z]{2}|[A-Z]\d|\d[A-Z])\s?-?\s?0*(\d{1,4})\b/', $u, $m)) {
                    return $m[1] . ' ' . $m[2];
                }
                // "Flight No - 362": the number alone — fill() adds the airline
                return preg_match('/^0*(\d{2,4})\b/', $u, $m) ? $m[1] : null;
            case 'from':
            case 'to':
                if (preg_match('/\(([A-Z]{3})\)/', $s, $m)) {
                    return $m[1];
                }
                // a bare "DAC" only when it is a real airport and not a label ("PNR:")
                if (preg_match('/^([A-Z]{3})(?:\b|$)(?!\s*:)/', $s, $m) && isset(self::airports()[$m[1]])) {
                    return $m[1];
                }
                $codes = Cities::inOrder($u);
                return $codes[0] ?? null;
            case 'date':
                return preg_match('/\d/', $s) ? $this->date($s) : null;
            case 'time':
            case 'arr_time':
                if (preg_match('/\b(\d{1,2})[:.](\d{2})\s*(AM|PM)?/i', $s, $m)) {
                    $h = (int) $m[1];
                    $ap = strtoupper($m[3] ?? '');
                    if ($ap === 'PM' && $h < 12) $h += 12;
                    if ($ap === 'AM' && $h === 12) $h = 0;
                    return $h < 24 ? sprintf('%02d:%02d', $h, $m[2]) : null;
                }
                return preg_match('/\b([01]\d|2[0-3])([0-5]\d)H\b/', $u, $m) ? $m[1] . ':' . $m[2] : null;
            case 'total':
                return preg_match('/\d/', $s) && ($v = $this->money(str_replace(' ', '', $s))) ? (string) $v : null;
            case 'name':
                $s = preg_replace('/^[»•\-\*\s]*\d*\.?\s*/u', '', $s);
                $s = preg_replace('/\s*[,(].*?(ADULT|CHILD|INFANT|ADT|CHD|INF).*$/i', '', $s);
                if (preg_match('/^([A-Z][A-Z\' \-]+),\s*([A-Z][A-Z\' \-]+)$/', $s, $m)) {   // "DELA CRUZ, JUAN MIGUEL"
                    $s = $m[1] . '/' . $m[2];
                }
                $s = trim($s);
                if (! preg_match('/^[A-Za-z][A-Za-z .\'\/\-]{2,60}$/', $s)) {
                    return null;
                }
                $words = preg_split('/[\s\/]+/', preg_replace('/^(MR|MRS|MS|MISS|MSTR|DR)\.?\s+/i', '', $s));
                if (count(array_filter($words, fn ($w) => strlen($w) >= 2)) < 2 || preg_match(self::LABEL_WORDS, strtoupper($s))
                    || preg_match(Detector::NOT_NAME, strtoupper($s))) {
                    return null;
                }
                return $s;
        }
        return null;
    }

    // ── learning ────────────────────────────────────────────────────────

    /**
     * Staff saved a card.
     *
     * @param array $taught    field => what staff saved, where the import gave something else or nothing
     * @param array $wrong     field => what the import gave that staff changed
     * @param array $confirmed fields the import gave and staff kept
     * @return string[] what was learned, "field ← LABEL"
     */
    public function learn(array $lines, array $taught, ?int $userId = null, array $wrong = [], array $confirmed = [], ?string $layoutName = null): array
    {
        if (! self::available()) {
            return [];
        }
        $this->layoutId = $this->layoutFor($lines, true, $layoutName);
        $kept = [];

        // what the import read, and from which rule — to blame or credit that rule
        if (self::smart() && ($wrong || $confirmed)) {
            $read = $this->extract($lines);
            $norm = fn ($v) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $v));
            foreach ($wrong as $field => $value) {
                $src = $this->sources[$field] ?? null;
                if ($src && $norm($read[$field][0] ?? '') === $norm($value) && ($src['learned'] || $this->layoutId)) {
                    // a built-in label is overruled on this layout only; a taught one where it was taught
                    $this->bump($field, $src['anchor'], $src['position'], $src['learned'] ? $src['layout'] : $this->layoutId, 'misses', $userId);
                    $kept[] = $field . ' ✗ ' . $src['anchor'];
                }
            }
            foreach ($confirmed as $field) {
                $src = $this->sources[$field] ?? null;
                if ($src && $src['learned']) {
                    $this->bump($field, $src['anchor'], $src['position'], $src['layout'], 'hits', $userId);
                }
            }
        }

        $rows = array_map(fn ($l) => $this->cells(str_replace('<<PAGE>>', '', (string) $l)), $lines);
        foreach ($taught as $field => $value) {
            if (! in_array($field, self::TEACHABLE, true) || trim((string) $value) === '') {
                continue;
            }
            $hit = $this->locate($rows, $field, (string) $value);
            if (! $hit) {
                continue;
            }
            [$i, $j, $prefix] = $hit;
            // its label: in front of it in the cell, the cell before it, or the header above it —
            // the first that is wording and carries nobody's data
            $options = [[$prefix, 'same']];
            if ($j > 0) {
                $options[] = [$rows[$i][$j - 1], 'same'];
            }
            if ($i > 0) {
                $above = $rows[$i - 1];
                $cand = $above[$j] ?? (count($above) === 1 ? $above[0] : null);
                if (($cand === null || ! $this->labelLike($cand)) && $j === count($rows[$i]) - 1) {
                    $cand = end($above);      // right-most value under the right-most header
                }
                $options[] = [$cand, 'below'];
            }
            $anchor = null;
            $pos = 'same';
            foreach ($options as [$cand, $where]) {
                if ($cand !== null && $this->labelLike($cand) && $this->safeAnchor($cand, $taught)) {
                    [$anchor, $pos] = [$cand, $where];
                    break;
                }
            }
            // no label beside it or right above: its place under the nearest heading — this layout only
            if ($anchor === null && $this->layoutId) {
                for ($up = 2; $up <= 4 && $i - $up >= 0; $up++) {
                    $head = $rows[$i - $up];
                    $cand = $head[$j] ?? (count($head) === 1 ? $head[0] : null);
                    if ($cand !== null && $this->labelLike($cand) && preg_match(self::LABEL_VOCAB, strtoupper($cand))) {
                        $anchor = $cand;
                        $pos = 'r' . $up . 'c' . $j;
                        break;
                    }
                }
            }
            if ($anchor === null || ! $this->safeAnchor($anchor, $taught)) {
                continue;
            }
            $anchor = mb_substr(trim(preg_replace('/\s+/', ' ', strtoupper($anchor)), " \t:#.-|»*"), 0, 80);
            $builtin = in_array($anchor, self::BUILTIN[$field] ?? [], true) && in_array($pos, ['same', 'below'], true);
            // one generic word ("Number", "Code") is a fine label on one design, too vague on all of them
            $vague = (bool) preg_match('/^(NUMBER|NO|NAME|DATE|CODE|ID|TYPE|DETAILS?|INFORMATION|INFO|VALUE|REF|TIME|FROM|TO|STATUS|SERIAL|DOCUMENT)$/', $anchor);
            // on this layout always (it ranks the label first here); anywhere only when it is new, specific and not a place
            if ($this->layoutId) {
                $this->bump($field, $anchor, $pos, $this->layoutId, 'hits', $userId);
            }
            if (! $builtin && ! $vague && ! str_starts_with($pos, 'r')) {
                $this->bump($field, $anchor, $pos, 0, 'hits', $userId);
            }
            if ($this->layoutId || (! $builtin && ! $vague && ! str_starts_with($pos, 'r'))) {
                $kept[] = $field . ' ← ' . $anchor;
            }
        }
        self::$learned = null;
        return $kept;
    }

    /** One more hit / miss for a rule; the rule is created on its first. */
    private function bump(string $field, string $anchor, string $pos, ?int $layout, string $col, ?int $userId): void
    {
        try {
            $key = ['field' => $field, 'anchor' => $anchor, 'position' => $pos];
            if (self::smart()) {
                $key['layout_id'] = (int) $layout;
            } elseif ($layout) {
                return;     // no layout memory yet — only rules for every layout
            }
            $q = DB::table('ticket_import_lessons')->where($key);
            if ($q->exists()) {
                $q->update([$col => DB::raw($col . ' + 1'), 'last_used_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table('ticket_import_lessons')->insert($key + [
                    'hits' => $col === 'hits' ? 1 : 0,
                    'created_by' => $userId, 'last_used_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ] + ($col === 'misses' ? ['misses' => 1] : []));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** A label may be stored only when it carries nobody's data: not a person, not a value staff typed. */
    private function safeAnchor(string $anchor, array $taught): bool
    {
        $a = strtoupper(trim($anchor));
        // a person, or a place ("Istanbul (IST)" leaves "ISTANBUL" in front of the code): values, never labels
        if (preg_match(self::PERSONISH, $a) || Patterns::isPlace($a)) {
            return false;
        }
        foreach ($taught as $v) {
            foreach (preg_split('/[^A-Z0-9]+/', strtoupper((string) $v), -1, PREG_SPLIT_NO_EMPTY) as $w) {
                if (strlen($w) >= 3 && preg_match('/\b' . preg_quote($w, '/') . '\b/', $a)) {
                    return false;
                }
            }
        }
        return true;
    }

    /** [line, cell, text before the value in that cell] of the first cell holding $value. */
    private function locate(array $rows, string $field, string $value): ?array
    {
        $norm = fn ($s) => preg_replace('/[^A-Z0-9]/', '', strtoupper($s));
        $want = $norm($value);
        $words = array_values(array_filter(preg_split('/[^A-Z]+/', strtoupper($value)), fn ($w) => strlen($w) >= 2));
        foreach ($rows as $i => $cells) {
            foreach ($cells as $j => $cell) {
                $u = strtoupper($cell);
                $at = null;
                switch ($field) {
                    case 'name':
                        if ($words && count(array_filter($words, fn ($w) => preg_match('/\b' . preg_quote($w, '/') . '\b/', $u))) === count($words)) {
                            $at = min(array_map(fn ($w) => strpos($u, $w), $words));
                        }
                        break;
                    case 'from':
                    case 'to':
                        if (preg_match('/\b' . preg_quote(strtoupper($value), '/') . '\b/', $u, $m, PREG_OFFSET_CAPTURE)) {
                            $at = $m[0][1];
                        } else {
                            foreach (Cities::MAP as $city => $code) {
                                if ($code === strtoupper($value) && ($p = strpos($u, $city)) !== false) {
                                    $at = $p;
                                    break;
                                }
                            }
                        }
                        break;
                    case 'date':
                        if (preg_match('/\d/', $cell) && $this->date($cell) === substr($value, 0, 10)) {
                            $at = (int) strcspn($cell, '0123456789');
                        }
                        break;
                    default:
                        if ($want !== '' && str_contains($norm($cell), $want)) {
                            $first = substr($want, 0, 1);
                            $at = (int) strpos($u, $first);
                        }
                }
                if ($at !== null) {
                    $prefix = substr($cell, 0, $at);
                    $prefix = preg_replace('/(\b\d+\.?|\b(MR|MRS|MS|MISS|MSTR|DR)\.?|[(\s])+$/i', '', $prefix);
                    return [$i, $j, trim($prefix, " \t:#.-|")];
                }
            }
        }
        return null;
    }

    /** Short wording with no digits — "Reservation PNR", "Booking ID". */
    private function labelLike(?string $s): bool
    {
        $s = trim((string) $s, " \t:#.-|");
        return $s !== '' && strlen($s) <= 40 && ! preg_match('/\d/', $s) && preg_match('/[A-Za-z]{2}/', $s)
            && count(preg_split('/\s+/', $s)) <= 5;
    }
}
