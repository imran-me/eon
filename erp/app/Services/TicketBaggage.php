<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ContractFlightPassenger;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\TicketPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\PostsPartyLedger;
use App\Traits\ResolvesPartyType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TicketBaggage
{
    use PostsPartyLedger;
    use ResolvesPartyType;

    public const COST = 'ticket_baggage_cost';

    public const CHARGE = 'ticket_baggage_charge';

    public const FIELDS = ['free_baggage_kg', 'extra_baggage_kg', 'extra_baggage_cost', 'extra_baggage_agent_charge', 'extra_baggage_bearer', 'baggage_conditions'];

    public static function ready(string $table = 'ticket_purchases'): bool
    {
        return Schema::hasColumns($table, self::FIELDS);
    }

    public static function rules(string $prefix = ''): array
    {
        return collect([
            'free_baggage_kg' => 'nullable|numeric|min:0|max:99999999.99',
            'extra_baggage_kg' => 'nullable|numeric|min:0|max:99999999.99',
            'extra_baggage_cost' => 'nullable|numeric|min:0|max:9999999999999.99',
            'extra_baggage_agent_charge' => 'nullable|numeric|min:0|max:9999999999999.99',
            'extra_baggage_bearer' => 'nullable|in:customer,company',
            'baggage_conditions' => 'nullable|string|max:5000',
        ])->mapWithKeys(fn ($rule, $field) => [$prefix.$field => $rule])->all();
    }

    /** Apply baggage only after this passenger's agent and ticket links have been saved. */
    public function forPassenger(ContractFlightPassenger $passenger, array $edits = []): void
    {
        if (! self::ready()) {
            if ($edits) {
                throw ValidationException::withMessages(['ticket_baggage' => 'The baggage migration must be installed first.']);
            }

            return;
        }
        $tickets = $passenger->ticketPurchases()->get()->keyBy('id');
        foreach ($edits as $id => $fields) {
            $ticket = $tickets->get((int) $id);
            if (! $ticket || (int) $ticket->passport_holder_id !== (int) $passenger->passport_holder_id
                || (int) $ticket->company_id !== TicketingProfit::COMPANY_ID) {
                throw ValidationException::withMessages(['ticket_baggage' => 'Baggage must belong to this passenger and company.']);
            }
            $ticket->update(TicketPurchase::entryDetailAttributes('ticket_purchases', array_intersect_key($fields, array_flip(self::FIELDS))));
        }
        foreach ($tickets as $ticket) {
            $this->sync($ticket);
        }
        $passenger->unsetRelation('ticketPurchases');
    }

    public static function buyer(TicketPurchase $ticket): ?int
    {
        $buyer = null;
        if (Schema::hasTable('contract_flight_passenger_tickets')) {
            $buyer = DB::table('contract_flight_passenger_tickets as pt')
                ->join('contract_flight_passengers as p', 'p.id', '=', 'pt.contract_flight_passenger_id')
                ->where('pt.ticket_purchase_id', $ticket->id)->value('p.agent_id');
        }
        if (! $buyer) {
            $buyer = DB::table('ticket_sale_items as i')->join('ticket_sales as s', 's.id', '=', 'i.ticket_sale_id')
                ->where('i.ticket_purchase_id', $ticket->id)->whereNull('s.deleted_at')->where('s.status', 'confirm')
                ->orderByDesc('i.id')->value('s.client_id');
        }
        if (! $buyer && $ticket->status === 'cancelled') {
            // A flight cancellation unlinks the ticket; its separate baggage charge still has its original buyer.
            $buyer = Transaction::where('type', self::CHARGE)->where('invoice_id', $ticket->id)
                ->where('debit', '>', 0)->latest('id')->value('user_id');
        }

        return $buyer ? (int) $buyer : null;
    }

    /** Actual dated postings, including adjustments, for Ticket Accounts and company profit. */
    public static function movements(?int $companyId = null, $from = null, $to = null, string $seller = ''): Collection
    {
        [$kind, $sellerId] = array_pad(explode(':', $seller, 2), 2, null);

        return DB::table('journal_entries as j')->join('journal_items as i', 'i.journal_entry_id', '=', 'j.id')
            ->join('accounts as a', 'a.id', '=', 'i.account_id')
            ->join('ticket_purchases as p', 'p.id', '=', 'j.source_id')
            ->leftJoin('passport_holders as ph', 'ph.id', '=', 'p.passport_holder_id')
            ->whereIn('j.source', [self::COST, self::CHARGE])->whereNull('j.deleted_at')->whereNull('i.deleted_at')
            ->whereIn('a.code', [config('accounts.ticket_purchase_cost'), config('accounts.ticket_sales_revenue')])
            ->when($companyId, fn ($q) => $q->where('j.company_id', $companyId))
            ->when($from, fn ($q) => $q->where('j.date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('j.date', '<=', $to->toDateString()))
            ->when($kind === 'v' && $sellerId, fn ($q) => $q->where('p.vendor_id', $sellerId))
            ->when($kind === 'p' && $sellerId, fn ($q) => $q->whereNull('p.vendor_id')->where('p.portal_id', $sellerId))
            ->get(['j.id', 'j.source', 'j.source_id', 'j.date', 'j.description', 'j.beneficiary_party_id', 'j.beneficiary', 'p.ticket_no', 'ph.name as passenger', 'i.debit', 'i.credit'])
            ->map(function ($row) {
                $cost = $row->source === self::COST ? (float) $row->debit - (float) $row->credit : 0.0;
                $charge = $row->source === self::CHARGE ? (float) $row->credit - (float) $row->debit : 0.0;

                return [
                    'id' => $row->id, 'ticket_purchase_id' => $row->source_id, 'kind' => 'baggage', 'pnr' => $row->ticket_no, 'passenger' => $row->passenger,
                    'date' => (string) $row->date, 'month' => substr((string) $row->date, 0, 7),
                    'client_id' => $row->source === self::CHARGE ? $row->beneficiary_party_id : null,
                    'client' => $row->source === self::CHARGE ? $row->beneficiary : null,
                    'cost_added' => $cost, 'charged' => max(0, $charge), 'reversed' => max(0, -$charge),
                    'revenue' => $charge, 'sold_cost' => $cost, 'note' => $row->description,
                ];
            });
    }

    /** Reconcile by addition, including a seller/buyer change. Caller owns the save transaction. */
    public function sync(TicketPurchase $ticket): void
    {
        if (! self::ready()) {
            return;
        }

        DB::transaction(function () use ($ticket) {
            $ticket = TicketPurchase::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $cost = round((float) $ticket->extra_baggage_cost, 2);
            $charge = $ticket->extra_baggage_bearer === 'customer' ? round((float) $ticket->extra_baggage_agent_charge, 2) : 0.0;
            $active = $cost > 0 || (float) $ticket->extra_baggage_agent_charge > 0 || (float) $ticket->extra_baggage_kg > 0;
            $fail = fn ($field, $message) => throw ValidationException::withMessages([$field => $message]);
            if ($active && ! in_array($ticket->extra_baggage_bearer, ['customer', 'company'], true)) {
                $fail('extra_baggage_bearer', 'Choose who pays for extra baggage.');
            }
            if ($active && (float) $ticket->extra_baggage_kg <= 0) {
                $fail('extra_baggage_kg', 'Enter extra baggage KG.');
            }
            if ($cost > 0 && ! $ticket->vendor_id && ! $ticket->portal_id) {
                $fail('extra_baggage_cost', 'Extra baggage needs the ticket seller.');
            }
            if (! $active && ! Transaction::where('invoice_id', $ticket->id)->whereIn('type', [self::COST, self::CHARGE])->exists()
                && ! PortalBalance::where('invoice_id', $ticket->id)->where('type', self::COST)->exists()) {
                return;
            }
            $buyer = self::buyer($ticket);
            if ($ticket->extra_baggage_bearer === 'customer' && $active && ! $buyer) {
                $fail('extra_baggage_agent_charge', 'Sell or link this ticket to its agent before charging extra baggage.');
            }
            if ($ticket->extra_baggage_bearer === 'company' && (float) $ticket->extra_baggage_agent_charge > 0) {
                $fail('extra_baggage_agent_charge', 'Agent charge must be zero when we bear the baggage cost.');
            }

            foreach ([self::COST, self::CHARGE] as $type) {
                $targetId = $type === self::COST ? (int) $ticket->vendor_id : (int) $buyer;
                $target = $type === self::COST ? ($ticket->vendor_id ? $cost : 0.0) : $charge;
                $posted = Transaction::where('type', $type)->where('invoice_id', $ticket->id)->get()
                    ->groupBy('user_id')->map(fn ($rows) => round((float) $rows->sum(fn ($r) => $r->debit - $r->credit), 2))->all();
                if ($targetId) {
                    $posted[$targetId] ??= 0.0;
                }
                foreach ($posted as $party => $amount) {
                    $delta = round(((int) $party === $targetId ? $target : 0) - $amount, 2);
                    if (abs($delta) < 0.005) {
                        continue;
                    }
                    User::whereKey($party)->lockForUpdate()->firstOrFail();
                    $account = $this->account($type === self::COST ? 'accounts_payable' : 'accounts_receivable');
                    $this->postPartyLedgerRow((int) $party, [
                        'company_id' => $ticket->company_id, 'type' => $type, 'invoice_id' => $ticket->id,
                        'account_id' => null, 'payment_date' => $this->date($ticket, $type), 'reference_no' => $ticket->ticket_no,
                        'debit' => max(0, $delta), 'credit' => max(0, -$delta), 'remarks' => $this->description($ticket, $type),
                    ]);
                    $this->journal($ticket, $type, $delta, $account, (int) $party);
                }
            }

            $wallets = PortalBalance::where('type', self::COST)->where('invoice_id', $ticket->id)->get()
                ->groupBy('portal_id')->map(fn ($rows) => round((float) $rows->sum(fn ($r) => $r->credit - $r->debit), 2))->all();
            if ($ticket->portal_id && ! $ticket->vendor_id) {
                $wallets[$ticket->portal_id] ??= 0.0;
            }
            foreach ($wallets as $portalId => $amount) {
                $desired = ! $ticket->vendor_id && (int) $portalId === (int) $ticket->portal_id ? $cost : 0;
                $delta = round($desired - $amount, 2);
                if (abs($delta) < 0.005) {
                    continue;
                }
                $portal = Portal::whereKey($portalId)->lockForUpdate()->firstOrFail();
                if (! $portal->account_id) {
                    $fail('extra_baggage_cost', 'The ticket portal needs an accounting account for extra baggage.');
                }
                $opening = (float) $portal->balance;
                $entry = $this->journal($ticket, self::COST, $delta, (int) $portal->account_id, null);
                PortalBalance::create([
                    'portal_id' => $portalId, 'invoice_id' => $ticket->id, 'type' => self::COST,
                    'date' => $entry->date, 'journal_entry_id' => $entry->id, 'reference' => $ticket->ticket_no,
                    'old_balance' => $opening, 'debit' => max(0, -$delta), 'credit' => max(0, $delta),
                    'current_balance' => $opening - $delta, 'remarks' => $this->description($ticket, self::COST), 'created_by' => auth()->id(),
                ]);
                $portal->update(['balance' => $opening - $delta]);
            }
        });
    }

    private function account(string $key): int
    {
        return (int) (Account::where('code', config('accounts.'.$key))->value('id')
            ?: throw new \RuntimeException('Missing baggage posting account: '.config('accounts.'.$key)));
    }

    private function date(TicketPurchase $ticket, string $type): string
    {
        return JournalEntry::where('source', $type)->where('source_id', $ticket->id)->exists()
            ? now()->toDateString() : substr((string) $ticket->purchase_date, 0, 10);
    }

    private function description(TicketPurchase $ticket, string $type): string
    {
        return mb_substr('Extra baggage '.($type === self::COST ? 'cost' : 'agent charge').' — '.$ticket->extra_baggage_kg.' KG · '.$ticket->ticket_no, 0, 250);
    }

    private function journal(TicketPurchase $ticket, string $type, float $delta, int $partyAccount, ?int $party): JournalEntry
    {
        $isCost = $type === self::COST;
        $nominal = $this->account($isCost ? 'ticket_purchase_cost' : 'ticket_sales_revenue');
        $entry = JournalEntry::create([
            'company_id' => $ticket->company_id, 'created_by' => auth()->id(), 'date' => $this->date($ticket, $type),
            'source' => $type, 'source_id' => $ticket->id, 'reference' => $ticket->ticket_no,
            'description' => $this->description($ticket, $type), 'beneficiary_party_id' => $party,
            'beneficiary' => $party ? User::whereKey($party)->value('name') : null,
        ]);
        $amount = abs($delta);
        $nominalDebit = $isCost === ($delta > 0);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $nominal,
            'debit' => $nominalDebit ? $amount : 0, 'credit' => $nominalDebit ? 0 : $amount,
            'note' => $this->description($ticket, $type)]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $partyAccount,
            'debit' => $nominalDebit ? 0 : $amount, 'credit' => $nominalDebit ? $amount : 0,
            'party_id' => $party, 'party_type' => $party ? ($isCost ? 'vendor' : $this->resolvePartyType(User::find($party))) : null,
            'note' => $this->description($ticket, $type)]);

        return $entry;
    }
}
