<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ContractFlightPassenger;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\TicketSale;
use App\Models\TicketSaleItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A package includes its linked tickets; the agent must not be billed twice. */
class FlightPackageTicketBilling
{
    public const TYPE = 'ticket_package_inclusion';

    public function forTicket(int $ticketId): void
    {
        foreach (TicketSaleItem::where('ticket_purchase_id', $ticketId)->pluck('ticket_sale_id')->unique() as $saleId) {
            $this->sync((int) $saleId);
        }
    }

    public function forPassenger(ContractFlightPassenger $passenger): void
    {
        foreach ($passenger->ticketPurchases()->pluck('ticket_purchases.id') as $ticketId) {
            $this->forTicket((int) $ticketId);
        }
    }

    /** Existing inclusion credits are bill adjustments, never cash receipts. */
    public static function covered(int $saleId, int $partyId): float
    {
        return max(0, (float) Transaction::where('type', self::TYPE)
            ->where('invoice_id', $saleId)->where('user_id', $partyId)
            ->selectRaw('COALESCE(SUM(credit - debit), 0) amount')->value('amount'));
    }

    public static function target(float $total, float $included, bool $hasCharge, bool $cancelled): float
    {
        return $hasCharge && ! $cancelled ? round(max(0, min($total, $included)), 2) : 0.0;
    }

    public function sync(int $saleId): void
    {
        DB::transaction(function () use ($saleId) {
            $sale = TicketSale::withTrashed()->whereKey($saleId)->lockForUpdate()->first();
            if (! $sale) {
                return;
            }
            $hasCharge = Transaction::where('type', 'ticket_sale')->where('invoice_id', $saleId)
                ->where('user_id', $sale->client_id)->where('debit', '>', 0)->exists();
            // New sale flows write the party charge before their journal.
            // Wait for balanced recognition; JournalItem's hook retries then.
            if ($hasCharge && ! $sale->trashed() && $sale->status !== 'cancelled') {
                $recognition = JournalEntry::where('source', 'ticket_sale')->where('source_id', $saleId)->oldest('id')->first();
                if (! $recognition || $recognition->items()->count() < 2 || ! $recognition->is_balanced) {
                    return;
                }
            }
            $included = 0.0;
            foreach ($sale->items()->whereHas('ticketPurchase', fn ($q) => $q->where('status', '!=', 'cancelled'))->get() as $item) {
                $package = ContractFlightPassenger::where('agent_id', $sale->client_id)
                    ->where('agent_price', '>', 0)
                    ->whereHas('ticketPurchases', fn ($q) => $q->where('ticket_purchases.id', $item->ticket_purchase_id))
                    ->exists();
                if ($package) {
                    $included += (float) $item->price;
                }
            }
            $target = self::target((float) $sale->total_amount, $included, $hasCharge,
                $sale->trashed() || $sale->status === 'cancelled');
            $discount = PartyDiscountService::total('ticket_sale', $saleId);
            if ($discount > 0 && $target + $discount + (float) $sale->paid_amount > (float) $sale->total_amount + .005) {
                throw ValidationException::withMessages(['discount' => 'Reverse the ticket discount before including this charge in a flight package.']);
            }

            // Restore old-agent adjustments too if the buyer was changed.
            $parties = Transaction::where('type', self::TYPE)->where('invoice_id', $saleId)
                ->get()->map(fn ($row) => [$row->user_id, $row->company_id]);
            $parties->push([$sale->client_id, $sale->company_id]);
            foreach ($parties->unique(fn ($scope) => implode(':', $scope)) as [$partyId, $companyId]) {
                if (! $partyId) {
                    continue;
                }
                $posted = (float) Transaction::where('type', self::TYPE)->where('invoice_id', $saleId)
                    ->where('user_id', $partyId)->where('company_id', $companyId)
                    ->selectRaw('COALESCE(SUM(credit - debit), 0) amount')->value('amount');
                $desired = (int) $partyId === (int) $sale->client_id && $companyId == $sale->company_id ? $target : 0;
                $delta = round($desired - $posted, 2);
                if (abs($delta) < .005) {
                    continue;
                }
                $ar = Account::where('code', config('accounts.accounts_receivable'))->firstOrFail();
                $revenue = Account::where('code', config('accounts.ticket_sales_revenue'))->firstOrFail();
                $balance = (float) Transaction::where('user_id', $partyId)->where('company_id', $companyId)
                    ->selectRaw('COALESCE(SUM(debit - credit), 0) amount')->value('amount');
                $description = $delta > 0
                    ? 'Ticket charge included in flight package — '.$sale->invoice_no
                    : 'Standalone ticket charge restored — '.$sale->invoice_no;
                $row = Transaction::create([
                    'company_id' => $companyId, 'user_id' => $partyId, 'user_type' => 'customer',
                    'type' => self::TYPE, 'invoice_id' => $saleId, 'account_id' => null,
                    'payment_date' => now()->toDateString(),
                    'reference_no' => 'PKG-'.$sale->invoice_no,
                    'old_balance' => $balance, 'balance' => $balance - $delta,
                    'debit' => $delta < 0 ? -$delta : 0, 'credit' => $delta > 0 ? $delta : 0,
                    'remarks' => $description,
                ]);
                $entry = JournalEntry::create([
                    'company_id' => $companyId, 'created_by' => auth()->id(),
                    'date' => now()->toDateString(), 'entry_time' => now()->format('H:i:s'),
                    'source' => self::TYPE, 'source_id' => $row->id,
                    'reference' => 'PKG-'.$sale->invoice_no.'-'.$row->id,
                    'description' => $description, 'beneficiary_party_id' => $partyId,
                ]);
                JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $revenue->id,
                    'debit' => max(0, $delta), 'credit' => max(0, -$delta), 'note' => $description]);
                JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $ar->id,
                    'debit' => max(0, -$delta), 'credit' => max(0, $delta),
                    'party_id' => $partyId, 'party_type' => 'agent', 'note' => $description]);
            }

            if (! $sale->trashed() && $sale->status !== 'cancelled') {
                $due = PartyDiscountService::due((float) $sale->total_amount - $target, (float) $sale->paid_amount, $discount);
                $sale->forceFill(['due_amount' => $due,
                    'payment_status' => $due <= 0 ? 'paid' : ((float) $sale->paid_amount > 0 ? 'partial' : 'due')])->saveQuietly();
                $sale->schedules()->where('type', 'receive')->update([
                    'amount' => $discount > 0 ? $due : max(0, (float) $sale->total_amount - $target),
                    'paid_amount' => $discount > 0 ? 0 : $sale->paid_amount,
                    'status' => $due <= 0 ? 'paid' : 'pending',
                ]);
            }
        });
    }
}
