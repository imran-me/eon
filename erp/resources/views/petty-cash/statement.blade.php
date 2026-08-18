{{--
    One custodian's statement — every movement with the running balance beside it.

    Three sources are merged here: cash issued, cash returned, and expenses settled
    against this float. They live in different tables because they are different
    things, but to the person holding the money they are one list, and the closing
    figure is what should be in their pocket right now.
--}}
@extends('layout.app')

@section('meta-information')
    <title>Petty Cash — {{ $float->custodian->name ?? 'Statement' }}</title>
@endsection

@section('css')
@include('layout.table-design')
<style>
    :root { --pc-primary: #0f766e; --pc-primary-dark: #115e59; --pc-border: #e5e7eb; --pc-muted: #6b7280; }
    .pc-shell { background: #f5f7fb; padding: 18px; }
    .pc-card { background:#fff; border:1px solid var(--pc-border); border-radius:16px; box-shadow:0 12px 30px rgba(15,23,42,.08); overflow:hidden; }
    .pc-header { background: linear-gradient(135deg, var(--pc-primary), var(--pc-primary-dark)); color:#fff; padding:20px 24px; display:flex; flex-wrap:wrap; gap:12px; justify-content:space-between; align-items:center; }
    .pc-header h2 { font-size:20px; font-weight:700; margin:0; }
    .pc-header .subtext { font-size:13px; opacity:.85; margin-top:4px; }
    .pc-back { background: rgba(255,255,255,.16); color:#fff; padding:8px 14px; border-radius:9px; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .pc-back:hover { background: rgba(255,255,255,.26); color:#fff; }
    .pc-strip { display:grid; gap:14px; padding:18px 24px; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); border-bottom:1px solid var(--pc-border); }
    .pc-box { border:1px solid var(--pc-border); border-radius:12px; padding:14px 16px; }
    .pc-box .label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--pc-muted); font-weight:700; }
    .pc-box .value { font-size:22px; font-weight:700; margin-top:4px; color:#111827; }
    .pc-box .note { font-size:11px; color:var(--pc-muted); margin-top:2px; }
    .tag { display:inline-flex; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:700; }
    .tag.issue   { background:#ecfdf5; color:#047857; }
    .tag.return  { background:#eff6ff; color:#1d4ed8; }
    .tag.expense { background:#fff7ed; color:#c2410c; }
</style>
@endsection

@section('main-content')

    @include('layout.expense-tabs')

@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
    $issued   = $movements->sum('in');
    $spentOut = $movements->where('type', 'expense')->sum('out');
    $returned = $movements->where('type', 'return')->sum('out');
@endphp

<div class="pc-shell">
    <div class="pc-card">

        <div class="pc-header">
            <div>
                <h2><i class="fas fa-receipt mr-2"></i>{{ $float->custodian->name ?? 'Custodian' }}</h2>
                <div class="subtext">
                    {{ $float->company->short_name ?: $float->company->name }}
                    &nbsp;·&nbsp; {{ $float->account->code }} {{ $float->account->name }}
                    &nbsp;·&nbsp; float limit ৳ {{ number_format($float->float_limit, 2) }}
                </div>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                {{-- Opens the paper version in its own tab and sends it straight to
                     the print dialog, the same way the party statement does. --}}
                <a href="{{ route('role.petty-cash.statement.print', ['role' => $role, 'float' => $float->id]) }}"
                   target="_blank" class="pc-back">
                    <i class="fas fa-print"></i>Print / PDF
                </a>
                <a href="{{ route('role.petty-cash.index', ['role' => $role]) }}" class="pc-back">
                    <i class="fas fa-arrow-left"></i>All Floats
                </a>
            </div>
        </div>

        <div class="pc-strip">
            <div class="pc-box" style="border-left:4px solid #10b981;">
                <div class="label">Cash Received</div>
                <div class="value">৳ {{ number_format($issued, 2) }}</div>
                <div class="note">Handed over in total</div>
            </div>
            <div class="pc-box" style="border-left:4px solid #f97316;">
                <div class="label">Spent</div>
                <div class="value">৳ {{ number_format($spentOut, 2) }}</div>
                <div class="note">Backed by receipts</div>
            </div>
            <div class="pc-box" style="border-left:4px solid #3b82f6;">
                <div class="label">Returned</div>
                <div class="value">৳ {{ number_format($returned, 2) }}</div>
                <div class="note">Given back to the drawer</div>
            </div>
            <div class="pc-box" style="border-left:4px solid #0f766e;">
                <div class="label">Should Be In Pocket</div>
                <div class="value">৳ {{ number_format($balance, 2) }}</div>
                <div class="note">Count the cash — it should match</div>
            </div>
            {{-- The other direction, and only when there is one. Deliberately last
                 and visually apart from the four above: those describe company cash
                 this person is holding, this is their own money the company has. --}}
            @if($owedBack > 0)
            <div class="pc-box" style="border-left:4px solid #d97706;">
                <div class="label">Owed Back To Them</div>
                <div class="value" style="color:#b45309">৳ {{ number_format($owedBack, 2) }}</div>
                <div class="note">Their own money — not part of the pocket</div>
            </div>
            @endif
        </div>

        <div class="table-responsive" style="padding:8px 12px 20px;">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">What</th>
                        <th class="px-4 py-3 text-left">Detail</th>
                        <th class="px-4 py-3 text-right">In</th>
                        <th class="px-4 py-3 text-right">Out</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                {{-- Newest first, the house default for every transaction table.
                     The controller still accumulates the running balance oldest
                     first — it cannot be computed any other way — so this only
                     reverses the walk over the finished rows. reverse() returns
                     a new collection, leaving the totals above untouched. --}}
                @forelse($movements->reverse() as $m)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                            {{ \Illuminate\Support\Carbon::parse($m['date'])->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="tag {{ $m['type'] }}">
                                {{ ['issue' => 'Received', 'return' => 'Returned', 'expense' => 'Spent'][$m['type']] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-800">{{ $m['label'] }}</div>
                            @if($m['note'])
                                <div class="text-xs text-gray-400">{{ $m['note'] }}</div>
                            @endif
                            @if(!empty($m['owed']) && $m['owed'] > 0)
                                {{-- The receipt came to more than the pocket held. Said
                                     plainly, so the Out column showing less than the
                                     purchase does not read as a missing amount. --}}
                                <div class="text-xs font-semibold mt-0.5" style="color:#b45309">
                                    Receipt ৳{{ number_format($m['total'], 2) }} — ৳{{ number_format($m['owed'], 2) }}
                                    paid from own pocket, owed back
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-emerald-700 font-medium">
                            {{ $m['in'] > 0 ? '৳ ' . number_format($m['in'], 2) : '' }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-orange-700 font-medium">
                            {{ $m['out'] > 0 ? '৳ ' . number_format($m['out'], 2) : '' }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-bold text-gray-800">
                            ৳ {{ number_format($m['balance'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">
                            Nothing has moved on this float yet.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
