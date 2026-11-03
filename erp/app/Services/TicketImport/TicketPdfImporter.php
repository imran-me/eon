<?php

namespace App\Services\TicketImport;

/**
 * Ticket PDF → what Ticket Entry's cards need. Reads only; writes nothing.
 *
 * The browser turns the PDF into text lines with pdf.js (the same way the
 * bank statement compare does), so no file reaches the server here and no
 * PDF library has to be installed. Each layout has its own reader; the first
 * whose matches() says yes reads the file. An unknown layout is reported
 * as unknown — never guessed at.
 *
 * Our own readers only, no outside service (owner, 2026-10-05).
 */
class TicketPdfImporter
{
    /** Reader keys to use and no others — to test how a layout reads without its own reader. */
    public static ?array $only = null;

    /** @return Readers\Reader[] — most specific first. */
    public static function readers(): array
    {
        $all = [
            new Readers\GalileoReader,
            new Readers\SabreReader,
            new Readers\UsBanglaReader,
            new Readers\PortalOrderReader,
            new Readers\AirArabiaReader,
            new Readers\IndigoReader,
            new Readers\FlydubaiReader,
            new Readers\JazeeraReader,
            new Readers\AgencyItineraryReader,
            new Readers\GenericReader,   // always last: any other layout
        ];

        return self::$only === null ? $all : array_values(array_filter($all, fn ($r) => in_array($r->key(), self::$only, true)));
    }

    /**
     * @param  string[]  $lines  the PDF's text lines, "<<PAGE>>" between pages
     */
    public function read(array $lines, string $fileName = ''): array
    {
        $lines = array_values(array_map(fn ($l) => trim((string) $l), $lines));
        // A scanned picture is read by OCR in the browser and arrives marked
        // "<<OCR>>": the same readers, but every card says to check it.
        $ocr = ($lines[0] ?? '') === '<<OCR>>';
        if ($ocr) {
            array_shift($lines);
        }
        $text = implode("\n", $lines);

        $result = self::blank($fileName);
        $result['ocr'] = $ocr;
        if (mb_strlen(trim(str_replace('<<PAGE>>', '', $text))) < 20) {
            $result['status'] = 'image';
            $result['message'] = 'This PDF is a picture (scanned or photographed) — it has no text to read. Enter it by hand.';

            return $result;
        }

        $known = false;
        foreach (self::readers() as $reader) {
            if (! $reader->matches($text)) {
                continue;
            }
            try {
                $read = $reader->read($lines);
            } catch (\Throwable $e) {
                // one reader breaking on a variant must not lose the file —
                // the next one (the general reader last) gets a try
                report($e);

                continue;
            }
            $result['layout'] = $reader->key();
            $result['layout_label'] = $reader->label();
            $result = array_merge($result, array_intersect_key($read, $result));
            $known = true;
            break;
        }

        // Whatever is still empty: by the labels staff taught, by each value's
        // type, by the common labels (Lessons + Detector). Never a dead end:
        // what is found is filled, the rest is typed on the card and learned on Save.
        // The general reader only guesses too, so its flights give way to ones
        // read by type when those are at least as complete.
        $lessons = new Lessons;
        try {
            $result = $lessons->fill($lines, $result, ! $known || $result['layout'] === 'generic');
        } catch (\Throwable $e) {
            report($e);
        }
        $result['layout_memory'] = $lessons->layoutId;
        if (! $known) {
            $result['layout'] = 'labels';
            $result['layout_label'] = $lessons->layoutId ? 'Layout seen before — read by what staff taught' : 'New layout — read by value types';
            array_unshift($result['warnings'], $lessons->layoutId
                ? 'This layout was learned from earlier imports — check what is filled, type the rest. Corrections are learned on Save.'
                : 'This layout is new: what could be read is filled — type the rest. What you type is learned on Save.');
        }
        $checks = array_intersect_key(['name' => 'passenger name', 'eticket' => 'ticket no.', 'passport' => 'passport',
            'route' => 'route', 'flight' => 'flight no.', 'date' => 'date / time'], array_flip($lessons->guessed));
        if ($checks) {
            $result['warnings'][] = 'Read by what the value looks like (no label for it on the page) — check: '.implode(', ', $checks).'.';
        }

        $result['segments'] = self::directions(self::withoutPhantoms($result['segments']));
        $result['status'] = $result['passengers'] && $result['segments'] ? 'read' : 'partial';
        if (! $result['passengers']) {
            $result['warnings'][] = 'No passenger name read — choose the passenger on the card.';
        }
        if (! $result['segments']) {
            $result['warnings'][] = 'The flight could not be read — choose the route on the card.';
        }
        $result['carrier'] ??= $result['segments'][0]['carrier'] ?? null;
        // The TICKET's own PNR (Galileo / GDS / booking code) is the PNR, not the airline's (owner,
        // 2026-10-17: "Import PDF e Airline PNR detect kore Galileo PNR (Ticket PNR) detect korbe").
        // The airline's is kept as airline_pnr — shown on the card's strip, and still matched for
        // "already on file", so a ticket saved earlier under it is still found.
        if ($result['gds_pnr'] && $result['gds_pnr'] !== $result['pnr']) {
            $result['airline_pnr'] = $result['pnr'];
            $result['pnr'] = $result['gds_pnr'];
        }
        if ($ocr) {
            array_unshift($result['warnings'], 'Read from a scanned picture (OCR) — check every field against the PDF.');
        }

        return $result;
    }

