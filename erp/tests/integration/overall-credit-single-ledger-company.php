<?php

use App\Models\PartyDiscount;
use App\Models\User;
use App\Services\PartyDiscountService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DB::beginTransaction();

try {
    $result = app(PartyDiscountService::class)->postOverall(
        153,
        10000,
        '2026-10-23',
        'Integration check for single-company party credit',
        (string) Str::uuid(),
        User::findOrFail(1),
        null
    );

    $row = PartyDiscount::findOrFail($result['rows'][0]->id);
    assert((int) $row->company_id === 2, 'The party ledger company should be inferred as company 2.');
    assert($row->document_type === PartyDiscountService::UNALLOCATED, 'The credit should remain on the party account when no invoice is open.');
    assert((float) $result['unallocated'] === 10000.0, 'The complete credit should remain unallocated.');

    echo "Overall credit single-company fallback checks passed.\n";
} finally {
    DB::rollBack();
}
