<?php

namespace Tests\Unit\Accounting;

use App\Services\PartyLedgerService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ActiveStatementRowsTest extends TestCase
{
    public function test_cancelled_pairs_are_omitted_but_valid_payment_and_period_edge_remain(): void
    {
        $ledger = new class extends PartyLedgerService
        {
            public function markReversalPairs(Collection $rows): Collection
            {
                return $rows;
            }
        };
        $rows = collect([
            (object) ['id' => 1731, 'debit' => 0, 'credit' => 100000, 'reversal_pair_id' => 1731],
            (object) ['id' => 1732, 'debit' => 0, 'credit' => 100000, 'reversal_pair_id' => null],
            (object) ['id' => 2408, 'debit' => 100000, 'credit' => 0, 'reversal_pair_id' => 1731],
            (object) ['id' => 2409, 'debit' => 100000, 'credit' => 0, 'reversal_pair_id' => 2409],
            (object) ['id' => 2410, 'debit' => 0, 'credit' => 100000, 'reversal_pair_id' => 2409],
            (object) ['id' => 2411, 'debit' => 25000, 'credit' => 0, 'reversal_pair_id' => null],
        ]);

        $active = $ledger->activeRows($rows, 50000);

        $this->assertSame([1732, 2411], $active->pluck('id')->all());
        $this->assertSame(50000.0, $active->first()->old_balance);
        $this->assertSame(-50000.0, $active->first()->balance);
        $this->assertSame(-25000.0, $active->last()->balance);
        $this->assertEquals($rows->sum('debit') - $rows->sum('credit'), $active->sum('debit') - $active->sum('credit'));
    }
}
