@php
    $grad = $portalGradients[$i % count($portalGradients)];
    $abbrev = $portalAbbrevs($pw->name);
    $lowBalance = $pw->balance < 200;
    if ($pw->status === 'inactive') {
        $statusClass = 'pw-status-inactive';
        $statusLabel = 'INACTIVE';
    } elseif (str_contains(strtolower($pw->name), 'bsp') || str_contains(strtolower($pw->name), 'iata')) {
        $statusClass = 'pw-status-bsp';
        $statusLabel = 'BSP-LIVE';
    } elseif ($lowBalance) {
        $statusClass = 'pw-status-topup';
        $statusLabel = 'TOP-UP SOON';
    } else {
        $statusClass = 'pw-status-active';
        $statusLabel = 'ACTIVE';
    }
    $syncTime = $pw->synced_at ? $pw->synced_at->format('h:i A') : '--:--';

    $grossUsed   = App\Models\PortalBalance::where('portal_id', $pw->id)->whereIn('type', ['ticket_purchase', 'opening_usage'])->sum('credit');
    $totalRepaid = App\Models\PortalBalance::where('portal_id', $pw->id)->where('type', 'add_balance')->sum('debit');
    $totalUsed   = max(0, $grossUsed - $totalRepaid);
@endphp
<div class="portal-wallet-card" style="background:linear-gradient(135deg,{{ $grad[0] }},{{ $grad[1] }});">
    <div class="pw-top">
        <div class="pw-ident">
            <div class="pw-logo">{{ $abbrev }}</div>
            <div class="pw-ident-text">
                <div class="pw-name">{{ $pw->name }}</div>
                <div class="pw-code">{{ strtoupper(str_replace([' ','-'], '_', $pw->name)) }}</div>
            </div>
        </div>
        <span class="pw-status {{ $statusClass }}">{{ $statusLabel }}</span>
    </div>
    <div class="pw-balance">BDT {{ number_format($totalUsed, 2) }}</div>
    @if($pw->next_payment_date || $pw->next_payment_amount)
    <div class="pw-footer">
        <div class="pw-meta-block">
            <span class="pw-meta-label">Nxt Payment Amount</span>
            <span class="pw-meta-value">৳ {{ $pw->next_payment_amount ? number_format($pw->next_payment_amount, 0) : '—' }}</span>
        </div>
        <div class="pw-meta-divider"></div>
        <div class="pw-meta-block pw-meta-block--right">
            <span class="pw-meta-label">Nxt Payment Date</span>
            <span class="pw-meta-value">{{ $pw->next_payment_date ? \Carbon\Carbon::parse($pw->next_payment_date)->format('d M Y') : '—' }}</span>
        </div>
    </div>
    @else
    <div class="pw-footer">
        <div class="pw-meta">Last Synced: <strong>{{ $syncTime }}</strong></div>
    </div>
    @endif
</div>
