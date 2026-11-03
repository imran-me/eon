<?php

namespace App\Services\TicketImport\Readers;

/**
 * IndiGo itinerary (goindigo.in): "PNR / Booking Ref | QBNHYJ", a passenger
 * table, then per flight "6E 1114 . A320", the two airports as "DAC - …
 * | MAA - …", and the two "14:55, 26 Sep 2026" stamps. No ticket number or
 * passport; the fare is the "Flight summary" (AirFare charge + the fees =
 * flight total) and add-ons on top give "Total fare".
 */
class IndigoReader extends Reader
{
    public function key(): string { return 'indigo'; }

    public function label(): string { return 'IndiGo itinerary'; }

    public function matches(string $text): bool
    {
        return str_contains($text, 'goindigo') && str_contains($text, 'PNR / Booking Ref');
    }

    public function read(array $lines): array
    {
        $out = ['passengers' => [], 'segments' => [], 'warnings' => [], 'carrier' => '6E', 'currency' => 'BDT'];
        $text = implode("\n", $lines);

        if (preg_match('/PNR \/ Booking Ref\s*\|?\s*([A-Z0-9]{5,7})\b/', $text, $m)) {
            $out['pnr'] = $m[1];
        }
        if (preg_match('/Date of booking (\d{1,2} \w{3} \d{4})/', $text, $m)) {
            $out['issue_date'] = $this->date($m[1]);
        }

        $pi = $this->find($lines, '/^Passenger Information$/');
        if ($pi !== null) {
            for ($i = $pi + 1; $i < count($lines) && ! str_starts_with($lines[$i], 'Sector'); $i++) {
                $cells = $this->cells($lines[$i]);
                if (preg_match('/^(MR|MRS|MS|MISS|MSTR|DR)\b/i', $cells[0])) {
                    $p = $this->passenger($cells[0]);
                    $p['pax_type'] = strtolower($cells[1] ?? 'adult') === 'child' ? 'child' : (strtolower($cells[1] ?? '') === 'infant' ? 'infant' : 'adult');
                    $out['passengers'][] = $p;
                }
            }
        }

        // segments: each starts at "6E 1114 . A320"
        $starts = [];
        foreach ($lines as $i => $l) {
            if (preg_match('/^([A-Z0-9]{2}) (\d{1,4}) \. /', $l)) {
                $starts[] = $i;
            }
        }
        foreach ($starts as $k => $s) {
            $end = min($starts[$k + 1] ?? count($lines), $s + 20);
            $block = array_slice($lines, $s, $end - $s);
            preg_match('/^([A-Z0-9]{2}) (\d{1,4})/', $block[0], $fm);
            [$carrier, $flightNo] = $this->flight($fm[1], $fm[2]);
            $seg = ['carrier' => $carrier, 'flight_no' => $flightNo, 'cabin' => 'economy'];
            $stamps = [];
            foreach ($block as $l) {
                if (! isset($seg['from']) && preg_match('/^([A-Z]{3}) - (.*?) \| ([A-Z]{3}) - (.*)$/', $l, $m)) {
                    $seg['from'] = $m[1];
                    $seg['to'] = $m[3];
                    $seg['from_name'] = trim($m[2]);
                    $seg['to_name'] = trim($m[4]);
                }
                if (preg_match('/^(\d{1,2}:\d{2}), (\d{1,2} \w{3} \d{4})$/', $l, $m)) {
                    $stamps[] = $this->at($this->date($m[2]), $m[1]);
                }
            }
            // the two stamps print in column order, not time order
            sort($stamps);
            $seg['dep'] = $stamps[0] ?? null;
            $seg['arr'] = $stamps[1] ?? null;
            $out['segments'][] = $this->segment($seg);
        }

        if (preg_match('/Check-in : (\d+)\s*KG/i', $text, $bm)) {
            foreach ($out['segments'] as $k => $s) {
                $out['segments'][$k]['baggage'] = $bm[1] . ' kg';
            }
        }

        // fare — printed for the booking; only put on a card when it is one person
        $base = preg_match('/AirFare charge \| BDT ([\d,]+)/', $text, $m) ? $this->money($m[1]) : null;
        $total = preg_match('/Total fare \| BDT ([\d,]+)/', $text, $m) ? $this->money($m[1]) : null;
        $flight = null;
        $fs = $this->find($lines, '/^Add-ons summary/');
        if ($fs !== null && preg_match('/^BDT ([\d,]+)$/', $lines[$fs - 1], $m)) {
            $flight = $this->money($m[1]);
        }
        // the fees listed under "Flight summary", one "name | BDT n" line each, up to the
        // flight subtotal — "Fuel Charge 9675, Bangladesh: Embarkation Fee 500, …"
        $breakdown = [];
        $af = $this->find($lines, '/^AirFare charge \|/');
        if ($af !== null) {
            for ($i = $af + 1, $n = $fs ?? count($lines); $i < $n; $i++) {
                if (preg_match('/^(.+?) \| BDT ([\d,]+)$/', $lines[$i], $m)) {
                    $breakdown[] = trim($m[1]) . ' ' . $this->money($m[2]);
                }
            }
        }
        if ($total !== null && count($out['passengers']) === 1) {
            $out['passengers'][0]['fare'] = [
                'base'  => $base,
                'tax'   => ($flight !== null && $base !== null) ? $flight - $base : null,
                'total' => $total,
                'tax_breakdown' => $breakdown ? implode(', ', $breakdown) : null,
            ];
            if ($flight !== null && $total > $flight) {
                $out['warnings'][] = 'Total includes add-ons of BDT ' . number_format($total - $flight) . ' (meals / extras).';
            }
        } elseif ($total !== null) {
            $out['warnings'][] = 'The fare (BDT ' . number_format($total) . ') is printed for the whole booking — type each person\'s cost.';
        }

        return $out;
    }
}
