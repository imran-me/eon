<?php

namespace App\Services\TicketImport;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\PassportHolder;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ties what a reader found to this ERP's own records — passport holder,
 * route (Manage Tickets), airline, airports — and says what is already on
 * file. Reads only. Every lookup is wrapped: a failure here costs the
 * suggestion, never the import.
 *
 * Passenger rules (the expensive mistake is linking the wrong person):
 *  - Passport number on the ticket and on a holder → that holder, certain.
 *  - Passport number on the ticket but on NO holder → a new passenger, even
 *    if a holder has the same name: same name, different passport is a
 *    different person (or a renewed passport — staff decide, never us).
 *  - No passport on the ticket → name match; picked only when exactly one
 *    holder matches, otherwise listed for staff to choose.
 */
class TicketImportMatcher
{
    private ?array $airlinesByCode = null;

    private ?array $airportsByCode = null;

    private ?array $holders = null;

    private ?array $routes = null;

    public function match(array $read): array
    {
        if (! in_array($read['status'], ['read', 'partial'], true)) {
            return $read;
        }

        foreach ($read['segments'] as $k => $s) {
            $read['segments'][$k]['airline_id'] = $this->airline($s['carrier'])['id'] ?? null;
            $read['segments'][$k]['from_airport'] = $this->airport($s['from']);
            $read['segments'][$k]['to_airport'] = $this->airport($s['to']);
        }
        $read['carrier_airline'] = $this->airline($read['carrier']);

        $out = array_values(array_filter($read['segments'], fn ($s) => $s['direction'] !== 'return'));
        $ret = array_values(array_filter($read['segments'], fn ($s) => $s['direction'] === 'return'));
        $read['trip_type'] = $ret ? 'two-way' : 'one-way';

        // passengers first: a passenger whose other leg is already on file
        // decides between routes that both fit (see route())
        foreach ($read['passengers'] as $k => $p) {
            $read['passengers'][$k]['holder'] = $this->holder($p);
        }
        $holderIds = array_values(array_filter(array_map(fn ($p) => $p['holder']['id'] ?? null, $read['passengers'])));
        $read['route'] = $this->route($out ?: $read['segments'], $read['carrier_airline']['id'] ?? null, $holderIds, $read['trip_type']);
        $read['on_file'] = $this->onFile($read);   // also fills each passenger's 'existing'

        return $read;
    }

    // ── airline / airport ────────────────────────────────────────────────

    public function airline(?string $code): ?array
    {
        if (! $code) {
            return null;
        }
        if ($this->airlinesByCode === null) {
            $this->airlinesByCode = [];
            try {
                foreach (Airline::query()->get(['id', 'name']) as $a) {
                    if (preg_match('/\(([A-Z0-9]{2,3})\)\s*$/i', (string) $a->name, $m)) {
                        $this->airlinesByCode[strtoupper($m[1])] ??= ['id' => $a->id, 'name' => trim($a->name)];
                    }
                }
            } catch (\Throwable) {
            }
        }

        return $this->airlinesByCode[strtoupper($code)] ?? null;
    }

    public function airport(?string $code): ?array
    {
        if (! $code) {
            return null;
        }
        if ($this->airportsByCode === null) {
            $this->airportsByCode = [];
            try {
                $byName = [];
                foreach (Airport::query()->with('country:id,code')->get(['id', 'name', 'code', 'country_id']) as $a) {
                    $row = ['id' => $a->id, 'name' => $a->name, 'flag' => strtolower((string) $a->country?->code)];
                    $c = strtoupper(trim((string) $a->code));
                    if (preg_match('/^[A-Z]{3}$/', $c)) {
                        $this->airportsByCode[$c] ??= $row;
                    } elseif (preg_match('/\(([A-Za-z]{3})\)\s*$/', (string) $a->name, $m)) {
                        // a row typed with an empty code box still names it: "Bahrain International Airport (BAH)"
                        $byName[strtoupper($m[1])] ??= $row;
                    }
                }
                $this->airportsByCode += $byName;
            } catch (\Throwable) {
            }
        }

        return $this->airportsByCode[strtoupper($code)] ?? null;
    }

    // ── route (Manage Tickets) ───────────────────────────────────────────

