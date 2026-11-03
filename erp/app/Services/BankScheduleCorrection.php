<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PaymentSchedule;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\PostsPartyLedger;
use App\Traits\ResolvesPartyType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Audited, append-only corrections for standalone schedule settlements. */
class BankScheduleCorrection
{
    use PostsPartyLedger;
    use ResolvesPartyType;

    public function parts(Request $request, JournalEntry $entry): array
    {
        $actor = $request->user();
        abort_unless($actor && ($actor->can('view all bank') || (int) $actor->company_id === (int) $entry->company_id), 403);
        abort_unless($entry->source === 'schedule_payment_settlement' && ! $entry->reversed_journal_entry_id
            && ! JournalEntry::where('reversed_journal_entry_id', $entry->id)->exists(), 422, 'Only an active schedule settlement can be corrected.');
        $schedule = PaymentSchedule::whereKey($entry->source_id)->where('company_id', $entry->company_id)->firstOrFail();
        abort_unless($schedule->schedulable_type === null && $schedule->status === 'paid', 422, 'Linked invoice or payroll payments must be corrected from their source record.');
        $entry->setRelation('items', JournalItem::where('journal_entry_id', $entry->id)->get());
        // Legacy schedule settlements can carry the operator's company while
        // the selected bank belongs to another group company. The journal's
        // account line is still authoritative, so resolve the bank globally
        // by that unique linked account instead of hiding it behind the stale
        // journal company. Cross-company correction remains super-admin only.
        $banks = Bank::whereIn('account_id', $entry->items->pluck('account_id'))->get();
        abort_unless($banks->count() === 1, 422, 'Expected exactly one bank in this settlement.');
        $bank = $banks->first();
        abort_unless($actor->can('view all bank') || (int) $bank->company_id === (int) $entry->company_id, 403,
            'This legacy settlement uses a bank from another company. A super admin must correct it.');
        $bankItem = $entry->items->firstWhere('account_id', $bank->account_id);
        $principal = $entry->items->first(fn ($item) => (int) $item->account_id !== (int) $bank->account_id);
        abort_unless($entry->items->count() === 2 && $principal && $bankItem, 422, 'Unsupported schedule posting structure.');
        $isDeposit = $schedule->type === 'receive';
        abort_unless(($isDeposit ? (float) $bankItem->debit : (float) $bankItem->credit) > 0, 422, 'Schedule direction does not match its bank posting.');
        $latest = DB::table('bank_transaction_amount_edits')->where('journal_entry_id', $entry->id)->orderByDesc('id')->first();
        $chargeItem = $latest?->new_charge_amount > 0 ? new JournalItem(['account_id' => $latest->new_charge_account_id]) : null;
        $vatItem = $latest?->new_vat_amount > 0 ? new JournalItem(['account_id' => $latest->new_vat_account_id]) : null;

        return ['bank' => $bank, 'schedule' => $schedule, 'bank_item' => $bankItem, 'principal_item' => $principal,
            'charge_item' => $chargeItem, 'vat_item' => $vatItem, 'is_deposit' => $isDeposit,
            'type' => $isDeposit ? 'deposit' : 'withdraw', 'amount' => (float) ($latest->new_amount ?? ($isDeposit ? $principal->credit : $principal->debit)),
            'charge_amount' => (float) ($latest->new_charge_amount ?? 0), 'vat_amount' => (float) ($latest->new_vat_amount ?? 0)];
    }

