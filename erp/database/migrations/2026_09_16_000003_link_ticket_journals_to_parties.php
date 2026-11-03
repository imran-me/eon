<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $receivableId = DB::table('accounts')->where('code', config('accounts.accounts_receivable'))->value('id');
            $payableId = DB::table('accounts')->where('code', config('accounts.accounts_payable'))->value('id');
            $revenueId = DB::table('accounts')->where('code', config('accounts.ticket_sales_revenue'))->value('id');

            // A failed/partially cleaned settlement can leave a journal header
            // with no lines. It is not an accounting entry and must not affect
            // later "first recognition / later settlement" detection.
            $emptyTicketJournalIds = DB::table('journal_entries as je')
                ->leftJoin('journal_items as ji', function ($join) {
                    $join->on('ji.journal_entry_id', '=', 'je.id')
                        ->whereNull('ji.deleted_at');
                })
                ->whereIn('je.source', ['ticket_sale', 'ticket_purchase'])
                ->whereNull('je.deleted_at')
                ->groupBy('je.id')
                ->havingRaw('COUNT(ji.id) = 0')
                ->pluck('je.id');

            if ($emptyTicketJournalIds->isNotEmpty()) {
                DB::table('journal_entries')->whereIn('id', $emptyTicketJournalIds)
                    ->update(['deleted_at' => now()]);
            }

            // Reconcile each existing ticket-sale recognition journal to the
            // sale's live totals. Later receipt journals remain separate; their
            // AR credits are deducted from the paid amount carried by the first
            // journal so payments are never posted twice after an edit.
            if ($receivableId && $revenueId) {
                DB::table('ticket_sales')
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('id')
                    ->chunkById(100, function ($sales) use ($receivableId, $revenueId) {
                        foreach ($sales as $sale) {
                            $journalIds = DB::table('journal_entries')
                                ->where('source', 'ticket_sale')
                                ->where('source_id', $sale->id)
                                ->whereNull('deleted_at')
                                ->orderBy('id')
                                ->pluck('id');

                            if ($journalIds->isEmpty()) {
                                continue;
                            }

                            $settled = $journalIds->count() > 1
                                ? (float) DB::table('journal_items')
                                    ->whereIn('journal_entry_id', $journalIds->slice(1)->values())
                                    ->where('account_id', $receivableId)
                                    ->whereNull('deleted_at')
                                    ->sum('credit')
                                : 0.0;

                            $total = (float) $sale->total_amount;
                            $paidOnRecognition = max(0.0, min($total, (float) $sale->paid_amount - $settled));
                            $dueOnRecognition = max(0.0, $total - $paidOnRecognition);
                            $bankAccountId = $sale->bank_id
                                ? DB::table('banks')->where('id', $sale->bank_id)->value('account_id')
                                : null;

                            // Never replace a recoverable old entry with a new
                            // unbalanced one when its paid portion has no mapped
                            // chart account. The normal posting code will now
                            // reject that condition on future saves.
                            if ($paidOnRecognition > 0 && ! $bankAccountId) {
                                continue;
                            }

                            $recognitionId = (int) $journalIds->first();
                            DB::table('journal_items')
                                ->where('journal_entry_id', $recognitionId)
                                ->whereNull('deleted_at')
                                ->update(['deleted_at' => now(), 'updated_at' => now()]);

                            $rows = [];
                            if ($paidOnRecognition > 0) {
                                $rows[] = $this->journalItem($recognitionId, (int) $bankAccountId, $paidOnRecognition, 0, 'Cash received — ' . $sale->invoice_no);
                            }
                            if ($dueOnRecognition > 0) {
                                $rows[] = $this->journalItem($recognitionId, (int) $receivableId, $dueOnRecognition, 0, 'Amount due — ' . $sale->invoice_no);
                            }
                            $rows[] = $this->journalItem($recognitionId, (int) $revenueId, 0, $total, 'Sales revenue — ' . $sale->invoice_no);
                            DB::table('journal_items')->insert($rows);
                        }
                    });
            }

            $this->linkSaleJournals($receivableId);
            $this->linkPurchaseJournals($payableId);
        });
    }

    /** @return array<string, mixed> */
    private function journalItem(int $journalId, int $accountId, float $debit, float $credit, string $note): array
    {
        return [
            'journal_entry_id' => $journalId,
            'account_id' => $accountId,
            'debit' => $debit,
            'credit' => $credit,
            'note' => $note,
            'party_type' => null,
            'party_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function linkSaleJournals(?int $receivableId): void
    {
        $sales = DB::table('ticket_sales as ts')
            ->join('users as u', 'u.id', '=', 'ts.client_id')
            ->select('ts.id', 'ts.client_id as party_id', 'u.name')
            ->get()
            ->keyBy('id');

        foreach ($sales as $saleId => $sale) {
            $partyType = $this->userPartyType((int) $sale->party_id);
            $journalIds = DB::table('journal_entries')
                ->where('source', 'ticket_sale')
                ->where('source_id', $saleId)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($journalIds->isEmpty()) {
                continue;
            }

            DB::table('journal_entries')->whereIn('id', $journalIds)->update([
                'beneficiary' => $sale->name,
                'beneficiary_party_id' => $sale->party_id,
            ]);

            DB::table('journal_items')
                ->whereIn('journal_entry_id', $journalIds)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($receivableId) {
                    if ($receivableId) {
                        $query->where('account_id', $receivableId);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                    $query->orWhere('note', 'like', 'AR cleared%')
                        ->orWhere('note', 'like', 'Amount due%');
                })
                ->update(['party_type' => $partyType, 'party_id' => $sale->party_id]);
        }
    }

    private function linkPurchaseJournals(?int $payableId): void
    {
        $purchases = DB::table('ticket_purchases as tp')
            ->join('users as u', 'u.id', '=', 'tp.vendor_id')
            ->select('tp.id', 'tp.vendor_id as party_id', 'u.name')
            ->get()
            ->keyBy('id');

        foreach ($purchases as $purchaseId => $purchase) {
            $journalIds = DB::table('journal_entries')
                ->where('source', 'ticket_purchase')
                ->where('source_id', $purchaseId)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($journalIds->isEmpty()) {
                continue;
            }

            DB::table('journal_entries')->whereIn('id', $journalIds)->update([
                'beneficiary' => $purchase->name,
                'beneficiary_party_id' => $purchase->party_id,
            ]);

            DB::table('journal_items')
                ->whereIn('journal_entry_id', $journalIds)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($payableId) {
                    if ($payableId) {
                        $query->where('account_id', $payableId);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                    $query->orWhere('note', 'like', 'Payable cleared%')
                        ->orWhere('note', 'like', 'Amount owed%');
                })
                ->update(['party_type' => 'vendor', 'party_id' => $purchase->party_id]);
        }
    }

    private function userPartyType(int $userId): string
    {
        $roles = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_id', $userId)
            ->where('mhr.model_type', 'App\\Models\\User')
            ->pluck('r.name')
            ->map(fn ($role) => strtolower((string) $role));

        if ($roles->contains('agent')) {
            return 'agent';
        }
        if ($roles->contains('vendor')) {
            return 'vendor';
        }
        return 'customer';
    }

    public function down(): void
    {
        // Deliberately irreversible: this migration repairs accounting data and
        // adds missing ownership metadata. Rolling it back must not re-introduce
        // an unbalanced journal or detach historical party attribution.
    }
};
