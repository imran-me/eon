@extends('layout.app')

@section('meta-information')
    <title>{{ $bank->name }} — Statement</title>
@endsection

@section('main-content')
@include('layout.bank-tabs')

<div class="bg-white rounded-lg shadow-md p-4 md:p-6">

    <a href="{{ route('role.banks.dashboard', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
       class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-700 mb-4">
        <i class="fas fa-arrow-left"></i> Back to Bank Accounts
    </a>

    {{-- Date filter --}}
    <form action="" method="GET" id="filterForm" class="mb-4 border border-gray-200 rounded-lg overflow-hidden shadow-sm">
        <button type="button" id="filterHeader"
            class="w-full flex items-center justify-between bg-gray-50 border-l-4 border-indigo-600 px-4 py-3 text-left">
            <h3 class="text-base font-semibold text-gray-800"><i class="fas fa-filter mr-2"></i>Date Range</h3>
            <i id="filterToggleIcon" class="fas fa-chevron-down text-gray-400 transition-transform duration-300 {{ request()->hasAny(['date_from','date_to']) ? 'rotate-180' : '' }}"></i>
        </button>
        <div id="filterBody" class="{{ request()->hasAny(['date_from','date_to']) ? '' : 'hidden' }} p-4 bg-white">
            <div class="flex flex-wrap gap-5 mb-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block mb-1.5 text-sm font-medium text-gray-700">Date From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block mb-1.5 text-sm font-medium text-gray-700">Date To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" id="resetBtn" class="px-4 py-2 rounded-md text-sm font-medium bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200">
                    <i class="fas fa-undo mr-1"></i>Reset
                </button>
                <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700">
                    <i class="fas fa-search mr-1"></i>Apply
                </button>
            </div>
        </div>
    </form>

    {{-- Report header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-500 text-white px-6 py-6 mb-5">
        <div>
            <h2 class="text-xl font-bold"><i class="fas fa-file-invoice mr-2"></i>{{ $bank->name }} — Statement</h2>
            <div class="text-sm opacity-85 mt-1">
                @if(request('date_from') || request('date_to'))
                    {{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d M Y') : 'Beginning' }}
                    —
                    {{ request('date_to') ? \Carbon\Carbon::parse(request('date_to'))->format('d M Y') : 'Today' }}
                @else
                    All Periods
                @endif
                &nbsp;·&nbsp; Generated: {{ now()->format('d M Y, h:i A') }}
            </div>
        </div>
        <a href="{{ route('role.banks.statement.pdf', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank' => $bank->id] + request()->only(['date_from', 'date_to'])) }}"
           target="_blank"
           class="inline-flex items-center gap-1 rounded-md border border-white/40 bg-white/20 px-4 py-2 text-sm font-semibold text-white hover:bg-white/30">
            <i class="fas fa-print mr-1"></i>Print Statement
        </a>
    </div>

    {{-- Account info bar --}}
    <div class="flex flex-wrap items-center gap-6 rounded-xl bg-white shadow-sm border border-gray-100 px-6 py-5 mb-5">
        <div class="flex flex-col gap-0.5">
            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Account Name</span>
            <span class="text-sm font-bold text-gray-800">{{ $bank->account_name }}</span>
        </div>
        <div class="flex flex-col gap-0.5">
            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Account Number</span>
            <span class="text-sm font-bold text-gray-800">
                <span class="font-mono bg-gray-100 border border-gray-200 rounded px-2 py-0.5 text-xs">{{ $bank->account_number }}</span>
            </span>
        </div>
        <div class="flex flex-col gap-0.5">
            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Type</span>
            <span class="text-sm font-bold text-gray-800">{{ ucfirst(str_replace('_', ' ', $bank->type)) }} · {{ ucfirst($bank->account_type) }}</span>
        </div>
        @if($bank->branch_name)
        <div class="flex flex-col gap-0.5">
            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Branch</span>
            <span class="text-sm font-bold text-gray-800">{{ $bank->branch_name }}</span>
        </div>
        @endif
        <div class="flex flex-col gap-0.5">
            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Total Transactions</span>
            <span class="text-sm font-bold text-gray-800">{{ $rows->count() }}</span>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="flex flex-wrap gap-4 mb-5">
        <div class="flex-1 min-w-[160px] rounded-xl bg-slate-50 border-l-4 border-slate-400 px-5 py-4">
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1.5"><i class="fas fa-flag mr-1"></i>Opening Balance</div>
            <div class="font-mono text-xl font-extrabold text-slate-600">
                {{ number_format(abs($openingBalance), 2) }} <small class="text-xs">{{ $openingBalance < 0 ? 'Cr' : 'Dr' }}</small>
            </div>
        </div>
        <div class="flex-1 min-w-[160px] rounded-xl bg-blue-50 border-l-4 border-blue-600 px-5 py-4">
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1.5"><i class="fas fa-arrow-right mr-1"></i>Total Debit</div>
            <div class="font-mono text-xl font-extrabold text-blue-700">{{ number_format($totalDebit, 2) }}</div>
        </div>
        <div class="flex-1 min-w-[160px] rounded-xl bg-green-50 border-l-4 border-green-600 px-5 py-4">
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1.5"><i class="fas fa-arrow-left mr-1"></i>Total Credit</div>
            <div class="font-mono text-xl font-extrabold text-green-700">{{ number_format($totalCredit, 2) }}</div>
        </div>
        <div class="flex-1 min-w-[160px] rounded-xl bg-amber-50 border-l-4 border-amber-600 px-5 py-4">
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1.5"><i class="fas fa-flag-checkered mr-1"></i>Closing Balance</div>
            <div class="font-mono text-xl font-extrabold text-amber-700">
                {{ number_format(abs($closingBalance), 2) }} <small class="text-xs">{{ $closingBalance < 0 ? 'Cr' : 'Dr' }}</small>
            </div>
        </div>
    </div>

    {{-- Ledger table --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
        @if($rows->isEmpty())
            <div class="text-center py-12 text-gray-400">
                <i class="fas fa-inbox text-4xl mb-3 block"></i>
                <p>No transactions found for this account in the selected period.</p>
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">#</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Date</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Reference</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Source</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Description / Note</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Debit</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Credit</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Newest transaction first, the house default for every
                         transaction table on screen. The controller still builds
                         the rows oldest-first — the running balance can only be
                         accumulated in that order, and the PDF next door shares
                         the same builder and stays chronological — so the flip
                         is display-only, and the Closing/Opening rows swap ends
                         with it so the balance above the list is still the one
                         the list starts from. --}}
                    @php $ledgerRows = $rows->reverse()->values(); @endphp

                    <tr class="bg-amber-50 font-bold text-sm">
                        <td class="px-4 py-3" colspan="5"><i class="fas fa-flag-checkered mr-1"></i>Closing Balance</td>
                        <td class="px-4 py-3 text-right font-mono text-blue-700">{{ number_format($totalDebit, 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-green-700">{{ number_format($totalCredit, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="font-mono {{ $closingBalance >= 0 ? 'text-blue-700' : 'text-red-600' }}">
                                {{ number_format(abs($closingBalance), 2) }} <small>{{ $closingBalance < 0 ? 'Cr' : 'Dr' }}</small>
                            </span>
                        </td>
                    </tr>

                    @foreach($ledgerRows as $i => $row)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-2.5 text-gray-400">{{ $i + 1 }}</td>
                        <td class="px-4 py-2.5 whitespace-nowrap text-gray-700">{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                        <td class="px-4 py-2.5">
                            @if($row['reference'])
                                <span class="font-mono bg-gray-100 border border-gray-200 rounded px-2 py-0.5 text-xs text-gray-600">{{ $row['reference'] }}</span>
                            @else
                                <span class="font-mono text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold capitalize bg-gray-100 text-gray-600">{{ ucfirst(str_replace('_', ' ', $row['source'])) }}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="text-sm text-gray-800">{{ Str::limit($row['description'], 40) }}</div>
                            @if($row['note'])
                                <div class="text-xs text-gray-400 mt-0.5"><i class="fas fa-note-sticky mr-1"></i>{{ $row['note'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @if($row['debit'] > 0)
                                <span class="font-mono font-semibold text-blue-700">{{ number_format($row['debit'], 2) }}</span>
                            @else
                                <span class="font-mono text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @if($row['credit'] > 0)
                                <span class="font-mono font-semibold text-green-700">{{ number_format($row['credit'], 2) }}</span>
                            @else
                                <span class="font-mono text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <span class="font-mono text-sm font-bold {{ $row['balance'] >= 0 ? 'text-blue-700' : 'text-red-600' }}">
                                {{ number_format(abs($row['balance']), 2) }} <small>{{ $row['balance'] < 0 ? 'Cr' : 'Dr' }}</small>
                            </span>
                        </td>
                    </tr>
                    @endforeach

                    <tr class="bg-blue-50 text-blue-700 italic text-xs border-b border-gray-100">
                        <td class="px-4 py-2.5">—</td>
                        <td class="px-4 py-2.5">{{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d M Y') : 'B/F' }}</td>
                        <td class="px-4 py-2.5" colspan="3"><i class="fas fa-arrow-right mr-1"></i>Opening Balance</td>
                        <td class="px-4 py-2.5 text-right">—</td>
                        <td class="px-4 py-2.5 text-right">—</td>
                        <td class="px-4 py-2.5 text-right font-mono font-bold {{ $openingBalance >= 0 ? 'text-blue-700' : 'text-red-600' }}">
                            {{ number_format(abs($openingBalance), 2) }} <small>{{ $openingBalance < 0 ? 'Cr' : 'Dr' }}</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection

@section('js')
<script>
    document.getElementById('filterHeader').addEventListener('click', function () {
        document.getElementById('filterToggleIcon').classList.toggle('rotate-180');
        document.getElementById('filterBody').classList.toggle('hidden');
    });

    document.getElementById('resetBtn').addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = window.location.pathname;
    });
</script>
@endsection
