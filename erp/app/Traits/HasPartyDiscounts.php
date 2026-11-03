<?php

namespace App\Traits;

use App\Models\ContractFlightPassenger;
use App\Models\PartyInvoice;
use App\Models\TicketSale;
use App\Services\PartyDiscountService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait HasPartyDiscounts
{
    public function partyDiscountType(): string
    {
        return match (true) {
            $this instanceof TicketSale => 'ticket_sale',
            $this instanceof PartyInvoice => 'party_invoice',
            default => ContractFlightPassenger::AGENT_LEDGER_TYPE,
        };
    }

    public static function bootHasPartyDiscounts(): void
    {
        static::updating(function (Model $doc) {
            $discount = PartyDiscountService::total($doc->partyDiscountType(), (int) $doc->id);
            if ($discount <= 0) {
                return;
            }
            $fields = ['company_id', 'client_id', 'user_id', 'agent_id', 'total_amount', 'amount', 'subtotal', 'tax_amount', 'discount_amount', 'agent_price', 'exception_flag', 'contract_flight_id', 'status'];
            if ($doc->isDirty($fields)) {
                throw ValidationException::withMessages(['discount' => 'Reverse active discounts before changing invoice price, party, company or cancellation status.']);
            }
            if (! $doc instanceof ContractFlightPassenger) {
                $due = app(PartyDiscountService::class)->outstanding($doc->partyDiscountType(), $doc);
                $doc->due_amount = $due;
                $doc->payment_status = $due <= 0 ? 'paid' : ((float) $doc->paid_amount > 0 ? 'partial' : 'due');
            }
        });
        static::deleting(function (Model $doc) {
            if (PartyDiscountService::total($doc->partyDiscountType(), (int) $doc->id) > 0) {
                throw ValidationException::withMessages(['discount' => 'Reverse active discounts before deleting this invoice/package.']);
            }
        });
    }
}
