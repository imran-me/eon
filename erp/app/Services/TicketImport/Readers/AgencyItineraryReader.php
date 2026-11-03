<?php

namespace App\Services\TicketImport\Readers;

/**
 * Agency e-ticket itinerary: "Booking Reference : TK/WJNEI7 | Date of issue :
 * 01 October, 2026", a PASSENGERS table "ISLAM /TARIQUL MR | 235-2268460132",
 * then one block per segment headed "ECN → IST 05 Oct 2026 | Aircraft: A321":
 * the times line "12:25 | 14:20 | Checked: …", the dates line below it, and
 * further down the flight number ("TK977") and cabin ("Economy") on lines of
 * their own. The reference carries the airline before the slash.
 */
class AgencyItineraryReader extends Reader
{
    private const HEAD = '/^([A-Z]{3})\s*(?:→|->|-)\s*([A-Z]{3})\b/u';

    public function key(): string { return 'agency_itinerary'; }

    public function label(): string { return 'Agency e-ticket itinerary'; }

    public function matches(string $text): bool
    {
        return (bool) preg_match('/^Booking Reference\s*:/m', $text)
            && str_contains($text, 'PASSENGERS')
            && str_contains($text, 'DEPARTURE | ARRIVAL | BAGGAGE');
    }

    public function read(array $lines): array
    {
        $out = ['passengers' => [], 'segments' => [], 'warnings' => []];

        $h = $this->find($lines, '/^Booking Reference\s*:/');
        if ($h !== null) {
            if (preg_match('/Booking Reference\s*:\s*(?:([A-Z0-9]{2})\s*\/\s*)?([A-Z0-9]{5,8})\b/', $lines[$h], $m)) {
                $out['carrier'] = $m[1] !== '' ? $m[1] : null;
                $out['pnr'] = $m[2];
            }
            if (preg_match('/Date of issue\s*:\s*(.+)$/i', $lines[$h], $m)) {
                $out['issue_date'] = $this->date($m[1]);
            }
        }

        // ── passengers: rows between the NAME header and the first segment ──
        $ph = $this->find($lines, '/^NAME \| TICKET NO\./');
        if ($ph !== null) {
            for ($i = $ph + 1, $n = count($lines); $i < $n; $i++) {
                if (preg_match(self::HEAD, $lines[$i]) || $lines[$i] === '<<PAGE>>') {
                    break;
                }
                $cells = $this->cells($lines[$i]);
                $ticket = null;
                foreach (array_slice($cells, 1) as $c) {
                    if (preg_match('/^(\d{3})-?(\d{10})$/', $c, $tm)) {
                        $ticket = $tm[1] . $tm[2];
                    }
                }
                if ($ticket === null || ! preg_match('/[A-Za-z]/', $cells[0])) {
                    continue;
                }
                $out['passengers'][] = $this->passenger($cells[0], ['eticket' => $ticket]);
            }
        }

        // ── segments: one block per "XXX → YYY" header ───────────────────
        $heads = [];
        foreach ($lines as $i => $l) {
            if (preg_match(self::HEAD, $l)) {
                $heads[] = $i;
            }
        }
        foreach ($heads as $k => $start) {
            $end = $heads[$k + 1] ?? count($lines);
            $block = array_slice($lines, $start, $end - $start);
            preg_match(self::HEAD, $block[0], $hm);

            $depTime = $arrTime = $depDate = $arrDate = $carrier = $flightNo = $cabin = $baggage = null;
            foreach ($block as $j => $l) {
                if ($depTime === null && preg_match('/^(\d{1,2}:\d{2}) \| (\d{1,2}:\d{2})\b/', $l, $m)) {
                    [$depTime, $arrTime] = [$m[1], $m[2]];
                    $dates = $this->cells($block[$j + 1] ?? '');
                    $depDate = $this->date($dates[0] ?? null);
                    $arrDate = $this->date($dates[1] ?? null) ?? $depDate;
                }
                if ($flightNo === null && preg_match('/^([A-Z][A-Z0-9]|[A-Z0-9][A-Z])\s?(\d{1,4})$/', $l, $m)) {
                    [$carrier, $flightNo] = $this->flight($m[1], $m[2]);
                }
                if ($cabin === null && preg_match('/^(Economy|Premium Economy|Business|First)\b/i', $l)) {
                    $cabin = $this->cabin($this->cells($l)[0]);
                }
                if ($baggage === null && preg_match('/(\d+)\s*KG/i', $l, $m)) {
                    $baggage = $m[1] . ' kg';
                }
            }
            // the header's own date when the dates line is missing
            $depDate ??= $this->date($block[0]);

            $out['segments'][] = $this->segment([
                'carrier' => $carrier, 'flight_no' => $flightNo,
                'from' => $hm[1], 'to' => $hm[2],
                'dep' => $this->at($depDate, $depTime),
                'arr' => $this->at($arrDate ?? $depDate, $arrTime),
                'cabin' => $cabin, 'baggage' => $baggage,
            ]);
        }

        return $out;
    }
}
