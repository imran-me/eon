<?php

namespace App\Services\TicketImport\Readers;

/**
 * Sabre "Travel Reservation" itinerary: "PREPARED FOR … RESERVATION CODE …
 * AIRLINE RESERVATION CODE". One PDF carries the whole group — every name
 * under PREPARED FOR, each with its own eTicket number under every segment.
 *
 * Dates print without a year ("DEPARTURE: MONDAY 05 OCT"); the year comes
 * from the header ("05 OCT 2026 | 06 OCT 2026 TRIP TO …"). Arrival prints a
 * time only, so it is the departure day, or the next day when the clock
 * went backwards.
 */
class SabreReader extends Reader
{
    public function key(): string { return 'sabre'; }

    public function label(): string { return 'Sabre itinerary'; }

    public function matches(string $text): bool
    {
        return str_contains($text, 'PREPARED FOR') && str_contains($text, 'AIRLINE RESERVATION CODE');
    }

    public function read(array $lines): array
    {
        $out = ['passengers' => [], 'segments' => [], 'warnings' => []];
        $year = preg_match('/\b\d{1,2} [A-Z]{3} (\d{4})\b/', $lines[0] ?? '', $ym) ? (int) $ym[1] : null;
        $tripStart = $this->date($lines[0] ?? null);

        // ── names: between PREPARED FOR and RESERVATION CODE ──────────────
        $pf = $this->find($lines, '/^PREPARED FOR\b/');
        $rc = $this->find($lines, '/^RESERVATION CODE\b/');
        $byName = [];
        if ($pf !== null && $rc !== null) {
            for ($i = $pf + 1; $i < $rc; $i++) {
                if (str_contains($lines[$i], '/')) {
                    $p = $this->passenger($this->cells($lines[$i])[0]);
                    $byName[$p['raw_name']] = count($out['passengers']);
                    $out['passengers'][] = $p;
                }
            }
        }
        if ($rc !== null) {
            // "RESERVATION CODE | IVJVUA" or, glued, "RESERVATION CODE IVJVUA"
            $out['gds_pnr'] = preg_match('/CODE\s*\|?\s*([A-Z0-9]{5,7})\b/', $lines[$rc], $gm) ? $gm[1] : null;
        }
        $arc = $this->find($lines, '/^AIRLINE RESERVATION CODE\b/');
        if ($arc !== null && preg_match('/CODE\s*\|?\s*([A-Z0-9]{5,7})(?:\s*\(([A-Z0-9]{2})\))?/', $lines[$arc], $m)) {
            $out['pnr'] = $m[1];
            $out['carrier'] = $m[2] ?? null;
            // one ticket on two airlines carries one airline PNR each — "5DWLNU (QR), 2H4X3D (PC)";
            // the first is the ticket's PNR, the others are said, not lost
            if (preg_match_all('/([A-Z0-9]{5,7})\s*\(([A-Z0-9]{2})\)/', $lines[$arc], $all, PREG_SET_ORDER) && count($all) > 1) {
                $more = array_map(fn ($x) => $x[1] . ' (' . $x[2] . ')', array_slice($all, 1));
                $out['warnings'][] = 'Other airline PNR: ' . implode(', ', $more);
            }
        }

        // ── segments: one per "DEPARTURE:" heading ────────────────────────
        $starts = [];
        foreach ($lines as $i => $l) {
            if (preg_match('/^DEPARTURE:\s+\w+\s+(\d{1,2} [A-Z]{3})/', $l)) {
                $starts[] = $i;
            }
        }
        $prevDep = $tripStart;
        foreach ($starts as $k => $s) {
            $end = $starts[$k + 1] ?? count($lines);
            $block = array_slice($lines, $s, $end - $s);
            preg_match('/^DEPARTURE:\s+\w+\s+(\d{1,2} [A-Z]{3})/', $block[0], $dm);
            $depDate = $this->date($dm[1], $year, $prevDep);
            $prevDep = $depDate;

            $seg = ['carrier' => null, 'flight_no' => null, 'from' => null, 'to' => null];
            $depTime = $arrTime = null;
            foreach ($block as $j => $l) {
                if (! $seg['from'] && preg_match('/\| ([A-Z]{3}) \| ([A-Z]{3})(?: \||$)/', $l, $m)) {
                    $seg['from'] = $m[1];
                    $seg['to'] = $m[2];
                }
                if (! $seg['flight_no'] && preg_match('/^([A-Z0-9]{2}) (\d{1,4})(?: \||$)/', $l, $m)) {
                    [$seg['carrier'], $seg['flight_no']] = $this->flight($m[1], $m[2]);
                    $seg['from_name'] = $this->cells($l)[1] ?? null;
                    $seg['to_name'] = $this->cells($l)[2] ?? null;
                }
                // the times row can open the line when the wrap moved "Departing At:" up
                // ("08:25 | 12:20 | Meals:" — 2026-10-11 DAC-DOH-ESB-ECN PDF)
                if (! $depTime && preg_match('/(?:^|\| )(\d{1,2}:\d{2}) \| (\d{1,2}:\d{2})(?: \||$)/', $l, $m)) {
                    [$depTime, $arrTime] = [$m[1], $m[2]];
                }
                if (preg_match('/^(Economy|Business|First|Premium Economy)\s*\/\s*([A-Z])\b/i', $l, $m)) {
                    $seg['cabin'] = $this->cabin($m[1]);
                    $seg['rbd'] = strtoupper($m[2]);
                }
                if (preg_match('/^Checked Baggage:\s*\w+,\s*(\d+)\s*kg/i', $l, $m)) {
                    $seg['baggage'] = $m[1] . ' kg';
                }
                if (preg_match('/^Status:/', $l) && isset($block[$j + 1])) {
                    $seg['status'] = trim($block[$j + 1]);
                }
                // "» TALUKDER/MD AL AMIN MR | Check-In Required | 0724883925051"
                if (preg_match('/^»\s*(.+)$/u', $l, $m)) {
                    $cells = $this->cells($m[1]);
                    $ticket = end($cells);
                    $idx = $byName[$cells[0]] ?? null;
                    if ($idx !== null && preg_match('/^\d{13}$/', $ticket)) {
                        $out['passengers'][$idx]['eticket'] ??= $ticket;
                    }
                }
            }
            $arrDate = $depDate;
            if ($depDate && $depTime && $arrTime && strcmp(sprintf('%05s', $arrTime), sprintf('%05s', $depTime)) < 0) {
                $arrDate = $this->plusDays($depDate, 1);
            }
            $seg['dep'] = $this->at($depDate, $depTime);
            $seg['arr'] = $this->at($arrDate, $arrTime);
            $out['segments'][] = $this->segment($seg);
        }

        return $out;
    }
}
