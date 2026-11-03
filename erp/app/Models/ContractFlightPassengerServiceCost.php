<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractFlightPassengerServiceCost extends Model
{
    public const JOURNAL_SOURCE = 'contract_flight_service_cost';

    /** Existing passenger columns remain the amount source used by the UI. */
    /**
     * The services, in the order the card shows them (owner, 2026-09-21).
     * `provider` says who the responsible person is: a trainer / consultant
     * (an agent of that type — we pay them, they have a ledger) for the
     * three training/consulting services, a vendor for anything else.
     * `manpower_card` is legacy: no longer offered, kept so rows already
     * saved still reconcile and count.
     */
    public const TYPES = [
        'immigration' => ['field' => 'immigration_cost', 'label' => 'Immigration Training', 'provider' => 'trainer'],
        'boarding' => ['field' => 'boarding_cost', 'label' => 'Boarding Training', 'provider' => 'trainer'],
        'manpower' => ['field' => 'manpower_cost', 'label' => 'BMET / Work Permit Consulting', 'provider' => 'trainer'],
        'other' => ['field' => 'other_cost', 'label' => 'Other service', 'provider' => 'vendor'],
        'manpower_card' => ['field' => 'manpower_card_fee', 'label' => 'Manpower card', 'provider' => 'vendor', 'legacy' => true],
    ];

    /** The roles a responsible person may carry for a given cost type. */
    public static function providerRoles(string $type): array
    {
        return (static::TYPES[$type]['provider'] ?? 'vendor') === 'trainer' ? ['trainer', 'consultant'] : ['vendor'];
    }

    /**
     * Who may be the responsible person for a cost type — the one query the
     * card's dropdown and the server validator both use, so they never list
     * different people (owner, 2026-09-21).
     *
     * Training / consulting: anyone on the agent or vendor ledger listed as
     * an Instructor (`users.service_categories`), plus agents carrying the
     * Trainer / Consultant type. Other services: vendors. Guarded — the
     * `service_categories` column arrives by a pending migration on the
     * shared users table, and until then only the role match applies.
     */
    public static function providerQuery(string $type): \Illuminate\Database\Eloquent\Builder
    {
        $roles = static::providerRoles($type);
        $instructorColumn = false;
        try {
            $instructorColumn = \Illuminate\Support\Facades\Schema::hasColumn('users', 'service_categories');
        } catch (\Throwable) {
        }

        return User::query()->where(function ($q) use ($roles, $instructorColumn) {
            $q->whereHas('roles', fn ($r) => $r->whereIn('name', $roles));
            if ($instructorColumn && in_array('trainer', $roles, true)) {
                $q->orWhere(function ($i) {
                    $i->whereJsonContains('service_categories', 'instructor')
                        ->whereHas('roles', fn ($r) => $r->whereIn('name', ['agent', 'vendor']));
                });
            }
        });
    }

    /** "Agent" or "Vendor" — which ledger a responsible person is on. */
    public static function providerLedgerOf(User $user): string
    {
        return $user->hasRole('vendor') && ! $user->hasRole('agent') ? 'Vendor' : 'Agent';
    }

    protected $fillable = [
        'contract_flight_passenger_id', 'cost_type', 'title', 'amount',
        'vendor_id', 'payment_status', 'paid_amount', 'bank_id',
        'incurred_on', 'payment_date', 'due_date', 'reference_no', 'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_amount' => 'float',
        'incurred_on' => 'date',
        'payment_date' => 'date',
        'due_date' => 'date',
    ];

    public function passenger()
    {
        return $this->belongsTo(ContractFlightPassenger::class, 'contract_flight_passenger_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function getDueAmountAttribute(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    public function getTypeLabelAttribute(): string
    {
        return static::TYPES[$this->cost_type]['label'] ?? ucfirst(str_replace('_', ' ', $this->cost_type));
    }
}
