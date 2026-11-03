{{--
    Wood Art · Accounts — Bulk expense entry (step 2: review).
    Nothing is posted until "Confirm & post". No <script> in here.
--}}
@extends('woodart::layouts.suite')

@php
    $role = request()->route('role');
    $mny  = fn ($n) => '৳ ' . number_format((float) $n, 0);
@endphp

@section('wa-head-actions')
    <form method="POST" action="{{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::route('bulk.edit', ['role' => $role]) }}" style="display:inline">
        @csrf
        <input type="hidden" name="payload" value="{{ $payload }}">
        <button type="submit" class="wap-btn wap-btn-ghost"><i class="bi bi-arrow-left"></i> Edit rows</button>
    </form>
@endsection

@section('wa-view')

    <div class="wap-kpi-grid">
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Project</span><span class="wap-kpi-ico"><i class="bi bi-building"></i></span></div>
            <div class="wap-kpi-value" style="font-size:18px">{{ $dept->name }}</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Entries</span><span class="wap-kpi-ico"><i class="bi bi-receipt-cutoff"></i></span></div>
            <div class="wap-kpi-value">{{ count($rows) }}</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Total to post</span><span class="wap-kpi-ico"><i class="bi bi-cash-coin"></i></span></div>
            <div class="wap-kpi-value">{{ $mny($cashOutTotal) }}</div>
            @if($bankChargeTotal > 0)<div class="wap-card-sub">Expense {{ $mny($total) }} + bank charge {{ $mny($bankChargeTotal) }}</div>@endif
        </div>
    </div>

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-list-check"></i> What will be posted</h3>
            <span class="wap-card-sub">Each row becomes an approved expense of {{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::name() }} with its own journal</span>
        </div>
        <div class="wap-card-body">
            <div class="wap-table-wrap">
                <table class="wap-table">
                    <thead><tr>
                        <th>#</th><th>Voucher</th><th>Date</th><th>Supplier / Vendor</th><th>Paid by / account</th><th>Description</th><th>Cost head / debit account</th>
                        <th>Money was</th><th>Payment method</th><th class="wap-t-num">Bank charge</th><th class="wap-t-num">Amount</th>
                    </tr></thead>
                    <tbody>
                    @foreach($rows as $i => $r)
                    <tr>
                        <td>{{ $i + 1 }}</td><td class="wap-t-strong">{{ $r['voucher_no'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($r['date'])->format('d M Y') }}</td>
                        <td>{{ $r['supplier_name'] }}</td>
                        <td class="wap-t-strong">{{ $r['person_name'] }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($r['description'], 70) }}</td>
                        <td><strong>{{ $r['head_name'] }}</strong><div class="wap-card-sub">{{ $r['account_label'] }}</div></td>
                        <td>{{ $r['source'] }}</td><td>{{ \Illuminate\Support\Str::headline($r['payment_method']) }}</td><td class="wap-t-num">{{ $mny($r['bank_charge_amount']) }}</td>
                        <td class="wap-t-num">{{ $mny($r['amount']) }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr>
                        <td colspan="10" class="wap-t-strong" style="text-align:right">Total cash out</td>
                        <td class="wap-t-num wap-t-strong">{{ $mny($cashOutTotal) }}</td>
                    </tr></tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-journal-check"></i> The effect on the books</h3>
            <span class="wap-card-sub">Debit the cost head &middot; credit who paid</span>
        </div>
        <div class="wap-card-body">
            <div class="wap-table-wrap">
                <table class="wap-table">
                    <thead><tr><th>Cost head</th><th>Debit account</th><th class="wap-t-num">Entries</th><th class="wap-t-num">Amount</th></tr></thead>
                    <tbody>
                    @foreach($perHead as $h)
                    <tr><td class="wap-t-strong">{{ $h['name'] }}</td><td>{{ $h['account'] }}</td><td class="wap-t-num">{{ $h['n'] }}</td><td class="wap-t-num">{{ $mny($h['total']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="wap-table-wrap" style="margin-top:16px">
                <table class="wap-table">
                    <thead><tr><th>Settlement account (credit)</th><th class="wap-t-num">Entries</th><th class="wap-t-num">Amount</th></tr></thead>
                    <tbody>
                    @foreach($perPerson as $p)
                    <tr><td class="wap-t-strong">{{ $p['name'] }}</td><td class="wap-t-num">{{ $p['n'] }}</td><td class="wap-t-num">{{ $mny($p['total']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="wap-banner" style="margin-top:16px">
                <i class="bi bi-shield-check"></i>
                <div>All rows post together or not at all. If any single row is refused, nothing is posted and you are told which one.</div>
            </div>

            <form method="POST" action="{{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::route('bulk.store', ['role' => $role]) }}" style="margin-top:16px">
                @csrf
                <input type="hidden" name="payload" value="{{ $payload }}">
                <input type="hidden" name="token" value="{{ $token }}">
                <button type="submit" class="wap-btn wap-btn-primary" style="background:#16955c; border-color:#16955c; box-shadow:0 6px 16px rgba(22,149,92,.35)">
                    <i class="bi bi-check2-circle"></i> Confirm &amp; post {{ count($rows) }} {{ \Illuminate\Support\Str::plural('entry', count($rows)) }}
                </button>
            </form>
        </div>
    </div>

@endsection
