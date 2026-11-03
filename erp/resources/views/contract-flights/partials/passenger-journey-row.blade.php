{{-- The passenger's journey strip as a table row under their line on
     Flights & Passengers — the same pipeline the Flight Dashboard ledger
     draws (ledgerPassengerRoute), rendered server-side because this list is.
     Hidden until the page's Status button is switched on. Expects $p and
     $colspan; $canEditStage decides whether the pins are buttons.

     `data-sort-attach` keeps it under its passenger when a column is sorted. --}}
@php
    $mjRoute   = \App\Models\ContractFlightPassenger::stageRoute();
    $mjCurrent = 0;
    foreach ($mjRoute as $i => $s) { if ($s['key'] === $p->stage) { $mjCurrent = $i; break; } }
    $mjTransit = $p->stage === 'transit' || $p->exception_flag === 'transit';
    $mjFlag    = $p->exception_flag ? (\App\Models\ContractFlightPassenger::EXCEPTION_FLAGS[$p->exception_flag] ?? ucfirst(str_replace('_', ' ', $p->exception_flag))) : null;
    $mjLast    = max(1, count($mjRoute) - 1);
    // A route with stops: "Ticket Issued" once per leg, each done as its PNR confirms.
    $mjSlots   = $p->ticketSlots();
    if (count($mjSlots) < 2) { $mjSlots = null; }
    $canEditStage = $canEditStage ?? auth()->user()?->can('edit contract flight');
    // The stub (owner, 2026-10-03: "make it more creative"): how far along,
    // where he is now, what comes next.
    $mjPct  = (int) round($mjCurrent / $mjLast * 100);
    $mjNext = $mjRoute[$mjCurrent + 1]['label'] ?? null;
    // The card's border takes its passenger row's KPI colour (owner, 2026-10-03).
    $mjKpiKey = (isset($paxKpis) && isset($mfpTones)) ? $p->kpiKey() : null;
    $mjRing = $mjKpiKey ? ($mfpTones[$paxKpis[$mjKpiKey]['tone']][7] ?? null) : null;
