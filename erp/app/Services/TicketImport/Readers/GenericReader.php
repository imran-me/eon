<?php

namespace App\Services\TicketImport\Readers;

use App\Services\TicketImport\Cities;

/**
 * The fallback for every layout that has no reader of its own — Biman,
 * Novoair, Air Astra, Emirates, Qatar, Saudia, Etihad, Gulf Air, Oman Air,
 * Kuwait, Turkish, Singapore, Malaysia, Thai, Air India, Cathay, China
 * Southern / Eastern, SriLankan, Salam Air, flynas, and the agency / OTA
 * printouts (Amadeus, Sabre, Galileo variants, ShareTrip, GoZayaan, BDFare,
 * Flight Expert …).
 *
 * Every IATA e-ticket carries the same facts in the same standard shapes,
 * whatever the design around them: a 6-character PNR, 13-digit ticket
 * numbers (3-digit airline prefix + 10), GDS names "SURNAME/GIVEN MR",
 * a flight "EK 585" next to two airport codes, dates "12OCT26" /
 * "12 Oct 2026" and times "14:55". This reader looks for those shapes
 * only. It never guesses a value it cannot see; every card it fills says
 * so, so staff check it against the PDF.
 *
 * Must stay LAST in TicketPdfImporter::readers() — it matches anything
 * that looks like an air ticket at all.
 */
class GenericReader extends Reader
{
    /** IATA ticket-number prefix → airline code (airlines selling in BD). */
    public const PREFIX = [
        '997' => 'BG', '779' => 'BS', '176' => 'EK', '157' => 'QR', '065' => 'SV',
        '607' => 'EY', '072' => 'GF', '910' => 'WY', '229' => 'KU', '235' => 'TK',
        '618' => 'SQ', '232' => 'MH', '217' => 'TG', '098' => 'AI', '781' => 'MU',
        '784' => 'CZ', '999' => 'CA', '160' => 'CX', '603' => 'UL', '141' => 'FZ',
        '077' => 'MS', '125' => 'BA', '057' => 'AF', '074' => 'KL', '220' => 'LH',
        '180' => 'KE', '988' => 'OZ', '081' => 'QF', '131' => 'JL', '205' => 'NH',
        '297' => 'CI', '695' => 'BR', '071' => 'ET', '312' => '6E', '228' => 'UK',
        '514' => 'G9', '555' => 'SU', '738' => 'VN', '079' => 'PR', '126' => 'GA',
    ];

    /** Airline codes accepted next to a flight number. */
    public const AIRLINES = [
        'BG', 'BS', '2A', 'VQ', 'EK', 'QR', 'SV', 'EY', 'GF', 'WY', 'KU', 'TK', 'SQ',
        'MH', 'TG', 'AI', 'IX', 'MU', 'CZ', 'CA', 'CX', 'UL', 'FZ', 'G9', '3L', 'J9',
        'XY', 'OV', '6E', 'UK', 'SG', 'AK', 'D7', 'FD', 'OD', 'TR', 'MS', 'ET', 'PC',
        'PK', 'PA', 'ER', 'BA', 'AF', 'KL', 'LH', 'KE', 'OZ', 'QF', 'JL', 'NH', 'CI',
        'BR', 'SU', 'VN', 'PR', 'GA', 'H9', 'U2', 'W6', 'RJ', 'ME', 'KC', 'HY', '8M',
        'Q2', 'BT', 'RA', 'KB', 'ZF', 'H1', 'IY', 'NP', 'F3', 'E5',
    ];

    /** "RAHMAN/MD ABDUR MR" — capitals as printed; the title may be "Mr". */
    private const GDS_NAME = '/\b([A-Z][A-Z\'\-]+(?: [A-Z][A-Z\'\-]+)*)\s*\/\s*([A-Z][A-Z\'\-]+(?: [A-Z][A-Z\'\-]+)*?)\s*\.?\s*(MR|MRS|MS|MISS|MSTR|MASTER|DR|Mr|Mrs|Ms|Miss|Mstr|Dr)\b/';