    public function amount(Request $request, JournalEntry $original, array $data): void
    {
        DB::transaction(function () use ($request, $original, $data) {
            $entry = JournalEntry::whereKey($original->id)->lockForUpdate()->firstOrFail();
            PaymentSchedule::whereKey($entry->source_id)->lockForUpdate()->firstOrFail();
            $parts = $this->parts($request, $entry);
            $amount = round((float) $data['amount'], 2);
            $charge = round((float) ($data['charge_amount'] ?? 0), 2);
            $vat = round((float) ($data['vat_amount'] ?? 0), 2);
            abort_if($parts['is_deposit'] && $charge + $vat >= $amount, 422, 'Charges must be less than the received amount.');
            $delta = round($amount - $parts['amount'], 2);
            $bankDelta = ($parts['is_deposit'] ? $delta : -$delta) - ($charge + $vat - $parts['charge_amount'] - $parts['vat_amount']);
            $bank = Bank::whereKey($parts['bank']->id)->lockForUpdate()->firstOrFail();
            if ($bankDelta < 0) {
                Bank::assertCanCover((int) $bank->account_id, abs($bankDelta), $bank->name);
            }
            $lines = [];
            $this->line($lines, $bank->account_id, $bankDelta, 'Bank settlement correction');
            $partyId = $entry->beneficiary_party_id ?: $parts['schedule']->party_id;
            $party = $partyId ? User::findOrFail($partyId) : null;
            $this->line($lines, $parts['principal_item']->account_id, $parts['is_deposit'] ? -$delta : $delta, 'Settlement principal correction', $party);
            if ($party && ! $entry->beneficiary_party_id && ! $parts['principal_item']->party_id) {
                $oldSigned = $parts['is_deposit'] ? -$parts['amount'] : $parts['amount'];
                $this->line($lines, $parts['principal_item']->account_id, -$oldSigned, 'Previous unallocated settlement');
                $this->line($lines, $parts['principal_item']->account_id, $oldSigned, 'Linked schedule party', $party);
            }
            foreach ([['charge', $charge], ['vat', $vat]] as [$key, $value]) {
                $old = $parts[$key.'_amount'];
                $oldAccount = $parts[$key.'_item']?->account_id;
                $newAccount = $value > 0 ? (int) $data[$key.'_account_id'] : null;
                $this->line($lines, $oldAccount, -$old, ucfirst($key).' correction');
                $this->line($lines, $newAccount, $value, ucfirst($key).' correction');
            }
            // Identical confirmations are harmless, including account reclassification.
            $lines = $this->netLines($lines);
            if (! $lines) {
                return;
            }
            $this->postAdjustment($entry, $lines, $data['reason']);
            if ($party) {
                $this->syncLedger($entry, $bank, $party, $amount, $data['reason']);
                $entry->update(['beneficiary_party_id' => $party->id, 'beneficiary' => $party->name]);
            }
            $parts['schedule']->update(['paid_amount' => $amount]);
            $bank->increment('balance', round($bankDelta, 2));
            DB::table('bank_transaction_amount_edits')->insert([
                'journal_entry_id' => $entry->id, 'old_amount' => $parts['amount'], 'new_amount' => $amount,
                'old_charge_amount' => $parts['charge_amount'], 'new_charge_amount' => $charge,
                'old_vat_amount' => $parts['vat_amount'], 'new_vat_amount' => $vat,
                'old_charge_account_id' => $parts['charge_item']?->account_id, 'new_charge_account_id' => $charge > 0 ? $data['charge_account_id'] : null,
                'old_vat_account_id' => $parts['vat_item']?->account_id, 'new_vat_account_id' => $vat > 0 ? $data['vat_account_id'] : null,
                'reason' => $data['reason'], 'edited_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function details(Request $request, JournalEntry $original, array $data): JournalEntry
    {
        return DB::transaction(function () use ($request, $original, $data) {
            $entry = JournalEntry::whereKey($original->id)->lockForUpdate()->firstOrFail();
            PaymentSchedule::whereKey($entry->source_id)->lockForUpdate()->firstOrFail();
            $parts = $this->parts($request, $entry);
            $oldPartyId = $entry->beneficiary_party_id ?: $parts['schedule']->party_id;
            $newPartyId = $data['beneficiary_party_id'] ?? null;
            abort_if($oldPartyId && ! $newPartyId, 422, 'Select a party to replace the linked party.');
            $newParty = $newPartyId ? User::role(['vendor', 'agent', 'customer'])->findOrFail($newPartyId) : null;
            if ($newParty && ! $request->user()->can('view all bank')) {
                $type = $this->resolvePartyType($newParty);
                abort_unless(CompanyPartyDirectory::belongs($newParty->id, $entry->company_id, $type === 'customer' ? 'client' : $type), 422, 'Select a party assigned to this company.');
            }
            $allocationPartyId = $entry->beneficiary_party_id ?: $parts['principal_item']->party_id;
            $oldParty = $allocationPartyId ? User::findOrFail($allocationPartyId) : null;
            $lines = [];
            if ((int) $allocationPartyId !== (int) $newPartyId) {
                $signed = $parts['is_deposit'] ? -$parts['amount'] : $parts['amount'];
                $this->line($lines, $parts['principal_item']->account_id, -$signed, 'Previous party allocation', $oldParty);
                $this->line($lines, $parts['principal_item']->account_id, $signed, 'Corrected party allocation', $newParty);
                $this->postAdjustment($entry, $lines, $data['correction_reason']);
            }
            if ($newParty) {
                $this->syncLedger($entry, $parts['bank'], $newParty, $parts['amount'], $data['correction_reason']);
            }
            $before = $entry->only(['description', 'beneficiary', 'beneficiary_party_id', 'beneficiary_method', 'beneficiary_bank', 'beneficiary_ref']);
            $before['note'] = $parts['schedule']->note;
            $note = $data['remarks'] ?? '';
            $beneficiary = $newParty?->name ?? ($data['beneficiary'] ?? $entry->beneficiary ?? $parts['schedule']->party_name);
            $entry->update(['description' => ($note ?: $parts['schedule']->source_label ?: 'Ad-hoc').' — '.$beneficiary,
                'beneficiary' => $beneficiary, 'beneficiary_party_id' => $newParty?->id,
                'beneficiary_method' => $data['pay_method'] ?? null, 'beneficiary_bank' => $data['pay_bank'] ?? null, 'beneficiary_ref' => $data['pay_ref'] ?? null]);
            $parts['schedule']->update(['party_id' => $newParty?->id, 'party_name' => $beneficiary,
                'party_type' => $newParty ? $this->resolvePartyType($newParty) : $parts['schedule']->party_type, 'note' => $note]);
            DB::table('journal_entry_detail_edits')->insert(['journal_entry_id' => $entry->id,
                'old_data' => json_encode($before), 'new_data' => json_encode(array_merge($entry->only(array_keys($before)), ['note' => $note])),
                'reason' => $data['correction_reason'], 'edited_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

            return $entry;
        });
    }

    private function line(array &$lines, ?int $accountId, float $signed, string $note, ?User $party = null): void
    {
        if ($accountId && abs($signed) >= .005) {
            $lines[] = ['account_id' => $accountId, 'signed' => round($signed, 2), 'note' => $note,
                'party_id' => $party?->id, 'party_type' => $party ? $this->resolvePartyType($party) : null];
        }
    }

    private function netLines(array $lines): array
    {
        return collect($lines)->groupBy(fn ($line) => $line['account_id'].':'.$line['party_id'])
            ->map(fn ($group) => array_merge($group->first(), ['signed' => round($group->sum('signed'), 2)]))
            ->filter(fn ($line) => abs($line['signed']) >= .005)->values()->all();
    }

    private function postAdjustment(JournalEntry $entry, array $lines, string $reason): void
    {
        abort_unless(abs(array_sum(array_column($lines, 'signed'))) < .005, 422, 'Correction journal does not balance.');
        $adjustment = JournalEntry::create(['company_id' => $entry->company_id, 'created_by' => auth()->id(),
            'date' => $entry->date, 'entry_time' => $entry->entry_time, 'reference' => 'SCH-COR-'.$entry->id.'-'.bin2hex(random_bytes(4)),
            'source' => 'schedule_payment_correction', 'source_id' => $entry->id, 'description' => 'Correction of '.$entry->reference.' — '.$reason]);
        foreach ($lines as $line) {
            $signed = $line['signed'];
            unset($line['signed']);
            JournalItem::create(['journal_entry_id' => $adjustment->id, 'debit' => max(0, $signed), 'credit' => max(0, -$signed)] + $line);
        }
    }

    private function postLedger(JournalEntry $entry, Bank $bank, User $party, float $credit, string $reason): void
    {
        User::whereKey($party->id)->lockForUpdate()->firstOrFail();
        $this->postPartyLedgerRow($party->id, ['company_id' => $entry->company_id, 'type' => 'schedule_payment_correction',
            'invoice_id' => $entry->id, 'account_id' => $bank->id, 'reference_no' => $entry->reference,
            'payment_date' => $entry->date, 'transaction_time' => $entry->entry_time, 'payment_method' => 'bank',
            'debit' => max(0, -$credit), 'credit' => max(0, $credit), 'remarks' => 'Schedule settlement correction — '.$reason]);
    }

    private function syncLedger(JournalEntry $entry, Bank $bank, User $party, float $amount, string $reason): void
    {
        // Legacy ad-hoc schedules may name a party without any ledger receipt.
        $totals = Transaction::where('company_id', $entry->company_id)->where('invoice_id', $entry->id)
            ->where('account_id', $bank->id)->where('reference_no', $entry->reference)
            ->whereIn('type', ['party_payment', 'schedule_payment_correction'])
            ->selectRaw('user_id, SUM(credit - debit) as paid')->groupBy('user_id')->get();
        foreach ($totals as $total) {
            if ((int) $total->user_id !== (int) $party->id && abs((float) $total->paid) >= .005) {
                $this->postLedger($entry, $bank, User::findOrFail($total->user_id), -(float) $total->paid, $reason);
            }
        }
        $already = (float) ($totals->firstWhere('user_id', $party->id)?->paid ?? 0);
        if (abs($amount - $already) >= .005) {
            $this->postLedger($entry, $bank, $party, $amount - $already, $reason);
        }
    }
}
