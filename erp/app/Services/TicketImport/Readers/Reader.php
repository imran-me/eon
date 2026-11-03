<?php

namespace App\Services\TicketImport\Readers;

/**
 * One airline / GDS / portal ticket layout.
 *
 * A reader gets the PDF's text as visual lines — rebuilt in the browser by
 * pdf.js exactly as the bank statement compare does it, with " | " wherever
 * two pieces of text on one line sit in different columns, and "<<PAGE>>"
 * between pages — and returns what it could read in ONE shape (see
 * TicketPdfImporter::blank()). It never guesses: a value it cannot find is
 * left null and the entry card shows it empty.
 *
 * Adding a layout = one class here + one line in TicketPdfImporter::readers().
 */
abstract class Reader
{
    /** Stable key, e.g. "galileo". */
    abstract public function key(): string;

    /** What the owner calls it, e.g. "Galileo e-ticket". */
    abstract public function label(): string;

    /** Does this text look like this layout? Must be cheap and specific. */
    abstract public function matches(string $text): bool;

    /** @param string[] $lines */
    abstract public function read(array $lines): array;

    // ── shared helpers ───────────────────────────────────────────────────

    protected const MONTHS = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
    ];

    /**
     * Any date the layouts print, to Y-m-d: "06 Oct 26", "04Oct26",
     * "25 Sep 2026", "12 September 2026", "05 Dec" / "05 OCT" (no year —
     * $yearHint, rolled forward when the month is earlier than $afterMonth).
     */
    protected function date(?string $s, ?int $yearHint = null, ?string $notBefore = null): ?string
    {
        if ($s === null) {
            return null;
        }
        // also "28/May/2025" and "28-May-25"
        if (! preg_match('/(\d{1,2})\s*[,\/\-]?\s*([A-Za-z]{3})[A-Za-z]*\.?\s*[,\/\-]?\s*(\d{4}|\d{2}(?!\d|:))?/', $s, $m)) {
            return null;
        }
        $mon = self::MONTHS[strtoupper($m[2])] ?? null;
        if (! $mon) {
            return null;
        }
        $day = (int) $m[1];
        $year = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;
        if ($year !== null && $year < 100) {
            $year += 2000;
        }
        if ($year === null) {
            $year = $yearHint ?? (int) date('Y');
            // "05 Dec" printed on a trip that started in October is this
            // year; "05 Jan" printed after a December leg is next year.
            if ($notBefore) {
                $candidate = sprintf('%04d-%02d-%02d', $year, $mon, $day);
                if ($candidate < substr($notBefore, 0, 10)) {
                    $year++;
                }
            }
        }
        if (! checkdate($mon, $day, $year)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $year, $mon, $day);
    }

    /** "Y-m-d" + "HH:MM" → "Y-m-d H:i", null when either half is missing. */
    protected function at(?string $date, ?string $time): ?string
    {
        if (! $date) {
            return null;
        }
        if (! $time || ! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return $date . ' 00:00';
        }
        return sprintf('%s %02d:%02d', $date, (int) $m[1], (int) $m[2]);
    }

    /** "Y-m-d" + n days. */
    protected function plusDays(string $date, int $days): string
    {
        return date('Y-m-d', strtotime($date . ' +' . $days . ' day'));
    }

    /** "31,191" / "BDT 25,478" → 31191.0 */
    protected function money(?string $s): ?float
    {
        if ($s === null || ! preg_match('/(\d[\d,]*(?:\.\d+)?)/', $s, $m)) {
            return null;
        }
        return (float) str_replace(',', '', $m[1]);
    }

    /** Economy / Business / First from any cabin wording. */
    protected function cabin(?string $s): ?string
    {
        $s = strtoupper((string) $s);
        if (str_contains($s, 'BUSINESS') || str_contains($s, 'CLUB')) return 'business';
        if (str_contains($s, 'FIRST')) return 'first';
        if (str_contains($s, 'ECONOMY') || str_contains($s, 'ECO')) return 'economy';
        return null;
    }

    /** "GF 249" / "GF0249" / "G9511" → ['GF', 'GF 249']. */
    protected function flight(string $carrier, string $number): array
    {
        $carrier = strtoupper($carrier);
        return [$carrier, $carrier . ' ' . ltrim($number, '0')];
    }

    /** Split a " | "-joined line into its cells. */
    protected function cells(string $line): array
    {
        return array_map('trim', explode(' | ', $line));
    }

    /** First line index at/after $from whose text matches $re, or null. */
    protected function find(array $lines, string $re, int $from = 0): ?int
    {
        for ($i = $from, $n = count($lines); $i < $n; $i++) {
            if (preg_match($re, $lines[$i])) {
                return $i;
            }
        }
        return null;
    }

    /**
     * A GDS name "TALUKDER/MD AL AMIN MR" → ['MD AL AMIN TALUKDER', 'MR'].
     * A plain "Mr. Md Jone" → ['MD JONE', 'MR'].
     */
    protected function person(string $raw): array
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw));
        $title = null;
        $titles = 'MR|MRS|MS|MISS|MSTR|MASTER|DR|CHD|INF';
        if (preg_match('/^(' . $titles . ')\.?\s+/i', $raw, $m)) {
            $title = strtoupper($m[1]);
            $raw = substr($raw, strlen($m[0]));
        }
        if (preg_match('/\s+(' . $titles . ')\.?$/i', $raw, $m)) {
            $title = strtoupper($m[1]);
            $raw = substr($raw, 0, -strlen($m[0]));
        }
        if (str_contains($raw, '/')) {
            [$surname, $given] = array_map('trim', explode('/', $raw, 2));
            $raw = trim($given . ' ' . $surname);
        }
        return [strtoupper(trim($raw)), $title];
    }

    /** A passenger in the shape every reader returns. */
    protected function passenger(string $rawName, array $extra = []): array
    {
        [$name, $title] = $this->person($rawName);
        return $extra + [
            'name'        => $name,
            'raw_name'    => trim($rawName),
            'title'       => $title,
            'passport_no' => null,
            'dob'         => null,
            'eticket'     => null,
            'pax_type'    => 'adult',
            'fare'        => null,
        ];
    }

    /** A flight segment in the shape every reader returns. */
    protected function segment(array $s): array
    {
        return $s + [
            'carrier' => null, 'flight_no' => null,
            'from' => null, 'to' => null, 'from_name' => null, 'to_name' => null,
            'dep' => null, 'arr' => null,
            'cabin' => null, 'rbd' => null, 'baggage' => null, 'status' => null,
            'direction' => null,
        ];
    }
}
