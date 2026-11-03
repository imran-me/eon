<?php

namespace Tests\Unit\ContractFlights;

use App\Services\ContractFlightCostSplit;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CostSplitTest extends TestCase
{
    #[DataProvider('validShares')]
    public function test_only_the_chosen_share_is_billed(float $total, string $bearer, mixed $share, ?int $agentId, float $expected): void
    {
        $this->assertSame($expected, ContractFlightCostSplit::agentAmount($total, $bearer, $share, $agentId));
    }

    public static function validShares(): array
    {
        return [
            'company without agent' => [8666, 'company', null, null, 0.0],
            'agent pays full' => [8666, 'agent', null, 7, 8666.0],
            'split' => [8666, 'split', 5000, 7, 5000.0],
            'company pays all in split' => [8666, 'split', 0, 7, 0.0],
            'agent pays all in split' => [8666, 'split', 8666, 7, 8666.0],
            'fractional share' => [8666.55, 'split', '5000.25', 7, 5000.25],
        ];
    }

    #[DataProvider('invalidShares')]
    public function test_invalid_splits_cannot_post(mixed $share, ?int $agentId): void
    {
        $this->expectException(ValidationException::class);
        ContractFlightCostSplit::agentAmount(8666, 'split', $share, $agentId);
    }

    public static function invalidShares(): array
    {
        return [[null, 7], ['', 7], ['invalid', 7], [-1, 7], [8666.01, 7], [5000, null]];
    }
}
