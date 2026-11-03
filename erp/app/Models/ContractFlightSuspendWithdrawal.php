<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Suspend withdraw (owner, 2026-11-02). An airline or portal suspends a ticket; we hire another vendor
 * to lift it. The vendor's cost goes to that vendor's ledger; either we bear it or the agent does; our
 * own charge goes on top. Agent bears → ONE agent line = cost + our charge.
 */
class ContractFlightSuspendWithdrawal extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['cost' => 'float', 'our_charge' => 'float', 'agent_total' => 'float'];

    /** Vendor ledger row: the suspend-withdraw vendor's bill. */
    public const VENDOR_TYPE = 'contract_flight_suspend_withdraw';

    /** Agent ledger row: his ONE line (cost he bears + our charge). */
    public const AGENT_TYPE = 'contract_flight_suspend_withdraw_charge';

    public const BEARERS = ['agent' => 'Agent pays', 'company' => 'We bear it', 'split' => 'Split'];

    /** Table + route present. Never throws — the shared Re-assign dialog asks it. */
    public static function available(): bool
    {
        try {
            return Schema::hasTable('contract_flight_suspend_withdrawals')
                && Route::has('role.contract-flight-passengers.suspend-withdraw');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Vendors that can lift a suspension. Never throws. */
    public static function vendors(): array
    {
        try {
            return User::whereHas('roles', fn ($r) => $r->where('name', 'vendor'))
                ->orderBy('name')->limit(500)->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function passenger()
    {
        return $this->belongsTo(ContractFlightPassenger::class, 'contract_flight_passenger_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }
}
