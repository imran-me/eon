<?php

namespace App\Services\TicketImport\Readers;

use App\Services\TicketImport\Cities;

/**
 * Galileo (Travelport) e-ticket, printed under our own agency header:
 * "Electronic Ticket … Airline PNR | Galileo PNR | Date of Issue".
 *
 * The only layout so far that prints the passport number. It prints cities,
 * not airport codes, and its From / To cells wrap over several lines, so a
 * segment is the block of lines ending at "Special Svc", and its two airports
 * are the first two different cities named inside that block.
 */
class GalileoReader extends Reader
{
    public function key(): string { return 'galileo'; }

    public function label(): string { return 'Galileo e-ticket'; }

    public function matches(string $text): bool
    {
        return str_contains($text, 'Galileo PNR') && str_contains($text, 'Itinerary Information');
    }

    public function read(array $lines): array
    {
        $out = ['passengers' => [], 'segments' => [], 'warnings' => []];

        // ── passengers: rows under the "Number | Number" header ───────────
        $start = $this->find($lines, '/^Number \| Number/');
        $stop = $this->find($lines, '/^Airline PNR/');
        if ($start !== null && $stop !== null) {
            for ($i = $start + 1; $i < $stop; $i++) {
                $cells = $this->cells($lines[$i]);
                if (! str_contains($cells[0], '/')) {
                    continue;
                }
                $p = $this->passenger($cells[0]);
                foreach (array_slice($cells, 1) as $c) {
                    if (preg_match('/^\d{13}$/', $c)) {
                        $p['eticket'] = $c;
                    } elseif (preg_match('/^[A-Z]{1,2}\d{6,9}$/', $c)) {
                        $p['passport_no'] = $c;
                    }
                }
                $out['passengers'][] = $p;
            }
        }

        // ── PNRs and issue date ───────────────────────────────────────────
        if ($stop !== null && isset($lines[$stop + 1])) {
            $cells = $this->cells($lines[$stop + 1]);
            if (preg_match('/^([A-Z0-9]{5,7})\s*(?:\(([A-Z0-9]{2})\s*-\s*([^)]+)\))?/', $cells[0] ?? '', $m)) {
                $out['pnr'] = $m[1];
                $out['carrier'] = $m[2] ?? null;
            }
            // read from the right: Galileo PNR and issue date are the last two cells; a ticket on
            // two airlines puts the second airline's PNR between ("5L6F85 (QR …) | 2H6SKT | J9J15F | 06Oct26")
            $n = count($cells);
            $gds = $n >= 3 ? $cells[$n - 2] : ($cells[1] ?? '');
            $out['gds_pnr'] = preg_match('/^[A-Z0-9]{5,7}$/', $gds) ? $gds : null;
            $out['issue_date'] = $this->date($n >= 3 ? $cells[$n - 1] : null);
            $more = array_filter(array_slice($cells, 1, max(0, $n - 3)), fn ($c) => preg_match('/^[A-Z0-9]{5,7}\b/', $c));
            if ($more) {
                $out['warnings'][] = 'Other airline PNR: ' . implode(', ', $more);
            }
        }

        // ── segments ──────────────────────────────────────────────────────
        $head = $this->find($lines, '/^Flight # \|/');
        if ($head !== null) {
            $block = [];
            for ($i = $head + 1, $n = count($lines); $i < $n; $i++) {
                if ($lines[$i] === '<<PAGE>>') {
                    continue;
                }
                $block[] = $lines[$i];
                if (str_starts_with($lines[$i], 'Special Svc')) {
                    if ($seg = $this->segmentFrom($block)) {
                        $out['segments'][] = $seg;
                    }
                    $block = [];
                }
            }
            if ($block && ($seg = $this->segmentFrom($block))) {
                $out['segments'][] = $seg;
            }
        }

        // a city the map does not know leaves a hole; chain the legs to fill it
        foreach ($out['segments'] as $k => $s) {
            if (! $s['from'] && $k > 0) {
                $out['segments'][$k]['from'] = $out['segments'][$k - 1]['to'];
            }
        }

        return $out;
    }

    private function segmentFrom(array $block): ?array
    {
        // "QR 639 | 04:10 | 06:50" on one line, or (2026-10-08 Qatar/Pegasus PDF) the times
        // on one line — "04:10 | 06:50 | Aircraft …" — and "QR 639 | A320-100/200" on another
        $flightLine = null;
        $fl = $times = null;
        foreach ($block as $l) {
            if (preg_match('/^([A-Z0-9]{2})\s?(\d{1,4}) \| (\d{1,2}:\d{2}) \| (\d{1,2}:\d{2})$/', $l, $m)) {
                $flightLine = $m;
                break;
            }
            if (! $fl && preg_match('/^([A-Z][A-Z0-9]|[0-9][A-Z])\s?(\d{1,4})(?: \||$)/', $l, $m)) {
                $fl = $m;
            }
            if (! $times && preg_match('/^(\d{1,2}:\d{2}) \| (\d{1,2}:\d{2})(?: \||$)/', $l, $m)) {
                $times = $m;
            }
        }
        if (! $flightLine && $fl && $times) {
            $flightLine = [null, $fl[1], $fl[2], $times[1], $times[2]];
        }
        if (! $flightLine) {
            return null;
        }
        $text = implode("\n", $block);
        preg_match_all('/\b(\d{2} [A-Z][a-z]{2} \d{2})\b/', $text, $dm);
        $depDate = $this->date($dm[1][0] ?? null);
        $arrDate = $this->date($dm[1][1] ?? null) ?? $depDate;

        // Only the route cells name cities — keep "Aircraft", "Duration" etc. out.
        $routeText = implode(' ', array_filter($block, fn ($l) => ! preg_match('/^(Baggage|Class|Duration|Status|Aircraft|Special|Seat)\b/', $l)));
        $codes = Cities::inOrder($routeText);

        [$carrier, $flightNo] = $this->flight($flightLine[1], $flightLine[2]);
        $rbd = preg_match('/Class : ([A-Z])-/', $text, $cm) ? $cm[1] : null;

        return $this->segment([
            'carrier'   => $carrier,
            'flight_no' => $flightNo,
            'from'      => $codes[0] ?? null,
            'to'        => $codes[1] ?? null,
            'dep'       => $this->at($depDate, $flightLine[3]),
            'arr'       => $this->at($arrDate, $flightLine[4]),
            'cabin'     => $this->cabin(preg_match('/Class : (.+)/', $text, $c2) ? $c2[1] : null),
            'rbd'       => $rbd,
            'baggage'   => preg_match('/Baggage : (\d+)\s*K/i', $text, $bm) ? $bm[1] . ' kg' : null,
            'status'    => preg_match('/Status : (\w+)/', $text, $sm) ? $sm[1] : null,
        ]);
    }
}
