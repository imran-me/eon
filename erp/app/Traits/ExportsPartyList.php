<?php

namespace App\Traits;

use App\Exports\PartyListExport;
use App\Models\Company;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartyLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Excel/PDF list exports for the three party modules (agent, vendor,
 * customer). All three list screens are the same table over `users` filtered
 * by role, so the query, the row shape and both export responses live here
 * instead of being copied into each controller.
 */
trait ExportsPartyList
{
    /**
     * ['role' => 'agent', 'prefix' => 'EP-AG-', 'label' => 'Agent'] — supplied
     * by each controller that uses this trait.
     */
    abstract protected function partyExportMeta(): array;

    public function exportExcel(Request $request)
    {
        $meta = $this->partyExportMeta();
        $rows = $this->partyExportRows($request);

        $filename = strtolower($meta['label']) . '-list-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new PartyListExport($rows, $meta['label'], $request->search), $filename);
    }

    public function exportPdf(Request $request)
    {
        $meta = $this->partyExportMeta();

        $pdf = Pdf::loadView('exports.party-list-pdf', [
            'rows'    => $this->partyExportRows($request),
            'label'   => $meta['label'],
            'company' => Company::first(),
            'search'  => $request->search,
        ])->setPaper('a4', 'landscape');

        return $pdf->download(strtolower($meta['label']) . '-list-' . now()->format('Ymd-His') . '.pdf');
    }

    /**
     * Same filter as the index screen, but unpaginated — an export covers the
     * whole filtered result set, not just the page the user is looking at.
     */
    protected function partyExportRows(Request $request): array
    {
        $meta = $this->partyExportMeta();

        $query = User::query()->whereHas('roles', function ($q) use ($meta) {
            $q->where('name', $meta['role']);
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        $parties  = $query->latest()->get();
        $balances = $this->partyExportBalances($parties->pluck('id'));

        return $parties->map(function ($party) use ($meta, $balances) {
            $ledger  = $balances[$party->id] ?? null;
            $balance = $ledger ? (float) $ledger->balance : 0.0;

            return [
                'party_id'         => $meta['prefix'] . str_pad($party->id, 3, '0', STR_PAD_LEFT),
                'name'             => $party->name,
                'contact_person'   => $party->contact_person,
                'email'            => $party->email,
                'phone'            => $party->phone,
                'address'          => $party->address,
                'balance'          => abs($balance),
                'balance_type'     => $balance > 0 ? 'Dr' : ($balance < 0 ? 'Cr' : ''),
                'last_transaction' => $ledger && $ledger->payment_date
                    ? Carbon::parse($ledger->payment_date)->format('Y-m-d')
                    : null,
                'status'           => ucwords(str_replace('_', ' ', $party->status ?? 'inactive')),
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
