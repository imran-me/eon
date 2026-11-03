<?php

namespace App\Services\TicketImport\Readers;

/**
 * Jazeera Airways "Booking Confirmed" (jazeeraairways.com). The sample the
 * owner had (H73KNM) was a SCANNED picture, read by OCR in the browser, so
 * this reader expects OCR's mistakes: "40ct26" for "4 Oct 26", stray
 * symbols, a time that did not come through. A value it cannot be sure of
 * is left empty, and every card from a scan says so.
 *
 *   Booking Reference: H73KNM · Booked 28 Sept, 2026
 *   Flight No. J9534 …  "Dhaka - DAC — Kuwait - KWI" … times / "4 Oct 26"
 *   Baggage: "Mr. SYEDUZZAMAN MAZUMDER 7kg ( 1Piece) 20kg (Piece )"
 */
class JazeeraReader extends Reader
{
    public function key(): string { return 'jazeera'; }

    public function label(): string { return 'Jazeera Airways booking'; }

    public function matches(string $text): bool
    {
        return (bool) preg_match('/jazeera/i', $text) && (bool) preg_match('/Booking\s+Reference/i', $text);
    }

    public function read(array $lines): array
    {
        $lines = array_map(fn ($l) => $this->clean($l), $lines);
        $text = implode("\n", $lines);
        $out = ['passengers' => [], 'segments' => [], 'warnings' => [], 'carrier' => 'J9'];

        if (preg_match('/Booking\s+Reference:?\s*([A-Z0-9]{6})/i', $text, $m)) {
            $out['pnr'] = strtoupper($m[1]);
        }
        if (preg_match('/Booked\s+(\d{1,2}\s+[A-Za-z]{3,9},?\s+\d{4})/', $text, $m)) {
            $out['issue_date'] = $this->date(str_replace(',', '', $m[1]));
        }

        // passengers: the baggage table prints each full name on one line
        $seen = [];
        foreach ($lines as $l) {
            if (preg_match('/^(Mr|Mrs|Ms|Miss|Mstr)\.?\s+([A-Z][A-Z .\'-]+?)\s+\d+\s*kg/', $l, $m)) {
                $p = $this->passenger($m[1] . ' ' . trim($m[2]));
                if (! isset($seen[$p['name']])) {
                    $seen[$p['name']] = true;
                    $out['passengers'][] = $p;
                }
            }
        }
        // no baggage table: the header name ("Mr. SYEDUZZAMAN" + "MAZUMDER" on the next line)
        if (! $out['passengers']) {
            foreach ($lines as $i => $l) {
                if (preg_match('/^(Mr|Mrs|Ms|Miss|Mstr)\.?\s+([A-Z][A-Z .\'-]+?)(?:\s*[\[\(|=].*)?$/', $l, $m)) {
                    $name = trim($m[2]);
                    if (isset($lines[$i + 1]) && preg_match('/^[A-Z][A-Z\'-]+$/', trim($lines[$i + 1]))) {
                        $name .= ' ' . trim($lines[$i + 1]);
                    }
                    $out['passengers'][] = $this->passenger($m[1] . ' ' . $name);
                    break;
                }
            }
        }

        // segments: each starts at "Flight No. J9534"
        $starts = [];
        foreach ($lines as $i => $l) {
            if (preg_match('/Flight\s*No\.?\s*([A-Z0-9]{2})\s?(\d{2,4})/i', $l)) {
                $starts[] = $i;
            }
        }
        foreach ($starts as $k => $s) {
            $end = $starts[$k + 1] ?? min(count($lines), $s + 10);
            $block = array_slice($lines, $s, $end - $s);
            preg_match('/Flight\s*No\.?\s*([A-Z0-9]{2})\s?(\d{2,4})/i', $block[0], $fm);
            [$carrier, $flightNo] = $this->flight($fm[1], $fm[2]);
            $seg = ['carrier' => $carrier, 'flight_no' => $flightNo, 'cabin' => 'economy'];
            $bt = implode("\n", $block);
            if (preg_match('/-\s*([A-Z]{3})\b.*?-\s*([A-Z]{3})\b/s', $bt, $cm)) {
                $seg['from'] = $cm[1];
                $seg['to'] = $cm[2];
            }
            preg_match_all('/\b(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{2,4})\b/', $bt, $dm);
            $date = $this->date($dm[1][0] ?? null) ?? ($out['segments'] ? substr((string) end($out['segments'])['dep'], 0, 10) : null);
            // a time at the START of a line is the departure, one after the airport name the arrival
            $dep = $arr = null;
            foreach ($block as $l) {
                if (! $dep && preg_match('/^(\d{1,2}:\d{2})\b/', $l, $tm)) $dep = $tm[1];
                if (! $arr && preg_match('/Airport\s*\|\s*(\d{1,2}:\d{2})/', $l, $tm)) $arr = $tm[1];
            }
            // no readable time: the date alone — "00:00" would look like a real midnight departure
            $seg['dep'] = $date ? ($dep ? $this->at($date, $dep) : $date) : null;
            $seg['arr'] = $date && $arr ? $this->at($date, $arr) : null;
            if (! $dep || ! $arr) {
                $out['warnings'][] = $flightNo . ': a departure or arrival time could not be read from the scan — type it.';
            }
            $out['segments'][] = $this->segment($seg);
        }

        if (preg_match('/\d+\s*kg[^\n]*?(\d{2,})\s*kg/i', $text, $bm)) {
            foreach ($out['segments'] as $k => $s) {
                $out['segments'][$k]['baggage'] = $bm[1] . ' kg';
            }
        }
        return $out;
    }

    /** Undo OCR's usual slips: "40ct26" → "4 Oct 26", "Sept" → "Sep". */
    private function clean(string $l): string
    {
        $l = preg_replace('/\b(\d{1,2})\s*[0O](ct)\s*(\d{2,4})\b/i', '$1 O$2 $3', $l);
        $l = preg_replace('/\b(\d{1,2})(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)(\d{2,4})\b/i', '$1 $2 $3', $l);
        $l = preg_replace('/\bSept\b/', 'Sep', $l);
        return trim(preg_replace('/\s+/', ' ', $l));
    }
}
