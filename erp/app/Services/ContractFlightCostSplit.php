<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ContractFlightCostSplit
{
    public static function agentAmount(float $total, ?string $bearer, mixed $share, ?int $agentId): float
    {
        $total = round($total, 2);
        if ($bearer === 'company') {
            return 0.0;
        }
        if ($bearer === 'split') {
            if (! $agentId || ! is_numeric($share) || (float) $share < 0 || round((float) $share, 2) > $total) {
                throw ValidationException::withMessages(['agent_amount' => 'Enter the agent share between zero and the total amount.']);
            }

            return round((float) $share, 2);
        }
        if ($bearer !== 'agent' || (! $agentId && $total > 0)) {
            throw ValidationException::withMessages(['bearer' => 'Choose who bears the cost and an agent to bill.']);
        }

        return $total;
    }
}