    private ?string $issued = null;

    public function key(): string { return 'generic'; }

    public function label(): string { return 'Air ticket (general reader)'; }

    public function matches(string $text): bool
    {
        $u = strtoupper($text);
        $ticketish = preg_match('/E-?TICKET|ITINERARY|BOOKING|RESERVATION|PNR|BOARDING|FLIGHT/', $u);
        if (! $ticketish) {
            return false;
        }
        if ($this->segmentsFrom(preg_split('/\n/', $u)) !== []) {
            return true;
        }
        // No flight understood: still take it when the people are clear — the
        // card gets the passenger and staff enter the route (owner, 2026-10-11).
        // Invoices / EMD receipts print names and ticket numbers too, so not those.
        if (preg_match('/INVOICE|MISCELLANEOUS DOCUMENT|\bEMD\b|STATEMENT OF ACCOUNT/', $u)) {
            return false;
        }
        // a person printed the way tickets print one (capitals, as typed — not
        // prose like "Architecture/Engineering team") AND a ticket no. or a PNR
        $person = preg_match(self::GDS_NAME, $text)
            || preg_match('/^(?:\d+\.?\s*)?(?:MR|MRS|MS|MISS|MSTR|Mr|Mrs|Ms|Miss|Mstr)\.?\s+[A-Z][A-Za-z\']+(?: [A-Za-z\']+){0,4}\s*(?:\||$)/m', $text);
        $ticketNo = preg_match('/\b(' . implode('|', array_keys(self::PREFIX)) . ')[\s\-]?\d{10}\b/', $u);
        $pnr = preg_match('/(?:PNR|BOOKING\s*REF\w*|RESERVATION\s*(?:CODE|NUMBER)|RECORD LOCATOR)\s*(?:NO\.?)?\s*[:#|]?\s*[A-Z0-9]{6}\b/', $u);
        return $person && ($ticketNo || $pnr);
    }

