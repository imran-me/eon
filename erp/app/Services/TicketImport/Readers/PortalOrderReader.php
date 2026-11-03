<?php

namespace App\Services\TicketImport\Readers;

/**
 * Portal order itinerary: "Order ID | Create Date | Ticket Date | Purge Date
 * | Issuing Office | Payment", then "Departure" and "Return" sections of
 * flight rows "TK0969 | Lefkosa (ECN) • 15:40 | Istanbul (IST) • 17:30 |
 * Turkish Airlines | OPEN" with the dates on the line below. Passengers carry
 * date of birth and ticket number, no passport.
 *
 * Dates print without a year ("06 Oct, Tue"); the year is the ticket date's,
 * rolled forward when a date would fall before the one ahead of it.
 */
class PortalOrderReader extends Reader
{
    public function key(): string { return 'portal_order'; }

    public function label(): string { return 'Portal order itinerary'; }

    public function matches(string $text): bool
    {
        return str_contains($text, 'Order ID') && str_contains($text, 'Flight Details') && str_contains($text, 'Passengers Details');
    }

    public function read(array $lines): array
    {
        $out = ['passengers' => [], 'segments' => [], 'warnings' => []];

        // pdf.js often glues header cells ("Order ID Create Date | Ticket Date"),
        // so read the value line by shape: PNR first, then Create / Ticket dates
        $out['pnr'] = null;
        $out['issue_date'] = null;
        $h = $this->find($lines, '/^Order ID\b/');
        if ($h !== null && isset($lines[$h + 1])) {
            if (preg_match('/^([A-Z0-9]{5,8})\b/', $lines[$h + 1], $pm)) {
                $out['pnr'] = $pm[1];
            }
            preg_match_all('/\d{1,2} [A-Za-z]{3},? \d{4}/', $lines[$h + 1], $dm);
            $out['issue_date'] = $this->date($dm[0][1] ?? $dm[0][0] ?? null);
        }
        $year = $out['issue_date'] ? (int) substr($out['issue_date'], 0, 4) : null;

        // ── segments ──────────────────────────────────────────────────────
        $direction = 'out';
        $prev = $out['issue_date'] ?? null;
        foreach ($lines as $i => $l) {
            if (preg_match('/^Return \|/', $l)) {
                $direction = 'return';
            }
            if (! preg_match('/^([A-Z0-9]{2})(\d{1,4}) \| .*?\(([A-Z]{3})\)\s*\S?\s*(\d{1,2}:\d{2}) \| .*?\(([A-Z]{3})\)\s*\S?\s*(\d{1,2}:\d{2})(.*)$/u', $l, $m)) {
                continue;
            }
            preg_match_all('/(\d{1,2} [A-Z][a-z]{2}),/', $lines[$i + 1] ?? '', $dm);
            $depDate = $this->date($dm[1][0] ?? null, $year, $prev);
            $arrDate = $this->date($dm[1][1] ?? null, $year, $depDate) ?? $depDate;
            $prev = $depDate ?? $prev;
            [$carrier, $flightNo] = $this->flight($m[1], $m[2]);
            $below = $this->cells($lines[$i + 1] ?? '');
            $out['segments'][] = $this->segment([
                'carrier' => $carrier, 'flight_no' => $flightNo,
                'from' => $m[3], 'to' => $m[5],
                'dep' => $this->at($depDate, $m[4]),
                'arr' => $this->at($arrDate, $m[6]),
                'cabin' => $this->cabin(end($below)),
                'rbd' => preg_match('/^([A-Z])(?:,[A-Z])*$/', $below[count($below) - 3] ?? '', $rm) ? $rm[1] : null,
                'status' => trim((string) last($this->cells($m[7]))) ?: null,
                'direction' => $direction,
            ]);
        }

        // ── passengers ────────────────────────────────────────────────────
        $ph = $this->find($lines, '/^Passenger Name \| Passenger Type/');
        if ($ph !== null) {
            for ($i = $ph + 1; $i < count($lines); $i++) {
                $cells = $this->cells($lines[$i]);
                $ticket = null;
                foreach ($cells as $c) {
                    if (preg_match('/^\d{13}$/', $c)) {
                        $ticket = $c;
                    }
                }
                if (! $ticket) {
                    if (preg_match('/^(Baggage|General Information)/', $lines[$i])) {
                        break;
                    }
                    continue;
                }
                $p = $this->passenger($cells[0], ['eticket' => $ticket]);
                $p['dob'] = $this->date($cells[2] ?? null);
                $p['pax_type'] = strtolower($cells[1] ?? '') === 'child' ? 'child' : (strtolower($cells[1] ?? '') === 'infant' ? 'infant' : 'adult');
                $out['passengers'][] = $p;
            }
        }

        // ── baggage: "Mr. X | 23 Kilograms | 1 Piece x 8 Kilograms" ───────
        foreach ($lines as $l) {
            if (preg_match('/\| (\d+) Kilograms \|/', $l, $bm)) {
                foreach ($out['segments'] as $k => $s) {
                    $out['segments'][$k]['baggage'] ??= $bm[1] . ' kg';
                }
                break;
            }
        }

        return $out;
    }
}