    /**
     * The route this ticket belongs on. A ticket covering DAC→BAH→IST on one
     * e-ticket is ONE card from DAC to IST (its sectors go in the card's
     * Route legs); it fits either a direct route DAC→IST, or one leg of a
     * longer via route (DAC-BAH-IST-ECN = [DAC-IST] + [IST-ECN]) — then the
     * card is that leg's ticket. No fit → a new route is proposed, made only
     * when staff click it.
     */
    private function route(array $segs, ?int $airlineId, array $holderIds = [], string $trip = 'one-way'): array
    {
        $from = $segs[0]['from'] ?? null;
        $to = end($segs)['to'] ?? null;
        $fromAp = $this->airport($from);
        $toAp = $this->airport($to);
        $via = array_map(fn ($s) => $s['to'], array_slice($segs, 0, -1));

        $result = [
            'from' => $from, 'to' => $to, 'via' => $via,
            'from_airport_id' => $fromAp['id'] ?? null, 'to_airport_id' => $toAp['id'] ?? null,
            'airline_id' => $airlineId,
            // a transit stop on the ticket is part of the route's name: DAC-BAH-IST is
            // one ticket (owner, 2026-10-07); Via is only where a route splits into two tickets
            'title' => $from && $to ? implode('-', array_merge([$from], $via, [$to])) : null,
            'match' => null, 'candidates' => [],
        ];
        if (! $fromAp || ! $toAp) {
            return $result;
        }

        foreach ($this->routes() as $r) {
            $legs = $r['legs'];
            if (count($legs) < 2) {
                if ($r['from'] === $fromAp['id'] && $r['to'] === $toAp['id']) {
                    $score = 100 + ($r['airline'] === $airlineId ? 20 : ($r['airline'] ? -30 : 0));
                    // a round-trip route (one round-trip ticket, 2026-10-18) for a round-trip PDF, not a one-way one
                    $score += ! empty($r['round']) ? ($trip === 'two-way' ? 15 : -15) : 0;
                    $result['candidates'][] = ['id' => $r['id'], 'title' => $r['title'], 'leg' => null, 'score' => $score, 'round' => ! empty($r['round'])];
                }

                continue;
            }
            foreach ($legs as $leg) {
                if ($leg['from_id'] === $fromAp['id'] && $leg['to_id'] === $toAp['id']) {
                    $score = 80 + ((int) $leg['airline_id'] === $airlineId ? 20 : ($leg['airline_id'] ? -30 : 0));
                    $result['candidates'][] = ['id' => $r['id'], 'title' => $r['title'], 'leg' => (int) $leg['sequence'], 'legs' => count($legs), 'score' => $score];
                }
            }
        }
        // Legs arrive separately (owner, 2026-10-05): when these passengers'
        // OTHER leg is already entered on one of the fitting routes, that is
        // the route — it outweighs the airline, or the two halves of one
        // journey land on two look-alike routes and never connect.
        if ($holderIds && $result['candidates']) {
            foreach ($result['candidates'] as $i => $c) {
                // other leg on file: this ticket completes that order; same leg
                // on file: it is that ticket again or its reissue — either way
                // the route is the one his ticket is already on
                $onFile = $this->legOnFile($holderIds, $c['id'], (int) ($c['leg'] ?: 1));
                if ($onFile === 'other') {
                    $result['candidates'][$i]['score'] += 60;
                    $result['candidates'][$i]['joins'] = true;
                } elseif ($onFile === 'same') {
                    $result['candidates'][$i]['score'] += 40;
                }
            }
        }
        // The stops decide between routes with the same ends (owner, 2026-10-07): a
        // DAC → BAH → IST ticket is DAC-BAH-IST (or that leg of DAC-BAH-IST-ECN), and a
        // nonstop DAC → IST is never DAC-BAH-IST. Read from the route's name, which
        // lists its stops; a name that does not spell out both ends ("DAC-ECN") says
        // nothing either way and keeps its score.
        $norm = fn ($s) => trim(preg_replace('/[^A-Z]+/', '-', preg_replace('/\(.*?\)/', '', strtoupper((string) $s))), '-');
        $path = $norm($result['title']);
        // a round-trip route's name spells out its return too (DAC-ECN-DAC): its outbound part is compared
        $named = function (array $c) use ($norm, $to): string {
            $n = $norm($c['title']);
            if (! empty($c['round']) && $to) {
                $parts = explode('-', $n);
                $k = array_search(strtoupper((string) $to), $parts, true);
                if ($k !== false) {
                    $n = implode('-', array_slice($parts, 0, $k + 1));
                }
            }

            return $n;
        };
        foreach ($result['candidates'] as $i => $c) {
            $t = '-'.$named($c).'-';
            if ($c['leg'] ? str_contains($t, '-'.$path.'-') : $t === '-'.$path.'-') {
                $result['candidates'][$i]['score'] += 30;
            } elseif (str_contains($t, '-'.$from.'-') && str_contains($t, '-'.$to.'-')) {
                $result['candidates'][$i]['score'] -= 70;   // other stops: listed, never auto-picked
            }
        }
        usort($result['candidates'], fn ($a, $b) => $b['score'] <=> $a['score']);
        // picked only when it is the clear best; a tie is staff's call
        $c = $result['candidates'];
        if ($c && $c[0]['score'] > 50 && (! isset($c[1]) || $c[1]['score'] < $c[0]['score'])) {
            $result['match'] = $c[0];
        }

        return $result;
    }

