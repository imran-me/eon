<?php

namespace App\Services;

/**
 * Turns the text of a bank's own account statement into transaction rows.
 *
 * The text arrives as lines already extracted from the PDF (the browser does
 * that with pdf.js — see banks/reconciliation.blade.php), one visual line per
 * element, top to bottom, optionally with each line's page and vertical
 * position. Nothing here knows which bank printed it: a row is simply "a line
 * that carries a date and ends in a balance", and the money columns are read
 * from the balance movement, not from where the numbers sit. That is
 * deliberate — Islami Bank, BRAC, City and DBBL all order the Withdrawal /
 * Deposit columns differently, and a parser keyed on column position would
 * silently swap money in and out the first time the layout changed.
 *
 * Two real layouts shaped the stitching of wrapped descriptions:
 *  - BRAC prints the date and the money on the first line of a row and the
 *    rest of the description underneath.
 *  - Islami Bank centres the date and the money vertically in the row, so a
 *    two-line description has one line ABOVE the dated line and one below.
 * With positions known, every undated line is attached to the nearest dated
 * line above or below it; without positions it attaches to the row before.
 *
 * What comes out is chronological (the statement is flipped if it was printed
 * newest-first), and every row says how confident it is: `flags` is empty on
 * a clean row and names the doubt otherwise ('balance_break' when the running
 * balance does not follow from the previous row — the mark of a line the
 * extractor mangled or a row this could not stitch back together).
 */
class BankStatementParser
{
    // dd/mm/yyyy · dd-mm-yyyy · dd.mm.yy — the forms Bangladeshi banks print.
    private const DATE_NUMERIC = '/(?<!\d)(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4}|\d{2})(?!\d)/';
    // 01-Aug-2026 · 1 Aug 2026 · 01-AUG-26 · 01 August, 2026
    private const DATE_MONTH   = '/(?<!\d)(\d{1,2})[\-\s\/]?([A-Za-z]{3})[A-Za-z]*[\-\s\/,]+(\d{4}|\d{2})(?!\d)/';
    // 2026-08-01
    private const DATE_ISO     = '/(?<!\d)(\d{4})-(\d{2})-(\d{2})(?!\d)/';

    private const TIME = '/(?<!\d)(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AaPp]\.?[Mm]\.?)?(?![\d:])/';

    // A money figure: optional sign or parentheses, any comma grouping
    // (1,234.00 and the lakh style 12,34,567.00), always two decimals — the
    // decimals are what keep cheque and reference numbers out. An optional
    // Cr/Dr after it is how some statements sign the balance.
    private const AMOUNT = '/(?<![\w.,])(\(?-?\d{1,3}(?:,\d{2,3})*\.\d{2}\)?|\(?-?\d+\.\d{2}\)?)\s*(Cr|Dr|CR|DR|cr|dr)?(?![\w.,])/';

    private const OPENING_WORDS = '/\b(opening\s+balance|balance\s+(?:b\/?f|forward|brought)|brought\s+forward|b\/f|previous\s+balance|beginning\s+balance)\b/i';
    private const CLOSING_WORDS = '/\b(closing\s+balance|balance\s+c\/?f|carried\s+forward|c\/f|ending\s+balance)\b/i';
    // "Balance as on 01/08/2026" is the opening figure when it comes before
    // the rows and the closing one when it comes after them.
    private const BALANCE_AS_ON = '/\bbalance\s+as\s+(on|at|of)\b/i';
    // Lines that carry a date or a figure but are not transactions: the
    // period line, print stamps, page numbers, totals.
    private const NOISE_WORDS   = '/\b(total|sub\s*total|grand\s+total|page\s+\d+|printed\s+on|generated\s+on|report\s+taken|taken\s+on|issue\s+date|opening\s+date|statement\s+(period|date|of\s+account)|for\s+the\s+period|account\s+(no|number|title|type)|as\s+on)\b/i';

    private const ACCOUNT_NO = '/\baccount\s*(?:no|number|num)\.?\s*:?\s*([0-9][0-9 \-]{7,})/i';

    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    /** @var bool  Numeric dates read as day/month (true) or month/day. */
    private bool $dayFirst = true;

