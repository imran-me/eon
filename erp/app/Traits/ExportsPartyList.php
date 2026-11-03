<?php

namespace App\Traits;

use App\Models\Company;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartyLedgerService;
use App\Services\VendorClassificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Browser print lists for the three party modules (agent, vendor,
 * customer). All three list screens are the same table over `users` filtered
 * by role, so the query, row shape and print response live here
 * instead of being copied into each controller.
 */
trait ExportsPartyList
{
    /**
     * ['role' => 'agent', 'prefix' => 'EP-AG-', 'label' => 'Agent'] — supplied
     * by each controller that uses this trait.
     */
    abstract protected function partyExportMeta(): array;

    public function print(Request $request)
    {
        $meta = $this->partyExportMeta();

        return view('exports.party-list-pdf', [
            'rows' => $this->partyPrintRows($request),
            'label' => $meta['label'],
            'company' => Company::find(auth()->user()?->company_id) ?? Company::first(),
            'search' => $request->search,
            'filters' => $request->only('service_category', 'vendor_type_id', 'vendor_category_id'),
        ]);
    }

    /**
     * Same filter as the index screen, but unpaginated — a print covers the
     * whole filtered result set, not just the page the user is looking at.
     */
    protected function partyPrintRows(Request $request): array
    {
        $meta = $this->partyExportMeta();

        $query = User::query()->whereHas('roles', function ($q) use ($meta) {
            $q->where('name', $meta['role']);
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('service_category')) {
            $query->whereJsonContains('service_categories', $request->service_category);
        }

        if ($meta['role'] === 'vendor' && VendorClassificationService::installed()) {
            $query->with(['vendorProfile.type', 'vendorCategories']);
            if ($request->filled('vendor_type_id')) {
                $query->whereHas('vendorProfile', fn ($q) => $q->where('vendor_type_id', $request->vendor_type_id));
            }
            if ($request->filled('vendor_category_id')) {
                $query->whereHas('vendorCategories', fn ($q) => $q->whereKey($request->vendor_category_id));
            }
        }

        $parties = $query->latest()->get();
        $balances = $this->partyExportBalances($parties->pluck('id'));

        return $parties->map(function ($party) use ($meta, $balances) {
            $ledger = $balances[$party->id] ?? null;
            $balance = $ledger ? (float) $ledger->balance : 0.0;

            return [
                'party_id' => $meta['prefix'].str_pad($party->id, 3, '0', STR_PAD_LEFT),
                'name' => $party->name,
                'services' => $meta['role'] === 'vendor' && $party->relationLoaded('vendorProfile') && $party->vendorProfile?->type
                    ? collect([$party->vendorProfile->type->name])->merge($party->vendorCategories->pluck('name'))->implode(' — ')
                    : collect($party->service_categories ?? [])->map(fn ($service) => ucfirst($service))->implode(', '),
                'contact_person' => $party->contact_person,
                'email' => $party->email,
                'phone' => $party->phone,
                'address' => $party->address,
                'balance' => abs($balance),
                'balance_type' => $balance > 0 ? 'Dr' : ($balance < 0 ? 'Cr' : ''),
                'last_transaction' => $ledger && $ledger->payment_date
                    ? Carbon::parse($ledger->payment_date)->format('Y-m-d')
                    : null,
                'status' => ucwords(str_replace('_', ' ', $party->status ?? 'inactive')),
            ];
        })->all();
    }

    /**
     * Current running balance per party — the balance carried on their most
     * recently inserted ledger transaction (chained, cumulative).
     */
    protected function partyExportBalances($userIds)
    {
        $latestIds = Transaction::whereIn('user_id', $userIds)
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_id')
            ->pluck('id');

        // The latest row carries the date we want to show, but its stored
        // `balance` is an insert-time snapshot that back-dated entries can
        // orphan — so overwrite it with the ledger's true summed balance.
        $netBalances = app(PartyLedgerService::class)->currentBalances($userIds);

        return Transaction::whereIn('id', $latestIds)->get()
            ->each(fn ($row) => $row->balance = $netBalances[$row->user_id] ?? 0.0)
            ->keyBy('user_id');
    }
}
