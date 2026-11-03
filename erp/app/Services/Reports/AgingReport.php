<?php

namespace App\Services\Reports;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AR / AP Aging — what each party owes us (receivables: agents and customers) or what we owe each
 * party (payables: vendors), cut into how long it has been outstanding.
 *
 * READ-ONLY, and it reads the SAME ledger the Party Statement does (`transactions`, debit − credit),
 * so a party's aging total always equals the balance on his statement — never a second set of books.
 *
 *  · Open items are the party's debits not yet settled: every credit (payment, refund, reversal) is
 *    applied to his OLDEST debit first (FIFO) — the ledger carries no per-invoice link for most
 *    postings, so this is the only rule that always reconciles.
 *  · A debit is aged from its DUE DATE where its document has one (ticket sales / purchases, party
 *    invoices), otherwise from the entry date — i.e. no due date means "due when posted".
 *  · A party whose credits exceed his debits is an ADVANCE (customer overpaid / vendor paid ahead): it
 *    is listed separately and is never netted against another party's due.
 *
 * Buckets follow the Zoho report: Current (not yet due), 1–15, 16–30, 31–45, > 45 days past due.
 */
class AgingReport
{
    public const BUCKETS = ['current' => 'Current', 'b15' => '1 - 15 Days', 'b30' => '16 - 30 Days', 'b45' => '31 - 45 Days', 'over' => '> 45 Days'];

    /** Transaction types whose document carries a due date: type => [table, id column]. */
    private const DUE_TABLES = [
        'ticket_sale'     => ['ticket_sales', 'id'],
        'ticket_purchase' => ['ticket_purchases', 'id'],
        'party_invoice'   => ['party_invoices', 'id'],
    ];

    /** @param 'receivable'|'payable' $side */
    public function build(string $side, ?int $companyId, string $asOf): array
    {
        $roles = $side === 'receivable' ? ['agent', 'customer'] : ['vendor'];
        $partyTypes = $side === 'receivable' ? ['agent', 'client'] : ['vendor'];

        $users = User::role($roles)
            ->when($companyId, fn ($q) => $q->whereIn('id', DB::table('company_party')->where('company_id', $companyId)->whereIn('party_type', $partyTypes)->select('user_id')))
            ->get(['id', 'name', 'phone'])->keyBy('id');

        $empty = ['rows' => [], 'advances' => [], 'totals' => array_fill_keys(array_keys(self::BUCKETS), 0.0) + ['total' => 0.0], 'advance_total' => 0.0, 'overdue' => 0.0, 'asOf' => $asOf];
        if ($users->isEmpty()) {
            return $empty;
        }

        $tx = DB::table('transactions')
            ->whereIn('user_id', $users->keys())
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereDate('payment_date', '<=', $asOf)
            ->orderBy('user_id')->orderBy('payment_date')->orderBy('id')
            ->get(['id', 'user_id', 'type', 'invoice_id', 'payment_date', 'debit', 'credit']);

        $dues = $this->dueDates($tx);

        $rows = [];
        $advances = [];
        $totals = $empty['totals'];
        $asOfDate = Carbon::parse($asOf)->startOfDay();

        foreach ($tx->groupBy('user_id') as $uid => $list) {
            $debits = [];
            $credit = 0.0;
            $last = null;
            foreach ($list as $t) {
                $last = $t->payment_date ? substr((string) $t->payment_date, 0, 10) : $last;
                if ((float) $t->debit > 0) {
                    $due = $dues[$t->type . ':' . $t->invoice_id] ?? null;
                    $debits[] = ['age' => $due ?: substr((string) $t->payment_date, 0, 10), 'amt' => (float) $t->debit];
                }
                $credit += (float) $t->credit;
            }

            usort($debits, fn ($a, $b) => strcmp($a['age'], $b['age']));
            foreach ($debits as &$d) {                                   // FIFO: payments settle the oldest first
                $take = min($credit, $d['amt']);
                $d['amt'] -= $take;
                $credit -= $take;
            }
            unset($d);

            $b = array_fill_keys(array_keys(self::BUCKETS), 0.0);
            foreach ($debits as $d) {
                if ($d['amt'] <= 0.004) {
                    continue;
                }
                $days = Carbon::parse($d['age'])->startOfDay()->diffInDays($asOfDate, false);   // + = past due
                $key = $days <= 0 ? 'current' : ($days <= 15 ? 'b15' : ($days <= 30 ? 'b30' : ($days <= 45 ? 'b45' : 'over')));
                $b[$key] += $d['amt'];
            }
            $open = array_sum($b);
            $u = $users[$uid];

            if ($open > 0.004) {
                $rows[] = ['id' => (int) $uid, 'name' => $u->name, 'buckets' => $b, 'total' => $open, 'last' => $last,
                           'overdue' => $open - $b['current']];
                foreach ($b as $k => $v) {
                    $totals[$k] += $v;
                }
                $totals['total'] += $open;
            } elseif ($credit > 0.004) {
                $advances[] = ['id' => (int) $uid, 'name' => $u->name, 'amount' => $credit, 'last' => $last];
            }
        }

        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);
        usort($advances, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'rows' => $rows, 'advances' => $advances, 'totals' => $totals,
            'advance_total' => (float) array_sum(array_column($advances, 'amount')),
            'overdue' => $totals['total'] - $totals['current'], 'asOf' => $asOf,
        ];
    }

    /** "type:id" → due date (Y-m-d) for the documents that have one — a handful of bulk lookups, never per row. */
    private function dueDates(Collection $tx): array
    {
        $out = [];
        foreach (self::DUE_TABLES as $type => [$table, $idCol]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'due_date')) {
                continue;
            }
            $ids = $tx->where('type', $type)->where('debit', '>', 0)->pluck('invoice_id')->filter()->unique()->values();
            if ($ids->isEmpty()) {
                continue;
            }
            foreach (DB::table($table)->whereIn($idCol, $ids)->whereNotNull('due_date')->get([$idCol, 'due_date']) as $r) {
                $out[$type . ':' . $r->{$idCol}] = substr((string) $r->due_date, 0, 10);
            }
        }

        return $out;
    }
}