    public static function blank(string $fileName = ''): array
    {
        return [
            'file' => $fileName,
            'status' => 'unknown',   // read | partial | unknown | image | failed
            'message' => null,
            'layout' => null,
            'layout_label' => null,
            'pnr' => null,        // the ticket's PNR — what goes in the card's PNR box (the GDS one when printed)
            'gds_pnr' => null,        // the Galileo / Sabre / booking code, when the PDF prints one
            'airline_pnr' => null,        // the airline's own, when it differs (2026-10-17)
            'carrier' => null,        // validating / issuing airline code
            'issue_date' => null,
            'currency' => null,
            'passengers' => [],
            'segments' => [],
            'warnings' => [],
            'ocr' => false,       // read from a scanned picture
            'layout_memory' => null,       // ticket_import_layouts.id this PDF's design matched
        ];
    }

    /**
     * A "flight" that carries nothing of a flight — no airline, no flight number, no time — beside
     * real ones is a misread: two airport-like codes in a remark. Pegasus prints "TOW/ECN/P784 -" (a
     * service note); read as TOW → ECN it made IST → ECN look like IST → ECN → ECN, and the route
     * IST-ECN was never picked (owner, 2026-10-18). A ticket whose flights all carry nothing keeps them.
     */
    private static function withoutPhantoms(array $segs): array
    {
        $real = fn ($s) => ! empty($s['carrier']) || ! empty($s['flight_no']) || ! empty($s['dep']) || ! empty($s['arr']);
        if (count($segs) < 2 || ! array_filter($segs, $real)) {
            return $segs;
        }

        return array_values(array_filter($segs, $real));
    }

    /**
     * Mark each segment out / return. A reader that knows (portal, flydubai)
     * has already said so. Otherwise: a journey that ends where it began is a
     * round trip, and the return starts at the first connection longer than
     * a day — a stop that long is a stay, not a transfer.
     */
    private static function directions(array $segs): array
    {
        if (! $segs) {
            return $segs;
        }
        if (array_filter($segs, fn ($s) => $s['direction'] === 'return')) {
            return array_map(fn ($s) => ['direction' => $s['direction'] ?? 'out'] + $s, $segs);
        }
        $turn = null;
        $first = $segs[0]['from'] ?? null;
        $last = end($segs)['to'] ?? null;
        if (count($segs) > 1 && $first && $first === $last) {
            for ($k = 1, $n = count($segs); $k < $n; $k++) {
                $a = strtotime((string) $segs[$k - 1]['arr']);
                $d = strtotime((string) $segs[$k]['dep']);
                if ($a && $d && $d - $a > 86400) {
                    $turn = $k;
                    break;
                }
            }
            $turn ??= intdiv(count($segs), 2);
        }
        foreach ($segs as $k => $s) {
            $segs[$k]['direction'] = ($turn !== null && $k >= $turn) ? 'return' : 'out';
        }

        return $segs;
    }
}