@endphp
<tr class="mfp-journey-row" data-sort-attach hidden{!! $mjRing ? ' style="--mj-border:' . e($mjRing) . '"' : '' !!}>
    <td colspan="{{ $colspan ?? 10 }}">
        <div class="mj-card">
        <div class="mj-stub">
            <span class="mj-ring" style="--mj-p:{{ $mjPct }}%" title="{{ $mjPct }}% of the journey done"><span>{{ $mjPct }}%</span></span>
            <span class="mj-now">
                <small><i class="fas fa-route"></i> Now @if($canEditStage && \Illuminate\Support\Facades\Route::has('role.contract-flight-stages.sync'))<button type="button" class="mj-edit" onclick="if (window.openStageEditor) openStageEditor()" title="Edit stages: reorder, rename, add or delete" aria-label="Edit pipeline stages"><i class="fas fa-pen"></i></button>@endif</small>
                <b>{{ \App\Models\ContractFlightPassenger::stageLabel($p->stage) }}</b>
                @if($mjNext)<span class="mj-next">Next <i class="fas fa-arrow-right"></i> {{ $mjNext }}</span>@else<span class="mj-next">Journey complete</span>@endif
                @if($mjFlag)<span class="mj-flag"><i class="fas fa-triangle-exclamation"></i>{{ $mjFlag }}</span>@endif
            </span>
        </div>
        <div class="mj-scroll"><ol class="mj-route" style="--mj-progress:{{ ($mjCurrent / $mjLast) * 100 }}%" aria-label="{{ $p->passportHolder?->name ?? 'Passenger' }} journey">
            @foreach($mjRoute as $i => $s)
                @php
                    $skipped = $s['is_conditional'] && $mjCurrent > $i && ! ($s['key'] === 'transit' && $mjTransit);
                    $state = $skipped ? 'is-skipped' : ($i < $mjCurrent ? 'is-done' : ($i === $mjCurrent ? 'is-current' : 'is-next'));
                    $stateLabel = $skipped ? 'Not required' : ($i < $mjCurrent ? 'Completed' : ($i === $mjCurrent ? 'Current stage' : 'Upcoming'));
                    $focus = abs($i - $mjCurrent) <= 1 ? 'is-focus' : 'is-compact';
                @endphp
                @if($s['key'] === 'ticket_issued' && $mjSlots)
                    @foreach($mjSlots as $slot)
                        @php
                            if ($loop->first) { $mjMarked = false; }
                            $done = $slot['done'] || $i < $mjCurrent;
                            $isCur = ! $done && $i === $mjCurrent && ! $mjMarked;
                            if ($isCur) { $mjMarked = true; }
                            $hold = $slot['hold'] ?? null;
                            $holdState = $hold && ! $done ? ($hold['status'] === 'booked' ? 'is-hold' : ($hold['status'] === 'expired' ? 'is-expired' : 'is-pending')) : null;
                            $sState = $done ? 'is-done' : ($holdState ? $holdState . ($isCur ? ' is-current' : '') : ($isCur ? 'is-current' : 'is-next'));
                            $holdLabel = $hold ? ($hold['status'] === 'booked' ? 'Booked · hold till ' . ($hold['expires_at'] ?: '—') : ($hold['status'] === 'expired' ? 'Hold expired · ' . $hold['reference'] : ucfirst($hold['status']) . ' · ' . $hold['reference'])) : null;
                            $sLabel = $done ? 'Purchased · ' . ($slot['ticket_no'] ?: 'PNR') : ($holdLabel ?: ($isCur ? 'Current stage' : 'Upcoming'));
                            // Every leg bought and he is still at this stage: the last
                            // leg is where he stands, so it carries the "now" marker.
                            if ($i === $mjCurrent && $loop->last && collect($mjSlots)->every(fn ($x) => $x['done'])) {
                                $sState = 'is-current'; $isCur = true; $done = false;
                            }
                        @endphp
                        <li class="mj-step {{ $sState }} {{ $focus }}" aria-current="{{ $isCur ? 'step' : 'false' }}">
                            <button type="button" class="mj-stage" data-tip="{{ $slot['label'] }} Issued{{ $slot['sector'] ? ' · ' . $slot['sector'] : '' }}&#10;{{ $sLabel }}{!! $canEditStage && ! $isCur ? '&#10;Click to set this stage' : '' !!}" data-passenger="{{ $p->id }}" data-stage="{{ $s['key'] }}" data-label="{{ $s['label'] }}" data-name="{{ $p->passportHolder?->name ?? 'This passenger' }}" data-current="{{ $isCur ? 1 : 0 }}" {{ $canEditStage ? '' : 'disabled' }} aria-label="{{ $slot['label'] }} Issued: {{ $sLabel }}">
                                <span class="mj-pin"><i class="fas {{ $done ? 'fa-check' : $s['icon'] }}" aria-hidden="true"></i></span>
                                <span class="mj-copy"><b>{{ $slot['label'] }}{{ $slot['sector'] ? ' · ' . $slot['sector'] : ' Issued' }}</b><small>{{ $sLabel }}</small></span>
                            </button>
                        </li>
                    @endforeach
                @else
                <li class="mj-step {{ $state }} {{ $focus }}" aria-current="{{ $i === $mjCurrent ? 'step' : 'false' }}">
                    <button type="button" class="mj-stage" data-tip="{{ $s['label'] }}&#10;{{ $stateLabel }}{!! $canEditStage && $i !== $mjCurrent ? '&#10;Click to set this stage' : '' !!}" data-passenger="{{ $p->id }}" data-stage="{{ $s['key'] }}" data-label="{{ $s['label'] }}" data-name="{{ $p->passportHolder?->name ?? 'This passenger' }}" data-current="{{ $i === $mjCurrent ? 1 : 0 }}" {{ $canEditStage ? '' : 'disabled' }} aria-label="{{ $s['label'] }}: {{ $stateLabel }}">
                        <span class="mj-pin"><i class="fas {{ $state === 'is-done' ? 'fa-check' : $s['icon'] }}" aria-hidden="true"></i></span>
                        <span class="mj-copy"><b>{{ $s['label'] }}</b><small>{{ $stateLabel }}</small></span>
                    </button>
                </li>
                @endif
            @endforeach
        </ol></div>
        </div>
    </td>
</tr>
