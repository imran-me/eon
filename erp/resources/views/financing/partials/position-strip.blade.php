{{--
    Where this book stands, in four figures.

    Read from the WHOLE book, not the page on screen — the controller computes it
    over every row that matches the current filters. A summary that quietly meant
    "this page only" would be believed, because nothing on the page would say
    otherwise.

    Expects: $bookTotals, $book
--}}
@php
    $t     = $bookTotals;
    $owe   = $book === 'borrowed';
    $taka  = fn ($n) => number_format(round((float) $n));
    // Personal borrowings are NOT the company's debt. Kept visible but named
    // apart, because adding them into "what we owe" would overstate the
    // liability by whatever the boss happens to owe in his own name.
    $companyShare = round($t['outstanding'] - $t['personal'], 2);
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    @include('payroll.partials.kpi', [
        'label'     => $owe ? 'We Still Owe' : 'Still To Collect',
        'value'     => '৳ ' . $taka($t['outstanding']),
        'icon'      => $owe ? 'fa-building-columns' : 'fa-hand-holding-dollar',
        'iconBg'    => '#fee2e2',
        'iconText'  => '#dc2626',
        'valueTone' => $t['outstanding'] > 0 ? 'text-red-600' : 'text-green-600',
        'goodDown'  => true,
        'foot'      => $owe && $t['personal'] > 0
            ? '৳ ' . $taka($companyShare) . ' company · ৳ ' . $taka($t['personal']) . ' personal (not ours)'
            : $t['active'] . ' active of ' . $t['count'],
    ])
    @include('payroll.partials.kpi', [
        'label'    => $owe ? 'Borrowed In All' : 'Advanced In All',
        'value'    => '৳ ' . $taka($t['exposure']),
        'icon'     => 'fa-money-bill-transfer',
        'iconBg'   => '#dbeafe',
        'iconText' => '#2563eb',
        'foot'     => '৳ ' . $taka($t['settled']) . ' settled so far'
            . ($t['running'] > 0 ? ' · ' . $t['running'] . ' running' : ''),
    ])
    @include('payroll.partials.kpi', [
        'label'    => 'Monthly Instalments',
        'value'    => '৳ ' . $taka($t['emi']),
        'icon'     => 'fa-calendar-check',
        'iconBg'   => '#ede9fe',
        'iconText' => '#7c3aed',
        // The interest rides in the foot rather than taking a card of its own.
        // It is what the borrowing COSTS over its whole life — a figure the
        // principal never shows — but it is read once a quarter, not daily.
        'foot'     => ($t['nextDue']
                ? 'next due ' . \Illuminate\Support\Carbon::parse($t['nextDue'])->format('d M Y')
                : 'nothing scheduled')
            . ($t['interest'] > 0
                ? ' · ৳ ' . $taka($t['interest']) . ' interest over the term'
                    . ($t['rateWeighted'] > 0 ? ' @ ' . $t['rateWeighted'] . '%' : '')
                : ' · no interest'),
    ])
    @include('payroll.partials.kpi', [
        'label'     => 'Overdue',
        // The AMOUNT, not the count. "3 instalments" says nothing about whether
        // this is a phone call or a crisis; the taka figure does.
        'value'     => '৳ ' . $taka($t['overdueAmount']),
        'icon'      => 'fa-triangle-exclamation',
        'iconBg'    => $t['overdue'] > 0 ? '#fee2e2' : '#dcfce7',
        'iconText'  => $t['overdue'] > 0 ? '#dc2626' : '#16a34a',
        'valueTone' => $t['overdue'] > 0 ? 'text-red-600' : 'text-green-600',
        'goodDown'  => true,
        'foot'      => $t['overdue'] > 0
            ? $t['overdue'] . ' ' . \Illuminate\Support\Str::plural('instalment', $t['overdue']) . ' past due'
            : '৳ ' . $taka($t['due30']) . ' due in the next 30 days',
    ])
</div>
