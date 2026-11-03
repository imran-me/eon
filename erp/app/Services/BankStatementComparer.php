<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Lines a bank's statement up against the ERP ledger for the same account,
 * day by day, and names every point where they disagree.
 *
 * Money in / money out is the language of both sides here. On the ledger a
 * bank account is an asset, so a journal debit is money IN and a credit is
 * money OUT — the opposite of the Dr/Cr words a bank prints, which is why the
 * ledger rows are translated to in/out once, on the way in, and the labels
 * never appear again.
 *
 * Matching is by amount and direction, first on the same day, then within a
 * week (the ERP dated it differently from when the bank cleared it), then a
 * lone leftover on each side of one day is paired as "amount differs". What
 * is still unpaired is either missing from the ERP or missing from the bank.
 * Nothing is written anywhere: this is a report, recomputed on every upload.
 */
class BankStatementComparer
{
    private const TOLERANCE  = 0.005;
    private const DATE_REACH = 7;     // days either side for a "date differs" pairing
    private const MAX_CHARGE = 250.0; // the most a bank charge folded into a figure can be

    /**
     * @param array $statement  BankStatementParser::parse() output
     * @param iterable $ledgerRows  rows from BankControllerV2::buildBankStatementRows()
     *                              (entry_id, txn_no, date, entry_time, description,
     *                              debit, credit, reference, beneficiary…)
     * @param float $ledgerOpening  ledger balance the day before the statement starts
     */
    public function compare(array $statement, iterable $ledgerRows, float $ledgerOpening): array
    {
        // ── Ledger side ──────────────────────────────────────────────────
        $ledger  = [];
        $opening = $ledgerOpening;
        foreach ($ledgerRows as $i => $r) {
            $in  = (float) ($r['debit'] ?? 0);
            $out = (float) ($r['credit'] ?? 0);
            // The account's opening-balance entry is not a bank transaction;
            // the bank shows the same money as its opening figure.
            if (str_starts_with((string) ($r['reference'] ?? ''), 'OPENING-')) {
                $opening += $in - $out;
                continue;
            }
            if (abs($in) < self::TOLERANCE && abs($out) < self::TOLERANCE) {
                continue;
            }
            $ledger[] = [
                'key'         => 'L' . $i,
                'entry_id'    => $r['entry_id'] ?? null,
                'txn_no'      => $r['txn_no'] ?? null,
                'date'        => $r['date'],
                'time'        => $r['entry_time'] ?? null,
                'recorded_at' => $r['recorded_at'] ?? null,
                'day'         => self::dayNumber($r['date']),
                'description' => (string) ($r['description'] ?? ''),
                'beneficiary' => $r['beneficiary'] ?? null,
                'in'          => $in > 0 ? $in : null,
                'out'         => $out > 0 ? $out : null,
                'direction'   => $in > 0 ? 'in' : 'out',
                'amount'      => $in > 0 ? round($in, 2) : round($out, 2),
                'balance'     => null,
                'match'       => null,
                'reverses'    => $r['reversed_entry_id'] ?? null,
                'reversed'    => (bool) ($r['is_reversed'] ?? false),
            ];
        }
        $running = $opening;
        foreach ($ledger as &$l) {
            $running += ($l['in'] ?? 0) - ($l['out'] ?? 0);
            $l['balance'] = round($running, 2);
        }
        unset($l);
        $ledgerOpening = round($opening, 2);

        // ── Statement side ───────────────────────────────────────────────
        $stmt = [];
        foreach ($statement['rows'] as $i => $r) {
            $r['key']   = 'S' . $i;
            $r['match'] = null;
            $r['day']   = self::dayNumber($r['date']);
            $r['in']    = $r['deposit'];
            $r['out']   = $r['withdrawal'];
            $stmt[]     = $r;
        }

        // A first row the parser could not orient (no opening line printed)
        // is settled here: whichever direction lands on the ledger's opening.
        foreach ($stmt as &$s) {
            if ($s['direction'] !== null || $s['amount'] === null) {
                continue;
            }
            $a = $s['amount'];
            if (abs(($s['balance'] - $a) - $ledgerOpening) < self::TOLERANCE) {
                $s['direction'] = 'in';
                $s['in'] = $a;
            } elseif (abs(($s['balance'] + $a) - $ledgerOpening) < self::TOLERANCE) {
                $s['direction'] = 'out';
                $s['out'] = $a;
            } else {
                // Still unknown — read it as the movement's sign against the
                // ledger opening and say so.
                $s['direction'] = $s['balance'] >= $ledgerOpening ? 'in' : 'out';
                $s[$s['direction']] = $a;
                $s['flags'][] = 'direction_guessed';
            }
            break; // only the first row can be unoriented
        }
        unset($s);

        $stmtOpening = $statement['opening_balance'];
        if ($stmtOpening === null && $stmt) {
            $first = $stmt[0];
            if ($first['amount'] !== null && $first['direction'] !== null) {
                $stmtOpening = round($first['balance'] - ($first['direction'] === 'in' ? $first['amount'] : -$first['amount']), 2);
            }
        }

        // ── Matching ─────────────────────────────────────────────────────

        // Pass 1 — same day, same direction, same amount. "Same day" is the
        // bank's booking date or, when the statement prints one, the value /
        // post date: Islami Bank books a Friday iTransfer on Sunday, and the
        // ERP will have recorded it on the Friday.
        foreach ($stmt as $si => $s) {
            if ($s['amount'] === null) {
                continue;
            }
            $days = array_unique(array_filter([$s['date'], $s['value_date'] ?? null]));
            foreach ($days as $day) {
                foreach ($ledger as $li => $l) {
                    if ($l['match'] === null && $l['date'] === $day && $l['direction'] === $s['direction']
                        && abs($l['amount'] - $s['amount']) < self::TOLERANCE) {
                        $this->pair($stmt, $ledger, $si, $li, 'matched');
                        continue 3;
                    }
                }
            }
        }

        // Pass 2 — same day, same direction, and the bank's figure is the
        // ERP's plus a small charge: NPSB adds 10.00 incl. VAT, RTGS 100.00,
        // an iTransfer 12.50 + VAT, a remote deposit loses 50.00 on the way
        // in. The ERP recorded the round amount and never the charge — by
        // far the commonest difference on these statements, so it is named
        // as such rather than left as an unpaired row on each side. Same-day
        // evidence is taken before the cross-day exact match below: a
        // transfer of the same round figure a few days later is common, the
        // charge on the same day is the likelier story.
        foreach ($stmt as $si => $s) {
            if ($s['match'] !== null || $s['amount'] === null) {
                continue;
            }
            $days = array_unique(array_filter([$s['date'], $s['value_date'] ?? null]));
            $best = null;
            foreach ($ledger as $li => $l) {
                if ($l['match'] !== null || $l['direction'] !== $s['direction'] || ! in_array($l['date'], $days, true)) {
                    continue;
                }
                // Money out: the bank took more. Money in: the bank credited less.
                $fee = $s['direction'] === 'out' ? $s['amount'] - $l['amount'] : $l['amount'] - $s['amount'];
                if ($fee > self::TOLERANCE && $fee <= self::MAX_CHARGE && ($best === null || $fee < $best[1])) {
                    $best = [$li, round($fee, 2)];
                }
            }
            if ($best !== null) {
                $this->pair($stmt, $ledger, $si, $best[0], 'charge_missing', ['fee' => $best[1]]);
            }
        }

        // Pass 3 — same money within a week: the ERP's date is wrong.
        foreach ($stmt as $si => $s) {
            if ($s['match'] !== null || $s['amount'] === null) {
                continue;
            }
            $best = null;
            foreach ($ledger as $li => $l) {
                if ($l['match'] !== null || $l['direction'] !== $s['direction']
                    || abs($l['amount'] - $s['amount']) >= self::TOLERANCE) {
                    continue;
                }
                $gap = abs($l['day'] - $s['day']);
                if ($gap <= self::DATE_REACH && ($best === null || $gap < $best[1])) {
                    $best = [$li, $gap];
                }
            }
            if ($best !== null) {
                $this->pair($stmt, $ledger, $si, $best[0], 'date_differs');
            }
        }

        // Pass 4 — one leftover on each side of the same day, same direction:
        // almost certainly the same transaction typed with the wrong figure.
        $days = array_unique(array_merge(array_column($stmt, 'date'), array_column($ledger, 'date')));
        foreach ($days as $day) {
            foreach (['in', 'out'] as $dir) {
                $ss = array_keys(array_filter($stmt, fn ($s) => $s['match'] === null && $s['amount'] !== null && $s['date'] === $day && $s['direction'] === $dir));
                $ls = array_keys(array_filter($ledger, fn ($l) => $l['match'] === null && $l['date'] === $day && $l['direction'] === $dir));
                if (count($ss) === 1 && count($ls) === 1) {
                    $this->pair($stmt, $ledger, $ss[0], $ls[0], 'amount_differs');
                }
            }
        }

        // Pass 5 — an ERP entry and the reversal that undid it, neither of
        // which the bank ever saw. They net to nothing and are shown as one
        // cancelled pair, not two errors. (If the bank did process both, the
        // passes above already matched them to statement rows.)
        $byEntry = [];
        foreach ($ledger as $li => $l) {
            if ($l['entry_id'] !== null) {
                $byEntry[$l['entry_id']] = $li;
            }
        }
        foreach ($ledger as $li => $l) {
            if ($l['match'] !== null || ! $l['reverses'] || ! isset($byEntry[$l['reverses']])) {
                continue;
            }
            $oi = $byEntry[$l['reverses']];
            if ($ledger[$oi]['match'] === null && abs($ledger[$oi]['amount'] - $l['amount']) < self::TOLERANCE) {
                $ledger[$oi]['match'] = ['index' => null, 'status' => 'cancelled', 'with' => $li];
                $ledger[$li]['match'] = ['index' => null, 'status' => 'cancelled', 'with' => $oi, 'hidden' => true];
            }
        }

        // ── Day-by-day view ──────────────────────────────────────────────
        $ledgerByDay = [];
        foreach ($ledger as $li => $l) {
            $ledgerByDay[$l['date']][] = $li;
        }
        $stmtByDay = [];
        foreach ($stmt as $si => $s) {
            $stmtByDay[$s['date']][] = $si;
        }
        $allDays = array_unique(array_merge(array_keys($stmtByDay), array_keys($ledgerByDay)));
        sort($allDays);

        $summary = [
            'matched'          => 0,
            'missing_in_erp'   => 0,
            'not_on_statement' => 0,
            'date_differs'     => 0,
            'amount_differs'   => 0,
            'charge_missing'   => 0,
            'day_mismatches'   => 0,
            'timing_days'      => 0,
            'cancelled'        => 0,
            'unreadable'       => 0,
        ];
        $issues   = [];
        $daysOut  = [];
        $stmtCarry   = $stmtOpening;
        $ledgerCarry = $ledgerOpening;
        $prevDiff    = ($stmtOpening !== null) ? round($stmtOpening - $ledgerOpening, 2) : null;

        foreach ($allDays as $day) {
            $rows = [];

            foreach ($stmtByDay[$day] ?? [] as $si) {
                $s = $stmt[$si];
                $l = $s['match'] !== null ? $ledger[$s['match']['index']] : null;
                $status = $s['match']['status'] ?? ($s['amount'] === null ? 'memo' : 'missing_in_erp');
                if (in_array('balance_break', $s['flags'], true)) {
                    $summary['unreadable']++;
                }
                $rows[] = [
                    'stmt'   => $s,
                    'ledger' => $l,
                    'status' => $status,
                    'note'   => $this->note($status, $s, $l),
                    'delta'  => $l ? round($s['balance'] - $l['balance'], 2) : null,
                ];
                if ($status !== 'memo') {
                    $summary[$status]++;
                }
            }
            foreach ($ledgerByDay[$day] ?? [] as $li) {
                $l = $ledger[$li];
                if ($l['match'] !== null) {
                    if (($l['match']['status'] ?? null) === 'cancelled' && empty($l['match']['hidden'])) {
                        $rows[] = [
                            'stmt'   => null,
                            'ledger' => $l,
                            'status' => 'cancelled',
                            'note'   => sprintf(
                                '%s was reversed by %s in the ERP — the two cancel out and the bank saw neither.',
                                $l['txn_no'] ?? 'This entry',
                                $ledger[$l['match']['with']]['txn_no'] ?? 'a reversal'
                            ),
                            'delta'  => null,
                        ];
                        $summary['cancelled']++;
                    }
                    continue; // shown beside its statement row (maybe on another day)
                }
                $rows[] = [
                    'stmt'   => null,
                    'ledger' => $l,
                    'status' => 'not_on_statement',
                    'note'   => $this->note('not_on_statement', null, $l),
                    'delta'  => null,
                ];
                $summary['not_on_statement']++;
            }

            // Closing balances for the day. A side with no rows that day
            // carries the previous figure — a real balance, just unchanged.
            $stmtRows = $stmtByDay[$day] ?? [];
            $stmtClose = $stmtRows ? $stmt[end($stmtRows)]['balance'] : $stmtCarry;
            $stmtCarried = ! $stmtRows;
            $ledRows = $ledgerByDay[$day] ?? [];
            $ledgerClose = $ledRows ? $ledger[end($ledRows)]['balance'] : $ledgerCarry;
            $ledgerCarried = ! $ledRows;
            $stmtCarry   = $stmtClose;
            $ledgerCarry = $ledgerClose;

            $diff = ($stmtClose === null) ? null : round($stmtClose - $ledgerClose, 2);

            // Timing: money both sides agree on but book on different days —
            // a Friday iTransfer the bank posts on Sunday, or an ERP entry
            // dated a day off. At the end of this day the bank has counted
            // the pairs it booked by now, the ERP the pairs it dated by now;
            // the gap between those two sets is expected, not an error.
            $timing = 0.0;
            $timingCount = 0;
            foreach ($stmt as $s) {
                if ($s['match'] === null || $s['match']['index'] === null) {
                    continue;
                }
                $l = $ledger[$s['match']['index']];
                if ($l['date'] === $s['date']) {
                    continue;
                }
                $signed = $s['direction'] === 'in' ? $s['amount'] : -$s['amount'];
                $bankHasIt = $s['date'] <= $day;
                $erpHasIt  = $l['date'] <= $day;
                if ($bankHasIt !== $erpHasIt) {
                    $timing += $bankHasIt ? $signed : -$signed;
                    $timingCount++;
                }
            }
            $timing = round($timing, 2);
            $unexplained = $diff === null ? null : round($diff - $timing, 2);

            $matched = $unexplained !== null && abs($unexplained) < self::TOLERANCE;
            $diffChanged = $unexplained !== null && ($prevDiff === null || abs($unexplained - $prevDiff) >= self::TOLERANCE);
            if ($unexplained !== null && ! $matched) {
                $summary['day_mismatches']++;
            } elseif ($matched && abs($diff) >= self::TOLERANCE) {
                $summary['timing_days']++;
            }

            $daysOut[] = [
                'date'           => $day,
                'rows'           => $rows,
                'stmt_close'     => $stmtClose,
                'stmt_carried'   => $stmtCarried,
                'ledger_close'   => $ledgerClose,
                'ledger_carried' => $ledgerCarried,
                'diff'           => $diff,
                'timing'         => $timing,
                'timing_count'   => $timingCount,
                'unexplained'    => $unexplained,
                'matched'        => $matched,
                // The day the unexplained difference moved is the day the
                // error happened; the days after merely inherit it.
                'diff_changed'   => $diffChanged && ! $matched,
                // ERP entries of this day that sit beside a bank row printed
                // under another date — listed there, counted here.
                'ledger_elsewhere' => count(array_filter($ledRows, fn ($li) => $ledger[$li]['match'] !== null
                    && $ledger[$li]['match']['index'] !== null
                    && $stmt[$ledger[$li]['match']['index']]['date'] !== $day)),
                'stmt_in'        => round(array_sum(array_map(fn ($si) => $stmt[$si]['in'] ?? 0, $stmtRows)), 2),
                'stmt_out'       => round(array_sum(array_map(fn ($si) => $stmt[$si]['out'] ?? 0, $stmtRows)), 2),
                'ledger_in'      => round(array_sum(array_map(fn ($li) => $ledger[$li]['in'] ?? 0, $ledRows)), 2),
                'ledger_out'     => round(array_sum(array_map(fn ($li) => $ledger[$li]['out'] ?? 0, $ledRows)), 2),
            ];

            foreach ($rows as $row) {
                if (in_array($row['status'], ['missing_in_erp', 'not_on_statement', 'date_differs', 'amount_differs', 'charge_missing'], true)) {
                    $issues[] = [
                        'date'     => $day,
                        'severity' => $row['status'] === 'date_differs' ? 'warn' : 'error',
                        'kind'     => $row['status'],
                        'text'     => $row['note'],
                    ];
                }
            }
            if ($unexplained !== null && ! $matched && $diffChanged) {
                $issues[] = [
                    'date'     => $day,
                    'severity' => 'error',
                    'kind'     => 'day_mismatch',
                    'text'     => sprintf(
                        'Closing balance on %s: bank %s, ERP %s — off by %s%s%s.',
                        Carbon::parse($day)->format('d M Y'),
                        number_format($stmtClose, 2),
                        number_format($ledgerClose, 2),
                        number_format(abs($unexplained), 2),
                        abs($timing) >= self::TOLERANCE
                            ? sprintf(' beyond %s of timing (%d entr%s booked on a different day)', number_format(abs($timing), 2), $timingCount, $timingCount === 1 ? 'y' : 'ies')
                            : '',
                        $prevDiff !== null && abs($prevDiff) >= self::TOLERANCE
                            ? sprintf(' (was off by %s the day before)', number_format(abs($prevDiff), 2))
                            : ''
                    ),
                ];
            }
            if ($unexplained !== null) {
                $prevDiff = $unexplained;
            }
        }

        $openingDiff = $stmtOpening === null ? null : round($stmtOpening - $ledgerOpening, 2);
        if ($openingDiff !== null && abs($openingDiff) >= self::TOLERANCE) {
            array_unshift($issues, [
                'date'     => $statement['date_from'],
                'severity' => 'error',
                'kind'     => 'opening_mismatch',
                'text'     => sprintf(
                    'Opening balance differs before this statement starts: bank %s, ERP %s (off by %s). That part of the gap comes from an earlier period.',
                    number_format($stmtOpening, 2),
                    number_format($ledgerOpening, 2),
                    number_format(abs($openingDiff), 2)
                ),
            ]);
        }
        foreach ($stmt as $s) {
            if (in_array('balance_break', $s['flags'], true)) {
                $issues[] = [
                    'date'     => $s['date'],
                    'severity' => 'warn',
                    'kind'     => 'unreadable',
                    'text'     => sprintf(
                        'Line %d of the statement (%s) could not be read cleanly — its balance does not follow from the row before. Check that row by eye.',
                        $s['line'],
                        Carbon::parse($s['date'])->format('d M Y')
                    ),
                ];
            }
        }

        $stmtClosing   = $statement['closing_balance'];
        $ledgerClosing = $ledger ? end($ledger)['balance'] : $ledgerOpening;

        usort($issues, fn ($a, $b) => [$a['date'], $a['severity'] === 'error' ? 0 : 1] <=> [$b['date'], $b['severity'] === 'error' ? 0 : 1]);

        return [
            'opening' => [
                'statement' => $stmtOpening,
                'ledger'    => $ledgerOpening,
                'diff'      => $openingDiff,
                'matched'   => $openingDiff !== null && abs($openingDiff) < self::TOLERANCE,
            ],
            'closing' => [
                'statement' => $stmtClosing,
                'ledger'    => round($ledgerClosing, 2),
                'diff'      => $stmtClosing === null ? null : round($stmtClosing - $ledgerClosing, 2),
                'matched'   => $stmtClosing !== null && abs($stmtClosing - $ledgerClosing) < self::TOLERANCE,
            ],
            'days'    => $daysOut,
            'summary' => $summary + [
                'statement_rows' => count(array_filter($stmt, fn ($s) => $s['amount'] !== null)),
                'ledger_rows'    => count($ledger),
                'days'           => count($daysOut),
            ],
            'issues'  => $issues,
            'clean'   => ! array_filter($issues, fn ($i) => $i['severity'] === 'error'),
        ];
    }