    /**
     * @param  string     $text    The extracted statement text, one line per "\n".
     * @param  array|null $layout  Optional, one entry per line of $text (same
     *                             order, empty lines included): [page, y] with
     *                             y in PDF points, larger = higher on the page.
     * @return array{
     *   rows: array<int, array>,
     *   opening_balance: ?float, closing_balance: ?float, stated_closing: ?float,
     *   account_no: ?string,
     *   date_from: ?string, date_to: ?string,
     *   order: string, line_count: int, skipped: array<int, array>, warnings: string[]
     * }
     */
    public function parse(string $text, ?array $layout = null): array
    {
        $lines = $this->splitLines($text, $layout);
        $this->dayFirst = $this->detectDayFirst(array_column($lines, 'text'));

        $opening     = null;
        $stated      = null;
        $accountNo   = null;
        $columnOrder = null; // 'out_in' | 'in_out' from the table header
        $skipped     = [];
        $rowsSeen    = false;

        // ── Pass A: what is each line? ─────────────────────────────────────
        foreach ($lines as &$l) {
            $line    = $l['text'];
            $amounts = $this->extractAmounts($line);
            $date    = $this->extractDates($line);
            $l['amounts'] = $amounts;
            $l['dates']   = $date;
            $l['kind']    = 'text';

            if ($accountNo === null && preg_match(self::ACCOUNT_NO, $line, $am)) {
                $digits = preg_replace('/\D/', '', $am[1]);
                if (strlen($digits) >= 8) {
                    $accountNo = $digits;
                }
            }

            // Header row of the table — repeated on every page, so always
            // dropped; the first one also says which money column comes first.
            if (! $amounts
                && preg_match('/\b(withdraw\w*|debit|dr)\b/i', $line, $wm, PREG_OFFSET_CAPTURE)
                && preg_match('/\b(deposit\w*|credit|cr)\b/i', $line, $dm, PREG_OFFSET_CAPTURE)) {
                $columnOrder ??= $wm[0][1] < $dm[0][1] ? 'out_in' : 'in_out';
                $l['kind'] = 'header';
                continue;
            }
            if ($amounts && preg_match(self::OPENING_WORDS, $line)) {
                $opening ??= end($amounts)['value'];
                $l['kind'] = 'opening';
                continue;
            }
            if ($amounts && ! $date && preg_match(self::CLOSING_WORDS, $line)) {
                $stated = end($amounts)['value'];
                $l['kind'] = 'closing';
                continue;
            }
            if ($amounts && preg_match(self::BALANCE_AS_ON, $line)) {
                if (! $rowsSeen && $opening === null) {
                    $opening = end($amounts)['value'];
                } else {
                    $stated = end($amounts)['value'];
                }
                $l['kind'] = $rowsSeen ? 'closing' : 'opening';
                continue;
            }
            if ($date) {
                // "Report taken on: 14/09/2026 7.52", "Statement for the
                // period 01/09/2026 to 22/09/2026" — dated, but a print stamp
                // or a period line, not money moving.
                if (count($amounts) < 2 && preg_match(self::NOISE_WORDS, $line)) {
                    $l['kind'] = 'noise';
                    continue;
                }
                $l['kind'] = 'row';
                $rowsSeen  = true;
                continue;
            }
            if (preg_match(self::NOISE_WORDS, $line)) {
                $l['kind'] = 'noise';
                // "TOTAL  5,089,735.01  5,730,757.76  641,022.75" — the last
                // figure is the statement's own closing balance, which the
                // parsed rows must agree with.
                if ($rowsSeen && count($amounts) >= 3 && preg_match('/\btotal\b/i', $line)) {
                    $stated ??= end($amounts)['value'];
                }
                continue;
            }
            // BRAC's totals line has no label at all: three figures, nothing
            // else. One lone figure ("1.87" — the tail of a wrapped "Vat:
            // 1.87") is still description text.
            if (count($amounts) >= 2 && ! preg_match('/[A-Za-z]/', $this->stripAmounts($line))) {
                $l['kind'] = 'noise';
                if ($rowsSeen && count($amounts) >= 3) {
                    $stated ??= end($amounts)['value'];
                }
                continue;
            }
        }
        unset($l);

        // ── Pass B: which row does each stray line belong to? ─────────────
        $this->attachTextLines($lines);

        // ── Pass C: assemble the rows ─────────────────────────────────────
        $rows = [];
        foreach ($lines as $i => $l) {
            if ($l['kind'] !== 'row') {
                if ($l['kind'] === 'text' && $l['amounts'] && $l['anchor'] === null) {
                    $skipped[] = ['line' => $l['no'], 'text' => $l['text'], 'why' => 'amounts without a date'];
                }
                continue;
            }

            $rest = $this->stripDates($l['text']);
            $time = $this->extractTime($rest, $timeSpan);
            if ($time !== null) {
                $rest = substr_replace($rest, ' ', $timeSpan[0], $timeSpan[1]);
            }
            $row = [
                'line'        => $l['no'],
                'raw'         => $l['text'],
                'date'        => $l['dates'][0],
                // A second date on the row (Islami Bank's Post Date next to
                // its Trans Date) — the day the customer moved the money,
                // which is usually what the ERP recorded.
                'value_date'  => $l['dates'][1] ?? null,
                'time'        => $time,
                'particulars' => '',
                'amounts'     => [],
                'balance'     => null,
                'flags'       => [],
            ];

            $before = [];
            $after  = [];
            foreach ($lines as $t) {
                if ($t['kind'] !== 'text' || $t['anchor'] !== $i) {
                    continue;
                }
                if ($t['side'] === 'before') {
                    $before[] = $t;
                } else {
                    $after[] = $t;
                }
            }
            // Money printed above the date (some layouts top-align the
            // figures) belongs to this row too.
            foreach ($before as $t) {
                $this->absorbAmounts($row, $t['amounts']);
            }
            $this->absorbAmounts($row, $l['amounts']);
            $texts = [];
            foreach ($before as $t) {
                $texts[] = $this->stripAmounts($t['text']);
            }
            $texts[] = $this->stripAmounts($rest);
            foreach ($after as $t) {
                if ($row['balance'] === null || ! $row['amounts']) {
                    $this->absorbAmounts($row, $t['amounts']);
                    $texts[] = $this->stripAmounts($t['text']);
                } else {
                    $text = $t['text'];
                    // A wrapped date cell puts the time on the line below.
                    $span = null;
                    if ($row['time'] === null && ($tt = $this->extractTime($text, $span)) !== null && $span) {
                        $row['time'] = $tt;
                        $text = substr_replace($text, ' ', $span[0], $span[1]);
                    }
                    $texts[] = $text;
                }
            }
            $row['particulars'] = trim(preg_replace('/\s+/', ' ', implode(' ', $texts)));

            if ($row['amounts'] && $row['balance'] !== null) {
                $rows[] = $row;
            } else {
                $skipped[] = ['line' => $l['no'], 'text' => $l['text'], 'why' => 'dated line without a readable amount and balance'];
            }
        }

        if (! $rows) {
            return $this->result([], $opening, $stated, $accountNo, 'asc', count($lines), $skipped, [
                'No transaction rows were found. The statement may be a scanned image, or its lines are laid out in a way this reader does not recognise yet — open "Extracted text" below to see what the PDF gave us.',
            ]);
        }

        // Chronological. Statements printed newest-first are flipped; within a
        // day the printed order is kept because that is the order the bank
        // applied them.
        $order = 'asc';
        $first = $rows[0]['date'];
        $last  = $rows[count($rows) - 1]['date'];
        if ($first > $last) {
            $order = 'desc';
            $rows  = $this->reverseKeepingDays($rows);
        }

        $rows = $this->resolveDirections($rows, $opening, $columnOrder);

        $warnings = [];
        $closing  = $rows[count($rows) - 1]['balance'];
        if ($stated !== null && abs($stated - $closing) > 0.004) {
            $warnings[] = sprintf(
                'The statement prints a closing balance of %s but its last row reads %s — some rows may have been misread. Check the flagged rows and the extracted text.',
                number_format($stated, 2),
                number_format($closing, 2)
            );
        }
        $broken = count(array_filter($rows, fn ($r) => in_array('balance_break', $r['flags'], true)));
        if ($broken) {
            $warnings[] = sprintf('%d row%s could not be read cleanly (the running balance does not follow from the row before).', $broken, $broken === 1 ? '' : 's');
        }

        return $this->result($rows, $opening, $stated, $accountNo, $order, count($lines), $skipped, $warnings);
    }