    public function read(array $lines): array
    {
        $up = array_map(fn ($l) => strtoupper(str_replace('<<PAGE>>', '', $l)), $lines);
        $text = implode("\n", $up);
        $out = ['passengers' => [], 'segments' => [], 'warnings' => [
            'No dedicated reader for this layout — read by the general reader. Check every field against the PDF.',
        ]];

        // PNR: the airline's reference wins over the GDS / agency one
        // \b before the code: "BOOKING REF" must not read "ERENCE" out of "REFERENCE";
        // the lookahead drops words that sit where a code would ("TICKET NUMBER")
        $pnrRe = '\b\s*(?:NO\.?|NUMBER|CODE)?\s*[:#|\-]*\s*\b(?!(?:TICKET|NUMBER|STATUS|ADULT|ISSUED|DETAIL|PASSEN|BOOKIN|CONFIR|AIRLIN|ECONOM|SAMPLE)\w*)([A-Z0-9]{6})\b';
        if (preg_match('/AIRLINE\s*(?:PNR|REF(?:ERENCE)?|BOOKING (?:REF(?:ERENCE)?|CODE)|RESERVATION (?:CODE|NUMBER))' . $pnrRe . '/', $text, $m)
            && preg_match('/[A-Z]/', $m[1])) {
            $out['pnr'] = $m[1];
        } elseif (preg_match('/\bAIRLINE\s*[:|]\s*[A-Z0-9]{2}\s*\/\s*([A-Z0-9]{6})\b/', $text, $m) && preg_match('/[A-Z]/', $m[1])) {
            $out['pnr'] = $m[1];        // Amadeus: "BOOKING REF: AMADEUS: 7XKJQB, AIRLINE: SV/9HGT2P"
        }
        if (preg_match('/(?:PNR|BOOKING\s*REF(?:ERENCE)?|RESERVATION\s*(?:CODE|NUMBER|NO)|CONFIRMATION\s*(?:CODE|NUMBER|NO)|RECORD LOCATOR|BOOKING\s*(?:ID|CODE))' . $pnrRe . '/', $text, $m)
            && preg_match('/[A-Z]/', $m[1])) {
            if (empty($out['pnr'])) {
                $out['pnr'] = $m[1];
            } elseif ($m[1] !== $out['pnr']) {
                $out['gds_pnr'] = $m[1];
            }
        }

        // a table: "ORDER ID | CREATE DATE …" with the values on the next line
        if (empty($out['pnr'])) {
            foreach ($up as $k => $l) {
                // the value can sit one or two lines down (a translated label between)
                $next = null;
                foreach ([1, 2] as $d) {
                    if (isset($up[$k + $d]) && preg_match('/^([A-Z0-9]{6})(?=\s*(?:\||$))/', $up[$k + $d], $m) && preg_match('/[A-Z]/', $m[1])) {
                        $next = $k + $d;
                        break;
                    }
                }
                if (preg_match('/^(?:YOUR )?(?:PNR|ORDER ID|BOOKING (?:ID|REF\w*|CODE)|RESERVATION \w+|AIRLINE PNR)\b/', $l) && $next !== null) {
                    $up[$k + 1] = $up[$next];
                    $out['pnr'] = $m[1];
                    if (preg_match('/^\S+ \| (\d{1,2} [A-Z]{3},? \d{4})/', $up[$k + 1], $dm)) {
                        $out['issue_date'] = $this->date($dm[1]);
                    }
                    break;
                }
            }
        }
        if (preg_match('/(?:DATE OF ISSUE|ISSUE DATE|ISSUED ON|BOOKING DATE|DATE OF BOOKING)\s*[:|]*\s*(\d{1,2}\s*[A-Z]{3}[A-Z]*\s*,?\s*\d{2,4})/', $text, $m)) {
            $out['issue_date'] = $this->date($m[1]);
        }

        // ticket numbers, in order; the first prefix names the validating airline
        // "1574883925056", "157-4883925056", "057 231 765 920 9"
        preg_match_all('/\b(\d{3})[\s\-]?(\d{3}\s?\d{3}\s?\d{3}\s?\d|\d{10})\b/', $text, $tm, PREG_SET_ORDER);
        $tickets = [];
        foreach ($tm as $t) {
            $t[2] = str_replace(' ', '', $t[2]);
            if (isset(self::PREFIX[$t[1]]) || preg_match('/TICKET/', $text)) {
                $tickets[$t[1] . $t[2]] = $t[1];
            }
        }
        $firstPrefix = $tickets ? reset($tickets) : null;
        if ($firstPrefix && isset(self::PREFIX[$firstPrefix])) {
            $out['carrier'] = self::PREFIX[$firstPrefix];
        }
        $tickets = array_keys($tickets);

        // passengers: GDS names first, then "Passenger name: …" labels
        $names = [];
        $titles = 'MR|MRS|MS|MISS|MSTR|MASTER|DR';
        // on the text as printed: a GDS name is in capitals, prose is not
        preg_match_all(self::GDS_NAME, implode("\n", $lines), $nm, PREG_SET_ORDER);
        foreach ($nm as $n) {
            $names[] = $n[1] . '/' . $n[2] . ' ' . strtoupper($n[3]);
        }
        if (! $names) {
            // table rows that start with a title: "MR. FNU ARIF | ADULT | …"
            preg_match_all('/^(?:\d+\.?\s*)?((?:' . $titles . ')\.?\s+[A-Z][A-Z\' ]{2,40}?)\s*(?:\||$)/m', $text, $nm);
            $names = $nm[1] ?? [];
        }
        if (! $names) {
            // "Passenger Name: X" — a label with a colon, never a table header
            preg_match_all('/(?:PASSENGER|TRAVELL?ER|PAX)(?:\s*NAME)?\s*:\s*((?:(?:' . $titles . ')\.?\s+)?[A-Z][A-Z\.\' ]{2,40}?)\s*(?:\||$)/m', $text, $nm);
            $names = $nm[1] ?? [];
        }
        $seen = [];
        foreach ($names as $raw) {
            $p = $this->passenger($raw);
            if ($p['name'] === '' || isset($seen[$p['name']]) || str_word_count($p['name']) > 6) {
                continue;
            }
            $seen[$p['name']] = true;
            if (in_array($p['title'], ['MSTR', 'MASTER'], true) || str_contains($raw, 'CHD')) {
                $p['pax_type'] = 'child';
            }
            $out['passengers'][] = $p;
        }
        foreach ($out['passengers'] as $k => $p) {
            if (isset($tickets[$k]) && count($tickets) >= count($out['passengers'])) {
                $out['passengers'][$k]['eticket'] = $tickets[$k];
            }
        }

        // passport — only safe to put on a card when there is one person
        preg_match_all('/PASSPORT\s*(?:NO\.?|NUMBER|#)?\s*[:|]*\s*([A-Z]{1,2}\d{6,8})\b/', $text, $pm);
        $passports = array_values(array_unique($pm[1] ?? []));
        if (count($passports) === count($out['passengers'])) {
            foreach ($passports as $k => $no) {
                $out['passengers'][$k]['passport_no'] = $no;
            }
        }

        // fare — the booking total; only on a card when it is one person
        if (preg_match('/(?:GRAND TOTAL|TOTAL (?:FARE|AMOUNT|PRICE|PAID)|TOTAL)\s*[:|]*\s*(BDT|USD|AED|SAR|QAR|INR|MYR|EUR)?\s*([\d,]+(?:\.\d{1,2})?)/', $text, $m)
            && $this->money($m[2]) > 0) {
            $out['currency'] = $m[1] ?: (str_contains($text, 'BDT') ? 'BDT' : null);
            $total = $this->money($m[2]);
            $base = preg_match('/(?:BASE FARE|AIR ?FARE|FARE)\s*[:|]*\s*(?:BDT|USD|AED)?\s*([\d,]+(?:\.\d{1,2})?)/', $text, $b) ? $this->money($b[1]) : null;
            if (count($out['passengers']) === 1) {
                $out['passengers'][0]['fare'] = [
                    'base' => $base, 'tax' => ($base !== null && $base <= $total) ? $total - $base : null,
                    'total' => $total, 'tax_breakdown' => null,
                ];
            } else {
                $out['warnings'][] = 'A total of ' . number_format($total) . ' is printed — check whether it is per person or for the booking.';
            }
        }

        $this->issued = $out['issue_date'] ?? null;
        $out['segments'] = $this->segmentsFrom($up);
        $out['carrier'] ??= $out['segments'][0]['carrier'] ?? null;
        return $out;
    }