    /** 'other' = these passengers hold another leg of this route; 'same' = this very leg; null = neither. */
    private function legOnFile(array $holderIds, int $routeId, int $leg): ?string
    {
        try {
            $q = fn (string $table, string $op) => DB::table($table)->whereNull('deleted_at')->where('status', '!=', 'cancelled')
                ->whereIn('passport_holder_id', $holderIds)->where('ticket_id', $routeId)
                ->whereRaw('COALESCE(leg_sequence, 1) '.$op.' ?', [$leg])
                ->where('created_at', '>=', now()->subDays(400))
                ->when($op === '<>', fn ($w) => $w->whereNotNull('order_group'))->exists();
            if ($q('ticket_purchases', '<>') || $q('ticket_bookings', '<>')) {
                return 'other';
            }

            return ($q('ticket_purchases', '=') || $q('ticket_bookings', '=')) ? 'same' : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function routes(): array
    {
        if ($this->routes !== null) {
            return $this->routes;
        }
        $this->routes = [];
        try {
            foreach (Ticket::query()->where('status', 1)->get() as $t) {
                $this->routes[] = [
                    'id' => $t->id, 'title' => $t->title,
                    'from' => $t->from_airport_id ? (int) $t->from_airport_id : null,
                    'to' => $t->to_airport_id ? (int) $t->to_airport_id : null,
                    'airline' => $t->airline_id ? (int) $t->airline_id : null,
                    'round' => $t->isRoundTrip() && ! $t->hasSeparateReturn(),
                    'legs' => array_map(fn ($l) => [
                        'sequence' => $l['sequence'], 'airline_id' => $l['airline_id'],
                        'from_id' => $l['from_id'] ? (int) $l['from_id'] : null,
                        'to_id' => $l['to_id'] ? (int) $l['to_id'] : null,
                    ], $t->legsArray()),
                ];
            }
        } catch (\Throwable) {
        }

        return $this->routes;
    }

    // ── passport holder ──────────────────────────────────────────────────

    private function holder(array $p): array
    {
        $res = ['id' => null, 'how' => null, 'label' => null, 'options' => []];
        $holders = $this->holders();
        $pp = strtoupper(preg_replace('/\s+/', '', (string) $p['passport_no']));

        if ($pp !== '') {
            foreach ($holders as $h) {
                if ($h['pp'] === $pp) {
                    return ['id' => $h['id'], 'how' => 'passport', 'label' => $h['label'], 'options' => []];
                }
            }
        }

        $key = self::nameKey($p['name']);
        $loose = self::looseKey($p['name']);
        $hits = [];
        foreach ($holders as $h) {
            if ($h['key'] === $key) {
                $hits[$h['id']] = $h + ['how' => 'name'];
            } elseif ($h['loose'] === $loose) {
                $hits[$h['id']] ??= $h + ['how' => 'name_close'];
            }
        }
        // date of birth on both sides and different → not the same person
        if ($p['dob']) {
            $hits = array_filter($hits, fn ($h) => ! $h['dob'] || $h['dob'] === $p['dob']);
        }
        $res['options'] = array_values(array_map(fn ($h) => ['id' => $h['id'], 'label' => $h['label'], 'how' => $h['how']], $hits));

        // a passport on the ticket that no holder has: never auto-link by name
        if ($pp === '' && count($hits) === 1) {
            $h = reset($hits);
            $res['id'] = $h['id'];
            $res['how'] = $h['how'];
            $res['label'] = $h['label'];
        }

        return $res;
    }

    private function holders(): array
    {
        if ($this->holders !== null) {
            return $this->holders;
        }
        $this->holders = [];
        try {
            foreach (PassportHolder::query()->where('status', 1)->get(['id', 'name', 'passport_no', 'date_of_birth']) as $h) {
                $this->holders[] = [
                    'id' => $h->id,
                    'label' => $h->name.($h->passport_no ? ' — '.$h->passport_no : ''),
                    'pp' => strtoupper(preg_replace('/\s+/', '', (string) $h->passport_no)),
                    'dob' => $h->date_of_birth ? substr((string) $h->date_of_birth, 0, 10) : null,
                    'key' => self::nameKey((string) $h->name),
                    'loose' => self::looseKey((string) $h->name),
                ];
            }
        } catch (\Throwable) {
        }

        return $this->holders;
    }

    /** Name tokens, titles dropped, Md / Mohammad / Mohammed … as one, sorted. */
    public static function nameKey(string $name): string
    {
        $t = preg_split('/\s+/', trim(preg_replace('/[^A-Z ]+/', ' ', strtoupper($name))));
        $alias = [
            'MOHAMMAD' => 'MD', 'MOHAMMED' => 'MD', 'MUHAMMAD' => 'MD', 'MOHAMMOD' => 'MD', 'MOHAMAD' => 'MD', 'MUHAMMED' => 'MD', 'MOHD' => 'MD',
            'MOST' => 'MST', 'MOSAMMAT' => 'MST', 'MOSAMMET' => 'MST', 'MUSAMMAT' => 'MST',
        ];
        $drop = ['MR', 'MRS', 'MS', 'MISS', 'MSTR', 'MASTER', 'DR', ''];
        $t = array_values(array_filter(array_map(fn ($x) => $alias[$x] ?? $x, $t), fn ($x) => ! in_array($x, $drop, true)));
        sort($t);

        return implode(' ', $t);
    }

    /** Letters only, order ignored — catches a name run together: "MOHAMMEDBABUL MIAH" = "MD BABUL MIAH". */
    public static function looseKey(string $name): string
    {
        $k = str_replace(' ', '', self::nameKey(str_replace(['MOHAMMAD', 'MOHAMMED', 'MUHAMMAD'], ' MD ', strtoupper($name))));
        $chars = str_split($k);
        sort($chars);

        return implode('', $chars);
    }

    // ── already on file? ─────────────────────────────────────────────────

    /**
     * E-ticket numbers already entered (certain duplicates) and the PNR's
     * existing tickets (a group PNR legitimately repeats — shown, not blocked).
     */
    private function onFile(array &$read): array
    {
        $res = ['etickets' => [], 'pnr_count' => 0];
        // A ticket carries two PNRs (the airline's and the GDS's) and staff
        // have typed either one: 2026-10-05, the Galileo PNR J8QWBD typed by
        // hand, then the same ticket imported under the airline PNR QSDGRW.
        // Since 2026-10-17 the ticket's (GDS) PNR is `pnr` and the airline's is `airline_pnr` — all three count.
        $pnrs = array_values(array_unique(array_filter([$read['pnr'] ?? null, $read['gds_pnr'] ?? null, $read['airline_pnr'] ?? null])));
        $onPnr = function ($q, string $col) use ($pnrs) {
            $q->where(function ($w) use ($pnrs, $col) {
                foreach ($pnrs as $p) {
                    $w->orWhere($col, $p)->orWhere($col, 'like', $p.' (%');
                }
            });
        };
        try {
            $etickets = array_values(array_filter(array_column($read['passengers'], 'eticket')));
            if ($etickets && Schema::hasColumn('ticket_purchases', 'name_ticket_no')) {
                $res['etickets'] = DB::table('ticket_purchases')->whereNull('deleted_at')->where('status', '!=', 'cancelled')
                    ->whereIn('name_ticket_no', $etickets)->pluck('ticket_no', 'name_ticket_no')->all();
            }
            if ($pnrs) {
                $res['pnr_count'] = DB::table('ticket_purchases')->whereNull('deleted_at')->where('status', '!=', 'cancelled')
                    ->where(fn ($q) => $onPnr($q, 'ticket_no'))->count()
                    + DB::table('ticket_bookings')->whereNull('deleted_at')->whereNull('confirmed_purchase_id')
                        ->where(fn ($q) => $onPnr($q, 'pnr'))->count();
            }
            // per matched passenger: his live tickets already on either PNR
            foreach ($read['passengers'] as $k => $p) {
                $read['passengers'][$k]['existing'] = [];
                $hid = $p['holder']['id'] ?? null;
                if (! $hid || ! $pnrs) {
                    continue;
                }
                $read['passengers'][$k]['existing'] = DB::table('ticket_purchases')->whereNull('deleted_at')
                    ->where('status', '!=', 'cancelled')->where('passport_holder_id', $hid)
                    ->where(fn ($q) => $onPnr($q, 'ticket_no'))
                    ->orderBy('id')->get(['id', 'ticket_no', 'purchase_date', 'amount'])
                    ->map(fn ($r) => ['id' => (int) $r->id, 'pnr' => $r->ticket_no, 'date' => substr((string) $r->purchase_date, 0, 10), 'amount' => (float) $r->amount])
                    ->all();
            }
        } catch (\Throwable) {
        }

        return $res;
    }
}