    // ── Pieces ─────────────────────────────────────────────────────────────

    /**
     * Lines with their number, and page/position when the browser sent them.
     * Empty lines are dropped, but the layout array is indexed like the raw
     * split, so it is consumed in step with the raw lines.
     */
    private function splitLines(string $text, ?array $layout): array
    {
        $raw = preg_split('/\r\n|\r|\n/', $text);
        $out = [];
        foreach ($raw as $i => $line) {
            $line = trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line));
            if ($line === '') {
                continue;
            }
            $meta = $layout[$i] ?? null;
            $out[] = [
                'no'     => $i + 1,
                'text'   => $line,
                'page'   => is_array($meta) && isset($meta[0]) ? (int) $meta[0] : null,
                'y'      => is_array($meta) && isset($meta[1]) ? (float) $meta[1] : null,
                'anchor' => null,
                'side'   => null,
            ];
        }
        return $out;
    }

    /**
     * Give every stray text line a row to belong to.
     *
     * With positions: the nearest dated row on the same page, above or
     * below, as long as it is within two typical line pitches — a page
     * footer forty points below the last row stays an orphan. Without
     * positions: the dated row before it, the way a plain text dump reads.
     */
    private function attachTextLines(array &$lines): void
    {
        $positioned = ! array_filter($lines, fn ($l) => $l['kind'] === 'text' && $l['y'] === null);

        if (! $positioned) {
            $last = null;
            foreach ($lines as $i => &$l) {
                if ($l['kind'] === 'row') {
                    $last = $i;
                } elseif ($l['kind'] === 'text' && $last !== null) {
                    $l['anchor'] = $last;
                    $l['side']   = 'after';
                }
            }
            unset($l);
            return;
        }

        // Typical distance between consecutive lines, per page.
        $pitch = [];
        $byPage = [];
        foreach ($lines as $i => $l) {
            $byPage[$l['page']][] = $i;
        }
        foreach ($byPage as $page => $idx) {
            $gaps = [];
            for ($k = 1, $n = count($idx); $k < $n; $k++) {
                $g = abs($lines[$idx[$k - 1]]['y'] - $lines[$idx[$k]]['y']);
                if ($g > 0.5 && $g <= 40) {
                    $gaps[] = $g;
                }
            }
            sort($gaps);
            $pitch[$page] = $gaps ? $gaps[intdiv(count($gaps), 2)] : 12.0;
        }

        foreach ($lines as $i => &$l) {
            if ($l['kind'] !== 'text') {
                continue;
            }
            $best = null;
            foreach ($byPage[$l['page']] as $j) {
                if ($lines[$j]['kind'] !== 'row') {
                    continue;
                }
                $d = abs($lines[$j]['y'] - $l['y']);
                if ($best === null || $d < $best[1]) {
                    $best = [$j, $d];
                }
            }
            if ($best !== null && $best[1] <= 2.0 * $pitch[$l['page']]) {
                $l['anchor'] = $best[0];
                $l['side']   = $l['y'] > $lines[$best[0]]['y'] ? 'before' : 'after';
            }
        }
        unset($l);
    }

    private function result(array $rows, ?float $opening, ?float $stated, ?string $accountNo, string $order, int $lineCount, array $skipped, array $warnings): array
    {
        $clean = array_map(function ($r) {
            unset($r['amounts']);
            return $r;
        }, $rows);

        return [
            'rows'            => $clean,
            'opening_balance' => $opening,
            'closing_balance' => $rows ? $rows[count($rows) - 1]['balance'] : null,
            'stated_closing'  => $stated,
            'account_no'      => $accountNo,
            'date_from'       => $rows ? $rows[0]['date'] : null,
            'date_to'         => $rows ? $rows[count($rows) - 1]['date'] : null,
            'order'           => $order,
            'line_count'      => $lineCount,
            'skipped'         => $skipped,
            'warnings'        => $warnings,
        ];
    }

    /**
     * dd/mm or mm/dd? Decided once over the whole document: if any numeric
     * date has a first part above 12 it is day-first; if any has a second
     * part above 12 it is month-first; with neither, Bangladeshi convention.
     */
    private function detectDayFirst(array $lines): bool
    {
        foreach ($lines as $line) {
            if (preg_match_all(self::DATE_NUMERIC, $line, $ms, PREG_SET_ORDER)) {
                foreach ($ms as $m) {
                    if ((int) $m[1] > 12) {
                        return true;
                    }
                    if ((int) $m[2] > 12) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /** Every date on the line, left to right, as Y-m-d. */
    private function extractDates(string $line): array
    {
        $found = [];
        foreach ([self::DATE_ISO, self::DATE_NUMERIC, self::DATE_MONTH] as $pattern) {
            if (! preg_match_all($pattern, $line, $ms, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($ms as $m) {
                $ymd = match ($pattern) {
                    self::DATE_ISO     => $this->ymd((int) $m[1][0], (int) $m[2][0], (int) $m[3][0]),
                    self::DATE_NUMERIC => $this->dayFirst
                        ? $this->ymd($this->year($m[3][0]), (int) $m[2][0], (int) $m[1][0])
                        : $this->ymd($this->year($m[3][0]), (int) $m[1][0], (int) $m[2][0]),
                    default            => isset(self::MONTHS[strtolower($m[2][0])])
                        ? $this->ymd($this->year($m[3][0]), self::MONTHS[strtolower($m[2][0])], (int) $m[1][0])
                        : null,
                };
                if ($ymd !== null) {
                    $found[$m[0][1]] = $ymd;
                }
            }
        }
        ksort($found);
        return array_values($found);
    }

    private function year(string $y): int
    {
        return strlen($y) === 2 ? 2000 + (int) $y : (int) $y;
    }

    private function ymd(int $y, int $m, int $d): ?string
    {
        return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
    }

    private function stripDates(string $line): string
    {
        return preg_replace([self::DATE_ISO, self::DATE_NUMERIC, self::DATE_MONTH], ' ', $line);
    }

    private function extractTime(string $text, ?array &$span = null): ?string
    {
        $span = null;
        if (! preg_match(self::TIME, $text, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $h = (int) $m[1][0];
        $i = (int) $m[2][0];
        if ($h > 23 || $i > 59) {
            return null;
        }
        $ampm = isset($m[4]) && $m[4][1] >= 0 ? strtolower(str_replace('.', '', $m[4][0])) : '';
        if ($ampm === 'pm' && $h < 12) {
            $h += 12;
        } elseif ($ampm === 'am' && $h === 12) {
            $h = 0;
        }
        $span = [$m[0][1], strlen($m[0][0])];
        return sprintf('%02d:%02d', $h, $i);
    }

    /** @return array<int, array{value: float, raw: string}> */
    private function extractAmounts(string $line): array
    {
        if (! preg_match_all(self::AMOUNT, $line, $ms, PREG_SET_ORDER)) {
            return [];
        }
        $out = [];
        foreach ($ms as $m) {
            $raw = $m[1];
            $neg = str_starts_with($raw, '(') || str_starts_with($raw, '-');
            $val = (float) str_replace(['(', ')', ',', '-'], '', $raw);
            if (isset($m[2]) && strtolower($m[2]) === 'dr') {
                $neg = true;
            }
            $out[] = ['value' => $neg ? -$val : $val, 'raw' => $m[0]];
        }
        return $out;
    }

    private function stripAmounts(string $text): string
    {
        return preg_replace(self::AMOUNT, ' ', $text);
    }

    /**
     * Feed the amounts found on a line into the row being built. The last
     * figure on a row is its balance; everything before it is money moved.
     */
    private function absorbAmounts(array &$row, array $amounts): void
    {
        if (! $amounts) {
            return;
        }
        $all = $row['amounts'];
        if ($row['balance'] !== null) {
            $all[] = $row['balance'];
        }
        foreach ($amounts as $a) {
            $all[] = $a['value'];
        }
        $row['balance'] = array_pop($all);
        $row['amounts'] = $all;
    }

    /**
     * Newest-first statements are flipped whole, but the rows of one day keep
     * the order they were printed in, so the balances still chain.
     */
    private function reverseKeepingDays(array $rows): array
    {
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[$r['date']][] = $r;
        }
        $byDay = array_reverse($byDay, true);
        $out   = [];
        foreach ($byDay as $day) {
            // Within a day the bank printed newest-first too, so reverse.
            foreach (array_reverse($day) as $r) {
                $out[] = $r;
            }
        }
        return $out;
    }

    /**
     * Decide, for every row, whether money came in or went out — from how the
     * balance moved, never from which column the figure was printed in.
     * Rows whose balance does not follow from the previous one are flagged
     * rather than guessed silently.
     */
    private function resolveDirections(array $rows, ?float $opening, ?string $columnOrder): array
    {
        $prev = $opening;

        foreach ($rows as &$row) {
            // `printed` keeps the zeros: a row that shows "0.00  44,000.00"
            // under a "Debit  Credit" header has told us the column even
            // when the balance chain cannot.
            $printed = $row['amounts'];
            $amounts = array_values(array_filter($printed, fn ($a) => abs($a) > 0.004));
            $balance = $row['balance'];

            $row['deposit']    = null;
            $row['withdrawal'] = null;
            $row['direction']  = null;
            $row['amount']     = null;

            if (! $amounts) {
                // Only a balance on the row — a memo line the bank printed in
                // the table. Kept so the balance chain stays visible; flagged
                // so nobody thinks money moved.
                $row['flags'][] = 'no_amount';
                if ($prev !== null && abs($balance - $prev) > 0.004) {
                    $row['flags'][] = 'balance_break';
                }
                $prev = $balance;
                continue;
            }

            $delta  = $prev === null ? null : round($balance - $prev, 2);
            $chosen = null;

            if ($delta !== null) {
                // Try the readings the figures allow and keep the one that
                // reproduces the balance movement exactly.
                $candidates = [];
                foreach ($amounts as $a) {
                    $candidates[] = ['in' => abs($a), 'out' => 0.0];
                    $candidates[] = ['in' => 0.0, 'out' => abs($a)];
                }
                if (count($amounts) === 2) {
                    $candidates[] = ['in' => abs($amounts[0]), 'out' => abs($amounts[1])];
                    $candidates[] = ['in' => abs($amounts[1]), 'out' => abs($amounts[0])];
                }
                foreach ($candidates as $c) {
                    if (abs(($c['in'] - $c['out']) - $delta) < 0.005) {
                        $chosen = $c;
                        break;
                    }
                }
            }

            if ($chosen === null) {
                // No previous balance to lean on (first row without an opening
                // line), or the chain is broken. Fall back to the header's
                // column order when the row printed both columns, else the
                // balance movement's sign, else leave it for the comparer to
                // settle against the ledger.
                if ($delta !== null) {
                    $row['flags'][] = 'balance_break';
                }
                $a = abs($amounts[0]);
                if (count($printed) === 2 && $columnOrder) {
                    $chosen = $columnOrder === 'out_in'
                        ? ['out' => abs($printed[0]), 'in' => abs($printed[1])]
                        : ['in' => abs($printed[0]), 'out' => abs($printed[1])];
                } elseif ($delta !== null && abs($delta) > 0.004) {
                    $chosen = $delta > 0 ? ['in' => $a, 'out' => 0.0] : ['in' => 0.0, 'out' => $a];
                } elseif (count($amounts) === 1 && $amounts[0] < 0) {
                    $chosen = ['in' => 0.0, 'out' => $a];
                } else {
                    $row['flags'][] = 'direction_unknown';
                    $row['amount']  = $a;
                    $prev = $balance;
                    continue;
                }
            }

            $row['deposit']    = $chosen['in'] > 0 ? $chosen['in'] : null;
            $row['withdrawal'] = $chosen['out'] > 0 ? $chosen['out'] : null;
            if ($chosen['in'] > 0 && $chosen['out'] > 0) {
                $row['direction'] = $chosen['in'] >= $chosen['out'] ? 'in' : 'out';
                $row['amount']    = round(abs($chosen['in'] - $chosen['out']), 2);
                $row['flags'][]   = 'both_columns';
            } else {
                $row['direction'] = $chosen['in'] > 0 ? 'in' : 'out';
                $row['amount']    = $chosen['in'] > 0 ? $chosen['in'] : $chosen['out'];
            }
            $prev = $balance;
        }
        unset($row);

        return $rows;
    }
}