    /** Flights: a flight number with two airport codes and a date close by. */
    private function segmentsFrom(array $up): array
    {
        $known = array_flip(array_merge(array_values(Cities::MAP), [
            'DAC', 'CGP', 'ZYL', 'CXB', 'SPD', 'RJH', 'JSR', 'BZL', 'IST', 'SAW', 'DXB',
            'DWC', 'AUH', 'SHJ', 'DOH', 'BAH', 'MCT', 'KWI', 'RUH', 'JED', 'DMM', 'MED',
            'TIF', 'AHB', 'TUU', 'ELQ', 'GIZ', 'TAB', 'RKT', 'SLL', 'KUL', 'SIN', 'BKK',
            'DMK', 'CCU', 'DEL', 'BOM', 'MAA', 'BLR', 'HYD', 'KTM', 'CMB', 'MLE', 'CAN',
            'KMG', 'PVG', 'PEK', 'HKG', 'ICN', 'NRT', 'HND', 'LHR', 'MAN', 'CDG', 'FRA',
            'FCO', 'MXP', 'ECN', 'RMO', 'CAI', 'AMM', 'BEY', 'ADD', 'NBO', 'JFK', 'YYZ',
            'SYD', 'MEL', 'CGK', 'MNL', 'SGN', 'HAN', 'PEN', 'BKI', 'KCH', 'TAS', 'GYD',
        ]));
        $airlines = array_flip(self::AIRLINES);
        $segs = [];
        $keys = [];
        $n = count($up);
        for ($i = 0; $i < $n; $i++) {
            if (! preg_match_all('/\b([A-Z]{2}|[A-Z]\d|\d[A-Z])\s?-?\s?(\d{2,4})\b/', $up[$i], $fms, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($fms as $fm) {
                if (! isset($airlines[$fm[1]])) {
                    continue;
                }
                // the flight's own row and the two under it first; wider only when that misses
                $win = implode(' | ', array_slice($up, $i, 3));
                if (! preg_match('/\d{1,2}[\s\/\-]?(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)/', $win)) {
                    $win = implode(' | ', array_slice($up, max(0, $i - 2), 7));
                }
                preg_match_all('/\b([A-Z]{3})\b/', $win, $am);
                $codes = [];
                foreach ($am[1] as $c) {
                    if (isset($known[$c]) && end($codes) !== $c) {
                        $codes[] = $c;
                    }
                }
                if (count($codes) < 2) {
                    // cities written out: "Dhaka … Dubai"
                    $codes = Cities::inOrder($win);
                }
                if (count($codes) < 2 || $codes[0] === $codes[1]) {
                    continue;
                }
                preg_match_all('/\b(\d{1,2}[\s\/\-]?(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\.?[\s\/\-]?,?\s?(?:\d{4}|\d{2}(?![\d:]))?)/', $win, $dm);
                $after = $segs ? end($segs)['dep'] : ($this->issued ?? date('Y-m-d', strtotime('-30 days')));
                $yr = (int) substr((string) $after, 0, 4) ?: null;
                $dates = array_values(array_filter(array_map(fn ($d) => $this->date($d, $yr, $after), $dm[1])));
                if (! $dates) {
                    continue;
                }
                preg_match_all('/\b([01]?\d|2[0-3]):?([0-5]\d)\s?(?:HRS?|H)?\b(?!\s?(?:KG|PC))/', $win, $tmm, PREG_SET_ORDER);
                $times = array_values(array_filter(array_map(fn ($t) => strlen($t[0]) >= 4 && str_contains($t[0], ':') ? sprintf('%02d:%02d', $t[1], $t[2]) : null, $tmm)));
                [$carrier, $flightNo] = $this->flight($fm[1], $fm[2]);
                $dep = $this->at($dates[0], $times[0] ?? null);
                $arr = isset($times[1]) ? $this->at($dates[1] ?? $dates[0], $times[1]) : null;
                if ($arr && $dep && $arr < $dep && ! isset($dates[1])) {
                    $arr = $this->at($this->plusDays($dates[0], 1), $times[1]);
                }
                $key = $flightNo . '|' . $dates[0];
                if (isset($keys[$key])) {
                    continue;
                }
                $keys[$key] = true;
                $bag = preg_match('/\b(\d{1,2})\s?(KG|PC|PCS)\b/', $win, $bm) ? $bm[1] . ' ' . strtolower($bm[2]) : null;
                $segs[] = $this->segment([
                    'carrier' => $carrier, 'flight_no' => $flightNo,
                    'from' => $codes[0], 'to' => $codes[1],
                    'dep' => $dep, 'arr' => $arr,
                    'cabin' => $this->cabin($win), 'baggage' => $bag,
                    'status' => str_contains($win, 'CONFIRMED') || preg_match('/\bOK\b|\bHK\b/', $win) ? 'confirmed' : null,
                ]);
                break;
            }
        }
        usort($segs, fn ($a, $b) => strcmp((string) $a['dep'], (string) $b['dep']));
        return $segs;
    }
}
