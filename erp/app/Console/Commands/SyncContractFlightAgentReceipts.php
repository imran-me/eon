<?php

namespace App\Console\Commands;

use App\Models\ContractFlightPassenger;
use App\Services\ContractFlightAgentReceipts;
use Illuminate\Console\Command;

/**
 * One-time catch-up (safe to re-run): spread every agent's Party Statement
 * receipts over their contract-flight passengers, oldest departure first,
 * and write the result onto the passenger rows. From now on the same thing
 * happens automatically after each payment and each charge, so this only
 * matters for payments recorded before that hook existed.
 */
class SyncContractFlightAgentReceipts extends Command
{
    protected $signature = 'contract-flights:sync-agent-receipts {--dry-run : Show what would change without writing}';

    protected $description = 'Align contract-flight passengers\' received-from-agent figures with the agents\' Party Statement receipts';

    public function handle(): int
    {
        $agentIds = ContractFlightPassenger::whereNotNull('agent_id')->distinct()->pluck('agent_id');
        $this->info("Agents with contract-flight passengers: {$agentIds->count()}");

        ['credits' => $credits, 'allocated' => $allocated] = ContractFlightAgentReceipts::allocate($agentIds);
        $changes = 0;

        foreach ($agentIds as $agentId) {
            $credit = $credits[$agentId] ?? 0.0;
            $rows = ContractFlightPassenger::where('agent_id', $agentId)->with('agent:id,name')->get(['id', 'agent_id', 'agent_price', 'agent_paid_amount']);
            $name = $rows->first()?->agent?->name ?? "#{$agentId}";
            if ($credit <= 0) {
                $this->line("  {$name}: no ledger receipts — typed figures left as they are");
                continue;
            }
            foreach ($rows as $p) {
                $paid = round($allocated[$p->id] ?? 0.0, 2);
                if (abs((float) ($p->agent_paid_amount ?? 0) - $paid) >= 0.005) {
                    $changes++;
                    $this->line(sprintf('  %s: passenger #%d received %s -> %s', $name, $p->id, number_format((float) $p->agent_paid_amount, 2), number_format($paid, 2)));
                }
            }
            if (! $this->option('dry-run')) {
                ContractFlightAgentReceipts::sync((int) $agentId);
            }
        }

        $this->info(($this->option('dry-run') ? 'Would change ' : 'Changed ') . $changes . ' passenger row(s).');
        return self::SUCCESS;
    }
}
