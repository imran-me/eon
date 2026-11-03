<?php

namespace App\Services\TicketImport\Readers;

/**
 * US-Bangla Airlines' own e-ticket ("Booking reference # …", www.usbair.com).
 * Prints the passport number and the full fare: base, tax & s/c and the
 * tax codes ("MY : 2,211; H8 : 30; …").
 *
 * Its itinerary row wraps: the dates sit on the line above the flight
 * number and the departure / arrival times on the line below it.
 */
class UsBanglaReader extends Reader
{
    public function key(): string { return 'usbangla'; }

    public function label(): string { return 'US-Bangla e-ticket'; }

    public function matches(string $text): bool
    {
        return (bool) preg_match('/usbair\.com|US[\s-]?Bangla Airlines/i', $text) && str_contains($text, 'Booking reference');
    }

    public function read(array $lines): array
    {
        $out = ['passengers' => [], 'segments' => [], 'warnings' => [], 'carrier' => 'BS', 'currency' => 'BDT'];
        $text = implode("\n", $lines);

        if (preg_match('/Booking reference\s*#\s*([A-Z0-9]{5,7})/', $text, $m)) {
            $out['pnr'] = $m[1];
        }
        if (preg_match('/Date of issue \| : \| (\d{1,2} \w{3} \d{2,4})/', $text, $m)) {
            $out['issue_date'] = $this->date($m[1]);
        }

        // ── passengers ────────────────────────────────────────────────────
        $h = $this->find($lines, '/^Passenger Name \| Ticket Number/');
        if ($h !== null) {
            for ($i = $h + 1; $i < count($lines) && ! str_starts_with($lines[$i], 'Travel Note'); $i++) {
                $cells = $this->cells($lines[$i]);
                if (count($cells) < 2 || ! preg_match('/^\d{13}$/', $cells[1])) {
                    continue;
                }
                $p = $this->passenger($cells[0], ['eticket' => $cells[1]]);
                foreach (array_slice($cells, 2) as $c) {
                    if (preg_match('/^[A-Z]{1,2}\d{6,9}$/', $c)) {
                        $p['passport_no'] = $c;
                    } elseif (preg_match('/child/i', $c)) {
                        $p['pax_type'] = 'child';
                    } elseif (preg_match('/infant/i', $c)) {
                        $p['pax_type'] = 'infant';
                    }
                }
                $out['passengers'][] = $p;
            }
        }

        // ── segments ──────────────────────────────────────────────────────
        foreach ($lines as $i => $l) {
            if (! preg_match('/^(BS)\s?(\d{2,4})\s+(?:\d{1,2}:\d{2}\s+)?.*?\(([A-Z]{3})\).*?\(([A-Z]{3})\)(.*)$/', $l, $m)) {
                continue;
            }
            preg_match_all('/\b(\d{1,2} [A-Z][a-z]{2} \d{2})\b/', $lines[$i - 1] ?? '', $dm);
            preg_match_all('/\b(\d{1,2}:\d{2})\b/', $lines[$i + 1] ?? '', $tm);
            $depDate = $this->date($dm[1][0] ?? null);
            $arrDate = $this->date($dm[1][1] ?? null) ?? $depDate;
            $rest = $this->cells($m[5]);
            [$carrier, $flightNo] = $this->flight($m[1], $m[2]);
            $seg = [
                'carrier' => $carrier, 'flight_no' => $flightNo,
                'from' => $m[3], 'to' => $m[4],
                'dep' => $this->at($depDate, $tm[1][0] ?? null),
                'arr' => $this->at($arrDate, $tm[1][1] ?? null),
                'cabin' => $this->cabin($m[5]),
                'status' => preg_match('/\b(OK|HK|RQ|WL)\b/', $m[5], $sm) ? $sm[1] : null,
            ];
            foreach ($rest as $c) {
                if (preg_match('/^[A-Z]$/', $c)) {
                    $seg['rbd'] = $c;
                    break;
                }
            }
            $out['segments'][] = $this->segment($seg);
        }

        // ── fare: "KUL ➜ DAC | Adult(s) | MD RAKIB | 35 Kg | XSKULO | BDT | 31,191 | 3,218 | 34,409 | 34,409"
        $fares = [];
        foreach ($lines as $l) {
            $cells = $this->cells($l);
            if (count($cells) >= 9 && preg_match('/^[A-Z]{3}\s*\S+\s*[A-Z]{3}$/u', $cells[0]) && preg_match('/^[A-Z]{3}$/', $cells[5] ?? '')) {
                [$name] = $this->person($cells[2]);
                $f = $fares[$name] ?? ['base' => 0, 'tax' => 0, 'total' => 0];
                $f['base'] += $this->money($cells[6]) ?? 0;
                $f['tax'] += $this->money($cells[7]) ?? 0;
                $f['total'] += $this->money($cells[8]) ?? 0;
                $fares[$name] = $f;
                $out['currency'] = $cells[5];
                foreach ($out['segments'] as $k => $s) {
                    if (! $s['baggage'] && preg_match('/(\d+)\s*Kg/i', $cells[3], $bm)) {
                        $out['segments'][$k]['baggage'] = $bm[1] . ' kg';
                    }
                }
            }
        }
        $taxCodes = preg_match('/((?:[A-Z0-9]{2} : [\d,]+;\s*)+)/', $text, $tm) ? $this->taxCodes($tm[1]) : null;
        foreach ($out['passengers'] as $k => $p) {
            $f = $fares[$p['name']] ?? (count($fares) === 1 && count($out['passengers']) === 1 ? reset($fares) : null);
            if ($f) {
                // the tax codes are printed once for the booking — only safe to
                // copy onto the card when the booking is one passenger
                $f['tax_breakdown'] = count($out['passengers']) === 1 ? $taxCodes : null;
                $out['passengers'][$k]['fare'] = $f;
            }
        }

        return $out;
    }

    /** "MY : 2,211; H8 : 30;" → "MY 2211, H8 30" (the entry card's own format). */
    private function taxCodes(string $s): string
    {
        preg_match_all('/([A-Z0-9]{2}) : ([\d,]+)/', $s, $m, PREG_SET_ORDER);
        return implode(', ', array_map(fn ($x) => $x[1] . ' ' . str_replace(',', '', $x[2]), $m));
    }
}
