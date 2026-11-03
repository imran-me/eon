<?php

namespace App\Services\TicketImport\Readers;

/**
 * Air Arabia "CONFIRMED RESERVATION" (airarabia.com). Codes on one line
 * ("DAC | SHJ"), flight number on the next, then "Economy | 25 Sep 2026
 * 22:40 | 26 Sep 2026 01:35". No ticket number, no passport, no fare.
 *
 * The names sit between "Scan this barcode" and "Reservation Number".
 * The one sample seen printed two ("Mr Fahim Monayem", "MR MD SAIFUL
 * ISLAM") — so every name becomes a card and the review says so when there
 * is more than one, rather than guessing which one travels.
 */
class AirArabiaReader extends Reader
{
    public function key(): string { return 'airarabia'; }

    public function label(): string { return 'Air Arabia reservation'; }

    public function matches(string $text): bool
    {
        return str_contains($text, 'airarabia.com') && str_contains($text, 'Reservation Number');
    }

    public function read(array $lines): array
    {
        $out = ['passengers' => [], 'segments' => [], 'warnings' => [], 'carrier' => 'G9'];

        $scan = $this->find($lines, '/^Scan this barcode/');
        $res = $this->find($lines, '/^Reservation Number/');
        if ($scan !== null && $res !== null) {
            for ($i = $scan + 1; $i < $res; $i++) {
                if (preg_match('/^(MR|MRS|MS|MISS|MSTR|DR)\.?\s+\S/i', $lines[$i])) {
                    $out['passengers'][] = $this->passenger($lines[$i]);
                }
            }
        }
        if ($res !== null && preg_match('/^([A-Z0-9]{5,7})$/', $lines[$res + 1] ?? '', $m)) {
            $out['pnr'] = $m[1];
        }
        if (count($out['passengers']) > 1) {
            $out['warnings'][] = 'Air Arabia printed ' . count($out['passengers']) . ' names — remove any card for a person who is not travelling.';
        }

        foreach ($lines as $i => $l) {
            if (! preg_match('/^([A-Z]{3}) \| ([A-Z]{3})$/', $l, $cm)) {
                continue;
            }
            $fl = $lines[$i + 1] ?? '';
            $dt = $lines[$i + 2] ?? '';
            if (! preg_match('/^([A-Z0-9]{2})(\d{1,4}) \|/', $fl, $fm)) {
                continue;
            }
            preg_match_all('/(\d{1,2} [A-Z][a-z]{2} \d{4}) (\d{1,2}:\d{2})/', $dt, $dm, PREG_SET_ORDER);
            [$carrier, $flightNo] = $this->flight($fm[1], $fm[2]);
            $flCells = $this->cells($fl);
            $out['segments'][] = $this->segment([
                'carrier' => $carrier, 'flight_no' => $flightNo,
                'from' => $cm[1], 'to' => $cm[2],
                'from_name' => $flCells[1] ?? null, 'to_name' => $flCells[2] ?? null,
                'dep' => isset($dm[0]) ? $this->at($this->date($dm[0][1]), $dm[0][2]) : null,
                'arr' => isset($dm[1]) ? $this->at($this->date($dm[1][1]), $dm[1][2]) : null,
                'cabin' => $this->cabin($this->cells($dt)[0] ?? null),
            ]);
        }

        // "Checked Baggage | 20Kg 1 Piece | 20Kg 1 Piece" — one column per sector
        foreach ($lines as $l) {
            if (str_starts_with($l, 'Checked Baggage |')) {
                $cols = array_slice($this->cells($l), 1);
                foreach ($out['segments'] as $k => $s) {
                    if (preg_match('/(\d+)\s*Kg/i', $cols[$k] ?? $cols[0] ?? '', $bm)) {
                        $out['segments'][$k]['baggage'] = $bm[1] . ' kg';
                    }
                }
            }
        }

        return $out;
    }
}
