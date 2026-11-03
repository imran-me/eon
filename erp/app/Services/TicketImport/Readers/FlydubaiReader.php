<?php

namespace App\Services\TicketImport\Readers;

/**
 * flydubai booking confirmation: one PAGE per passenger, each repeating the
 * whole journey. "Departure from Dhaka (Flight FZ 524/FZ 781)" names the
 * flights in order; each flight is a "12 September 2026, Saturday | 13 …"
 * date line, a "22:00 | - - 05h 05min - - | 01:05 | Economy Flex" time line
 * and a "DAC | DXB" code line. No ticket number, passport or fare.
 */
class FlydubaiReader extends Reader
{
    public function key(): string { return 'flydubai'; }

    public function label(): string { return 'flydubai confirmation'; }

    public function matches(string $text): bool
    {
        return str_contains($text, 'flydubai booking reference');
    }

    public function read(array $lines): array
    {
        $out = ['passengers' => [], 'segments' => [], 'warnings' => [], 'carrier' => 'FZ'];
        $text = implode("\n", $lines);

        if (preg_match('/Your booking is confirmed\s*(?:\||\n)\s*([A-Z0-9]{5,7})\b/', $text, $m)) {
            $out['pnr'] = $m[1];
        }
        if (preg_match('/Booked on (\d{1,2} \w+ \d{4})/', $text, $m)) {
            $out['issue_date'] = $this->date($m[1]);
        }

        // one passenger per page: the line after "Passenger details"
        foreach ($lines as $i => $l) {
            if ($l === 'Passenger details' && isset($lines[$i + 1])) {
                $p = $this->passenger($lines[$i + 1]);
                $type = strtolower($lines[$i + 2] ?? '');
                $p['pax_type'] = str_contains($type, 'child') ? 'child' : (str_contains($type, 'infant') ? 'infant' : 'adult');
                $out['passengers'][] = $p;
            }
        }

        // the journey, read from the first page only
        $pageEnd = $this->find($lines, '/^<<PAGE>>$/') ?? count($lines);
        $page = array_slice($lines, 0, $pageEnd);
        $flights = [];
        $direction = 'out';
        $dates = $times = $codes = [];
        foreach ($page as $l) {
            if (preg_match('/^(Departure|Return) from .*\(Flights? ([^)]+)\)/', $l, $m)) {
                $direction = $m[1] === 'Return' ? 'return' : 'out';
                foreach (preg_split('/\s*\/\s*/', $m[2]) as $f) {
                    $flights[] = [trim($f), $direction];
                }
            }
            if (preg_match('/^(\d{1,2} [A-Za-z]+ \d{4}), \w+ \| (\d{1,2} [A-Za-z]+ \d{4})/', $l, $m)) {
                $dates[] = [$this->date($m[1]), $this->date($m[2])];
            }
            if (preg_match('/^(\d{1,2}:\d{2}) \| [-\s]*\d+h \d+min[-\s]* \| (\d{1,2}:\d{2})(.*)$/', $l, $m)) {
                $times[] = [$m[1], $m[2], $m[3]];
            }
            if (preg_match('/^([A-Z]{3}) \| ([A-Z]{3})(?: \||$)/', $l, $m)) {
                $codes[] = [$m[1], $m[2]];
            }
        }
        $cabin = null;
        foreach ($times as $t) {
            $cabin ??= $this->cabin($t[2]);
        }
        $baggage = preg_match('/(\d+) kg checked baggage/i', $text, $bm) ? $bm[1] . ' kg' : null;
        foreach ($codes as $k => [$from, $to]) {
            $fl = $flights[$k] ?? null;
            [$carrier, $flightNo] = $fl && preg_match('/^([A-Z0-9]{2})\s?(\d{1,4})$/', $fl[0], $fm) ? $this->flight($fm[1], $fm[2]) : ['FZ', null];
            $out['segments'][] = $this->segment([
                'carrier' => $carrier, 'flight_no' => $flightNo,
                'from' => $from, 'to' => $to,
                'dep' => $this->at($dates[$k][0] ?? null, $times[$k][0] ?? null),
                'arr' => $this->at($dates[$k][1] ?? null, $times[$k][1] ?? null),
                'cabin' => $cabin, 'baggage' => $baggage,
                'direction' => $fl[1] ?? null,
            ]);
        }

        return $out;
    }
}
