<?php

use App\Models\Transaction;
use App\Services\TicketRefundStatementPresenter;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$rows = Transaction::where('user_id', 52)
    ->where('company_id', 2)
    ->whereIn('id', [2865, 2868, 2872, 2873])
    ->orderBy('id')
    ->get();

$grouped = app(TicketRefundStatementPresenter::class)->group($rows, 126546);
$summary = $grouped->firstWhere('type', 'ticket_refund_summary');

assert($grouped->count() === 2, 'The active ticket and void lifecycle should produce two visible rows.');
assert($summary !== null, 'The void lifecycle summary is missing.');
assert((float) $summary->debit === 7000.0, 'The void lifecycle net debit must be 7,000.');
assert((float) $summary->credit === 0.0, 'The void lifecycle must not show a net credit.');
assert(count($summary->refund_summary['transaction_ids']) === 3, 'Original sale, reversal and void charge must remain in audit details.');
assert((float) $summary->refund_summary['airline_penalty'] === 2000.0, 'Airline penalty must be 2,000.');
assert((float) $summary->refund_summary['service_charge'] === 5000.0, 'Service charge must be 5,000.');

echo "Ticket void statement grouping checks passed.\n";
