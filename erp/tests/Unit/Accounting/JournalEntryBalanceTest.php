<?php

namespace Tests\Unit\Accounting;

use App\Models\JournalEntry;
use App\Models\JournalItem;
use PHPUnit\Framework\TestCase;

/**
 * Double-entry integrity: debits must equal credits. Database-free -- the
 * lines are attached in memory, so this exercises the model's own arithmetic
 * (`is_balanced`, `total_debit`, `total_credit`) and nothing else.
 *
 * What is NOT covered here: that every posting service actually writes
 * balanced lines. That needs a database with the real schema, which CI does
 * not have yet (see docs/ci-review-pipeline.md, "Blockers").
 */
class JournalEntryBalanceTest extends TestCase
{
    private function entry(array $lines): JournalEntry
    {
        $entry = new JournalEntry;
        $entry->setRelation('items', collect(array_map(
            fn (array $l) => new JournalItem(['debit' => $l[0], 'credit' => $l[1]]),
            $lines
        )));

        return $entry;
    }

    public function test_equal_debit_and_credit_is_balanced(): void
    {
        $entry = $this->entry([[1500.50, 0], [0, 1500.50]]);

        $this->assertTrue($entry->is_balanced);
        $this->assertSame(1500.5, $entry->total_debit);
        $this->assertSame(1500.5, $entry->total_credit);
    }

    public function test_split_credit_lines_that_sum_to_the_debit_are_balanced(): void
    {
        // Shape used by agent cancellation postings: one debit, two credits.
        $this->assertTrue($this->entry([[8500, 0], [0, 6000], [0, 2500]])->is_balanced);
    }

    public function test_a_one_sided_entry_is_not_balanced(): void
    {
        $this->assertFalse($this->entry([[1000, 0]])->is_balanced);
    }

    public function test_a_difference_of_one_taka_is_not_balanced(): void
    {
        $this->assertFalse($this->entry([[1000, 0], [0, 999]])->is_balanced);
    }

    public function test_sub_paisa_float_noise_is_tolerated(): void
    {
        // 0.1 + 0.2 != 0.3 in binary floats; the model allows < 0.001.
        $this->assertTrue($this->entry([[0.1, 0], [0.2, 0], [0, 0.3]])->is_balanced);
    }
}
