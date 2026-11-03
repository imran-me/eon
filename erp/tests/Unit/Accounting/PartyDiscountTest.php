<?php

namespace Tests\Unit\Accounting;

use App\Models\ContractFlightPassengerChange;
use App\Services\PartyDiscountService;
use PHPUnit\Framework\TestCase;

class PartyDiscountTest extends TestCase
{
    public function test_discount_reduces_due_without_being_a_payment(): void
    {
        $this->assertSame(45000.0, PartyDiscountService::due(50000, 0, 5000));
        $this->assertSame(35000.0, PartyDiscountService::due(50000, 10000, 5000));
        $this->assertSame(40000.0, PartyDiscountService::due(50000, 10000, 0));
        $this->assertSame(0.0, PartyDiscountService::due(50000, 45000, 5000));
    }

    public function test_discount_and_reversal_are_never_classed_as_flight_receipts(): void
    {
        $this->assertContains('party_discount', ContractFlightPassengerChange::NOT_RECEIPT_TYPES);
        $this->assertContains('party_discount_reversal', ContractFlightPassengerChange::NOT_RECEIPT_TYPES);
    }
}