    /** Whole days since the epoch, so date distance is one subtraction. */
    private static function dayNumber(string $date): int
    {
        return intdiv((int) strtotime($date . ' 00:00:00 UTC'), 86400);
    }

    private function pair(array &$stmt, array &$ledger, int $si, int $li, string $status, array $extra = []): void
    {
        $stmt[$si]['match']   = ['index' => $li, 'status' => $status] + $extra;
        $ledger[$li]['match'] = ['index' => $si, 'status' => $status] + $extra;
    }

    private function note(string $status, ?array $s, ?array $l): ?string
    {
        $money = fn ($row) => sprintf('%s %s', $row['direction'] === 'in' ? 'in' : 'out', number_format($row['amount'], 2));
        $day   = fn ($d) => Carbon::parse($d)->format('d M');

        return match ($status) {
            'missing_in_erp'   => sprintf('Bank shows %s (%s) — no ERP entry for it.', $money($s), $this->shorten($s['particulars'])),
            'not_on_statement' => sprintf('ERP has %s %s (%s) — not on the bank statement.', $l['txn_no'] ?? '', $money($l), $this->shorten($l['description'])),
            'date_differs'     => sprintf('Bank cleared it on %s, ERP recorded %s on %s.', $day($s['date']), $l['txn_no'] ?? 'it', $day($l['date'])),
            'amount_differs'   => sprintf('Same day, same way, different figure: bank %s, ERP %s %s.', number_format($s['amount'], 2), $l['txn_no'] ?? '', number_format($l['amount'], 2)),
            'charge_missing'   => $s['direction'] === 'out'
                ? sprintf('Bank took %s — that is %s plus a %s charge. ERP %s has %s only: record the %s charge.',
                    number_format($s['amount'], 2), number_format($l['amount'], 2), number_format($s['match']['fee'], 2),
                    $l['txn_no'] ?? '', number_format($l['amount'], 2), number_format($s['match']['fee'], 2))
                : sprintf('Bank credited %s — that is %s less a %s charge. ERP %s has %s: record the %s charge.',
                    number_format($s['amount'], 2), number_format($l['amount'], 2), number_format($s['match']['fee'], 2),
                    $l['txn_no'] ?? '', number_format($l['amount'], 2), number_format($s['match']['fee'], 2)),
            'matched'          => ($l !== null && $l['date'] !== $s['date'])
                ? sprintf('Bank booked it on %s; the ERP has it on %s%s.', $day($s['date']), $day($l['date']),
                    ($s['value_date'] ?? null) === $l['date'] ? ' — the statement\'s own post date' : '')
                : null,
            'memo'             => 'Bank printed a balance here with no amount — a memo line.',
            default            => null,
        };
    }

    private function shorten(string $text, int $max = 60): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : ($text === '' ? '—' : $text);
    }
}
